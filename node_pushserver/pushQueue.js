var pushQueue = exports;
var config = require('./config');
var pusher = require('./pusher');
var debug = require('debug')('pushQueue');
var kue = require('kue');
var string = require('string');

var jobs = kue.createQueue({
	'redis': config.redis
});

jobs.process('push', function(job, done) {
	var data = job.data;
	var message = string('' + data.payload.notification_html).stripTags().trim().s;

	var callback = function(err) {
		if (!err) {
			var args = Array.prototype.slice.call(arguments);
			debug('pushed', data.device_type, data.device_id, message, args);
		} else {
			debug('could not push', data.device_type, data.device_id, err);
		}

		done();
	}

	switch (data.device_type) {
		case 'android':
			var payload = {
				action: data.action
			};
			if (data.payload.notification_id > 0) {
				payload['notification_id'] = data.payload.notification_id;
				payload['notification'] = message;
			}
			pusher.gcm([data.device_id], payload, callback);
			break;
		case 'ios':
			var apnMessage = require('./helper').prepareApnMessage(message);
			pusher.apn(data.device_id, {
				'aps': {
					'alert': apnMessage
				}
			}, callback);
			break;
		case 'windows':
			if (data.extra_data.channel_uri) {
				var payload = data.payload;
				payload.extra_data = {};
				for (var i in data.extra_data) {
					if (i != 'channel_uri') {
						// forward all extra data, except the channel_uri
						payload.extra_data[i] = data.extra_data[i];
					}
				}

				pusher.wns(data.extra_data.channel_uri, JSON.stringify(payload), callback);
			} else {
				callback('channel_uri is missing');
			}
			break;
	}
});

pushQueue.enqueue = function(deviceType, deviceId, payload, extraData) {
	var job = jobs.create('push', {
		'device_type': deviceType,
		'device_id': deviceId,
		'payload': payload,
		'extra_data': extraData
	});

	job.attempts(config.pushQueue.attempts);
	job.backoff({ type:'exponential' });

	job.save(function(err) {
		if (err) {
			debug('failed to save job', deviceType, deviceId, err);
		} else {
			debug('job enqueued', deviceType, deviceId);
		}
	});
}

if (config.pushQueue.webPort > 0) {
	kue.app.listen(config.pushQueue.webPort);
}