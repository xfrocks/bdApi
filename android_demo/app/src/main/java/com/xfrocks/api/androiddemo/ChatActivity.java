package com.xfrocks.api.androiddemo;

import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.IntentFilter;
import android.net.Uri;
import android.os.Bundle;
import android.support.v7.app.AlertDialog;
import android.support.v7.app.AppCompatActivity;
import android.support.v7.widget.LinearLayoutManager;
import android.support.v7.widget.RecyclerView;
import android.support.v7.widget.Toolbar;
import android.util.Log;
import android.view.LayoutInflater;
import android.view.Menu;
import android.view.MenuInflater;
import android.view.MenuItem;
import android.view.View;
import android.view.ViewGroup;
import android.widget.EditText;
import android.widget.ImageButton;
import android.widget.ImageView;
import android.widget.ProgressBar;
import android.widget.TextView;
import android.widget.Toast;

import com.android.volley.VolleyError;
import com.android.volley.toolbox.ImageLoader;
import com.xfrocks.api.androiddemo.gcm.ChatOrNotifReceiver;

import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;

import java.text.Format;
import java.util.ArrayList;
import java.util.Date;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

public class ChatActivity extends AppCompatActivity {

    public static final String EXTRA_ACCESS_TOKEN = "access_token";
    public static final String EXTRA_USER = "user";
    public static final String EXTRA_CONVERSATION_ID = "conversation_id";
    private static final String STATE_ACCESS_TOKEN = "accessToken";
    private static final String STATE_CONVERSATION_ID = "conversationId";

    private static final Pattern patternUrl = Pattern.compile("(index\\.php\\?|/)conversations/(\\d+)/");

    private ProgressBar mProgressBar;
    private LinearLayoutManager mMessagesLayoutManager;
    private RecyclerView mMessages;

    private ViewGroup mFooter;
    private EditText mMessage;

    private Api.AccessToken mAccessToken;
    private Api.User mUser;
    private Api.Conversation mConversation;
    private int mConversationId;
    private int mPages;
    private int mPage;

