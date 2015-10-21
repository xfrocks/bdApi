package com.xfrocks.api.androiddemo.gcm;

import android.app.NotificationManager;
import android.app.PendingIntent;
import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.media.RingtoneManager;
import android.net.Uri;
import android.support.v4.app.NotificationCompat;
import android.util.Log;

import com.xfrocks.api.androiddemo.BuildConfig;
import com.xfrocks.api.androiddemo.ChatActivity;

public class ChatOrNotifReceiver extends BroadcastReceiver {

    public static final String ACTION = "com.xfrocks.api.androiddemo.ChatOrNotif";

    private static final String EXTRA_CONVERSATION_ID = "conversation_id";
    private static final String EXTRA_MESSAGE_ID = "message_id";
    private static final String EXTRA_NOTIFICATION_TITLE = "notification_title";
    private static final String EXTRA_NOTIFICATION_TEXT = "notification_text";


    @Override
    public void onReceive(Context context, Intent intent) {
        showNotification(context, intent);
    }

    public static void broadcast(Context context, int conversationId, int messageId,
                                 String creatorUsername, String conversationTitle, String messageBody) {
        if (BuildConfig.DEBUG) {
            Log.i(ReceiverService.class.getSimpleName(), String.format("%3$s: %5$s (%4$s/%1$d/%2$d)",
                    conversationId, messageId, creatorUsername, conversationTitle, messageBody));
        }

        Intent chatOrNotifIntent = new Intent(ACTION);
        chatOrNotifIntent.putExtra(EXTRA_CONVERSATION_ID, conversationId);
        chatOrNotifIntent.putExtra(EXTRA_MESSAGE_ID, messageId);
        chatOrNotifIntent.putExtra(EXTRA_NOTIFICATION_TITLE, conversationTitle);
        chatOrNotifIntent.putExtra(EXTRA_NOTIFICATION_TEXT, String.format("%1$s: %2$s",
                creatorUsername, messageBody));

        context.sendOrderedBroadcast(chatOrNotifIntent, null);
    }

    private static void showNotification(Context context, Intent chatOrNotifIntent) {
        Intent activityIntent = new Intent(context, ChatActivity.class);
        activityIntent.putExtra(ChatActivity.EXTRA_CONVERSATION_ID,
                chatOrNotifIntent.getIntExtra(EXTRA_CONVERSATION_ID, 0));
        PendingIntent pendingIntent = PendingIntent.getActivity(context,
                0, activityIntent, PendingIntent.FLAG_ONE_SHOT);

        Uri defaultSoundUri = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION);
        NotificationCompat.Builder notificationBuilder = new NotificationCompat.Builder(context)
                .setSmallIcon(android.R.drawable.ic_dialog_alert)
                .setContentTitle(chatOrNotifIntent.getStringExtra(EXTRA_NOTIFICATION_TITLE))
                .setContentText(chatOrNotifIntent.getStringExtra(EXTRA_NOTIFICATION_TEXT))
                .setAutoCancel(true)
                .setSound(defaultSoundUri)
                .setContentIntent(pendingIntent);

        NotificationManager notificationManager =
                (NotificationManager) context.getSystemService(Context.NOTIFICATION_SERVICE);

        notificationManager.notify(0, notificationBuilder.build());
    }

    public static int getConversationId(Intent intent) {
        return intent.getIntExtra(EXTRA_CONVERSATION_ID, 0);
    }

    public static int getMessageId(Intent intent) {
        return intent.getIntExtra(EXTRA_MESSAGE_ID, 0);
    }
}
