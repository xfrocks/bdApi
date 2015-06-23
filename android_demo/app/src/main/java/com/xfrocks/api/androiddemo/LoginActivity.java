package com.xfrocks.api.androiddemo;

import android.app.Activity;
import android.app.AlertDialog;
import android.app.LoaderManager.LoaderCallbacks;
import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.CursorLoader;
import android.content.DialogInterface;
import android.content.Intent;
import android.content.IntentFilter;
import android.content.IntentSender;
import android.content.Loader;
import android.database.Cursor;
import android.net.Uri;
import android.os.AsyncTask;
import android.os.Bundle;
import android.provider.ContactsContract;
import android.text.TextUtils;
import android.view.KeyEvent;
import android.view.View;
import android.view.View.OnClickListener;
import android.view.inputmethod.EditorInfo;
import android.widget.ArrayAdapter;
import android.widget.AutoCompleteTextView;
import android.widget.Button;
import android.widget.CheckBox;
import android.widget.EditText;
import android.widget.TextView;
import android.widget.Toast;

import com.android.volley.VolleyError;
import com.google.android.gms.auth.GoogleAuthUtil;
import com.google.android.gms.common.ConnectionResult;
import com.google.android.gms.common.Scopes;
import com.google.android.gms.common.SignInButton;
import com.google.android.gms.common.api.GoogleApiClient;
import com.google.android.gms.common.api.Scope;
import com.google.android.gms.plus.Plus;
import com.xfrocks.api.androiddemo.gcm.RegistrationService;
import com.xfrocks.api.androiddemo.persist.AccessTokenHelper;

import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;

import java.util.ArrayList;
import java.util.List;
import java.util.Map;

/**
 * A login screen that offers login via email/password.
 */
