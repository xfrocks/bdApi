package com.xfrocks.api.androiddemo;

import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.IntentFilter;
import android.os.Bundle;
import android.support.v4.widget.SwipeRefreshLayout;
import android.support.v7.app.AppCompatActivity;
import android.support.v7.widget.LinearLayoutManager;
import android.support.v7.widget.RecyclerView;
import android.support.v7.widget.Toolbar;
import android.util.Log;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ImageView;
import android.widget.TextView;

import com.android.volley.toolbox.ImageLoader;
import com.xfrocks.api.androiddemo.gcm.ChatOrNotifReceiver;

import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;

import java.text.Format;
import java.util.ArrayList;
import java.util.Date;

public class ChatActivity extends AppCompatActivity {

    public static final String EXTRA_ACCESS_TOKEN = "access_token";
    public static final String EXTRA_CONVERSATION_ID = "conversation_id";
    private static final String STATE_ACCESS_TOKEN = "accessToken";
    private static final String STATE_CONVERSATION_ID = "conversationId";

    private SwipeRefreshLayout mSwipeRefresh;
    private LinearLayoutManager mMessagesLayoutManager;
    private RecyclerView mMessages;

    private Api.AccessToken mAccessToken;
    private int mConversationId;
    private int mPages;
    private int mPage;

