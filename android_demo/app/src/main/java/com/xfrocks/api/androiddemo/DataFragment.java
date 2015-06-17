package com.xfrocks.api.androiddemo;

import android.app.Activity;
import android.content.Context;
import android.content.Intent;
import android.net.Uri;
import android.os.Bundle;
import android.support.v4.app.Fragment;
import android.support.v4.app.ListFragment;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.BaseAdapter;
import android.widget.ListView;
import android.widget.TextView;
import android.widget.Toast;

import com.android.volley.VolleyError;
import com.android.volley.toolbox.HttpHeaderParser;
import com.xfrocks.api.androiddemo.persist.Row;

import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;

import java.util.ArrayList;
import java.util.Collections;
import java.util.Iterator;
import java.util.List;

public class DataFragment extends ListFragment {

    private static final String ARG_ACCESS_TOKEN = "access_token";
    private static final String ARG_URL = "url";
    private static final String STATE_DATA = "data";

    Row mParentRow;
    List<Row> mData = new ArrayList<>();
    BaseAdapter mDataAdapter;

    public static DataFragment newInstance(String url, Api.AccessToken at) {
        DataFragment fragment = new DataFragment();

        Bundle args = new Bundle();
        args.putString(ARG_URL, url);
        args.putSerializable(ARG_ACCESS_TOKEN, at);
        fragment.setArguments(args);

        return fragment;
    }

    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        if (savedInstanceState != null && savedInstanceState.containsKey(STATE_DATA)) {
            Row[] rows = (Row[]) savedInstanceState.getParcelableArray(STATE_DATA);
            if (rows != null) {
                Collections.addAll(mData, rows);
            }
        }
    }

    @Override
    public void onResume() {
        super.onResume();

        setListAdapterSafe();

        if (mData.size() == 0) {
            Bundle args = getArguments();
            if (args.containsKey(ARG_URL) && args.containsKey(ARG_ACCESS_TOKEN)) {
                String url = args.getString(ARG_URL);
                Api.AccessToken at = (Api.AccessToken) args.getSerializable(ARG_ACCESS_TOKEN);

                new DataRequest(url, at).start();
            }
        }
    }

    @Override
    public void onSaveInstanceState(Bundle outState) {
        super.onSaveInstanceState(outState);

        outState.putParcelableArray(STATE_DATA, mData.toArray(new Row[mData.size()]));
    }

    @Override
    public void onListItemClick(ListView l, View v, int position, long id) {
        Activity activity = getActivity();
        if (!(activity instanceof MainActivity)) {
            return;
        }
        MainActivity ma = (MainActivity) activity;

        Row row = mData.get(position);
        if (row == null) {
            return;
        }

        if (row.subRows != null
                && row.subRows.size() > 0) {
            Fragment fragment = DataSubFragment.newInstance(row, row.subRows);
            ma.addFragmentToBackStack(fragment);
        } else if (mParentRow != null) {
            if ("links".equals(mParentRow.key)) {
                if ("permalink".equals(row.key)) {
                    Intent intent = new Intent(Intent.ACTION_VIEW, Uri.parse(row.value));
                    startActivity(intent);
                } else {
                    ma.addDataFragment(row.value);
                }
            }
        }
    }

    void setListAdapterSafe() {
        Activity activity = getActivity();
        if (activity == null) {
            return;
        }

        if (mDataAdapter == null) {
            mDataAdapter = new DataAdapter(activity);
            setListAdapter(mDataAdapter);
        }
    }

    private class DataRequest extends Api.GetRequest {
        DataRequest(String url, Api.AccessToken at) {
            super(url, new Api.Params(at));
        }

        @Override
        protected void onStart() {
            mData.clear();
            mDataAdapter.notifyDataSetInvalidated();
        }

        @Override
        protected void onSuccess(JSONObject response) {
            parseRows(response, mData);
        }

        @Override
        protected void onError(VolleyError error) {
            String message = getErrorMessage(error);

            if (message != null) {
                Toast.makeText(getActivity(), message, Toast.LENGTH_LONG).show();
            }
        }

        @Override
        protected void onComplete(boolean isSuccess) {
            mDataAdapter.notifyDataSetChanged();
        }

        private void parseRows(JSONObject obj, List<Row> rows) {
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

        private void parseRows(JSONArray array, List<Row> rows) {
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

        private void parseRow(Object value, Row row) {
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

    private class DataAdapter extends BaseAdapter {

        private LayoutInflater mInflater;

        DataAdapter(Context context) {
            mInflater = (LayoutInflater) context.getSystemService(Context.LAYOUT_INFLATER_SERVICE);
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
}
