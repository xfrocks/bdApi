package com.xfrocks.api.androiddemo.persist;

import android.content.Context;
import android.util.Log;

import com.xfrocks.api.androiddemo.Api;
import com.xfrocks.api.androiddemo.BuildConfig;

import java.io.FileInputStream;
import java.io.FileNotFoundException;
import java.io.FileOutputStream;
import java.io.IOException;
import java.io.ObjectInputStream;
import java.io.ObjectOutputStream;

public class AccessTokenHelper {

    private static final String TAG = "AccessTokenHelper";

    public static boolean save(Context context, Api.AccessToken at) {
        if (at == null) {
            return context.deleteFile(TAG);
        }

        try {
            FileOutputStream fos = context.openFileOutput(TAG, Context.MODE_PRIVATE);
            ObjectOutputStream os = new ObjectOutputStream(fos);

            os.writeObject(at);
            os.close();
        } catch (IOException e) {
            if (BuildConfig.DEBUG) {
                Log.e(TAG, "save", e);
            }

            return false;
        }

        return true;
    }

    public static Api.AccessToken load(Context context) {
        Api.AccessToken at = null;

        try {
            FileInputStream fis = context.openFileInput(TAG);
            ObjectInputStream is = new ObjectInputStream(fis);

            at = (Api.AccessToken) is.readObject();
            is.close();
        } catch (FileNotFoundException e1) {
            // ignore
        } catch (Exception e) {
            if (BuildConfig.DEBUG) {
                Log.e(TAG, "load", e);
            }
        }

        return at;
    }

}
