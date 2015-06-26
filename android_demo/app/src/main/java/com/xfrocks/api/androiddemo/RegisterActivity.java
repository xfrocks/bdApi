package com.xfrocks.api.androiddemo;

import android.app.DatePickerDialog;
import android.app.Dialog;
import android.app.LoaderManager.LoaderCallbacks;
import android.app.ProgressDialog;
import android.content.CursorLoader;
import android.content.Intent;
import android.content.Loader;
import android.database.Cursor;
import android.net.Uri;
import android.os.Bundle;
import android.provider.ContactsContract;
import android.support.annotation.NonNull;
import android.support.v4.app.DialogFragment;
import android.support.v7.app.AppCompatActivity;
import android.text.TextUtils;
import android.view.View;
import android.view.View.OnClickListener;
import android.widget.ArrayAdapter;
import android.widget.AutoCompleteTextView;
import android.widget.Button;
import android.widget.DatePicker;
import android.widget.EditText;

import org.json.JSONException;
import org.json.JSONObject;

import java.util.ArrayList;
import java.util.Calendar;
import java.util.List;

public class RegisterActivity extends AppCompatActivity implements LoaderCallbacks<Cursor> {

    public static final String EXTRA_USER = "user";
    public static final String RESULT_EXTRA_ACCESS_TOKEN = "access_token";

    private AutoCompleteTextView mEmailView;
    private EditText mUsernameView;
    private EditText mPasswordView;
    private EditText mDobView;
    private ProgressDialog mProgressDialog;

