//noinspection ThisExpressionReferencesGlobalObjectJS
/** @param {jQuery} $ jQuery Object */
!function ($, window, document) {

    XenForo.bdApiConsumer_AutoLogin = function ($container) {
        var providerCode = $container.data('code');
        var clientId = $container.data('clientId');
        var loginLink = $container.data('loginLink');
        if (!providerCode || !clientId || !loginLink) {
            return;
        }

        window[providerCode + 'Init'] = function () {
            var SDK = window[providerCode + 'SDK'];
            SDK.init({client_id: clientId});

            SDK.isAuthorized('read', function (isAuthorized, apiData) {
                if (isAuthorized) {
                    var loginData = {
                        redirect: window.location.href,
                        provider: providerCode,
                        external_user_id: apiData['user_id']
                    };
                    for (var i in apiData) {
                        if (apiData.hasOwnProperty(i)) {
                            loginData['_api_data_' + i] = apiData[i];
                        }
                    }

                    // try to auto login this user
                    XenForo.ajax(
                        loginLink, loginData,
                        function (ajaxData) {
                            if (ajaxData['_redirectTarget']) {
                                if (ajaxData['_redirectMessage']) {
                                    XenForo.alert(ajaxData['_redirectMessage'], '', 2000, function () {
                                        document.location = ajaxData['_redirectTarget'];
                                    });
                                } else {
                                    document.location = ajaxData['_redirectTarget'];
                                }
                            } else {
                                if (ajaxData.message) {
                                    XenForo.alert(ajaxData.message, '', 5000);
                                }
                            }
                        },
                        {error: null, global: false}
                    );
                }
            });
        }
    };

    // *********************************************************************

    XenForo.register('script.bdApiConsumer_AutoLogin', 'XenForo.bdApiConsumer_AutoLogin');

}(jQuery, this, document);