package com.xfrocks.api.androiddemo;

import android.annotation.SuppressLint;
import android.app.Activity;
import android.content.ContentResolver;
import android.content.Context;
import android.content.DialogInterface;
import android.content.Intent;
import android.database.Cursor;
import android.graphics.Color;
import android.net.Uri;
import android.os.Bundle;
import android.provider.MediaStore;
import android.support.v4.app.Fragment;
import android.support.v4.app.ListFragment;
import android.support.v7.app.AlertDialog;
import android.util.Log;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.BaseAdapter;
import android.widget.EditText;
import android.widget.ListView;
import android.widget.TextView;
import android.widget.Toast;

import com.android.volley.VolleyError;
import com.xfrocks.api.androiddemo.helper.ChooserIntent;
import com.xfrocks.api.androiddemo.persist.Row;

import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;

import java.io.FileNotFoundException;
import java.io.IOException;
import java.io.InputStream;
import java.util.ArrayList;
import java.util.List;

public class PostFragment extends ListFragment {

    private static final String ARG_URL = "url";
    private static final String ARG_ACCESS_TOKEN = "access_token";
    private static final String STATE_DATA = "data";
    private static final String STATE_URL = "url";
    private static final String STATE_ACCESS_TOKEN = "access_token";
    private static final String STATE_CURRENT_KEY = "current_key";
    private static final int RC_PICK_FILE = 1;

    private ArrayList<Row> mData = new ArrayList<>();

    private BaseAdapter mDataAdapter;
    private String mUrl;
    private Api.AccessToken mAccessToken;
    private String mCurrentKey = null;

    public static PostFragment newInstance(String url, Api.AccessToken at) {
        PostFragment fragment = new PostFragment();

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
    }

    @Override
    public void onActivityResult(int requestCode, int resultCode, Intent data) {
        super.onActivityResult(requestCode, resultCode, data);

        switch (requestCode) {
            case RC_PICK_FILE:
                if (mCurrentKey != null
                        && resultCode == Activity.RESULT_OK) {
                    ContentResolver cr = getContext().getContentResolver();
                    Uri uri = ChooserIntent.getUriFromChooser(getContext(), data);

                    try {
                        InputStream inputStream = cr.openInputStream(uri);
                        if (inputStream != null) {
                            inputStream.close();

                            for (Row dataRow : mData) {
                                if (mCurrentKey.equals(dataRow.key)) {
                                    dataRow.value = uri.toString();
                                    mDataAdapter.notifyDataSetChanged();
                                }
                            }
                        }
                    } catch (IOException e) {
                        Log.e(getClass().getSimpleName(), e.toString());
                    }
                }
                break;
        }
    }

    @Override
    public void onResume() {
        super.onResume();

        if (mData.size() == 0) {
            Bundle args = getArguments();
            if (args.containsKey(ARG_URL)) {
                mUrl = args.getString(ARG_URL);
                mAccessToken = (Api.AccessToken) args.getSerializable(ARG_ACCESS_TOKEN);

                new OptionsRequest(mUrl, mAccessToken).start();
            }
        }
    }

    @Override
    public void onSaveInstanceState(Bundle outState) {
        super.onSaveInstanceState(outState);

        outState.putParcelableArrayList(STATE_DATA, mData);
        outState.putString(STATE_URL, mUrl);
        outState.putSerializable(STATE_ACCESS_TOKEN, mAccessToken);
        outState.putString(STATE_CURRENT_KEY, mCurrentKey);
    }

    @Override
    public void onListItemClick(ListView l, View v, int position, long id) {
        Row row = mData.get(position);
        if (row == null) {
            return;
        }

        promptParam(row);
    }

    public void submit() {
        new SubmitRequest(mUrl, mAccessToken, mData).start();
    }

    private void restoreData(Bundle savedInstanceState) {
        if (savedInstanceState != null && savedInstanceState.containsKey(STATE_DATA)) {
            mData = savedInstanceState.getParcelableArrayList(STATE_DATA);

            if (savedInstanceState.containsKey(STATE_URL)) {
                mUrl = savedInstanceState.getString(STATE_URL);
            }

            if (savedInstanceState.containsKey(STATE_ACCESS_TOKEN)) {
                mAccessToken = (Api.AccessToken) savedInstanceState.getSerializable(STATE_ACCESS_TOKEN);
            }

            if (savedInstanceState.containsKey(STATE_CURRENT_KEY)) {
                mCurrentKey = savedInstanceState.getString(STATE_CURRENT_KEY);
            }
        }
    }

