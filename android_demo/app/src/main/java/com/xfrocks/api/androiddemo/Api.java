package com.xfrocks.api.androiddemo;

import android.text.TextUtils;
import android.util.Log;

import com.android.volley.AuthFailureError;
import com.android.volley.DefaultRetryPolicy;
import com.android.volley.NetworkResponse;
import com.android.volley.Response;
import com.android.volley.VolleyError;
import com.android.volley.toolbox.HttpHeaderParser;
import com.xfrocks.api.androiddemo.persist.Row;

import org.apache.http.HttpEntity;
import org.apache.http.entity.mime.HttpMultipartMode;
import org.apache.http.entity.mime.MultipartEntityBuilder;
import org.apache.http.entity.mime.content.InputStreamBody;
import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;

import java.io.ByteArrayOutputStream;
import java.io.IOException;
import java.io.InputStream;
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
import java.util.Locale;
import java.util.Map;

public class Api {

    public static final String PARAM_LOCALE = "locale";

    public static final String URL_OAUTH_TOKEN = "oauth/token";
    public static final String URL_OAUTH_TOKEN_FACEBOOK = "oauth/token/facebook";
    public static final String URL_OAUTH_TOKEN_TWITTER = "oauth/token/twitter";
    public static final String URL_OAUTH_TOKEN_GOOGLE = "oauth/token/google";
    public static final String URL_INDEX = "index";
    public static final String URL_USERS = "users";
    public static final String URL_USERS_ME = "users/me";
    public static final String URL_USERS_ME_AVATAR = "users/me/avatar";
    public static final String URL_TOOLS_LOGIN_SOCIAL = "tools/login/social";

    public static final String URL_OAUTH_TOKEN_PARAM_GRANT_TYPE = "grant_type";
    public static final String URL_OAUTH_TOKEN_PARAM_GRANT_TYPE_PASSWORD = "password";
    public static final String URL_OAUTH_TOKEN_PARAM_GRANT_TYPE_REFRESH_TOKEN = "refresh_token";
    public static final String URL_OAUTH_TOKEN_PARAM_GRANT_TYPE_AUTHORIZATION_CODE = "authorization_code";
    public static final String URL_OAUTH_TOKEN_PARAM_USERNAME = "username";
    public static final String URL_OAUTH_TOKEN_PARAM_PASSWORD = "password";
    public static final String URL_OAUTH_TOKEN_PARAM_REFRESH_TOKEN = "refresh_token";
    public static final String URL_OAUTH_TOKEN_PARAM_CODE = "code";
    public static final String URL_OAUTH_TOKEN_PARAM_REDIRECT_URI = "redirect_uri";
    public static final String URL_OAUTH_TOKEN_FACEBOOK_PARAM_TOKEN = "facebook_token";
    public static final String URL_OAUTH_TOKEN_TWITTER_PARAM_URI = "twitter_uri";
    public static final String URL_OAUTH_TOKEN_TWITTER_PARAM_AUTH = "twitter_auth";
    public static final String URL_OAUTH_TOKEN_GOOGLE_PARAM_TOKEN = "google_token";

    public static final String URL_USERS_PARAM_USERNAME = "username";
    public static final String URL_USERS_PARAM_EMAIL = "user_email";
    public static final String URL_USERS_PARAM_PASSWORD = "password";
    public static final String URL_USERS_PARAM_DOB_YEAR = "user_dob_year";
    public static final String URL_USERS_PARAM_DOB_MONTH = "user_dob_month";
    public static final String URL_USERS_PARAM_DOB_DAY = "user_dob_day";
    public static final String URL_USERS_PARAM_EXTRA_DATA = "extra_data";
    public static final String URL_USERS_PARAM_EXTRA_TIMESTAMP = "extra_timestamp";

    public static final String URL_USERS_ME_AVATAR_PARAM_AVATAR = "avatar";

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

