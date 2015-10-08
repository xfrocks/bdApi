package com.xfrocks.api.androiddemo;

import android.content.Context;
import android.graphics.Bitmap;
import android.net.Uri;

import com.android.volley.RequestQueue;
import com.android.volley.toolbox.ImageLoader;
import com.android.volley.toolbox.Volley;

import java.io.File;

public class App extends android.app.Application {

    private static App sInstance;

    private RequestQueue mRequestQueue;
    private ImageLoader mNetworkImageLoader;

    @Override
    public void onCreate() {
        super.onCreate();

        mRequestQueue = Volley.newRequestQueue(getApplicationContext());

        sInstance = this;
    }

    public RequestQueue getRequestQueue() {
        return mRequestQueue;
    }

    public ImageLoader getNetworkImageLoader() {
        if (mNetworkImageLoader == null) {
            // create a network image loader with a super simple image cache
            // which only keep cached data of one bitmap for one url at a time
            mNetworkImageLoader = new ImageLoader(getRequestQueue(), new ImageLoader.ImageCache() {
                String mUrl;
                Bitmap mBitmap;

                @Override
                public Bitmap getBitmap(String url) {
                    if (url.equals(mUrl)) {
                        return mBitmap;
                    }

                    return null;
                }

                @Override
                public void putBitmap(String url, Bitmap bitmap) {
                    mUrl = url;
                    mBitmap = bitmap;
                }
            });
        }

        return mNetworkImageLoader;
    }

    public synchronized static App getInstance() {
        return sInstance;
    }

    public static Uri getTempForCamera(Context context) {
        return Uri.fromFile(new File(context.getExternalCacheDir(), "camera.jpg"));
    }

}
