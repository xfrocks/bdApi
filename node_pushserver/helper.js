var helper = exports;
var debug = require('debug')('helper');

helper.prepareApnMessage = function(originalMessage) {
	var message = '';

	if (originalMessage.length > 230) {
		message = originalMessage.substr(0, 227) + '...';
		debug('prepareApnMessage', originalMessage, '->', message);
	} else {
		message = originalMessage;
	}

	return message;
};