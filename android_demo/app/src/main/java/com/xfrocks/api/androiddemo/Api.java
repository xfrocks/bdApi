package com.xfrocks.api.androiddemo;

import android.util.Log;

import com.android.volley.AuthFailureError;
import com.android.volley.NetworkResponse;
import com.android.volley.Response;
import com.android.volley.VolleyError;
import com.android.volley.toolbox.HttpHeaderParser;
import com.xfrocks.api.androiddemo.persist.Row;

import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;

import java.io.Serializable;
import java.io.UnsupportedEncodingException;
import java.net.URLEncoder;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.util.ArrayList;
import java.util.Date;
import java.util.HashMap;
import java.util.Iterator;
import java.util.List;
import java.util.Map;

public class Api {

    public static final String URL_OAUTH_TOKEN = "oauth/token";
    public static final String URL_INDEX = "index";
    public static final String URL_USERS_ME = "users/me";

    public static final String URL_OAUTH_TOKEN_PARAM_GRANT_TYPE = "grant_type";
    public static final String URL_OAUTH_TOKEN_PARAM_GRANT_TYPE_PASSWORD = "password";
    public static final String URL_OAUTH_TOKEN_PARAM_GRANT_TYPE_REFRESH_TOKEN = "refresh_token";
    public static final String URL_OAUTH_TOKEN_PARAM_GRANT_TYPE_AUTHORIZATION_CODE = "authorization_code";
    public static final String URL_OAUTH_TOKEN_PARAM_USERNAME = "username";
    public static final String URL_OAUTH_TOKEN_PARAM_PASSWORD = "password";
    public static final String URL_OAUTH_TOKEN_PARAM_REFRESH_TOKEN = "refresh_token";
    public static final String URL_OAUTH_TOKEN_PARAM_CODE = "code";
    public static final String URL_OAUTH_TOKEN_PARAM_REDIRECT_URI = "redirect_uri";

    public static AccessToken makeAccessToken(JSONObject response) {
        try {
            AccessToken at = new AccessToken();
            at.token = response.getString("access_token");
            at.userId = response.getLong("user_id");

            if (response.has("refresh_token")) {
                at.refreshToken = response.getString("refresh_token");
            }

            return at;
        } catch (JSONException e) {
            // ignore
        }

        return null;
    }

    public static User makeUser(JSONObject response) {
        try {
            JSONObject user = response.getJSONObject("user");

            User u = new User();
            u.username = user.getString("username");
            u.avatar = user.getJSONObject("links").getString("avatar_big");

            return u;
        } catch (JSONException e) {
            // ignore
        }

        return null;
    }

    private static String makeOneTimeToken(long userId, AccessToken at) {
        long timestamp = new Date().getTime() / 1000 + (long) 3600;

        MessageDigest md;
        try {
            md = MessageDigest.getInstance("MD5");
        } catch (NoSuchAlgorithmException e) {
            return e.getMessage();
        }

        md.update(String.format("%d%d%s%s",
                userId,
                timestamp,
                at != null ? at.getToken() : "",
                BuildConfig.CLIENT_SECRET
        ).getBytes());
        byte[] digest = md.digest();

        StringBuilder sb = new StringBuilder();
        for (byte d : digest) {
            String h = Integer.toHexString(0xFF & d);
            while (h.length() < 2) {
                h = "0" + h;
            }
            sb.append(h);
        }

        return String.format("%d,%d,%s,%s", userId, timestamp, sb, BuildConfig.CLIENT_ID);
    }

    public static String makeAuthorizeUri() {
        try {
            return String.format(
                    "%s/index.php?oauth/authorize/&client_id=%s&redirect_uri=%s&response_type=code&scope=%s",
                    BuildConfig.API_ROOT,
                    URLEncoder.encode(BuildConfig.CLIENT_ID, "UTF-8"),
                    URLEncoder.encode(BuildConfig.AUTHORIZE_REDIRECT_URI, "UTF-8"),
                    URLEncoder.encode("read", "UTF-8")
            );
        } catch (UnsupportedEncodingException e) {
            // ignore
        }

        return null;
    }

    private static String makeUrl(int method, String url, Map<String, String> params) {
        if (!url.contains("://")) {
            url = String.format("%s/index.php?%s", BuildConfig.API_ROOT, url);
        }

        if (method == com.android.volley.Request.Method.GET) {
            // append params to url automatically, and clear the map
            for (String paramKey : params.keySet()) {
                String paramValue = params.get(paramKey);

                try {
                    url += String.format("%s%s=%s", url.contains("?") ? "&" : "?",
                            paramKey, URLEncoder.encode(paramValue, "utf-8"));
                } catch (UnsupportedEncodingException e) {
                    e.printStackTrace();
                }
            }

            params.clear();
        }

        return url;
    }

    public static class Request extends com.android.volley.Request<JSONObject> {

        final Map<String, String> mParams;

        public Request(int method, String url, Map<String, String> params) {
            super(method, url, null);

            mParams = params;

            // a tag must present at construction time so caller should know to cancel
            // the request when its life cycle is interrupted
            setTag(this.getClass().getSimpleName());
        }

