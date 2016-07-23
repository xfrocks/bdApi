package com.xfrocks.api.androiddemo;

import android.content.Context;
import android.graphics.Bitmap;
import android.net.Uri;
import android.support.multidex.MultiDexApplication;
import android.text.TextUtils;

import com.android.volley.RequestQueue;
import com.android.volley.toolbox.HttpStack;
import com.android.volley.toolbox.HurlStack;
import com.android.volley.toolbox.ImageLoader;
import com.android.volley.toolbox.Volley;
import com.xfrocks.api.androiddemo.helper.PubKeyManager;

import java.io.File;
import java.security.KeyManagementException;
import java.security.NoSuchAlgorithmException;

import javax.net.ssl.SSLContext;
import javax.net.ssl.SSLSocketFactory;
import javax.net.ssl.TrustManager;

public class App extends MultiDexApplication {

    private static App sInstance;

    private RequestQueue mRequestQueue;
    private ImageLoader mNetworkImageLoader;

    @Override
    public void onCreate() {
        super.onCreate();

        String publicKey = BuildConfig.PUBLIC_KEY;
        HttpStack httpStack = null;
        if (!TextUtils.isEmpty(publicKey)) {
            try {
                TrustManager tm[] = {new PubKeyManager(publicKey)};
                SSLContext context = SSLContext.getInstance("TLS");
                context.init(null, tm, null);
                SSLSocketFactory pinnedSSLSocketFactory = context.getSocketFactory();
                httpStack = new HurlStack(null, pinnedSSLSocketFactory);
            } catch (Exception e) {
                e.printStackTrace();
            }
        }

        mRequestQueue = Volley.newRequestQueue(getApplicationContext(), httpStack);

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