    private void setProgressBarVisibility(boolean visible) {
        Activity activity = getActivity();
        if (activity instanceof MainActivity) {
            ((MainActivity) activity).setTheProgressBarVisibility(visible);
        }
    }

    private void promptParam(Row row) {
        mCurrentKey = row.key;
        AlertDialog dialog = null;

        switch (row.type) {
            case "file":
                Intent chooserIntent = ChooserIntent.create(getContext(), R.string.post_pick_file, "*/*");
                startActivityForResult(chooserIntent, RC_PICK_FILE);
                break;
            case "string":
                dialog = createPromptString(row);
                break;
        }

        if (dialog != null) {
            dialog.show();
        }
    }

    private AlertDialog createPromptString(final Row row) {
        LayoutInflater li = (LayoutInflater) getActivity().getSystemService(Context.LAYOUT_INFLATER_SERVICE);

        @SuppressLint("InflateParams")
        View view = li.inflate(R.layout.dialog_post_param_string, null);

        final EditText paramValue = (EditText) view.findViewById(R.id.param_value);
        if (row.value != null) {
            paramValue.setText(row.value);
        }

        return new AlertDialog.Builder(getActivity())
                .setTitle(row.key)
                .setView(view)
                .setPositiveButton(android.R.string.ok, new DialogInterface.OnClickListener() {
                    @Override
                    public void onClick(DialogInterface dialog, int which) {
                        row.value = paramValue.getText().toString();
                        mDataAdapter.notifyDataSetChanged();
                    }
                })
                .setNegativeButton(android.R.string.cancel, null)
                .create();
    }

    private class OptionsRequest extends Api.OptionsRequest {
        OptionsRequest(String url, Api.AccessToken at) {
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
            if (response.has("POST")) {
                try {
                    JSONObject postInfo = response.getJSONObject("POST");
                    if (postInfo.has("parameters")) {
                        JSONArray postParams = postInfo.getJSONArray("parameters");
                        for (int i = 0, l = postParams.length(); i < l; i++) {
                            JSONObject postParam = postParams.getJSONObject(i);
                            Row row = new Row();
                            row.key = postParam.getString("name");
                            row.type = postParam.getString("type");
                            mData.add(row);
                        }
                    }
                } catch (JSONException e) {
                    // ignore
                }
            }
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

    private class SubmitRequest extends Api.PostRequest {
        SubmitRequest(String url, Api.AccessToken at, List<Row> data) {
            super(url, new Api.Params(at).and(data));

            for (Row row : data) {
                if (row.value != null
                        && !row.value.isEmpty()
                        && "file".equals(row.type)) {
                    Uri uri = Uri.parse(row.value);

                    try {
                        addFile(row.key,
                                ChooserIntent.getFileNameFromUri(getContext(), uri),
                                getContext().getContentResolver().openInputStream(uri));
                    } catch (Exception e) {
                        Log.e(getClass().getSimpleName(), e.toString());
                    }
                }
            }
        }

        @Override
        protected void onStart() {
            setProgressBarVisibility(true);
        }

        @Override
        protected void onSuccess(JSONObject response) {
            Toast.makeText(getContext(), R.string.post_success, Toast.LENGTH_LONG).show();

            Activity activity = getActivity();
            if (!(activity instanceof MainActivity)) {
                return;
            }
            MainActivity ma = (MainActivity) activity;

            ArrayList<Row> data = new ArrayList<>();
            parseRows(response, data);

            Fragment fragment = DataSubFragment.newInstance(null, data);
            ma.addFragmentToBackStack(fragment, false);
        }

        @Override
        void onError(VolleyError error) {
            String message = getErrorMessage(error);

            if (message != null) {
                Toast.makeText(getContext(), message, Toast.LENGTH_LONG).show();
            }
        }

        @Override
        protected void onComplete() {
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
            viewHolder.text2.setHint(row.type);
            if (row.value != null
                    && !row.value.isEmpty()) {
                viewHolder.text2.setText(row.value);
            } else {
                viewHolder.text2.setText(String.format("(%s)", row.type));
            }

            return convertView;
        }
    }

    private static class ViewHolder {
        TextView text1;
        TextView text2;
    }
}
