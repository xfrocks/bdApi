var pusher = exports;
var config = require('./config');
var deviceDb = require('./db').devices;
var apn = require('apn');
var debug = require('debug')('pusher');
var _ = require('lodash');
var gcm = require('node-gcm');
var wns = require('wns');

var apnConnection = null;
if (config.apn.enabled) {
	apnConnection = new apn.Connection(config.apn.connectionOptions);

	var apnFeedbackOptions = {
		'batchFeedback': true,
		'interval': config.apn.feedback.interval
	};
	_.merge(apnFeedbackOptions, config.apn.connectionOptions);

	var apnFeedback = new apn.Feedback(apnFeedbackOptions);
	apnFeedback.on('feedback', function(devices) {
		debug('APN feedback', devices);
		devices.forEach(function(item) {
			deviceDb.delete('ios', item.device);
			debug('apnFeedback', 'delete device', 'ios', item.device);
		});
	});
}

pusher.apn = function(token, payload, callback) {
	var device = new apn.Device(token);

	var notification = new apn.Notification(payload);
    
    if (!payload.badge && config.apn.notificationOptions.badge) {
    	notification.badge = config.apn.notificationOptions.badge;
    }
    
    if (!payload.expire && config.apn.notificationOptions.expiry) {
	    notification.expiry = config.apn.notificationOptions.expiry;
	} else {
		// expire must has default value of 0
		notification.expiry = 0;
	}

	if (!payload.sound && config.apn.notificationOptions.sound) {
    	notification.sound = config.apn.notificationOptions.sound;
    }

    if (apnConnection != null) {
	    apnConnection.pushNotification(notification, device);
	}

    if (typeof callback == 'function') {
    	callback();
    }
}

pusher.gcm = function(gcmKey, registrationIds, data, callback) {
	if (!config.gcm.enabled) {
		if (typeof callback == 'function') {
			callback('GCM is disabled.');
		}
		return;
	}

	var sender = new gcm.Sender(gcmKey);

	var message = new gcm.Message(config.gcm.messageOptions);
	message.addDataWithObject(data);

	sender.send(message, registrationIds, 1, function(err, result) {
		if (typeof callback == 'function') {
			callback(err, result);
		}
	});
};

// store access token in memory only
var accessToken = '';
pusher.wns = function(channelUri, dataRaw, callback) {
	if (!config.wns.enabled) {
		if (typeof callback == 'function') {
			callback('WNS is disabled.');
		}
		return;
	}

	var options = _.merge({
		'accessToken': accessToken
	}, config.wns);

	wns.sendRaw(channelUri, dataRaw, options, function(err, result) {
		if (err) {
			if (err.newAccessToken) {
				debug('wns', 'updated access token (from error)');
				accessToken = err.newAccessToken;
			}
		} else if (result) {
			if (result.newAccessToken) {
				debug('wns', 'updated access token (from result)');
				accessToken = result.newAccessToken;
			}
		}

		if (typeof callback == 'function') {
			callback(err, result);
		}
	});
};