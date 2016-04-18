'use strict';

var pushQueue = exports;
var config = require('./config');
var helper = require('./helper');
var debug = require('debug')('pushserver:pushQueue');
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
            debug('Pushed', job.data.device_type, job.data.device_id);
            done(null, result);
        } else {
            debug('Could not push', job.data.device_type, job.data.device_id, err);
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
    if (!pusher) {
        return callback('pusher has not been setup properly');
    }

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

    var packageId = '';
    var gcmKey = '';
    if (data.extra_data && typeof data.extra_data.package == 'string') {
        packageId = data.extra_data.package;
        gcmKey = config.gcm.keys[packageId];
    } else {
        gcmKey = config.gcm.keys[config.gcm.defaultKeyId];
    }

    if (gcmKey) {
        pusher.gcm(gcmKey, data.device_id, gcmPayload, callback);
    } else {
        if (!packageId) {
            return callback('extra_data.package is missing');
        }

        if (!projectDb) {
            return callback('projectDb has not been setup properly');
        }

        projectDb.findConfig('gcm', packageId, function (projectConfig) {
            if (!projectConfig || !projectConfig.api_key) {
                return callback('Project could not be found', packageId);
            }

            try {
                pusher.gcm(projectConfig.api_key, data.device_id, gcmPayload, callback);
            } catch (e) {
                debug('Error pushing via GCM', e);
                callback('Unable to push via GCM');
            }
        });
    }
};

pushQueue._oniOSJob = function (job, callback) {
    if (!pusher) {
        return callback('pusher has not been setup properly');
    }

    var data = job.data;

    if (!data.payload.notification_html) {
        return callback('payload.notification_html is missing');
    }
    var message = helper.stripHtml(data.payload.notification_html);

    var apnMessage = helper.prepareApnMessage(message);
    if (!apnMessage) {
        return callback('No APN message');
    }
    job.log('apnMessage = %s', apnMessage);
    var payload = {aps: {alert: apnMessage}};

    if (_.has(data, 'payload.user_unread_notification_count')) {
        payload.aps.badge = data.payload.user_unread_notification_count;
    }

    var packageId = '';
    var connectionOptions = config.apn.connectionOptions;
    if (data.extra_data && typeof data.extra_data.package == 'string') {
        packageId = data.extra_data.package;
        connectionOptions = null;
    }

    if (connectionOptions) {
        pusher.apn(connectionOptions, data.device_id, payload, callback);
    } else {
        if (!packageId) {
            return callback('extra_data.package is missing');
        }

        if (!projectDb) {
            return callback('projectDb has not been setup properly');
        }

        projectDb.findConfig('apn', packageId, function (projectConfig) {
            if (!projectConfig) {
                return callback('Project could not be found', packageId);
            }

            var connectionOptions = {
                packageId: packageId
            };

            _.forEach(projectConfig, function(configValue, configKey) {
                switch (configKey) {
                    case 'address':
                    case 'gateway':
                        connectionOptions.address = configValue;
                        break;
                    case 'cert':
                    case 'cert_data':
                        connectionOptions.cert = configValue;
                        break;
                    case 'key':
                    case 'key_data':
                        connectionOptions.key = configValue;
                        break;
                    default:
                        connectionOptions[configKey] = configValue;
                }
            });

            try {
                pusher.apn(connectionOptions, data.device_id, payload, callback);
            } catch (e) {
                debug('Error pushing via APN', e);
                callback('Unable to push via APN');
            }
        });
    }
};

pushQueue._onWindowsJob = function (job, callback) {
    if (!pusher) {
        return callback('pusher has not been setup properly');
    }

    var data = job.data;
    var payload = data.payload;
    var packageId = '';
    var clientId = config.wns.client_id;
    var clientSecret = config.wns.client_secret;
    var channelUri = '';

    payload.extra_data = {};
    _.forEach(data.extra_data, function (value, key) {
        switch (key) {
            case 'channel_uri':
                channelUri = value;
                break;
            case 'package':
                packageId = value;
                clientId = '';
                clientSecret = '';
                break;
            default:
                payload.extra_data[key] = value;
        }
    });

    if (!channelUri) {
        return callback('channel_uri is missing');
    }
    var payloadJson = JSON.stringify(payload);

    if (clientId && clientSecret) {
        pusher.wns(clientId, clientSecret, channelUri, payloadJson, callback);
    } else {
        if (!packageId) {
            return callback('extra_data.package is missing');
        }

        if (!projectDb) {
            return callback('projectDb has not been setup properly');
        }

        projectDb.findConfig('wns', packageId, function (projectConfig) {
            if (!projectConfig
                || !projectConfig.client_id
                || !projectConfig.client_secret) {
                return callback('Project could not be found', packageId);
            }

            try {
                pusher.wns(projectConfig.client_id, projectConfig.client_secret, channelUri, payloadJson, callback);
            } catch (e) {
                debug('Error pushing via WNS', e);
                callback('Unable to push via WNS');
            }
        });
    }
};

var pushKue = null;
var pusher = null;
var projectDb = null;
pushQueue.setup = function (_pushKue, _pusher, _projectDb) {
    pushKue = _pushKue;
    _pushKue.process(config.pushQueue.queueId, 1, pushQueue._onJob);

    pusher = _pusher;
    projectDb = _projectDb;

    return pushQueue;
};