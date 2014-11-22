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

			var location = '' + window.location;
			if (location.indexOf('xfac_error') > -1)
			{
				// do not try to login if an error has been indicated
				return false;
			}

			sdk.isAuthorized('', function(isAuthorized, apiData)
			{
				if (isAuthorized)
				{
					window.location = window.xfacWpLogin + '&redirect_to=' + encodeURIComponent(location);
				}
			});
		}
	};
}(jQuery, this, document);
