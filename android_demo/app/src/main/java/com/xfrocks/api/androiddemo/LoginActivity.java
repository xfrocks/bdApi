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
import android.content.Loader;
import android.database.Cursor;
import android.net.Uri;
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
import com.android.volley.toolbox.HttpHeaderParser;
import com.xfrocks.api.androiddemo.gcm.RegistrationService;
import com.xfrocks.api.androiddemo.persist.AccessTokenHelper;

import org.json.JSONObject;

import java.util.ArrayList;
import java.util.List;
import java.util.Map;

/**
 * A login screen that offers login via email/password.
 */
public class LoginActivity extends Activity implements LoaderCallbacks<Cursor> {

    private TokenRequest mTokenRequest;
    private BroadcastReceiver mGcmReceiver;

    // UI references.
    private AutoCompleteTextView mEmailView;
    private EditText mPasswordView;
    private CheckBox mRememberView;
    private Button mSignIn;
    private Button mAuthorize;
    private Button mUnregister;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_login);

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

        mUnregister = (Button) findViewById(R.id.unregister);
        mUnregister.setVisibility(View.GONE);

        if (RegistrationService.canRun(LoginActivity.this)) {
            mGcmReceiver = new BroadcastReceiver() {
                @Override
                public void onReceive(Context context, Intent intent) {
                    if (intent.getBooleanExtra(RegistrationService.ACTION_REGISTRATION_UNREGISTERED, false)) {
                        mUnregister.setVisibility(View.GONE);
                    } else {
                        mUnregister.setVisibility(View.VISIBLE);
                    }
                }
            };

            mUnregister.setOnClickListener(new OnClickListener() {
                @Override
                public void onClick(View view) {
                    Intent gcmIntent = new Intent(LoginActivity.this, RegistrationService.class);
                    gcmIntent.putExtra(RegistrationService.EXTRA_UNREGISTER, true);
                    startService(gcmIntent);
                }
            });

            Intent gcmIntent = new Intent(LoginActivity.this, RegistrationService.class);
            startService(gcmIntent);
        }

        Intent intent = getIntent();
        if (intent != null) {
            attemptLogin(intent);
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

    private void populateAutoComplete() {
        getLoaderManager().initLoader(0, null, this);
    }

    public void authorize() {
        String authorizeUri = Api.makeAuthorizeUri();
        Intent intent = new Intent(Intent.ACTION_VIEW, Uri.parse(authorizeUri));
        startActivity(intent);
    }

    public void attemptLogin() {
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

    public void attemptLogin(Intent intent) {
        if (TextUtils.isEmpty(BuildConfig.AUTHORIZE_REDIRECT_URI)) {
            return;
        }

        Uri data = intent.getData();
        if (data == null) {
            return;
        }

        String code = data.getQueryParameter("code");

        if (TextUtils.isEmpty(code)) {
            Toast.makeText(this, R.string.error_no_authorization_code, Toast.LENGTH_LONG).show();
        } else {
            new AuthorizationCodeRequest(code).start();
        }
    }

    public void attemptLogin(Api.AccessToken at) {
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
        mUnregister.setEnabled(enabled);
    }

    private abstract class TokenRequest extends Api.PostRequest {
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
                return;
            }

            if (mRememberView.isChecked()) {
                AccessTokenHelper.save(LoginActivity.this, at);
            }

            Intent intent = new Intent(LoginActivity.this, MainActivity.class);
            intent.putExtra(MainActivity.EXTRA_ACCESS_TOKEN, at);
            startActivity(intent);

            if (RegistrationService.canRun(LoginActivity.this)) {
                Intent gcmIntent = new Intent(LoginActivity.this, RegistrationService.class);
                gcmIntent.putExtra(RegistrationService.EXTRA_ACCESS_TOKEN, at);
                startService(gcmIntent);
            }
        }

        @Override
        protected void onError(VolleyError error) {
            String message = null;

            if (error.getCause() != null) {
                message = error.getCause().getMessage();
            }

            if (message == null) {
                message = error.getMessage();
            }

            if (message == null && error.networkResponse != null) {
                try {
                    String jsonString = new String(error.networkResponse.data,
                            HttpHeaderParser.parseCharset(error.networkResponse.headers));

                    JSONObject jsonObject = new JSONObject(jsonString);

                    if (jsonObject.has("error_description")) {
                        message = jsonObject.getString("error_description");
                    }
                } catch (Exception e) {
                    // ignore
                }
            }

            if (message != null) {
                Toast.makeText(LoginActivity.this, message, Toast.LENGTH_LONG).show();
            }
        }

        @Override
        protected void onComplete(boolean isSuccess) {
            mTokenRequest = null;
            setViewsEnabled(true);
        }
    }

    private class PasswordRequest extends TokenRequest {
        PasswordRequest(String email, String password) {
            super(
                    Api.URL_OAUTH_TOKEN,
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
        AuthorizationCodeRequest(String code) {
            super(
                    Api.URL_OAUTH_TOKEN,
                    new Api.Params(
                            Api.URL_OAUTH_TOKEN_PARAM_GRANT_TYPE,
                            Api.URL_OAUTH_TOKEN_PARAM_GRANT_TYPE_AUTHORIZATION_CODE)
                            .and(Api.URL_OAUTH_TOKEN_PARAM_CODE, code)
                            .and(Api.URL_OAUTH_TOKEN_PARAM_REDIRECT_URI, BuildConfig.AUTHORIZE_REDIRECT_URI)
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
                    Api.URL_OAUTH_TOKEN,
                    new Api.Params(
                            Api.URL_OAUTH_TOKEN_PARAM_GRANT_TYPE,
                            Api.URL_OAUTH_TOKEN_PARAM_GRANT_TYPE_REFRESH_TOKEN)
                            .and(Api.URL_OAUTH_TOKEN_PARAM_REFRESH_TOKEN, refreshToken)
                            .andClientCredentials()
            );
        }
    }
}