    public static User makeUser(JSONObject obj) {
        try {
            User u = new User();

            if (obj.has("user_id")) {
                u.username = obj.getString("username");
                u.avatar = obj.getJSONObject("links").getString("avatar_big");
            } else {
                if (obj.has("username")) {
                    u.username = obj.getString("username");
                }

                if (obj.has("user_email")) {
                    u.userEmail = obj.getString("user_email");
                }

                if (obj.has("user_dob_year")
                        && obj.has("user_dob_month")
                        && obj.has("user_dob_day")) {
                    u.userDobYear = obj.getInt("user_dob_year");
                    u.userDobMonth = obj.getInt("user_dob_month");
                    u.userDobDay = obj.getInt("user_dob_day");
                }

                if (obj.has("extra_data")
                        && obj.has("extra_timestamp")) {
                    u.extraData = obj.getString("extra_data");
                    u.extraTimestamp = obj.getLong("extra_timestamp");
                }
            }

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

    public static String makeAuthorizeRedirectUri(String redirectTo) {
        if (TextUtils.isEmpty(BuildConfig.AUTHORIZE_REDIRECT_URI)) {
            return "";
        }

        if (TextUtils.isEmpty(redirectTo)) {
            return BuildConfig.AUTHORIZE_REDIRECT_URI;
        }

        try {
            return String.format(
                    "%s?redirect_to=%s",
                    BuildConfig.AUTHORIZE_REDIRECT_URI,
                    URLEncoder.encode(redirectTo, "UTF-8")
            );
        } catch (UnsupportedEncodingException e) {
            // ignore
        }

        return null;
    }

    public static String makeAuthorizeUri(String redirectTo) {
        try {
            String authorizeRedirectUri = makeAuthorizeRedirectUri(redirectTo);
            String encodedRedirectTo = "";
            if (authorizeRedirectUri != null) {
                URLEncoder.encode(authorizeRedirectUri, "UTF-8");
            }

            return String.format(
                    "%s/index.php?oauth/authorize/&client_id=%s&redirect_uri=%s&response_type=code&scope=%s",
                    BuildConfig.API_ROOT,
                    URLEncoder.encode(BuildConfig.CLIENT_ID, "UTF-8"),
                    encodedRedirectTo,
                    URLEncoder.encode("read", "UTF-8")
            );
        } catch (UnsupportedEncodingException e) {
            // ignore
        }

        return null;
    }

    private static String makeUrl(int method, String url, Map<String, String> params) {
        if (!url.contains("://")) {
            url = String.format("%s/index.php?%s", BuildConfig.API_ROOT, url.replace('?', '&'));
        }

        if (!url.contains("&locale=")
                && !params.containsKey(PARAM_LOCALE)) {
            Locale locale = Locale.getDefault();
            params.put(PARAM_LOCALE, String.format("%s-%s", locale.getLanguage(),
                    locale.getCountry()));
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

            if (BuildConfig.DEBUG) {
                // set a long time out for debugging
                setRetryPolicy(new DefaultRetryPolicy(60000,
                        DefaultRetryPolicy.DEFAULT_MAX_RETRIES,
                        DefaultRetryPolicy.DEFAULT_BACKOFF_MULT));
            }
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
                    message = getErrorMessage(jsonObject);
                } catch (Exception e) {
                    // ignore
                }
            }

            return message;
        }

        String getErrorMessage(JSONObject response) {
            String message = null;

            try {
                if (response.has("error_description")) {
                    message = response.getString("error_description");
                } else if (response.has("errors")) {
                    try {
                        JSONArray errors = response.getJSONArray("errors");
                        if (errors.length() > 0) {
                            message = errors.getString(0);
                        }
                    } catch (JSONException je) {
                        JSONObject errors = response.getJSONObject("errors");
                        JSONArray names = errors.names();
                        String name = names.getString(0);
                        message = errors.getString(name);
                    }
                }
            } catch (Exception e) {
                // ignore
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

        private Map<String, InputStreamBody> mFiles = new HashMap<>();
        private MultipartEntityBuilder mBodyBuilder = null;
        private HttpEntity mBuiltBody = null;

        public PostRequest(String url, Map<String, String> params) {
            super(Method.POST, makeUrl(Method.POST, url, params), params);
        }

        @Override
        public void start() {
            super.start();

            if (BuildConfig.DEBUG) {
                for (String key : mFiles.keySet()) {
                    Log.v(getTag().toString(), "Request[" + key + "](file)=" + mFiles.get(key).getFilename());
                }
            }
        }

        @Override
        public String getBodyContentType() {
            if (mFiles.size() == 0) {
                return super.getBodyContentType();
            }

            return mBuiltBody.getContentType().getValue();
        }

        @Override
        public byte[] getBody() throws AuthFailureError {
            if (mFiles.size() == 0) {
                return super.getBody();
            }

            if (mBuiltBody == null) {
                mBodyBuilder = MultipartEntityBuilder.create();
                mBodyBuilder.setMode(HttpMultipartMode.BROWSER_COMPATIBLE);

                for (Map.Entry<String, String> param : mParams.entrySet()) {
                    mBodyBuilder.addTextBody(param.getKey(), param.getValue());
                }

                for (Map.Entry<String, InputStreamBody> file : mFiles.entrySet()) {
                    mBodyBuilder.addPart(file.getKey(), file.getValue());
                }

                mBuiltBody = mBodyBuilder.build();
            }

            ByteArrayOutputStream bos = new ByteArrayOutputStream();
            try {
                mBuiltBody.writeTo(bos);
            } catch (IOException e) {
                Log.e(getTag().toString(), e.toString());
            }

            return bos.toByteArray();
        }

        void addFile(String key, String fileName, InputStream inputStream) throws IllegalAccessException {
            if (mBuiltBody != null) {
                throw new IllegalAccessException("Cannot addFile after body has been built.");
            }

            mFiles.put(key, new InputStreamBody(inputStream, fileName));
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
                            .and("extra_data[package]", App.getInstance().getApplicationContext().getPackageName())
            );
        }

        @Override
        protected Response<JSONObject> parseNetworkResponse(NetworkResponse response) {
            return Response.success(null, HttpHeaderParser.parseCacheHeaders(response));
        }
    }

    public static class Params extends HashMap<String, String> {

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

        public Params(long userId, AccessToken at) {
            super(1);

            String ott = Api.makeOneTimeToken(userId, at);
            if (ott != null) {
                put("oauth_token", ott);
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
        private String userEmail;
        private Integer userDobYear;
        private Integer userDobMonth;
        private Integer userDobDay;

        private String avatar;

        private String extraData;
        private long extraTimestamp;

        public String getUsername() {
            return username;
        }

        public String getEmail() {
            return userEmail;
        }

        public Integer getDobYear() {
            return userDobYear;
        }

        public Integer getDobMonth() {
            return userDobMonth;
        }

        public Integer getDobDay() {
            return userDobDay;
        }

        public String getExtraData() {
            return extraData;
        }

        public long getExtraTimestamp() {
            return extraTimestamp;
        }

        public String getAvatar() {
            return avatar;
        }

    }

}
