!function ($, window, document, _undefined) {
    window.xfacInit = function () {
        var sdk = window.xfacSDK;

        if (window.xfacClientId != _undefined) {
            sdk.init(
                {
                    client_id: window.xfacClientId
                });

            var location = '' + window.location;
            if (location.indexOf('xfac_error') > -1) {
                // do not attempt js actions if an error has been indicated
                return false;
            }

            var xenForoUserId = 0;
            if (window.xfacXenForoUserId != _undefined) {
                xenForoUserId = window.xfacXenForoUserId;
            }

            var scope = '';
            if (xenForoUserId > 0) {
                scope = 'read conversate';
            }

            sdk.isAuthorized(scope, function (isAuthorized, apiData) {
                if (isAuthorized) {
                    if (xenForoUserId == 0 && window.xfacWpLogin != _undefined) {
                        // login
                        window.location = window.xfacWpLogin + '&redirect_to=' + encodeURIComponent(location);
                    }
                    else {
                        if (apiData.user_id == xenForoUserId) {
                            // update counters
                            var conversationCount = 0;
                            var notificationCount = 0;
                            if (apiData.user_unread_conversation_count != _undefined) {
                                conversationCount = apiData.user_unread_conversation_count;
                                document.cookie = 'conversationCount=' + conversationCount;
                            }
                            if (apiData.user_unread_notification_count != _undefined) {
                                notificationCount = apiData.user_unread_notification_count;
                                document.cookie = 'notificationCount=' + notificationCount;
                            }

                            updateJsCount(conversationCount, notificationCount);
                        }
                    }
                }
                else {
                    if (window.xfacWpLogout != _undefined) {
                        // logout
                        window.location.href = window.xfacWpLogout + '&redirect_to=' + encodeURIComponent(location);
                    }
                }
            });
        }
    };

    var updateJsCount = function (conversationCount, notificationCount) {
        var $conversationCount = $('#xfacConversationCount').text(conversationCount).addClass('updated');
        if (conversationCount > 0) {
            $conversationCount.addClass('unread');
        }
        else {
            $conversationCount.removeClass('unread');
        }

        var $notificationCount = $('#xfacNotificationCount').text(notificationCount).addClass('updated');
        if (notificationCount > 0) {
            $notificationCount.addClass('unread');
        }
        else {
            $notificationCount.removeClass('unread');
        }
    };
}(jQuery, this, document);
