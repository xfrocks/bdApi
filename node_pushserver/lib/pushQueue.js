'use strict';

var pushQueue = exports;
var config = require('./config');
var helper = require('./helper');
var debug = require('debug')('pushserver:pushQueue');
var kue = require('kue');
var _ = require('lodash');
var string = require('string');

pushQueue.enqueue = function (deviceType, deviceId, payload, extraData) {
    var job = pushKue.create(config.pushQueue.queueId, {
        title: deviceType + ' ' + deviceId,
        device_type: deviceType,
        device_id: deviceId,
        payload: payload,
        extra_data: extraData
    });

    job.attempts(config.pushQueue.attempts);
    job.backoff({type: 'exponential'});
    job.ttl(config.pushQueue.ttlInMs);
    job.removeOnComplete(true);

    job.save(function (err) {
        if (!err) {
            debug('Queued', deviceType, deviceId);
        } else {
            debug('Error enqueuing', deviceType, deviceId, err);
        }
    });
};

pushQueue._onJob = function (job, done) {
    var callback = function (err, result) {
        if (!err) {
            debug('pushed', job.data.device_type, job.data.device_id);
            done(null, result);
        } else {
            debug('could not push', job.data.device_type, job.data.device_id, err);
            if (err instanceof Error) {
                done(err);
            } else {
                done(new Error(err));
            }
        }
    };

    switch (job.data.device_type) {
        case 'android':
            return pushQueue._onAndroidJob(job, callback);
        case 'ios':
            return pushQueue._oniOSJob(job, callback);
        case 'windows':
            return pushQueue._onWindowsJob(job, callback);
    }

    return callback('Unrecognized device type ' + data.device_type);
};

pushQueue._onAndroidJob = function (job, callback) {
    var data = job.data;
    var gcmPayload = {
        action: data.action
    };
    if (data.payload.notification_id > 0) {
        gcmPayload['notification_id'] = data.payload.notification_id;
        gcmPayload['notification'] = helper.stripHtml(data.payload.notification_html);
    } else {
        data.payload.forEach(function (dataPayload, i) {
            switch (i) {
                case 'notification_id':
                case 'notification_html':
                    // ignore
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

    if (pusher) {
        job.log('gcmKeyId = %s', gcmKeyId);
        pusher.gcm(config.gcm.keys[gcmKeyId], data.device_id, gcmPayload, callback);
    } else {
        callback('pusher has not been setup properly');
    }
};

pushQueue._oniOSJob = function (job, callback) {
    var data = job.data;
    var message = helper.stripHtml(data.payload.notification_html);
    var apnMessage = helper.prepareApnMessage(message);
    if (apnMessage) {
        job.log('apnMessage = %s', apnMessage);

        if (pusher) {
            pusher.apn(data.device_id, {
                aps: {
                    alert: apnMessage
                }
            }, callback);
        } else {
            callback('pusher has not been setup properly');
        }
    } else {
        callback('No APN message');
    }
};

pushQueue._onWindowsJob = function (job, callback) {
    var data = job.data;
    if (data.extra_data.channel_uri) {
        var wnsPayload = data.payload;

        wnsPayload.extra_data = {};
        _.forEach(data.extra_data, function (extraData, i) {
            if (i != 'channel_uri') {
                // forward all extra data, except the channel_uri
                wnsPayload.extra_data[i] = extraData;
            }
        });

        if (pusher) {
            pusher.wns(data.extra_data.channel_uri, JSON.stringify(wnsPayload), callback);
        } else {
            callback('pusher has not been setup properly');
        }
    } else {
        callback('channel_uri is missing');
    }
};

pushQueue.expressMiddleware = function () {
    return kue.app;
};

var pusher = null;
pushQueue.setPusher = function (_pusher) {
    pusher = _pusher;
};

var pushKue = kue.createQueue({
    disableSearch: true,
    jobEvents: false,
    redis: config.redis
});
pushKue.watchStuckJobs(1000);
pushKue.process(config.pushQueue.queueId, 1, pushQueue._onJob);