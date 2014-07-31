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

		var token = false;
		if (window.xfacOneTimeToken != _undefined)
		{
			token = window.xfacOneTimeToken;
		}

		if (token !== false)
		{
			sdk.request('users/me', function(apiData)
			{
				if (apiData == _undefined)
				{
					return;
				}

				if (apiData.user == _undefined)
				{
					return;
				}

				var conversationCount = 0;
				var notificationCount = 0;
				if (apiData.user.user_unread_conversation_count != _undefined)
				{
					conversationCount = apiData.user.user_unread_conversation_count;
					document.cookie = 'conversationCount=' + conversationCount;
				}
				if (apiData.user.user_unread_notification_count != _undefined)
				{
					notificationCount = apiData.user.user_unread_notification_count;
					document.cookie = 'notificationCount=' + notificationCount;
				}

				updateJsCount(conversationCount, notificationCount);
			}, token);
		}
	};
}(jQuery, this, document);
