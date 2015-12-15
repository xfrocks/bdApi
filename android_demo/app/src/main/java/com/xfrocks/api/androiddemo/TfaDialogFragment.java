package com.xfrocks.api.androiddemo;

import android.app.Activity;
import android.os.Bundle;
import android.support.annotation.Nullable;
import android.support.v4.app.DialogFragment;
import android.text.TextUtils;
import android.view.KeyEvent;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.view.WindowManager;
import android.view.inputmethod.EditorInfo;
import android.widget.EditText;
import android.widget.RadioButton;
import android.widget.RadioGroup;
import android.widget.TextView;

public class TfaDialogFragment extends DialogFragment
        implements TextView.OnEditorActionListener,
        RadioGroup.OnCheckedChangeListener {

    public interface TfaDialogListener {
        void onTfaTrigger(String providerId);

        void onTfaFinishDialog(String providerId, String code);
    }

    private static final String ARG_PROVIDER_IDS = "providerIds";

    public static TfaDialogFragment newInstance(String[] providerIds) {
        TfaDialogFragment fragment = new TfaDialogFragment();

        Bundle args = new Bundle();
        args.putStringArray(ARG_PROVIDER_IDS, providerIds);
        fragment.setArguments(args);

        return fragment;
    }

    private RadioGroup mProvidersGroup;
    private String mProviderId = null;

    private EditText mCode;

    @Nullable
    @Override
    public View onCreateView(LayoutInflater inflater, ViewGroup container, Bundle savedInstanceState) {
        View view = inflater.inflate(R.layout.dialog_tfa, container);

        mProvidersGroup = (RadioGroup) view.findViewById(R.id.tfa_providers);
        mProvidersGroup.setOnCheckedChangeListener(this);
        mCode = (EditText) view.findViewById(R.id.tfa_code);
        mCode.setOnEditorActionListener(this);

        getDialog().setTitle(R.string.tfa_required);

        return view;
    }

    @Override
    public void onResume() {
        super.onResume();

        Bundle args = getArguments();
        if (args.containsKey(ARG_PROVIDER_IDS)) {
            String[] providerIds = args.getStringArray(ARG_PROVIDER_IDS);
            createProviderButtons(providerIds);
        }
    }

    @Override
    public void onCheckedChanged(RadioGroup group, int checkedId) {
        RadioButton rb = (RadioButton) group.findViewById(checkedId);
        if (rb == null) {
            mCode.setVisibility(View.GONE);
            return;
        }

        Object tag = rb.getTag();
        if (tag == null || !(tag instanceof String)) {
            mCode.setVisibility(View.GONE);
            return;
        }

        mProviderId = (String) tag;
        mCode.setVisibility(View.VISIBLE);
        mCode.requestFocus();
        getDialog().getWindow().setSoftInputMode(
                WindowManager.LayoutParams.SOFT_INPUT_STATE_VISIBLE);

        switch (mProviderId) {
            case "email":
                Activity activity = getActivity();
                if (activity instanceof TfaDialogListener) {
                    TfaDialogListener listener = (TfaDialogListener) activity;
                    listener.onTfaTrigger(mProviderId);
                }
                break;
        }
    }

    @Override
    public boolean onEditorAction(TextView v, int actionId, KeyEvent event) {
        if (EditorInfo.IME_ACTION_DONE == actionId) {
            String code = mCode.getText().toString().trim();
            if (TextUtils.isEmpty(code)) {
                return false;
            }

            Activity activity = getActivity();
            if (!(activity instanceof TfaDialogListener)) {
                return false;
            }

            TfaDialogListener listener = (TfaDialogListener) activity;
            listener.onTfaFinishDialog(mProviderId, mCode.getText().toString());
            dismiss();

            return true;

        }

        return false;
    }

    private void createProviderButtons(String[] providerIds) {
        mProvidersGroup.removeAllViews();

        for (String providerId : providerIds) {
            providerId = providerId.trim().toLowerCase();
            RadioButton rb = new RadioButton(getContext());

            switch (providerId) {
                case "totp":
                    rb.setText(R.string.tfa_totp);
                    rb.setTag(providerId);
                    break;
                case "backup":
                    rb.setText(R.string.tfa_backup);
                    rb.setTag(providerId);
                    break;
                case "email":
                    rb.setText(R.string.tfa_email);
                    rb.setTag(providerId);
                    break;
                default:
                    rb.setText(String.format(getString(R.string.tfa_x_not_supported), providerId));
            }

            mProvidersGroup.addView(rb);
        }

        onCheckedChanged(mProvidersGroup, mProvidersGroup.getCheckedRadioButtonId());
    }
}