    private BroadcastReceiver mBroadcastReceiver;
    private Api.GetRequest mGetRequest;
    private MessagesAdapter mAdapter;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_chat);

        Toolbar toolbar = (Toolbar) findViewById(R.id.toolbar);
        setSupportActionBar(toolbar);

        mSwipeRefresh = (SwipeRefreshLayout) findViewById(R.id.swipe_refresh);
        mSwipeRefresh.setOnRefreshListener(new SwipeRefreshLayout.OnRefreshListener() {
            @Override
            public void onRefresh() {
                new MessagesRequest(mConversationId, 1, mAccessToken).start();
            }
        });

        mMessages = (RecyclerView) findViewById(R.id.messages);
        mMessages.setHasFixedSize(true);

        mMessagesLayoutManager = new LinearLayoutManager(this);
        mMessagesLayoutManager.setReverseLayout(true);
        mMessages.setLayoutManager(mMessagesLayoutManager);

        mMessages.addOnScrollListener(new RecyclerView.OnScrollListener() {

            @Override
            public void onScrolled(RecyclerView recyclerView, int dx, int dy) {
                super.onScrolled(recyclerView, dx, dy);

                if (dy < 0
                        && mMessagesLayoutManager.findLastCompletelyVisibleItemPosition() > mAdapter.getItemCount() - 5
                        && mPage < mPages
                        && mGetRequest == null) {
                    new MessagesRequest(mConversationId, mPage + 1, mAccessToken).start();
                }
            }
        });

        mAdapter = new MessagesAdapter(android.text.format.DateFormat.getTimeFormat(this));
        mMessages.setAdapter(mAdapter);

        mBroadcastReceiver = new BroadcastReceiver() {
            @Override
            public void onReceive(Context context, Intent intent) {
                if (mConversationId == 0
                        || !ChatOrNotifReceiver.ACTION.equals(intent.getAction())
                        || ChatOrNotifReceiver.getConversationId(intent) != mConversationId) {
                    return;
                }

                int messageId = ChatOrNotifReceiver.getMessageId(intent);
                Api.Message latestMessage = mAdapter.getLatestMessage();
                if (latestMessage != null
                        && latestMessage.getMessageId() > messageId) {
                    // this notification appeared to arrive a little too late
                    return;
                }

                // this broadcast is for new message in this conversation
                // process it now
                new PatchRequest(mConversationId, mAccessToken).start();

                abortBroadcast();
            }
        };
        registerReceiver(mBroadcastReceiver, new IntentFilter(ChatOrNotifReceiver.ACTION));
    }

    @Override
    protected void onResume() {
        super.onResume();

        Intent mainIntent = getIntent();
        if (mainIntent != null) {
            if (mainIntent.hasExtra(EXTRA_ACCESS_TOKEN)) {
                mAccessToken = (Api.AccessToken) mainIntent.getSerializableExtra(EXTRA_ACCESS_TOKEN);
            }

            if (mainIntent.hasExtra(EXTRA_CONVERSATION_ID)) {
                mConversationId = mainIntent.getIntExtra(EXTRA_CONVERSATION_ID, 0);
            }
        }

        if (mConversationId == 0) {
            finish();
            return;
        }

        if (mAccessToken != null) {
            if (mAdapter.getItemCount() == 0) {
                new MessagesRequest(mConversationId, 1, mAccessToken).start();
            }
        } else {
            Intent loginIntent = new Intent(this, LoginActivity.class);
            loginIntent.putExtra(LoginActivity.EXTRA_REDIRECT_TO,
                    "ChatActivity://" + mConversationId);

            startActivity(loginIntent);
            finish();
        }
    }

    @Override
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);

        Log.d("ChatActivity", intent.toString());
    }

    @Override
    protected void onPause() {
        super.onPause();

        if (mGetRequest != null) {
            mGetRequest.cancel();
        }
    }

    @Override
    protected void onDestroy() {
        super.onDestroy();

        if (mBroadcastReceiver != null) {
            unregisterReceiver(mBroadcastReceiver);
        }
    }

    @Override
    protected void onSaveInstanceState(Bundle outState) {
        super.onSaveInstanceState(outState);

        outState.putSerializable(STATE_ACCESS_TOKEN, mAccessToken);
        outState.putInt(STATE_CONVERSATION_ID, mConversationId);
    }

    @Override
    protected void onRestoreInstanceState(Bundle savedInstanceState) {
        super.onRestoreInstanceState(savedInstanceState);

        if (savedInstanceState.containsKey(STATE_ACCESS_TOKEN)
                && savedInstanceState.containsKey(STATE_CONVERSATION_ID)) {
            mAccessToken = (Api.AccessToken) savedInstanceState.getSerializable(STATE_ACCESS_TOKEN);
            mConversationId = savedInstanceState.getInt(STATE_CONVERSATION_ID);
        }
    }

    private class MessagesRequest extends Api.GetRequest {
        private final int mPage;

        public MessagesRequest(int conversationId, int page, Api.AccessToken at) {
            super(Api.URL_CONVERSATION_MESSAGES, new Api.Params(at)
                    .and(Api.URL_CONVERSATION_MESSAGES_PARAM_CONVERSATION_ID, conversationId)
                    .and(Api.URL_CONVERSATION_MESSAGES_PARAM_PAGE, page)
                    .and(Api.URL_CONVERSATION_MESSAGES_PARAM_ORDER,
                            Api.URL_CONVERSATION_MESSAGES_ORDER_REVERSE));

            mPage = Math.max(1, page);
        }

        @Override
        void onStart() {
            mGetRequest = this;

            if (mPage == 1) {
                mSwipeRefresh.setRefreshing(true);
            }
        }

        @Override
        protected void onSuccess(JSONObject response) {
            if (mPage == 1) {
                mAdapter.clear();
            }

            if (response.has("messages")) {
                try {
                    JSONArray messages = response.getJSONArray("messages");
                    for (int i = 0, l = messages.length(); i < l; i++) {
                        JSONObject messageJson = messages.getJSONObject(i);
                        Api.Message message = Api.makeMessage(messageJson);
                        if (message != null) {
                            mAdapter.addMessage(message);

                            if (mPage > 1) {
                                mAdapter.notifyItemInserted(mAdapter.getItemCount() - 1);
                            }
                        }
                    }
                } catch (JSONException e) {
                    // ignore
                }
            }

            ChatActivity.this.mPage = mPage;

            if (response.has("links")) {
                try {
                    JSONObject links = response.getJSONObject("links");
                    mPages = links.getInt("pages");
                } catch (JSONException e) {
                    e.printStackTrace();
                }
            }

            if (mPage == 1) {
                mAdapter.notifyDataSetChanged();
                mMessages.scrollToPosition(0);
            }
        }

        @Override
        void onComplete() {
            mGetRequest = null;
            mSwipeRefresh.setRefreshing(false);
        }
    }

    private class PatchRequest extends Api.GetRequest {
        public PatchRequest(int conversationId, Api.AccessToken at) {
            super(Api.URL_CONVERSATION_MESSAGES, new Api.Params(at)
                    .and(Api.URL_CONVERSATION_MESSAGES_PARAM_CONVERSATION_ID, conversationId)
                    .and(Api.URL_CONVERSATION_MESSAGES_PARAM_ORDER,
                            Api.URL_CONVERSATION_MESSAGES_ORDER_REVERSE));
        }

        @Override
        protected void onSuccess(JSONObject response) {
            Api.Message latestMessage = mAdapter.getLatestMessage();

            if (response.has("messages")) {
                try {
                    JSONArray messages = response.getJSONArray("messages");
                    for (int i = 0, l = messages.length(); i < l; i++) {
                        JSONObject messageJson = messages.getJSONObject(i);
                        Api.Message message = Api.makeMessage(messageJson);
                        if (message != null) {
                            if (latestMessage == null
                                    || latestMessage.getMessageId() < message.getMessageId()) {
                                mAdapter.prependMessage(message);
                                mAdapter.notifyItemInserted(0);
                            } else {
                                // prepend until one older message is found
                                break;
                            }
                        }
                    }
                } catch (JSONException e) {
                    // ignore
                }
            }

            mMessages.scrollToPosition(0);
        }
    }

    public class MessagesAdapter extends RecyclerView.Adapter<ViewHolder> {
        private final ArrayList<Api.Message> mData = new ArrayList<>();

        private Format mTimeFormat = null;

        public MessagesAdapter(Format mTimeFormat) {
            this.mTimeFormat = mTimeFormat;
        }

        @Override
        public ViewHolder onCreateViewHolder(ViewGroup parent, int viewType) {
            View v = LayoutInflater.from(parent.getContext())
                    .inflate(R.layout.list_item_message, parent, false);

            return new ViewHolder(v);
        }

        @Override
        public void onBindViewHolder(ViewHolder holder, int position) {
            Api.Message message = mData.get(position);
            Api.Message messagePrev = null;
            Api.Message messageNext = null;
            if (position > 0) {
                messagePrev = mData.get(position - 1);
            }
            if (position < mData.size() - 1) {
                messageNext = mData.get(position + 1);
            }

            if (messageNext == null
                    || messageNext.getCreatorId() != message.getCreatorId()) {
                holder.avatar.setVisibility(View.VISIBLE);
                holder.avatar.setContentDescription(message.getCreatorName());
                App.getInstance().getNetworkImageLoader().get(
                        message.getCreatorAvatar(),
                        ImageLoader.getImageListener(holder.avatar, R.drawable.avatar_l, 0)
                );
            } else {
                holder.avatar.setVisibility(View.GONE);
            }

            if (messagePrev == null
                    || messagePrev.getMessageCreateDate() > message.getMessageCreateDate() + 300
                    || holder.avatar.getVisibility() == View.VISIBLE) {
                holder.info.setVisibility(View.VISIBLE);
                holder.info.setText(String.format("%1$s · %2$s",
                        message.getCreatorName(),
                        mTimeFormat.format(new Date(message.getMessageCreateDate() * 1000L))));
            } else {
                holder.info.setVisibility(View.GONE);
            }

            holder.message.setText(message.getMessageBodyPlainText());
        }

        @Override
        public int getItemCount() {
            return mData.size();
        }

        public void clear() {
            mData.clear();
        }

        public Api.Message getLatestMessage() {
            if (mData.isEmpty()) {
                return null;
            }

            return mData.get(0);
        }

        public void addMessage(Api.Message message) {
            mData.add(message);
        }

        public void prependMessage(Api.Message message) {
            mData.add(0, message);
        }
    }

    public static class ViewHolder extends RecyclerView.ViewHolder {
        private final ImageView avatar;
        private final TextView message;
        private final TextView info;

        public ViewHolder(View v) {
            super(v);

            avatar = (ImageView) v.findViewById(R.id.avatar);
            message = (TextView) v.findViewById(R.id.message);
            info = (TextView) v.findViewById(R.id.info);
        }
    }
}
