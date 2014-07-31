! function($, window, document, _undefined)
{
	window.xfacInit = function()
	{
		var sdk = window.xfacSDK;

		if (window.xfacClientId != _undefined && window.xfacWpLogin != _undefined)
		{
			sdk.init(
			{
				client_id: window.xfacClientId
			});

			sdk.isAuthorized('read', function(isAuthorized, apiData)
			{
				if (isAuthorized)
				{
					window.location = window.xfacWpLogin + '&redirect_to=' + encodeURIComponent(window.location);
				}
			});
		}
	};
}(jQuery, this, document);