    private Integer mDobYear;
    private Integer mDobMonth;
    private Integer mDobDay;
    private String mExtraData;
    private long mExtraTimestamp;
    private RegisterRequest mRegisterRequest;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_register);

        mUsernameView = (EditText) findViewById(R.id.username);
        mEmailView = (AutoCompleteTextView) findViewById(R.id.email);
        mPasswordView = (EditText) findViewById(R.id.password);
        mDobView = (EditText) findViewById(R.id.dob);

        populateAutoComplete();
        mDobView.setSelectAllOnFocus(true);
        mDobView.setOnFocusChangeListener(new View.OnFocusChangeListener() {
            @Override
            public void onFocusChange(View view, boolean b) {
                if (b) {
                    showDatePicker();
                }
            }
        });
        mDobView.setOnClickListener(new OnClickListener() {
            @Override
            public void onClick(View view) {
                showDatePicker();
            }
        });

        Button mRegister = (Button) findViewById(R.id.register);
        mRegister.setOnClickListener(new OnClickListener() {
            @Override
            public void onClick(View view) {
                attemptRegister();
            }
        });

        Intent registerIntent = getIntent();
        if (registerIntent != null && registerIntent.hasExtra(EXTRA_USER)) {
            Api.User u = (Api.User) registerIntent.getSerializableExtra(EXTRA_USER);

            final String username = u.getUsername();
            if (!TextUtils.isEmpty(username)) {
                mUsernameView.setText(username);
            }

            final String email = u.getEmail();
            if (!TextUtils.isEmpty(email)) {
                mEmailView.setText(email);
            }

            final Integer dobYear = u.getDobYear();
            final Integer dobMonth = u.getDobMonth();
            final Integer dobDay = u.getDobDay();
            if (dobYear != null
                    && dobMonth != null
                    && dobDay != null) {
                setDate(dobYear, dobMonth, dobDay);
            }

            mExtraData = u.getExtraData();
            mExtraTimestamp = u.getExtraTimestamp();
        }
    }

    @Override
    protected void onPause() {
        super.onPause();

        if (mRegisterRequest != null) {
            mRegisterRequest.cancel();
        }

        if (mProgressDialog != null) {
            mProgressDialog.dismiss();
            mProgressDialog = null;
        }
    }

    private void populateAutoComplete() {
        getLoaderManager().initLoader(0, null, this);
    }

    /**
     * Attempts to sign in or register the account specified by the login form.
     * If there are form errors (invalid email, missing fields, etc.), the
     * errors are presented and no actual login attempt is made.
     */
    public void attemptRegister() {
        if (mRegisterRequest != null) {
            return;
        }

        // Reset errors.
        mEmailView.setError(null);
        mPasswordView.setError(null);

        String username = mUsernameView.getText().toString().trim();
        String email = mEmailView.getText().toString().trim();
        String password = mPasswordView.getText().toString();

        boolean cancel = false;
        View focusView = null;

        if (TextUtils.isEmpty(username)) {
            mUsernameView.setError(getString(R.string.error_field_required));
            focusView = mUsernameView;
            cancel = true;
        }

        if (TextUtils.isEmpty(password)) {
            if (TextUtils.isEmpty(mExtraData)) {
                // only requires password if no extra data exists
                mPasswordView.setError(getString(R.string.error_field_required));
                focusView = mPasswordView;
                cancel = true;
            }
        } else if (!isPasswordValid(password)) {
            mPasswordView.setError(getString(R.string.error_invalid_password));
            focusView = mPasswordView;
            cancel = true;
        }

        if (TextUtils.isEmpty(email)) {
            mEmailView.setError(getString(R.string.error_field_required));
            focusView = mEmailView;
            cancel = true;
        } else if (!isEmailValid(email)) {
            mEmailView.setError(getString(R.string.error_invalid_email));
            focusView = mEmailView;
            cancel = true;
        }

        if (cancel) {
            focusView.requestFocus();
        } else {
            new RegisterRequest(
                    username, email, password,
                    mDobYear != null ? mDobYear : 0,
                    mDobMonth != null ? mDobMonth : 0,
                    mDobDay != null ? mDobDay : 0
            ).start();
        }
    }

    private boolean isEmailValid(String email) {
        return email.contains("@");
    }

    private boolean isPasswordValid(String password) {
        return password.length() > 4;
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
                new ArrayAdapter<>(RegisterActivity.this,
                        android.R.layout.simple_dropdown_item_1line, emailAddressCollection);

        mEmailView.setAdapter(adapter);
    }

    private void setViewsEnabled(boolean enabled) {
        if (!enabled) {
            // disabling views, let's show the progress dialog (if not yet showing)
            if (mProgressDialog == null) {
                mProgressDialog = new ProgressDialog(RegisterActivity.this);
                mProgressDialog.setIndeterminate(true);
                mProgressDialog.setCancelable(false);
                mProgressDialog.show();
            }
        } else {
            // enabling views, hide the progress dialog if any
            if (mProgressDialog != null) {
                mProgressDialog.dismiss();
                mProgressDialog = null;
            }
        }
    }

    private void showDatePicker() {
        DialogFragment newFragment = new DialogFragment() {
            @NonNull
            @Override
            public Dialog onCreateDialog(Bundle savedInstanceState) {
                final Calendar c = Calendar.getInstance();
                int year = c.get(Calendar.YEAR);
                int month = c.get(Calendar.MONTH);
                int day = c.get(Calendar.DAY_OF_MONTH);

                if (mDobYear != null
                        && mDobMonth != null
                        && mDobDay != null) {
                    year = mDobYear;
                    month = mDobMonth - 1;
                    day = mDobDay;
                }

                // Create a new instance of DatePickerDialog and return it
                return new DatePickerDialog(getActivity(), new DatePickerDialog.OnDateSetListener() {
                    @Override
                    public void onDateSet(DatePicker datePicker, int year, int month, int day) {
                        setDate(year, month + 1, day);
                        mDobView.selectAll();
                    }
                }, year, month, day);
            }
        };
        newFragment.show(getSupportFragmentManager(), "DatePickerDialog");
    }

    private void setDate(int year, int month, int day) {
        mDobYear = year;
        mDobMonth = month;
        mDobDay = day;

        mDobView.setText(String.format("%04d-%02d-%02d", year, month, day));
    }

    private class RegisterRequest extends Api.PostRequest {
        public RegisterRequest(String username,
                               String email,
                               String password,
                               int dobYear, int dobMonth, int dobDay) {
            super(Api.URL_USERS, new Api.Params(Api.URL_USERS_PARAM_USERNAME, username)
                    .and(Api.URL_USERS_PARAM_EMAIL, email)
                    .and(Api.URL_USERS_PARAM_PASSWORD, password)
                    .and(Api.URL_USERS_PARAM_DOB_YEAR, dobYear)
                    .and(Api.URL_USERS_PARAM_DOB_MONTH, dobMonth)
                    .and(Api.URL_USERS_PARAM_DOB_DAY, dobDay)
                    .and(Api.URL_USERS_PARAM_EXTRA_DATA, mExtraData)
                    .and(Api.URL_USERS_PARAM_EXTRA_TIMESTAMP, mExtraTimestamp)
                    .andClientCredentials());
        }

        @Override
        void onStart() {
            mRegisterRequest = this;
            setViewsEnabled(false);
        }

        @Override
        protected void onSuccess(JSONObject response) {
            if (response.has("token")) {
                try {
                    Api.AccessToken at = Api.makeAccessToken(response.getJSONObject("token"));
                    if (at != null) {
                        Intent resultIntent = new Intent();
                        resultIntent.putExtra(RESULT_EXTRA_ACCESS_TOKEN, at);
                        setResult(RESULT_OK, resultIntent);
                        finish();
                    }
                } catch (JSONException e) {
                    // ignore
                }
            }
        }

        @Override
        void onComplete() {
            mRegisterRequest = null;
            setViewsEnabled(true);
        }
    }
}

