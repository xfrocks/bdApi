package com.xfrocks.api.androiddemo;

import android.app.Activity;
import android.content.Context;
import android.content.Intent;
import android.graphics.Color;
import android.net.Uri;
import android.os.Bundle;
import android.os.Parcelable;
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
import com.xfrocks.api.androiddemo.persist.Row;

import org.json.JSONObject;

import java.util.ArrayList;

public class DataFragment extends ListFragment {

    private static final String ARG_URL = "url";
    private static final String ARG_ACCESS_TOKEN = "access_token";
    private static final String STATE_DATA = "data";
    private static final String STATE_LIST_VIEW = "list_view";

    Row mParentRow;
    ArrayList<Row> mData = new ArrayList<>();

    private BaseAdapter mDataAdapter;
    private Parcelable mListViewState;

    public static DataFragment newInstance(String url, Api.AccessToken at) {
        DataFragment fragment = new DataFragment();

        Bundle args = new Bundle();
        args.putString(ARG_URL, url);
        args.putSerializable(ARG_ACCESS_TOKEN, at);
        fragment.setArguments(args);

        return fragment;
    }

    @Override
    public void onViewCreated(View view, Bundle savedInstanceState) {
        super.onViewCreated(view, savedInstanceState);

        view.setBackgroundColor(Color.WHITE);

        restoreData(savedInstanceState);
        mDataAdapter = new DataAdapter(getActivity());
        setListAdapter(mDataAdapter);

        if (savedInstanceState != null && savedInstanceState.containsKey(STATE_LIST_VIEW)) {
            getListView().onRestoreInstanceState(savedInstanceState.getParcelable(STATE_LIST_VIEW));
        }
    }

    @Override
    public void onResume() {
        super.onResume();

        if (mData.size() == 0) {
            Bundle args = getArguments();
            if (args.containsKey(ARG_URL)) {
                String url = args.getString(ARG_URL);
                Api.AccessToken at = (Api.AccessToken) args.getSerializable(ARG_ACCESS_TOKEN);

                new DataRequest(url, at).start();
            }
        }
    }

    @Override
    public void onPause() {
        super.onPause();

        mListViewState = getListView().onSaveInstanceState();
    }

    @Override
    public void onSaveInstanceState(Bundle outState) {
        super.onSaveInstanceState(outState);

        outState.putParcelableArrayList(STATE_DATA, mData);
        if (mListViewState != null) {
            outState.putParcelable(STATE_LIST_VIEW, mListViewState);
        }
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
            ma.addFragmentToBackStack(fragment, false);
        } else if (mParentRow != null) {
            if ("links".equals(mParentRow.key)) {
                if (!row.value.contains(BuildConfig.API_ROOT)) {
                    Intent intent = new Intent(Intent.ACTION_VIEW, Uri.parse(row.value));
                    startActivity(intent);
                } else {
                    ma.addDataFragment(row.value, null, false);
                }
            }
        }
    }

    void restoreData(Bundle savedInstanceState) {
        if (savedInstanceState != null && savedInstanceState.containsKey(STATE_DATA)) {
            mData = savedInstanceState.getParcelableArrayList(STATE_DATA);
        }
    }

    private void setProgressBarVisibility(boolean visible) {
        Activity activity = getActivity();
        if (activity instanceof MainActivity) {
            ((MainActivity) activity).setTheProgressBarVisibility(visible);
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
            setProgressBarVisibility(true);
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
        protected void onComplete() {
            mDataAdapter.notifyDataSetChanged();
            setProgressBarVisibility(false);
        }
    }

    private class DataAdapter extends BaseAdapter {

        private final LayoutInflater mInflater;

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
