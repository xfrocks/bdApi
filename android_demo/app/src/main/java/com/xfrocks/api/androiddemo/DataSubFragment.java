package com.xfrocks.api.androiddemo;

import android.os.Bundle;

import com.xfrocks.api.androiddemo.persist.Row;

import java.util.ArrayList;

public class DataSubFragment extends DataFragment {

    private static final String ARG_PARENT_ROW = "parentRow";
    private static final String ARG_ROWS = "rows";

    public static DataSubFragment newInstance(Row parentRow, ArrayList<Row> rows) {
        DataSubFragment fragment = new DataSubFragment();

        Bundle args = new Bundle();
        args.putParcelable(ARG_PARENT_ROW, parentRow);
        args.putParcelableArrayList(ARG_ROWS, rows);
        fragment.setArguments(args);

        return fragment;
    }

    @Override
    void restoreData(Bundle savedInstanceState) {
        Bundle args = getArguments();
        if (args.containsKey(ARG_PARENT_ROW) && args.containsKey(ARG_ROWS)) {
            mParentRow = args.getParcelable(ARG_PARENT_ROW);
            mData = args.getParcelableArrayList(ARG_ROWS);
        }
    }

    @Override
    public void onResume() {
        super.onResume();

        if (mData.size() == 0) {
            restoreData(null);
        }
    }
}
