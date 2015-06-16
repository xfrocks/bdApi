package com.xfrocks.api.androiddemo;

import android.app.ListActivity;
import android.content.Context;
import android.content.Intent;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.BaseAdapter;
import android.widget.TextView;

import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;

import java.util.ArrayList;
import java.util.Iterator;
import java.util.List;

public class MeActivity extends ListActivity {

    public static final String EXTRA_ACCESS_TOKEN = "access_token";

    private List<Row> mData = new ArrayList<>();

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        setListAdapter(new Adapter(this));
    }

    @Override
    protected void onResume() {
        super.onResume();

        Intent intent = getIntent();
        if (intent.hasExtra(EXTRA_ACCESS_TOKEN)) {
            Api.AccessToken at = (Api.AccessToken) intent.getSerializableExtra(EXTRA_ACCESS_TOKEN);
            new UsersMeRequest(at).start();
        } else {
            finish();
        }
    }

    private class Adapter extends BaseAdapter {

        private LayoutInflater mInflater;

        public Adapter(Context context) {
            mInflater = (LayoutInflater) context.getSystemService(LAYOUT_INFLATER_SERVICE);
        }

        @Override
        public int getCount() {
            return mData.size();
        }

        @Override
        public Object getItem(int i) {
            return mData.get(i);
        }

        @Override
        public long getItemId(int i) {
            return i;
        }

        @Override
        public View getView(int position, View convertView, ViewGroup parent) {
            final ViewHolder viewHolder;

            if (convertView == null) {
                convertView = mInflater.inflate(android.R.layout.simple_list_item_2, null);

                viewHolder = new ViewHolder();
                viewHolder.text1 = (TextView) convertView.findViewById(android.R.id.text1);
                viewHolder.text2 = (TextView) convertView.findViewById(android.R.id.text2);

                convertView.setTag(viewHolder);
            } else {
                viewHolder = (ViewHolder) convertView.getTag();
            }

            Row row = (Row) getItem(position);
            viewHolder.text1.setText(row.key);
            viewHolder.text2.setText(row.value);

            return convertView;
        }
    }

    private static class ViewHolder {
        TextView text1;
        TextView text2;
    }

    private static class Row {
        String key;
        String value;

        Row(String key, String value) {
            this.key = key;
            this.value = value;
        }
    }

    private class UsersMeRequest extends Api.GetRequest {

        UsersMeRequest(Api.AccessToken at) {
            super(Api.URL_USERS_ME, new Api.Params(at));
        }

        @Override
        protected void onStart() {
            mData.clear();
        }

        @Override
        protected void onSuccess(JSONObject response) {
            if (response.has("user")) {
                try {
                    JSONObject user = response.getJSONObject("user");
                    Iterator<String> keys = user.keys();
                    while (keys.hasNext()) {
                        String key = keys.next();

                        try {
                            Object value = user.get(key);
                            if (!(value instanceof JSONObject)
                                    && !(value instanceof JSONArray)) {
                                mData.add(new Row(key, String.valueOf(value)));
                            }
                        } catch (JSONException e) {
                            // ignore
                        }
                    }
                } catch (JSONException e) {
                    // ignore
                }
            }
        }

        @Override
        protected void onComplete(boolean isSuccess) {
            if (isSuccess) {
                ((Adapter) getListAdapter()).notifyDataSetInvalidated();
            }
        }
    }

}
