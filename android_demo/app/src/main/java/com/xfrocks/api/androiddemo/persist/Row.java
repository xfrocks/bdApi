package com.xfrocks.api.androiddemo.persist;

import android.os.Parcel;
import android.os.Parcelable;

import java.util.ArrayList;

public class Row implements Parcelable {
    public String key;
    public String value;
    public ArrayList<Row> subRows;

    public Row() {
        // do nothing
    }

    private Row(Parcel in) {
        key = in.readString();
        value = in.readString();
        subRows = in.createTypedArrayList(Row.CREATOR);
    }

    public static final Creator<Row> CREATOR = new Creator<Row>() {
        @Override
        public Row createFromParcel(Parcel in) {
            return new Row(in);
        }

        @Override
        public Row[] newArray(int size) {
            return new Row[size];
        }
    };

    @Override
    public int describeContents() {
        return 0;
    }

    @Override
    public void writeToParcel(Parcel parcel, int i) {
        parcel.writeString(key);
        parcel.writeString(value);
        parcel.writeTypedList(subRows);
    }

    @Override
    public String toString() {
        return key;
    }
}