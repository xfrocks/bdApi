package com.xfrocks.api.androiddemo;

import android.app.DatePickerDialog;
import android.app.Dialog;
import android.app.ProgressDialog;
import android.content.Intent;
import android.os.Bundle;
import android.support.annotation.NonNull;
import android.support.design.widget.TabLayout;
import android.support.v4.app.DialogFragment;
import android.support.v4.app.FragmentManager;
import android.support.v7.app.AppCompatActivity;
import android.text.TextUtils;
import android.view.View;
import android.view.View.OnClickListener;
import android.widget.Button;
import android.widget.DatePicker;
import android.widget.EditText;
import android.widget.Toast;

import org.json.JSONException;
import org.json.JSONObject;

import java.util.Calendar;

public class RegisterActivity extends AppCompatActivity
        implements TfaDialogFragment.TfaDialogListener {

    public static final String EXTRA_USER = "user";
    public static final String RESULT_EXTRA_ACCESS_TOKEN = "access_token";

    private EditText mEmailView;
    private EditText mUsernameView;
    private EditText mPasswordView;
    private EditText mDobView;
    private Button mRegister;
    private ProgressDialog mProgressDialog;

    private Integer mDobYear;
    private Integer mDobMonth;
    private Integer mDobDay;
    private Api.User mTargetUser;
    private Api.User[] mAssocUsers;
    private String mExtraData;
    private long mExtraTimestamp;
    private Api.PostRequest mRegisterRequest;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_register);

        mUsernameView = (EditText) findViewById(R.id.username);
        mEmailView = (EditText) findViewById(R.id.email);
        mPasswordView = (EditText) findViewById(R.id.password);
        mDobView = (EditText) findViewById(R.id.dob);

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

        mRegister = (Button) findViewById(R.id.register);
        mRegister.setOnClickListener(new OnClickListener() {
            @Override
            public void onClick(View view) {
                if (mTargetUser == null) {
                    attemptRegister();
                } else {
                    attemptAssociate(null, null);
                }
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

            mAssocUsers = u.getAssocs();
            mExtraData = u.getExtraData();
            mExtraTimestamp = u.getExtraTimestamp();

            if (mAssocUsers.length > 0) {
                TabLayout mTabLayout = (TabLayout) findViewById(R.id.tab);
                mTabLayout.setVisibility(View.VISIBLE);
                mTabLayout.addTab(mTabLayout.newTab().setText(R.string.action_register));
                for (Api.User assocUser : mAssocUsers) {
                    mTabLayout.addTab(mTabLayout.newTab()
                            .setText(assocUser.getUsername() != null
                                    ? assocUser.getUsername()
                                    : assocUser.getEmail())
                            .setTag(assocUser.getUserId()));
                }
                mTabLayout.setOnTabSelectedListener(new TabLayout.OnTabSelectedListener() {
                    @Override
                    public void onTabSelected(TabLayout.Tab tab) {
                        if (tab.getTag() instanceof Integer) {
                            setRegisterOrAssociate((Integer) tab.getTag());
                        } else {
                            setRegisterOrAssociate(0);
                        }
                    }

                    @Override
                    public void onTabUnselected(TabLayout.Tab tab) {

                    }

                    @Override
                    public void onTabReselected(TabLayout.Tab tab) {

                    }
                });
            }
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

    @Override
    public void onTfaTrigger(String providerId) {
        attemptAssociate(providerId, null);
    }

    @Override
    public void onTfaFinishDialog(String providerId, String code) {
        attemptAssociate(providerId, code);
    }

    /**
     * Attempts to register the account with information from the form fields.
     * If there are input errors (invalid email, missing fields, etc.), the
     * errors are presented and no actual register attempt is made.
     */
    private void attemptRegister() {
        if (mRegisterRequest != null) {
            return;
        }
        if (mTargetUser != null) {
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

    private void attemptAssociate(String tfaProviderId, String tfaProviderCode) {
        if (mRegisterRequest != null) {
            return;
        }
        if (mTargetUser == null) {
            return;
        }

        // Reset errors.
        mPasswordView.setError(null);

        String password = mPasswordView.getText().toString();

        boolean cancel = false;
        View focusView = null;

        if (TextUtils.isEmpty(password)) {
            mPasswordView.setError(getString(R.string.error_field_required));
            focusView = mPasswordView;
            cancel = true;
        } else if (!isPasswordValid(password)) {
            mPasswordView.setError(getString(R.string.error_invalid_password));
            focusView = mPasswordView;
            cancel = true;
        }

        if (cancel) {
            focusView.requestFocus();
        } else {
            new AssociateRequest(mTargetUser.getUserId(), password, tfaProviderId, tfaProviderCode).start();
        }
    }

    private boolean isEmailValid(String email) {
        return email.contains("@");
    }

    private boolean isPasswordValid(String password) {
        return password.length() > 4;
    }

    private void setRegisterOrAssociate(int userId) {
        mTargetUser = null;
        for (Api.User assocUser : mAssocUsers) {
            if (assocUser.getUserId() == userId) {
                mTargetUser = assocUser;
            }
        }

        if (mTargetUser == null) {
            mUsernameView.setVisibility(View.VISIBLE);
            mEmailView.setVisibility(View.VISIBLE);
            mPasswordView.setText("");
            mDobView.setVisibility(View.VISIBLE);
            mRegister.setText(R.string.action_register);
        } else {
            mUsernameView.setVisibility(View.GONE);
            mEmailView.setVisibility(View.GONE);
            mPasswordView.setText("");
            mDobView.setVisibility(View.GONE);
            mRegister.setText(R.string.action_associate);
        }
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
                        return;
                    }
                } catch (JSONException e) {
                    // ignore
                }
            }

            String errorMessage = getErrorMessage(response);
            if (errorMessage != null) {
                Toast.makeText(RegisterActivity.this, errorMessage, Toast.LENGTH_LONG).show();
                return;
            }
        }

        @Override
        void onComplete() {
            mRegisterRequest = null;
            setViewsEnabled(true);
        }
    }

    private class AssociateRequest extends Api.PostRequest {
        public AssociateRequest(int userId, String password, String tfaProviderId, String tfaProviderCode) {
            super(Api.URL_OAUTH_TOKEN_ASSOCIATE, new Api.Params(Api.URL_USERS_PARAM_USER_ID, userId)
                    .and(Api.URL_USERS_PARAM_PASSWORD, password)
                    .and(Api.URL_USERS_PARAM_EXTRA_DATA, mExtraData)
                    .and(Api.URL_USERS_PARAM_EXTRA_TIMESTAMP, mExtraTimestamp)
                    .andIf(tfaProviderId != null,
                            Api.URL_OAUTH_TOKEN_PARAM_TFA_PROVIDER_ID, tfaProviderId)
                    .andIf(tfaProviderId != null && tfaProviderCode == null,
                            Api.URL_OAUTH_TOKEN_PARAM_TFA_TRIGGER, 1)
                    .andIf(tfaProviderId != null && tfaProviderCode != null,
                            Api.URL_OAUTH_TOKEN_PARAM_TFA_PROVIDER_CODE, tfaProviderCode)
                    .andClientCredentials());
        }

        @Override
        void onStart() {
            mRegisterRequest = this;
            setViewsEnabled(false);
        }

        @Override
        protected void onSuccess(JSONObject response) {
            Api.AccessToken at = Api.makeAccessToken(response);
            if (at != null) {
                Intent resultIntent = new Intent();
                resultIntent.putExtra(RESULT_EXTRA_ACCESS_TOKEN, at);
                setResult(RESULT_OK, resultIntent);
                finish();
                return;
            }

            if (mResponseHeaders != null
                    && mResponseHeaders.containsKey(Api.URL_OAUTH_TOKEN_RESPONSE_HEADER_TFA_PROVIDERS)) {
                String headerValue = mResponseHeaders.get(Api.URL_OAUTH_TOKEN_RESPONSE_HEADER_TFA_PROVIDERS);
                String[] providerIds = headerValue.split(",");

                FragmentManager fm = getSupportFragmentManager();
                TfaDialogFragment tfaDialog = TfaDialogFragment.newInstance(providerIds);
                tfaDialog.show(fm, tfaDialog.getClass().getSimpleName());

                return;
            }

            String errorMessage = getErrorMessage(response);
            if (errorMessage != null) {
                Toast.makeText(RegisterActivity.this, errorMessage, Toast.LENGTH_LONG).show();
                return;
            }
        }

        @Override
        void onComplete() {
            mRegisterRequest = null;
            setViewsEnabled(true);
        }
    }
}

