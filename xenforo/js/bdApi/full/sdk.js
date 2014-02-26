/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	var dataUri = '{data_uri}';
	var requestBaseUri = '{request_uri}';

	window['{prefix}SDK'] = {};
	var SDK = window['{prefix}SDK'];
	var SDK_options =
	{
		'client_id': false
	};

	$.extend(true, SDK,
	{
		init: function(options)
		{
			$.extend(true, SDK_options, options);
		},

		isAuthorized: function(scope, callback)
		{
			// callback = function(isAuthorized, apiData) {};
			$.ajax(
				dataUri,
				{
					data:
					{
						'cmd': 'authorized',
						'client_id': SDK_options['client_id'],
						'scope': scope
					},
					dataType: 'jsonp',
					success: function(data, textStatus)
					{
						if (typeof callback != 'function')
						{
							return;
						}

						if (data['authorized'] == 1)
						{
							callback(true, data);
						}
						else
						{
							callback(false, null);
						}
					}
				}
			);
		},
		
		request: function(route, callback, accessToken, method, data)
		{
			// callback = function(apiData) {};
			var uri = requestBaseUri + '?' + route + '&_xfResponseType=jsonp';

			var ajaxOptions =
			{
				dataType: 'jsonp',
				success: function(data, textStatus)
				{
					if (typeof callback != 'function')
					{
						return;
					}

					callback(data);
				}
			};

			if (method != _undefined)
			{
				ajaxOptions.type = method;
			}
			else
			{
				ajaxOptions.type = 'GET';
			}

			if (accessToken != _undefined)
			{
				if (ajaxOptions.type == 'GET')
				{
					uri += '&oauth_token=' + accessToken;
				}
				else
				{
					if (data != _undefined)
					{
						data.oauth_token = accessToken;
					}
					else
					{
						data =
						{
							oauth_token: accessToken
						};
					}
				}
			}

			if (data != _undefined)
			{
				ajaxOptions.data = data;
			}
			
			$.ajax(
				uri,
				ajaxOptions
			);
		}
	});

	$(document).ready(function()
	{
		if (typeof window['{prefix}Init'] == 'function')
		{
			window['{prefix}Init']();
		}
	});
}
(jQuery, this, document);