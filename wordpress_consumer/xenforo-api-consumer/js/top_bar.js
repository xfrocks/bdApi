! function($, window, document, _undefined)
{
	var updateJsCount = function(conversationCount, notificationCount)
	{
		var $conversationCount = $('#xfacConversationCount').text(conversationCount).addClass('updated');
		if (conversationCount > 0)
		{
			$conversationCount.addClass('unread');
		}
		else
		{
			$conversationCount.removeClass('unread');
		}

		var $notificationCount = $('#xfacNotificationCount').text(notificationCount).addClass('updated');
		if (notificationCount > 0)
		{
			$notificationCount.addClass('unread');
		}
		else
		{
			$notificationCount.removeClass('unread');
		}
	};

	window.xfacInit = function()
	{
		var sdk = window.xfacSDK;

		if (window.xfacClientId != _undefined && window.xfacXenForoUserId != _undefined && window.xfacDoNotifications != _undefined && window.xfacDoConversations != _undefined)
		{
			sdk.init(
			{
				client_id: window.xfacClientId
			});

			sdk.isAuthorized('read' + (window.xfacDoConversations ? ' conversate' : ''), function(isAuthorized, apiData)
			{
				if (isAuthorized && apiData.user_id == window.xfacXenForoUserId)
				{
					var conversationCount = 0;
					var notificationCount = 0;
					if (apiData.user_unread_conversation_count != _undefined)
					{
						conversationCount = apiData.user_unread_conversation_count;
						document.cookie = 'conversationCount=' + conversationCount;
					}
					if (apiData.user_unread_notification_count != _undefined)
					{
						notificationCount = apiData.user_unread_notification_count;
						document.cookie = 'notificationCount=' + notificationCount;
					}

					updateJsCount(conversationCount, notificationCount);
				}
			});
		}
	};
}(jQuery, this, document);