        public void start() {
            if (BuildConfig.DEBUG) {
                Log.v(getTag().toString(), "Request=" + getUrl() + " (" + getMethod() + ")");
                for (String key : mParams.keySet()) {
                    Log.v(getTag().toString(), "Request[" + key + "]=" + mParams.get(key));
                }
            }

            onStart();

            App.getInstance().getRequestQueue().add(this);
        }

        @Override
        protected Map<String, String> getParams() throws AuthFailureError {
            return mParams;
        }

        @Override
        protected Response<JSONObject> parseNetworkResponse(NetworkResponse response) {
            try {
                String jsonString =
                        new String(response.data, HttpHeaderParser.parseCharset(response.headers));

                if (BuildConfig.DEBUG) {
                    Log.v(getTag().toString(), "Response=" + jsonString);
                }

                JSONObject jsonObject = new JSONObject(jsonString);

                return Response.success(jsonObject,
                        HttpHeaderParser.parseCacheHeaders(response));
            } catch (Exception e) {
                return Response.error(new VolleyError(e));
            }
        }

        @Override
        protected void deliverResponse(JSONObject response) {
            onSuccess(response);
            onComplete();
        }

        @Override
        public void deliverError(VolleyError error) {
            if (BuildConfig.DEBUG) {
                Log.v(getTag().toString(), "Error=" + error);
            }

            onError(error);
            onComplete();
        }

        void onStart() {
            // do something?
        }

        protected void onSuccess(JSONObject response) {
            // do something?
        }

        void onError(VolleyError error) {
            // do something?
        }

        void onComplete() {
            // do something?
        }

        String getErrorMessage(VolleyError error) {
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
                    } else if (jsonObject.has("errors")) {
                        JSONArray errors = jsonObject.getJSONArray("errors");
                        if (errors.length() > 0) {
                            message = errors.getString(0);
                        }
                    }
                } catch (Exception e) {
                    // ignore
                }
            }

            return message;
        }

        void parseRows(JSONObject obj, List<Row> rows) {
            Iterator<String> keys = obj.keys();
            while (keys.hasNext()) {
                final Row row = new Row();
                row.key = keys.next();

                try {
                    parseRow(obj.get(row.key), row);
                    rows.add(row);
                } catch (JSONException e) {
                    // ignore
                }
            }
        }

        void parseRows(JSONArray array, List<Row> rows) {
            for (int i = 0; i < array.length(); i++) {
                final Row row = new Row();
                row.key = String.valueOf(i);

                try {
                    parseRow(array.get(i), row);
                    rows.add(row);
                } catch (JSONException e) {
                    // ignore
                }
            }
        }

        void parseRow(Object value, Row row) {
            if (value instanceof JSONObject) {
                row.value = "(object)";
                row.subRows = new ArrayList<>();
                parseRows((JSONObject) value, row.subRows);
            } else if (value instanceof JSONArray) {
                row.value = "(array)";
                row.subRows = new ArrayList<>();
                parseRows((JSONArray) value, row.subRows);
            } else {
                row.value = String.valueOf(value);
            }
        }
    }

    public static class GetRequest extends Request {
        public GetRequest(String url, Map<String, String> params) {
            super(Method.GET, makeUrl(Method.GET, url, params), params);
        }
    }

    public static class PostRequest extends Request {
        public PostRequest(String url, Map<String, String> params) {
            super(Method.POST, makeUrl(Method.POST, url, params), params);
        }
    }

    public static class PushServerRequest extends Request {
        public PushServerRequest(String deviceId, String topic, AccessToken at) {
            super(
                    Method.POST,
                    BuildConfig.PUSH_SERVER + ("/subscribe"),
                    new Params("device_type", "android")
                            .and("device_id", deviceId)
                            .and("hub_uri", BuildConfig.API_ROOT + "/index.php?subscriptions")
                            .and("hub_topic", topic)
                            .and("oauth_client_id", BuildConfig.CLIENT_ID)
                            .and("oauth_token", Api.makeOneTimeToken(at != null ? at.getUserId() : 0, at))
            );
        }

        @Override
        protected Response<JSONObject> parseNetworkResponse(NetworkResponse response) {
            return Response.success(null, HttpHeaderParser.parseCacheHeaders(response));
        }
    }

    public static class Params extends HashMap<String, String> {

        public Params() {
            super();
        }

        public Params(String key, Object value) {
            super(1);
            put(key, value);
        }

        public Params(AccessToken at) {
            super(1);

            if (at != null) {
                put("oauth_token", at.getToken());
            }
        }

        public Params and(String key, Object value) {
            put(key, value);

            return this;
        }

        public Params andClientCredentials() {
            put("client_id", BuildConfig.CLIENT_ID);
            put("client_secret", BuildConfig.CLIENT_SECRET);

            return this;
        }

        private void put(String key, Object value) {
            if (value != null) {
                put(key, String.valueOf(value));
            }
        }
    }

    public static class AccessToken implements Serializable {

        private String token;
        private String refreshToken;
        private long userId;

        public String getToken() {
            return token;
        }

        public String getRefreshToken() {
            return refreshToken;
        }

        public long getUserId() {
            return userId;
        }

    }

    public static class User implements Serializable {

        private String username;
        private String avatar;

        public String getUsername() {
            return username;
        }

        public String getAvatar() {
            return avatar;
        }

    }

}
