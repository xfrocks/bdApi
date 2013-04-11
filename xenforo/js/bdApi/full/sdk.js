/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	var dataUri = '{data_uri}';

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