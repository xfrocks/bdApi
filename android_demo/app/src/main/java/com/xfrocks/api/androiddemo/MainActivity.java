package com.xfrocks.api.androiddemo;

import android.content.Intent;
import android.os.Bundle;
import android.support.v4.app.Fragment;
import android.support.v4.app.FragmentManager;
import android.support.v4.widget.DrawerLayout;
import android.support.v7.app.ActionBar;
import android.support.v7.app.AppCompatActivity;
import android.view.Menu;
import android.view.MenuItem;
import android.widget.ArrayAdapter;

import com.xfrocks.api.androiddemo.persist.Row;

import org.json.JSONException;
import org.json.JSONObject;

import java.util.Iterator;

public class MainActivity extends AppCompatActivity
        implements NavigationDrawerFragment.NavigationDrawerCallbacks {

    public static final String EXTRA_ACCESS_TOKEN = "access_token";

    private NavigationDrawerFragment mNavigationDrawerFragment;
    private ArrayAdapter<Row> mDrawerRows;

    /**
     * Used to store the last screen title. For use in {@link #restoreActionBar()}.
     */
    private CharSequence mTitle;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        mNavigationDrawerFragment = (NavigationDrawerFragment)
                getSupportFragmentManager().findFragmentById(R.id.navigation_drawer);
        mTitle = getTitle();

        // Set up the drawer.
        mDrawerRows = new ArrayAdapter<>(this,
                android.R.layout.simple_list_item_activated_1, android.R.id.text1);
        mNavigationDrawerFragment.setUp(
                R.id.navigation_drawer,
                (DrawerLayout) findViewById(R.id.drawer_layout),
                mDrawerRows);
    }

    @Override
    protected void onResume() {
        super.onResume();

        Intent intent = getIntent();
        if (intent.hasExtra(EXTRA_ACCESS_TOKEN)) {
            Api.AccessToken at = (Api.AccessToken) intent.getSerializableExtra(EXTRA_ACCESS_TOKEN);
            new IndexRequest(at).start();
        } else {
            finish();
        }
    }

    @Override
    public void onNavigationDrawerItemSelected(int position) {
        Row row = mDrawerRows.getItem(position);
        if (row != null) {
            FragmentManager fragmentManager = getSupportFragmentManager();
            fragmentManager.beginTransaction()
                    .replace(R.id.container, DataFragment.newInstance(row.value, null))
                    .commit();
        }
    }

    public void restoreActionBar() {
        ActionBar actionBar = getSupportActionBar();
        if (actionBar == null) {
            return;
        }

        //noinspection deprecation
        actionBar.setNavigationMode(ActionBar.NAVIGATION_MODE_STANDARD);

        actionBar.setDisplayShowTitleEnabled(true);
        actionBar.setTitle(mTitle);
    }

    public void addFragmentToBackStack(Fragment fragment) {
        FragmentManager fragmentManager = getSupportFragmentManager();
        String tag = String.valueOf(fragmentManager.getBackStackEntryCount());
        fragmentManager.beginTransaction()
                .replace(R.id.container, fragment, tag)
                .addToBackStack(tag)
                .commit();
    }

    @Override
    public boolean onCreateOptionsMenu(Menu menu) {
        if (!mNavigationDrawerFragment.isDrawerOpen()) {
            // Only show items in the action bar relevant to this screen
            // if the drawer is not showing. Otherwise, let the drawer
            // decide what to show in the action bar.
            restoreActionBar();
            return true;
        }
        return super.onCreateOptionsMenu(menu);
    }

    @Override
    public boolean onOptionsItemSelected(MenuItem item) {
        return mNavigationDrawerFragment.onOptionsItemSelected(item) || super.onOptionsItemSelected(item);
    }

    private class IndexRequest extends Api.GetRequest {
        public IndexRequest(Api.AccessToken at) {
            super(Api.URL_INDEX, new Api.Params(at));
        }

        @Override
        protected void onStart() {
            mDrawerRows.clear();
        }

        @Override
        protected void onSuccess(JSONObject response) {
            if (response.has("links")) {
                try {
                    JSONObject links = response.getJSONObject("links");
                    Iterator<String> keys = links.keys();
                    while (keys.hasNext()) {
                        final Row row = new Row();
                        row.key = keys.next();
                        row.value = links.getString(row.key);

                        mDrawerRows.add(row);
                    }
                } catch (JSONException e) {
                    // ignore
                }
            }

        }
    }

}
