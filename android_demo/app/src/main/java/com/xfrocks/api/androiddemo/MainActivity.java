package com.xfrocks.api.androiddemo;

import android.content.DialogInterface;
import android.content.Intent;
import android.content.res.Configuration;
import android.os.Bundle;
import android.support.annotation.NonNull;
import android.support.design.widget.NavigationView;
import android.support.v4.app.Fragment;
import android.support.v4.app.FragmentManager;
import android.support.v4.widget.DrawerLayout;
import android.support.v7.app.ActionBarDrawerToggle;
import android.support.v7.app.AlertDialog;
import android.support.v7.app.AppCompatActivity;
import android.support.v7.widget.Toolbar;
import android.view.Menu;
import android.view.MenuItem;
import android.widget.TextView;

import com.xfrocks.api.androiddemo.persist.Row;

import org.json.JSONException;
import org.json.JSONObject;

import java.util.ArrayList;
import java.util.Iterator;

public class MainActivity extends AppCompatActivity implements NavigationView.OnNavigationItemSelectedListener {

    public static final String EXTRA_ACCESS_TOKEN = "access_token";
    private static final String STATE_NAVIGATION_ROWS = "navigation_rows";
    private static final String STATE_USER = "user";

    private DrawerLayout mDrawerLayout;
    private ActionBarDrawerToggle mDrawerToggle;

    private NavigationView mNavigationView;
    private TextView mHeaderTxt;

    private ArrayList<Row> mNavigationRows = new ArrayList<>();
    private Api.User mUser;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        Toolbar toolbar = (Toolbar) findViewById(R.id.toolbar);
        setSupportActionBar(toolbar);

        mDrawerLayout = (DrawerLayout) findViewById(R.id.drawer_layout);
        mDrawerToggle = new ActionBarDrawerToggle(this, mDrawerLayout, toolbar,
                R.string.navigation_drawer_open,
                R.string.navigation_drawer_close);
        mDrawerLayout.setDrawerListener(mDrawerToggle);
        mDrawerToggle.syncState();

        mNavigationView = (NavigationView) findViewById(R.id.navigation_view);
        mHeaderTxt = (TextView) findViewById(R.id.header_txt);
        mNavigationView.setNavigationItemSelectedListener(this);
        mDrawerLayout.openDrawer(mNavigationView);
    }

    @Override
    protected void onResume() {
        super.onResume();

        Intent intent = getIntent();
        if (intent.hasExtra(EXTRA_ACCESS_TOKEN)) {
            Api.AccessToken at = (Api.AccessToken) intent.getSerializableExtra(EXTRA_ACCESS_TOKEN);

            if (mNavigationRows.size() == 0) {
                new IndexRequest(at).start();
            }

            if (mUser == null) {
                new UsersMeRequest(at).start();
            }
        }
    }

    @Override
    public void onConfigurationChanged(Configuration newConfig) {
        super.onConfigurationChanged(newConfig);
        // Forward the new configuration the drawer toggle component.
        mDrawerToggle.onConfigurationChanged(newConfig);
    }

    @Override
    protected void onSaveInstanceState(Bundle outState) {
        super.onSaveInstanceState(outState);

        outState.putParcelableArrayList(STATE_NAVIGATION_ROWS, mNavigationRows);
        outState.putSerializable(STATE_USER, mUser);
    }

    @Override
    protected void onRestoreInstanceState(@NonNull Bundle savedInstanceState) {
        super.onRestoreInstanceState(savedInstanceState);

        if (savedInstanceState.containsKey(STATE_NAVIGATION_ROWS)) {
            ArrayList<Row> rows = savedInstanceState.getParcelableArrayList(STATE_NAVIGATION_ROWS);
            if (rows != null) {
                setNavigationRows(rows);
            }
        }

        if (savedInstanceState.containsKey(STATE_USER)) {
            setUser((Api.User) savedInstanceState.getSerializable(STATE_USER));
        }
    }

    @Override
    public boolean onOptionsItemSelected(MenuItem item) {
        return mDrawerToggle.onOptionsItemSelected(item) || super.onOptionsItemSelected(item);
    }

    @Override
    public boolean onNavigationItemSelected(MenuItem menuItem) {
        Row row = mNavigationRows.get(menuItem.getItemId());
        if (row != null) {
            addDataFragment(row.value, true);
            mDrawerLayout.closeDrawer(mNavigationView);
            return true;
        }

        return false;
    }

    @Override
    public void onBackPressed() {
        if (getSupportFragmentManager().getBackStackEntryCount() > 0) {
            getSupportFragmentManager().popBackStack();
        } else {
            new AlertDialog.Builder(this)
                    .setMessage(R.string.are_you_sure_quit)
                    .setPositiveButton(android.R.string.yes, new DialogInterface.OnClickListener() {
                        @Override
                        public void onClick(DialogInterface dialog, int which) {
                            finish();
                        }
                    })
                    .setNegativeButton(android.R.string.no, null)
                    .show();
        }
    }

    public void setNavigationRows(ArrayList<Row> rows) {
        mNavigationRows = rows;

        Menu menu = mNavigationView.getMenu();
        menu.clear();
        for (Row row : mNavigationRows) {
            menu.add(Menu.NONE, mNavigationRows.size() - 1, Menu.NONE, row.key);
        }
    }

    public void setUser(Api.User u) {
        mUser = u;

        if (mUser != null) {
            mHeaderTxt.setText(mUser.getUsername());
        } else {
            mHeaderTxt.setText("");
        }
    }

    public void addDataFragment(String url, boolean clearStack) {
        FragmentManager fragmentManager = getSupportFragmentManager();

        if (clearStack) {
            // http://stackoverflow.com/questions/6186433/clear-back-stack-using-fragments
            fragmentManager.popBackStack(null, FragmentManager.POP_BACK_STACK_INCLUSIVE);
        }

        fragmentManager.beginTransaction()
                .replace(R.id.container, DataFragment.newInstance(url, null))
                .commit();
    }

    public void addFragmentToBackStack(Fragment fragment) {
        FragmentManager fragmentManager = getSupportFragmentManager();
        String tag = String.valueOf(fragmentManager.getBackStackEntryCount());
        fragmentManager.beginTransaction()
                .replace(R.id.container, fragment, tag)
                .addToBackStack(tag)
                .commit();
    }

    private class IndexRequest extends Api.GetRequest {
        public IndexRequest(Api.AccessToken at) {
            super(Api.URL_INDEX, new Api.Params(at));
        }

        @Override
        protected void onSuccess(JSONObject response) {
            if (response.has("links")) {
                try {
                    JSONObject links = response.getJSONObject("links");
                    Iterator<String> keys = links.keys();
                    ArrayList<Row> rows = new ArrayList<>(links.names().length());

                    while (keys.hasNext()) {
                        final Row row = new Row();
                        row.key = keys.next();
                        row.value = links.getString(row.key);
                        rows.add(row);
                    }

                    setNavigationRows(rows);
                } catch (JSONException e) {
                    // ignore
                }
            }
        }
    }

    private class UsersMeRequest extends Api.GetRequest {
        public UsersMeRequest(Api.AccessToken at) {
            super(Api.URL_USERS_ME, new Api.Params(at));
        }

        @Override
        protected void onSuccess(JSONObject response) {
            Api.User u = Api.makeUser(response);
            if (u != null) {
                setUser(u);
            }
        }
    }

}
