package com.xfrocks.api.androiddemo.gcm;

import android.app.IntentService;
import android.content.Context;
import android.content.Intent;
import android.util.Log;
import android.widget.Toast;

import com.google.android.gms.common.ConnectionResult;
import com.google.android.gms.common.GooglePlayServicesUtil;
import com.google.android.gms.gcm.GoogleCloudMessaging;
import com.google.android.gms.iid.InstanceID;
import com.xfrocks.api.androiddemo.Api;
import com.xfrocks.api.androiddemo.App;
import com.xfrocks.api.androiddemo.BuildConfig;
import com.xfrocks.api.androiddemo.R;

import org.json.JSONObject;

public class RegistrationService extends IntentService {

    public static final String EXTRA_ACCESS_TOKEN = "access_token";

    private static final String TAG = "RegistrationService";
    private static long mLastUserId = -1;

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
        long userId = 0;

        if (at != null) {
            userId = at.getUserId();
        }

        if (mLastUserId == -1 || mLastUserId != userId) {
            new RegisterRequest(gcmToken, userId, at).start();
            mLastUserId = userId;
        }
    }

    private static class RegisterRequest extends Api.PushServerRequest {
        private long mUserId;

        RegisterRequest(String gcmToken, long userId, Api.AccessToken at) {
            super(true, gcmToken, userId > 0 ? String.format("user_notification_%d", userId) : "", at);

            mUserId = userId;
        }

        @Override
        protected void onSuccess(JSONObject response) {
            final Toast t;
            final Context c = App.getInstance().getApplicationContext();

            if (mUserId == 0) {
                t = Toast.makeText(c, R.string.gcm_register_success, Toast.LENGTH_LONG);
            } else {
                t = Toast.makeText(
                        c,
                        String.format(c.getString(R.string.gcm_subscribe_x_success), String.valueOf(mUserId)),
                        Toast.LENGTH_LONG
                );
            }

            t.show();
        }
    }

}