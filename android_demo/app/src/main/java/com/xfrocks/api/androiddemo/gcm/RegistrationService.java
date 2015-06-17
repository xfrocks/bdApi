package com.xfrocks.api.androiddemo.gcm;

import android.app.IntentService;
import android.content.Context;
import android.content.Intent;
import android.util.Log;

import com.google.android.gms.common.ConnectionResult;
import com.google.android.gms.common.GooglePlayServicesUtil;
import com.google.android.gms.gcm.GoogleCloudMessaging;
import com.google.android.gms.iid.InstanceID;
import com.xfrocks.api.androiddemo.Api;
import com.xfrocks.api.androiddemo.BuildConfig;
import com.xfrocks.api.androiddemo.R;

public class RegistrationService extends IntentService {

    public static final String EXTRA_ACCESS_TOKEN = "access_token";

    private static final String TAG = "RegistrationService";
    private static String mTopic = null;

    public RegistrationService() {
        super(TAG);
    }

    public static boolean canRun(Context context) {
        boolean canRun;

        // check for push server address
        canRun = !BuildConfig.PUSH_SERVER.isEmpty();

        // check for Google Play Services
        int resultCode = GooglePlayServicesUtil.isGooglePlayServicesAvailable(context);
        canRun = canRun && (resultCode == ConnectionResult.SUCCESS);

        return canRun;

    }

    @Override
    protected void onHandleIntent(Intent intent) {
        try {
            // In the (unlikely) event that multiple refresh operations occur simultaneously,
            // ensure that they are processed sequentially.
            synchronized (TAG) {
                InstanceID instanceID = InstanceID.getInstance(this);
                String token = instanceID.getToken(getString(R.string.gcm_defaultSenderId),
                        GoogleCloudMessaging.INSTANCE_ID_SCOPE, null);

                if (BuildConfig.DEBUG) {
                    Log.v(TAG, "GCM token=" + token);
                }

                Api.AccessToken at = null;
                if (intent.hasExtra(EXTRA_ACCESS_TOKEN)) {
                    at = (Api.AccessToken) intent.getSerializableExtra(EXTRA_ACCESS_TOKEN);
                }

                sendRegistrationToServer(token, at);
            }
        } catch (Exception e) {
            if (BuildConfig.DEBUG) {
                Log.e(TAG, "Unable to get GCM token", e);
            }
        }
    }

    private void sendRegistrationToServer(String gcmToken, Api.AccessToken at) {
        final String topic;

        if (at != null && at.getUserId() > 0) {
            topic = String.format("user_notification_%d", at.getUserId());
        } else {
            topic = "";
        }

        if (mTopic == null || !mTopic.equals(topic)) {
            new RegisterRequest(gcmToken, topic, at).start();
        }
    }

    private static class RegisterRequest extends Api.PushServerRequest {
        RegisterRequest(String gcmToken, String topic, Api.AccessToken at) {
            super(true, gcmToken, topic, at);
        }
    }

}