    private BroadcastReceiver mBroadcastReceiver;
    private MessagesRequest mMessagesRequest;
    private PatchRequest mPatchRequest;
    private MessagesAdapter mAdapter;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_chat);

        Toolbar toolbar = (Toolbar) findViewById(R.id.toolbar);
        setSupportActionBar(toolbar);
        mProgressBar = (ProgressBar) findViewById(R.id.progress_bar);

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
                        && mMessagesRequest == null) {
                    new MessagesRequest(mConversationId, mPage + 1, mAccessToken).start();
                }
            }
        });

        mAdapter = new MessagesAdapter(android.text.format.DateFormat.getTimeFormat(this));
        mMessages.setAdapter(mAdapter);

        mFooter = (ViewGroup) findViewById(R.id.footer);
        mMessage = (EditText) findViewById(R.id.message);
        ImageButton mReply = (ImageButton) findViewById(R.id.reply);
        mReply.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View v) {
                attemptReply();
            }
        });

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
    }

    @Override
    public boolean onCreateOptionsMenu(Menu menu) {
        MenuInflater inflater = getMenuInflater();
        inflater.inflate(R.menu.menu_chat, menu);
        return true;
    }

    @Override
    protected void onResume() {
        super.onResume();

        Intent mainIntent = getIntent();
        if (mainIntent != null) {
            if (mainIntent.hasExtra(EXTRA_ACCESS_TOKEN)) {
                mAccessToken = (Api.AccessToken) mainIntent.getSerializableExtra(EXTRA_ACCESS_TOKEN);
            }

            if (mainIntent.hasExtra(EXTRA_USER)) {
                mUser = (Api.User) mainIntent.getSerializableExtra(EXTRA_USER);
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
            if (mUser == null) {
                new UsersMeRequest(mAccessToken).start();
            }

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

        registerReceiver(mBroadcastReceiver, new IntentFilter(ChatOrNotifReceiver.ACTION));
    }

    @Override
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);

        if (intent != null) {
            if (intent.hasExtra(EXTRA_ACCESS_TOKEN)) {
                mAccessToken = (Api.AccessToken) intent.getSerializableExtra(EXTRA_ACCESS_TOKEN);
            }

            if (intent.hasExtra(EXTRA_USER)) {
                mUser = (Api.User) intent.getSerializableExtra(EXTRA_USER);
            }

            if (intent.hasExtra(EXTRA_CONVERSATION_ID)) {
                int conversationId = intent.getIntExtra(EXTRA_CONVERSATION_ID, 0);
                if (conversationId > 0
                        && conversationId != mConversationId) {
                    new MessagesRequest(conversationId, 1, mAccessToken).start();
                }
            }
        }
    }

    @Override
    protected void onPause() {
        super.onPause();

        if (mMessagesRequest != null) {
            mMessagesRequest.cancel();
        }

        if (mPatchRequest != null) {
            mPatchRequest.cancel();
        }

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

    @Override
    public boolean onOptionsItemSelected(MenuItem item) {
        switch (item.getItemId()) {
            case R.id.refresh:
                if (mConversationId > 0
                        && mAccessToken != null) {
                    new MessagesRequest(mConversationId, 1, mAccessToken).start();
                }
                break;
            case R.id.permalink:
                if (mConversation != null) {
                    Intent browserIntent = new Intent(Intent.ACTION_VIEW, Uri.parse(mConversation.getPermalink()));
                    startActivity(browserIntent);
                }
                break;
            default:
                return super.onOptionsItemSelected(item);
        }

        return true;
    }

    private void setTheProgressBarVisibility(boolean visible) {
        if (mProgressBar != null) {
            mProgressBar.setVisibility(visible ? View.VISIBLE : View.GONE);
        }
    }

    private void setConversation(Api.Conversation conversation) {
        setTitle(conversation.getConversationTitle());

        if (conversation.canReply()) {
            mFooter.setVisibility(View.VISIBLE);
        } else {
            mFooter.setVisibility(View.GONE);
        }

        mConversationId = conversation.getConversationId();
        mConversation = conversation;
    }

    private void attemptReply() {
        String message = mMessage.getText().toString().trim();
        if (!message.isEmpty()) {
            mMessage.setText("");
            new PostMessageRequest(mConversationId, message, mAccessToken).start();
        }
    }

    private class UsersMeRequest extends Api.GetRequest {
        public UsersMeRequest(Api.AccessToken at) {
            super(Api.URL_USERS_ME, new Api.Params(at));
        }

        @Override
        protected void onSuccess(JSONObject response) {
            if (response.has("user")) {
                try {
                    JSONObject userJson = response.getJSONObject("user");
                    Api.User user = Api.makeUser(userJson);
                    if (user != null) {
                        mUser = user;
                    }
                } catch (JSONException e) {
                    // ignore
                }
            }
        }
    }

    private class MessagesRequest extends Api.GetRequest {
        private final int mPage;

        public MessagesRequest(int conversationId, int page, Api.AccessToken at) {
            super(Api.URL_CONVERSATION_MESSAGES, new Api.Params(at)
                    .and(Api.URL_CONVERSATION_MESSAGES_PARAM_CONVERSATION_ID, conversationId)
                    .and(Api.URL_CONVERSATION_MESSAGES_PARAM_PAGE, page)
                    .and(Api.URL_CONVERSATION_MESSAGES_PARAM_ORDER,
                            Api.URL_CONVERSATION_MESSAGES_ORDER_REVERSE)
                    .andIf(page > 1, "fields_exclude", "conversation"));

            mPage = Math.max(1, page);
        }

        @Override
        void onStart() {
            if (mMessagesRequest != null) {
                mMessagesRequest.cancel();
            }

            mMessagesRequest = this;

            if (mPage == 1) {
                setTheProgressBarVisibility(true);
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

            if (mPage == 1) {
                if (response.has("links")) {
                    try {
                        JSONObject links = response.getJSONObject("links");
                        mPages = links.getInt("pages");
                    } catch (JSONException e) {
                        // ignore
                    }
                }

                if (response.has("conversation")) {
                    try {
                        JSONObject conversationJson = response.getJSONObject("conversation");
                        Api.Conversation conversation = Api.makeConversation(conversationJson);
                        if (conversation != null) {
                            setConversation(conversation);
                        }
                    } catch (JSONException e) {
                        // ignore
                    }
                }

                mAdapter.notifyDataSetChanged();
                mMessages.scrollToPosition(0);
            }
        }

        @Override
        void onComplete() {
            mMessagesRequest = null;
            setTheProgressBarVisibility(false);
        }
    }

    private class PatchRequest extends Api.GetRequest {
        public PatchRequest(int conversationId, Api.AccessToken at) {
            super(Api.URL_CONVERSATION_MESSAGES, new Api.Params(at)
                    .and(Api.URL_CONVERSATION_MESSAGES_PARAM_CONVERSATION_ID, conversationId)
                    .and(Api.URL_CONVERSATION_MESSAGES_PARAM_ORDER,
                            Api.URL_CONVERSATION_MESSAGES_ORDER_REVERSE)
                    .and("fields_exclude", "conversation"));
        }

        @Override
        void onStart() {
            if (mPatchRequest != null) {
                mPatchRequest.cancel();
            }

            mPatchRequest = this;
        }

        @Override
        protected void onSuccess(JSONObject response) {
            Api.Message latestMessage = null;
            int recentlyPostedRemoved = 0;
            while (mAdapter.getItemCount() > 0) {
                Api.Message message = mAdapter.getLatestMessage();

                if (message.getMessageId() == null) {
                    mAdapter.removeMessage(message);
                    recentlyPostedRemoved++;
                } else {
                    latestMessage = message;
                    break;
                }
            }

            ArrayList<Api.Message> newMessages = new ArrayList<>();
            if (response.has("messages")) {
                try {
                    JSONArray messages = response.getJSONArray("messages");
                    for (int i = 0, l = messages.length(); i < l; i++) {
                        JSONObject messageJson = messages.getJSONObject(i);
                        Api.Message message = Api.makeMessage(messageJson);
                        if (message != null) {
                            if (latestMessage == null
                                    || latestMessage.getMessageId() < message.getMessageId()) {
                                newMessages.add(message);
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

            while (recentlyPostedRemoved > newMessages.size()) {
                mAdapter.notifyItemRemoved(0);
                recentlyPostedRemoved--;
            }

            for (int i = newMessages.size() - 1; i >= 0; i--) {
                mAdapter.prependMessage(newMessages.get(i));

                if (recentlyPostedRemoved > 0) {
                    recentlyPostedRemoved--;
                    mAdapter.notifyItemChanged(recentlyPostedRemoved);
                } else {
                    mAdapter.notifyItemInserted(0);
                }
            }

            mMessages.scrollToPosition(0);
        }

        @Override
        void onComplete() {
            mPatchRequest = null;
        }
    }

    private class PostMessageRequest extends Api.PostRequest {
        private final Api.Message mFakeMessage;

        public PostMessageRequest(int conversationId, String messageBody, Api.AccessToken at) {
            super(Api.URL_CONVERSATION_MESSAGES, new Api.Params(at)
                    .and(Api.URL_CONVERSATION_MESSAGES_PARAM_CONVERSATION_ID, conversationId)
                    .and(Api.URL_CONVERSATION_MESSAGES_PARAM_MESSAGE_BODY, messageBody)
                    .and("fields_include", "message_id"));

            mFakeMessage = Api.makeMessage(mUser, messageBody);
        }

        @Override
        void onStart() {
            mAdapter.prependMessage(mFakeMessage);
            mAdapter.notifyItemInserted(0);
            mMessages.scrollToPosition(0);
        }

        @Override
        protected void onSuccess(JSONObject response) {
            if (!response.has("message")) {
                showError(getErrorMessage(response));
                return;
            }

            new PatchRequest(mConversationId, mAccessToken).start();
        }

        @Override
        void onError(VolleyError error) {
            showError(getErrorMessage(error));
        }

        private void showError(String errorMessage) {
            if (errorMessage != null) {
                new AlertDialog.Builder(ChatActivity.this)
                        .setTitle(R.string.post_reply)
                        .setMessage(errorMessage)
                        .setPositiveButton(android.R.string.ok, null)
                        .show();

                mAdapter.notifyItemRemoved(mAdapter.removeMessage(mFakeMessage));
                mMessage.setText(mFakeMessage.getMessageBodyPlainText());
                mMessage.selectAll();
            }
        }
    }

    private class MessagesAdapter extends RecyclerView.Adapter<ViewHolder> {
        private final ArrayList<Api.Message> mData = new ArrayList<>();
        private static final int VIEW_TYPE_MINE = 0;
        private static final int VIEW_TYPE_OTHER = 1;

        private Format mTimeFormat = null;

        public MessagesAdapter(Format mTimeFormat) {
            this.mTimeFormat = mTimeFormat;
        }

        @Override
        public int getItemViewType(int position) {
            Api.Message message = mData.get(position);

            if (mUser != null
                    && mUser.getUserId().equals(message.getCreatorId())) {
                return VIEW_TYPE_MINE;
            } else {
                return VIEW_TYPE_OTHER;
            }
        }

        @Override
        public ViewHolder onCreateViewHolder(ViewGroup parent, int viewType) {
            int resId = viewType == VIEW_TYPE_MINE
                    ? R.layout.list_item_my_message
                    : R.layout.list_item_message;

            View v = LayoutInflater.from(parent.getContext()).inflate(resId, parent, false);

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

            if (holder.avatar != null) {
                if (messageNext == null
                        || !message.getCreatorId().equals(messageNext.getCreatorId())) {
                    holder.avatar.setVisibility(View.VISIBLE);
                    holder.avatar.setContentDescription(message.getCreatorName());
                    App.getInstance().getNetworkImageLoader().get(
                            message.getCreatorAvatar(),
                            ImageLoader.getImageListener(holder.avatar, R.drawable.avatar_l, 0)
                    );
                } else {
                    holder.avatar.setVisibility(View.GONE);
                }
            }

            if (messagePrev == null
                    || !message.getCreatorId().equals(messagePrev.getCreatorId())
                    || messagePrev.getMessageCreateDate() > message.getMessageCreateDate() + 300) {
                holder.info.setVisibility(View.VISIBLE);

                String timeStr;
                if (message.getMessageId() != null) {
                    timeStr = mTimeFormat.format(new Date(message.getMessageCreateDate() * 1000L));
                } else {
                    timeStr = getString(R.string.now);
                }

                if (holder.avatar == null) {
                    holder.info.setText(timeStr);
                } else {
                    holder.info.setText(String.format("%1$s · %2$s",
                            message.getCreatorName(),
                            timeStr));
                }
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

        public int removeMessage(Api.Message message) {
            int indexOf = mData.indexOf(message);
            if (indexOf > -1) {
                mData.remove(indexOf);
                return indexOf;
            }

            return -1;
        }
    }

    private static class ViewHolder extends RecyclerView.ViewHolder {
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

    public static int getConversationIdFromUrl(String url) {
        Matcher m = patternUrl.matcher(url);
        if (m.find()) {
            String conversationId = m.group(2);
            return Integer.parseInt(conversationId);
        }

        return 0;
    }
}
