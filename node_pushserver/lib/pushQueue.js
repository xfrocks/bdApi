var pushQueue = exports;
var config = require('./config');
var pusher = require('./pusher');
var debug = require('debug')('pushQueue');
var kue = require('kue');
var string = require('string');

var jobs = kue.createQueue({
    'redis': config.redis
});

jobs.process(config.pushQueue.queueId, 1, function (job, done) {
    var data = job.data;
    var message = string('' + data.payload.notification_html).stripTags().trim().s;

    var callback = function (err) {
        if (!err) {
            var args = Array.prototype.slice.call(arguments);
            debug('pushed', data.device_type, data.device_id, message, args);
        } else {
            debug('could not push', data.device_type, data.device_id, err);
        }

        done();
    };

    switch (data.device_type) {
        case 'android':
            var gcmPayload = {
                action: data.action
            };
            if (data.payload.notification_id > 0) {
                gcmPayload['notification_id'] = data.payload.notification_id;
                gcmPayload['notification'] = message;
            } else {
                data.payload.forEach(function (dataPayload, i) {
                    switch (i) {
                        case 'notification_id':
                        case 'notification_html':
                            // ignore;
                            break;
                        default:
                            gcmPayload[i] = dataPayload;
                    }
                });
            }

            var gcmKeyId;
            if (data.extra_data
                && typeof data.extra_data.package == 'string'
                && typeof config.gcm.keys[data.extra_data.package] == 'string') {
                gcmKeyId = data.extra_data.package;
            } else {
                gcmKeyId = config.gcm.defaultKeyId;
            }

            pusher.gcm(config.gcm.keys[gcmKeyId], [data.device_id], gcmPayload, callback);
            break;
        case 'ios':
            var apnMessage = require('./helper').prepareApnMessage(message);
            if (apnMessage) {
                pusher.apn(data.device_id, {
                    'aps': {
                        'alert': apnMessage
                    }
                }, callback);
            }
            break;
        case 'windows':
            if (data.extra_data.channel_uri) {
                var wnsPayload = data.payload;
                payload.extra_data = {};
                data.extra_data.forEach(function (extraData, i) {
                    if (i != 'channel_uri') {
                        // forward all extra data, except the channel_uri
                        wnsPayload.extra_data[i] = extraData;
                    }
                });

                pusher.wns(data.extra_data.channel_uri, JSON.stringify(wnsPayload), callback);
            } else {
                callback('channel_uri is missing');
            }
            break;
    }
});

pushQueue.enqueue = function (deviceType, deviceId, payload, extraData) {
    var job = jobs.create(config.pushQueue.queueId, {
        'device_type': deviceType,
        'device_id': deviceId,
        'payload': payload,
        'extra_data': extraData
    });

    job.attempts(config.pushQueue.attempts);
    job.backoff({type: 'exponential'});

    job.save(function (err) {
        if (err) {
            debug('failed to save job', deviceType, deviceId, err);
        } else {
            debug('job enqueued', deviceType, deviceId);
        }
    });
};

if (config.pushQueue.webPort > 0) {
    kue.app.listen(config.pushQueue.webPort);
}