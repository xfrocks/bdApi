package com.xfrocks.api.androiddemo;

import android.content.Context;

import com.android.volley.RequestQueue;
import com.android.volley.toolbox.Volley;

import java.io.File;

public class App extends android.app.Application {

    private static App sInstance;

    private RequestQueue mRequestQueue;

    @Override
    public void onCreate() {
        super.onCreate();

        mRequestQueue = Volley.newRequestQueue(getApplicationContext());

        sInstance = this;
    }

    public RequestQueue getRequestQueue() {
        return mRequestQueue;
    }

    public synchronized static App getInstance() {
        return sInstance;
    }

}