public class LoginActivity extends Activity
        implements LoaderCallbacks<Cursor>,
        GoogleApiClient.ConnectionCallbacks,
        GoogleApiClient.OnConnectionFailedListener {

    public static final String EXTRA_REDIRECT_TO = "redirect_to";
    private static final int RC_GOOGLE_API_RESOLVE = 1;
    private static final int RC_REGISTER = 2;

    private TokenRequest mTokenRequest;
    private BroadcastReceiver mGcmReceiver;

    private GoogleApiClient mGoogleApiClient;
    private boolean mGoogleApiIsResolving = false;
    private boolean mGoogleApiShouldResolve = false;

    // UI references.
    private AutoCompleteTextView mEmailView;
    private EditText mPasswordView;
    private CheckBox mRememberView;
    private Button mSignIn;
    private Button mAuthorize;
    private Button mGcmUnregister;
    private Button mFacebook;
    private Button mTwitter;
    private SignInButton mGoogleSignIn;
    private Button mRegister;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_login);

        mGoogleApiClient = new GoogleApiClient.Builder(this)
                .addConnectionCallbacks(this)
                .addOnConnectionFailedListener(this)
                .addApi(Plus.API)
                .addScope(new Scope(Scopes.PROFILE))
                .build();

        // Set up the login form.
        mEmailView = (AutoCompleteTextView) findViewById(R.id.email);
        populateAutoComplete();

        mPasswordView = (EditText) findViewById(R.id.password);
        mPasswordView.setOnEditorActionListener(new TextView.OnEditorActionListener() {
            @Override
            public boolean onEditorAction(TextView textView, int id, KeyEvent keyEvent) {
                if (id == R.id.login || id == EditorInfo.IME_NULL) {
                    attemptLogin();
                    return true;
                }
                return false;
            }
        });

        mRememberView = (CheckBox) findViewById(R.id.remember);

        mSignIn = (Button) findViewById(R.id.sign_in);
        mSignIn.setOnClickListener(new OnClickListener() {
            @Override
            public void onClick(View view) {
                attemptLogin();
            }
        });

        mAuthorize = (Button) findViewById(R.id.authorize);
        mAuthorize.setOnClickListener(new OnClickListener() {
            @Override
            public void onClick(View view) {
                authorize();
            }
        });
        if (TextUtils.isEmpty(BuildConfig.AUTHORIZE_REDIRECT_URI)) {
            mAuthorize.setVisibility(View.GONE);
        }

        mGcmUnregister = (Button) findViewById(R.id.gcm_unregister);
        mGcmUnregister.setVisibility(View.GONE);

        mGoogleSignIn = (SignInButton) findViewById(R.id.google_sign_in);
        mGoogleSignIn.setOnClickListener(new OnClickListener() {
            @Override
            public void onClick(View view) {
                attemptLoginGoogle();
            }
        });
        new ToolsLoginSocialRequest().start();

        mRegister = (Button) findViewById(R.id.register);
        mRegister.setOnClickListener(new OnClickListener() {
            @Override
            public void onClick(View view) {
                register(null);
            }
        });

        if (RegistrationService.canRun(LoginActivity.this)) {
            mGcmReceiver = new BroadcastReceiver() {
                @Override
                public void onReceive(Context context, Intent intent) {
                    if (intent.getBooleanExtra(RegistrationService.ACTION_REGISTRATION_UNREGISTERED, false)) {
                        mGcmUnregister.setVisibility(View.GONE);
                    } else {
                        mGcmUnregister.setVisibility(View.VISIBLE);
                    }
                }
            };

            mGcmUnregister.setOnClickListener(new OnClickListener() {
                @Override
                public void onClick(View view) {
                    Intent gcmIntent = new Intent(LoginActivity.this, RegistrationService.class);
                    gcmIntent.putExtra(RegistrationService.EXTRA_UNREGISTER, true);
                    startService(gcmIntent);
                }
            });

            if (AccessTokenHelper.load(this) == null) {
                // only register if no existing token found
                Intent gcmIntent = new Intent(LoginActivity.this, RegistrationService.class);
                startService(gcmIntent);
            }
        }

        Intent intent = getIntent();
        if (intent != null) {
            attemptLogin(intent);
        }
    }

    @Override
    protected void onActivityResult(int requestCode, int resultCode, Intent data) {
        super.onActivityResult(requestCode, resultCode, data);

        switch (requestCode) {
            case RC_GOOGLE_API_RESOLVE:
                if (resultCode == RESULT_OK) {
                    mGoogleApiShouldResolve = false;
                }

                mGoogleApiIsResolving = false;
                mGoogleApiClient.connect();
                break;
            case RC_REGISTER:
                if (resultCode == RESULT_OK
                        && data.hasExtra(RegisterActivity.RESULT_EXTRA_ACCESS_TOKEN)) {
                    Api.AccessToken at = (Api.AccessToken) data.getSerializableExtra(RegisterActivity.RESULT_EXTRA_ACCESS_TOKEN);
                    if (at != null) {
                        attemptLogin(at);
                    }
                }
                break;
        }
    }

    @Override
    protected void onResume() {
        super.onResume();

        mEmailView.setText("");
        mPasswordView.setText("");

        final Api.AccessToken at = AccessTokenHelper.load(this);
        if (at != null) {
            mRememberView.setChecked(true);

            AlertDialog.Builder builder = new AlertDialog.Builder(this);
            builder.setMessage(R.string.sign_in_with_remember)
                    .setPositiveButton(android.R.string.yes, new DialogInterface.OnClickListener() {
                        @Override
                        public void onClick(DialogInterface dialogInterface, int i) {
                            attemptLogin(at);
                        }
                    })
                    .setNegativeButton(android.R.string.no, new DialogInterface.OnClickListener() {
                        @Override
                        public void onClick(DialogInterface dialogInterface, int i) {
                            mRememberView.setChecked(false);
                            AccessTokenHelper.save(LoginActivity.this, null);
                        }
                    })
                    .show();
        }

        mEmailView.requestFocus();

        if (mGcmReceiver != null) {
            IntentFilter intentFilter = new IntentFilter(RegistrationService.ACTION_REGISTRATION);
            registerReceiver(mGcmReceiver, intentFilter);
        }
    }

    @Override
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);

        if (intent != null) {
            attemptLogin(intent);
        }
    }

    @Override
    protected void onPause() {
        super.onPause();

        if (mGcmReceiver != null) {
            unregisterReceiver(mGcmReceiver);
        }
    }

    @Override
    protected void onStop() {
        super.onStop();
        mGoogleApiClient.disconnect();
    }

    private void populateAutoComplete() {
        getLoaderManager().initLoader(0, null, this);
    }

    private void authorize() {
        Intent loginIntent = getIntent();
        String redirectTo = null;
        if (loginIntent.hasExtra(EXTRA_REDIRECT_TO)) {
            redirectTo = loginIntent.getStringExtra(EXTRA_REDIRECT_TO);
        }

        String authorizeUri = Api.makeAuthorizeUri(redirectTo);
        Intent intent = new Intent(Intent.ACTION_VIEW, Uri.parse(authorizeUri));
        startActivity(intent);
        finish();
    }

    private void register(Api.User u) {
        Intent registerIntent = new Intent(LoginActivity.this, RegisterActivity.class);
        registerIntent.putExtra(RegisterActivity.EXTRA_USER, u);
        startActivityForResult(registerIntent, RC_REGISTER);
    }

    private void attemptLoginGoogle() {
        mGoogleApiShouldResolve = true;
        mGoogleApiClient.connect();
    }

    private void attemptLogin() {
        if (mTokenRequest != null) {
            return;
        }

        // Reset errors.
        mEmailView.setError(null);
        mPasswordView.setError(null);

        // Store values at the time of the login attempt.
        String email = mEmailView.getText().toString();
        String password = mPasswordView.getText().toString();

        boolean cancel = false;
        View focusView = null;

        // Check for a valid email address.
        if (TextUtils.isEmpty(email)) {
            mEmailView.setError(getString(R.string.error_field_required));
            focusView = mEmailView;
            cancel = true;
        }

        // Check for a valid password, if the user entered one.
        if (TextUtils.isEmpty(password)) {
            mPasswordView.setError(getString(R.string.error_field_required));
            focusView = mPasswordView;
            cancel = true;
        }

        if (cancel) {
            // There was an error; don't attempt login and focus the first
            // form field with an error.
            focusView.requestFocus();
        } else {
            // Show a progress spinner, and kick off a background task to
            // perform the user login attempt.
            new PasswordRequest(email, password).start();
        }
    }

    private void attemptLogin(Intent intent) {
        if (TextUtils.isEmpty(BuildConfig.AUTHORIZE_REDIRECT_URI)) {
            return;
        }

        Uri data = intent.getData();
        if (data == null) {
            return;
        }

        String code = data.getQueryParameter("code");

        String redirectTo = data.getQueryParameter("redirect_to");
        if (!TextUtils.isEmpty(redirectTo)) {
            intent.putExtra(EXTRA_REDIRECT_TO, redirectTo);
        }

        if (TextUtils.isEmpty(code)) {
            Toast.makeText(this, R.string.error_no_authorization_code, Toast.LENGTH_LONG).show();
        } else {
            new AuthorizationCodeRequest(code, redirectTo).start();
        }

        setIntent(intent);
    }

    private void attemptLogin(Api.AccessToken at) {
        if (mTokenRequest != null) {
            return;
        }

        if (TextUtils.isEmpty(at.getRefreshToken())) {
            Toast.makeText(this, R.string.error_no_refresh_token, Toast.LENGTH_LONG).show();
        } else {
            new RefreshTokenRequest(at.getRefreshToken()).start();
        }
    }

    @Override
    public Loader<Cursor> onCreateLoader(int i, Bundle bundle) {
        return new CursorLoader(this,
                // Retrieve data rows for the device user's 'profile' contact.
                Uri.withAppendedPath(ContactsContract.Profile.CONTENT_URI,
                        ContactsContract.Contacts.Data.CONTENT_DIRECTORY), ProfileQuery.PROJECTION,

                // Select only email addresses.
                ContactsContract.Contacts.Data.MIMETYPE +
                        " = ?", new String[]{ContactsContract.CommonDataKinds.Email
                .CONTENT_ITEM_TYPE},

                // Show primary email addresses first. Note that there won't be
                // a primary email address if the user hasn't specified one.
                ContactsContract.Contacts.Data.IS_PRIMARY + " DESC");
    }

    @Override
    public void onLoadFinished(Loader<Cursor> cursorLoader, Cursor cursor) {
        List<String> emails = new ArrayList<>();
        cursor.moveToFirst();
        while (!cursor.isAfterLast()) {
            emails.add(cursor.getString(ProfileQuery.ADDRESS));
            cursor.moveToNext();
        }

        addEmailsToAutoComplete(emails);
    }

    @Override
    public void onLoaderReset(Loader<Cursor> cursorLoader) {

    }

    @Override
    public void onConnected(Bundle bundle) {
        mGoogleApiShouldResolve = false;

        new GetIdTokenTask().execute();
    }

    @Override
    public void onConnectionSuspended(int i) {
        // for Google+, do nothing for now
    }

    @Override
    public void onConnectionFailed(ConnectionResult connectionResult) {
        boolean resolved = false;

        if (!mGoogleApiIsResolving && mGoogleApiShouldResolve) {
            if (connectionResult.hasResolution()) {
                try {
                    connectionResult.startResolutionForResult(this, RC_GOOGLE_API_RESOLVE);
                    mGoogleApiIsResolving = true;
                } catch (IntentSender.SendIntentException e) {
                    mGoogleApiIsResolving = false;
                    mGoogleApiClient.connect();
                }

                resolved = true;
            }
        }

        if (!resolved) {
            Toast.makeText(this, R.string.error_google_failed, Toast.LENGTH_LONG).show();
        }
    }

    private interface ProfileQuery {
        String[] PROJECTION = {
                ContactsContract.CommonDataKinds.Email.ADDRESS,
                ContactsContract.CommonDataKinds.Email.IS_PRIMARY,
        };

        int ADDRESS = 0;
    }

    private void addEmailsToAutoComplete(List<String> emailAddressCollection) {
        //Create adapter to tell the AutoCompleteTextView what to show in its dropdown list.
        ArrayAdapter<String> adapter =
                new ArrayAdapter<>(LoginActivity.this,
                        android.R.layout.simple_dropdown_item_1line, emailAddressCollection);

        mEmailView.setAdapter(adapter);
    }

    private void setViewsEnabled(boolean enabled) {
        mEmailView.setEnabled(enabled);
        mPasswordView.setEnabled(enabled);
        mRememberView.setEnabled(enabled);
        mSignIn.setEnabled(enabled);
        mAuthorize.setEnabled(enabled);
        mGcmUnregister.setEnabled(enabled);

        if (mFacebook != null) {
            mFacebook.setEnabled(enabled);
        }
        if (mTwitter != null) {
            mTwitter.setEnabled(enabled);
        }
        if (mGoogleSignIn != null) {
            mGoogleSignIn.setEnabled(enabled);
        }

        mRegister.setEnabled(enabled);
    }

    private abstract class TokenRequest extends Api.PostRequest {
        TokenRequest(Map<String, String> params) {
            super(Api.URL_OAUTH_TOKEN, params);
        }

        TokenRequest(String url, Map<String, String> params) {
            super(url, params);
        }

        @Override
        protected void onStart() {
            mTokenRequest = this;
            setViewsEnabled(false);
            AccessTokenHelper.save(LoginActivity.this, null);
        }

        @Override
        protected void onSuccess(JSONObject response) {
            Api.AccessToken at = Api.makeAccessToken(response);
            if (at == null) {
                if (response.has("user_data")) {
                    try {
                        Api.User u = Api.makeUser(response.getJSONObject("user_data"));
                        if (u != null) {
                            register(u);
                        }
                    } catch (JSONException e) {
                        // ignore
                    }
                }

                return;
            }

            if (mRememberView.isChecked()) {
                AccessTokenHelper.save(LoginActivity.this, at);
            }

            Intent mainIntent = new Intent(LoginActivity.this, MainActivity.class);
            mainIntent.putExtra(MainActivity.EXTRA_ACCESS_TOKEN, at);

            Intent loginIntent = getIntent();
            if (loginIntent != null && loginIntent.hasExtra(EXTRA_REDIRECT_TO)) {
                mainIntent.putExtra(MainActivity.EXTRA_URL, loginIntent.getStringExtra(EXTRA_REDIRECT_TO));
            }

            startActivity(mainIntent);

            if (RegistrationService.canRun(LoginActivity.this)) {
                Intent gcmIntent = new Intent(LoginActivity.this, RegistrationService.class);
                gcmIntent.putExtra(RegistrationService.EXTRA_ACCESS_TOKEN, at);
                startService(gcmIntent);
            }

            finish();
        }

        @Override
        protected void onError(VolleyError error) {
            String message = getErrorMessage(error);

            if (message != null) {
                Toast.makeText(LoginActivity.this, message, Toast.LENGTH_LONG).show();
            }
        }

        @Override
        protected void onComplete() {
            mTokenRequest = null;
            setViewsEnabled(true);
        }
    }

    private class PasswordRequest extends TokenRequest {
        PasswordRequest(String email, String password) {
            super(
                    new Api.Params(
                            Api.URL_OAUTH_TOKEN_PARAM_GRANT_TYPE,
                            Api.URL_OAUTH_TOKEN_PARAM_GRANT_TYPE_PASSWORD)
                            .and(Api.URL_OAUTH_TOKEN_PARAM_USERNAME, email)
                            .and(Api.URL_OAUTH_TOKEN_PARAM_PASSWORD, password)
                            .andClientCredentials()
            );
        }
    }

    private class AuthorizationCodeRequest extends TokenRequest {
        AuthorizationCodeRequest(String code, String redirectTo) {
            super(
                    new Api.Params(
                            Api.URL_OAUTH_TOKEN_PARAM_GRANT_TYPE,
                            Api.URL_OAUTH_TOKEN_PARAM_GRANT_TYPE_AUTHORIZATION_CODE)
                            .and(Api.URL_OAUTH_TOKEN_PARAM_CODE, code)
                            .and(Api.URL_OAUTH_TOKEN_PARAM_REDIRECT_URI, Api.makeAuthorizeRedirectUri(redirectTo))
                            .andClientCredentials()
            );
        }

        @Override
        protected void onStart() {
            super.onStart();

            // auto remember with authorization code flow
            mRememberView.setChecked(true);
        }
    }

    private class RefreshTokenRequest extends TokenRequest {
        RefreshTokenRequest(String refreshToken) {
            super(
                    new Api.Params(
                            Api.URL_OAUTH_TOKEN_PARAM_GRANT_TYPE,
                            Api.URL_OAUTH_TOKEN_PARAM_GRANT_TYPE_REFRESH_TOKEN)
                            .and(Api.URL_OAUTH_TOKEN_PARAM_REFRESH_TOKEN, refreshToken)
                            .andClientCredentials()
            );
        }
    }

    private class ToolsLoginSocialRequest extends Api.PostRequest {
        ToolsLoginSocialRequest() {
            super(Api.URL_TOOLS_LOGIN_SOCIAL, new Api.Params(0, null));
        }

        @Override
        protected void onSuccess(JSONObject response) {
            try {
                if (response.has("social")) {
                    JSONArray networks = response.getJSONArray("social");
                    boolean facebook = false;
                    boolean twitter = false;
                    boolean google = false;

                    for (int i = 0; i < networks.length(); i++) {
                        String network = networks.getString(i);
                        switch (network) {
                            case "facebook":
                                facebook = true;
                                break;
                            case "twitter":
                                twitter = true;
                                break;
                            case "google":
                                google = true;
                                break;
                        }
                    }

                    if (mFacebook != null) {
                        mFacebook.setVisibility(facebook ? View.VISIBLE : View.GONE);
                    }
                    if (mTwitter != null) {
                        mTwitter.setVisibility(twitter ? View.VISIBLE : View.GONE);
                    }
                    if (mGoogleSignIn != null) {
                        mGoogleSignIn.setVisibility(google ? View.VISIBLE : View.GONE);
                    }
                }
            } catch (JSONException e) {
                // ignore
            }
        }
    }

    private class GetIdTokenTask extends AsyncTask<String, Void, String> {

        @Override
        protected String doInBackground(String... params) {
            final String accountName = Plus.AccountApi.getAccountName(mGoogleApiClient);
            final String scopes = "oauth2:" + Plus.SCOPE_PLUS_LOGIN.toString();
            String idToken = "";

            try {
                idToken = GoogleAuthUtil.getToken(
                        getApplicationContext(),
                        accountName,
                        scopes);
            } catch (Exception e) {
                // ignore
            }

            return idToken;
        }

        @Override
        protected void onPostExecute(String result) {
            if (TextUtils.isEmpty(result)) {
                Toast.makeText(LoginActivity.this, R.string.error_google_no_token, Toast.LENGTH_LONG).show();
                return;
            }

            new TokenGoogleRequest(result).start();
        }

    }

    private class TokenGoogleRequest extends TokenRequest {
        TokenGoogleRequest(String idToken) {
            super(
                    Api.URL_OAUTH_TOKEN_GOOGLE,
                    new Api.Params(Api.URL_OAUTH_TOKEN_GOOGLE_PARAM_TOKEN, idToken)
                            .andClientCredentials()
            );
        }
    }
}

