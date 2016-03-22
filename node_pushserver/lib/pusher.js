'use strict';

var pusher = exports;
var config = require('./config');
var deviceDb = require('./db').devices;
var debug = require('debug')('pushserver:pusher');
var _ = require('lodash');

var apn, gcm, wns;
pusher.setup = function (_apn, _gcm, _wns) {
    apn = _apn;
    gcm = _gcm;
    wns = _wns;

    return pusher;
};

var apnConnections = {};
pusher._resetApnConnections = function () {
    apnConnections = {};
};

pusher.cleanUpApnConnections = function (ttlInMs) {
    var cutoff = _.now() - ttlInMs;

    _.filter(apnConnections, function (ac) {
        if (ac.connection.terminated
            || ac.lastUsed < cutoff) {
            ac.connection.shutdown();
            ac.feedback.cancel();
            return false;
        }

        // keep this connection
        return true;
    });
};

var createApnConnection = function (packageId, connectionOptions) {
    if (!apn) {
        debug('apn has not been setup properly');
        return null;
    }

    if (typeof apnConnections[packageId] == 'undefined'
        || apnConnections[packageId].connection.terminated) {
        if (config.apn.connectionTtlInMs > 0) {
            pusher.cleanUpApnConnections(config.apn.connectionTtlInMs);
        }

        var connection = new apn.Connection(connectionOptions);

        var feedback = null;
        if (config.apn.feedback.interval > 0) {
            var feedbackOptions = {
                batchFeedback: true,
                interval: config.apn.feedback.interval
            };
            _.merge(feedbackOptions, connectionOptions);
            feedback = new apn.Feedback(feedbackOptions);
            feedback.on('feedback', function (devices) {
                devices.forEach(function (item) {
                    debug('apnFeedback', packageId, item);
                    deviceDb.delete('ios', item.device);
                });
            });
        }

        apnConnections[packageId] = {
            connection: connection,
            feedback: feedback,
            lastUsed: _.now()
        };
    } else {
        apnConnections[packageId].lastUsed = _.now();
    }

    return apnConnections[packageId];
};

pusher.apn = function (connectionOptions, token, payload, callback) {
    var ac = createApnConnection(connectionOptions.packageId, connectionOptions);
    if (ac === null) {
        return callback('Unable to create APN connection');
    }

    var device = new apn.Device(token);

    var notification = new apn.Notification(payload);
    if (!payload.badge && config.apn.notificationOptions.badge) {
        notification.badge = config.apn.notificationOptions.badge;
    }
    if (!payload.expiry && config.apn.notificationOptions.expiry) {
        notification.expiry = config.apn.notificationOptions.expiry;
    } else {
        // expire must have some value
        notification.expiry = 0;
    }

    if (!payload.sound && config.apn.notificationOptions.sound) {
        notification.sound = config.apn.notificationOptions.sound;
    }

    ac.connection.pushNotification(notification, device);
    if (typeof callback == 'function') {
        return callback();
    }
};

pusher.gcm = function (gcmKey, registrationId, data, callback) {
    if (!gcm) {
        debug('gcm has not been setup properly');
        return callback('Unable to create GCM sender');
    }

    var sender = new gcm.Sender(gcmKey);

    var message = new gcm.Message(config.gcm.messageOptions);
    message.addDataWithObject(data);

    sender.send(message, [registrationId], 1, function (err, result) {
        if (typeof callback == 'function') {
            return callback(err, result);
        }
    });
};

// store access tokens in memory only
var wnsAccessTokens = [];
pusher.wns = function (clientId, clientSecret, channelUri, dataRaw, callback) {
    if (!wns) {
        debug('wns has not been setup properly');
        return callback('Unable to send data to WNS');
    }

    var options = {
        client_id: clientId,
        client_secret: clientSecret
    };
    if (typeof wnsAccessTokens[clientId] === 'string') {
        options.accessToken = wnsAccessTokens[clientId];
    }

    wns.sendRaw(channelUri, dataRaw, options, function (err, result) {
        if (err) {
            if (err.newAccessToken) {
                debug('wns', 'updated access token (from error)');
                wnsAccessTokens[clientId] = err.newAccessToken;
            }
        } else if (result) {
            if (result.newAccessToken) {
                debug('wns', 'updated access token (from result)');
                wnsAccessTokens[clientId] = result.newAccessToken;
            }
        }

        if (typeof callback == 'function') {
            return callback(err, result);
        }
    });
};