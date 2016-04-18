'use strict';

var pusher = exports;
var config = require('./config');
var debug = require('debug')('pushserver:pusher');
var _ = require('lodash');

var apn;
var gcm;
var wns;
var deviceDb;
pusher.setup = function(_apn, _gcm, _wns, _deviceDb) {
    apn = _apn;
    gcm = _gcm;
    wns = _wns;
    deviceDb = _deviceDb;

    return pusher;
  };

var apnConnections = [];
var apnConnectionCount = 0;
pusher._resetApnConnections = function() {
    apnConnections = [];
    apnConnectionCount = 0;
  };

pusher.cleanUpApnConnections = function(ttlInMs) {
    var cutoff = _.now() - ttlInMs;

    _.filter(apnConnections, function(ac) {
        if (ac.connection.terminated ||
            ac.lastUsed < cutoff) {
          ac.connection.shutdown();
          ac.feedback.cancel();
          return false;
        }

        // keep this connection
        return true;
      });
  };

var createApnConnection = function(connectionOptions) {
    if (!apn) {
      debug('apn missing');
      return null;
    }
    if (!connectionOptions.packageId ||
        !connectionOptions.cert) {
      debug('connectionOptions missing');
      return null;
    }

    var connectionId = -1;
    _.forEach(apnConnections, function(ac, acId) {
        if (ac.connectionOptions.packageId !== connectionOptions.packageId) {
          return;
        }

        if (ac.connectionOptions.cert !== connectionOptions.cert) {
          return;
        }

        connectionId = acId;
      });

    if (connectionId === -1 ||
        apnConnections[connectionId].connection.terminated) {
      if (config.apn.connectionTtlInMs > 0) {
        pusher.cleanUpApnConnections(config.apn.connectionTtlInMs);
      }

      var connection = new apn.Connection(connectionOptions);
      connection.on('transmitted', function() {
          debug('apn', connectionId, 'transmitted', ac.transmittedCount++);
        });

      var feedback = null;
      if (config.apn.feedback.interval > 0) {
        var feedbackOptions = {
            batchFeedback: true,
            interval: config.apn.feedback.interval
          };
        _.merge(feedbackOptions, connectionOptions);
        feedback = new apn.Feedback(feedbackOptions);
        feedback.on('feedback', function(items) {
            items.forEach(function(item) {
                debug('apn', connectionId, 'feedback', item);
                deviceDb.delete('ios', item.device);
              });
          });
      }

      var ac = {
          id: apnConnections.length,
          connectionOptions: connectionOptions,
          transmittedCount: 0,

          connection: connection,
          feedback: feedback,
          lastUsed: _.now()
        };

      apnConnections.push(ac);
      apnConnectionCount++;
      connectionId = apnConnectionCount - 1;
    } else {
      apnConnections[connectionId].lastUsed = _.now();
    }

    return apnConnections[connectionId];
  };

pusher.apn = function(connectionOptions, token, payload, callback) {
    if (!_.has(payload, 'aps.alert')) {
      return callback('payload.aps.alert missing');
    }

    var ac = createApnConnection(connectionOptions);
    if (ac === null) {
      return callback('Unable to create APN connection');
    }
    debug('apn', 'connection.lastUsed =', ac.lastUsed);

    var device = new apn.Device(token);

    var filteredPayload = _.omit(payload, ['aps', 'expiry']);
    var notification = new apn.Notification(filteredPayload);

    notification.alert = payload.aps.alert;

    if (_.has(payload, 'aps.badge')) {
      notification.badge = payload.aps.badge;
    } else if (_.has(config, 'apn.notificationOptions.badge')) {
      notification.badge = config.apn.notificationOptions.badge;
    }

    if (_.has(payload, 'aps.sound')) {
      notification.sound = payload.aps.sound;
    } else if (_.has(config, 'apn.notificationOptions.sound')) {
      notification.sound = config.apn.notificationOptions.sound;
    } else {
      notification.sound = 'default';
    }

    if (_.has(payload, 'expiry')) {
      notification.expiry = payload.expiry;
    } else if (_.has(config, 'apn.notificationOptions.expiry')) {
      notification.expiry = config.apn.notificationOptions.expiry;
    } else {
      // notification shouldn't expire itself by default
      notification.expiry = 0;
    }

    debug('apn', 'pushing', device, notification);
    ac.connection.pushNotification(notification, device);
    if (_.isFunction(callback)) {
      return callback();
    }
  };

pusher.gcm = function(gcmKey, registrationId, data, callback) {
    if (!gcm) {
      debug('gcm missing');
      return callback('Unable to create GCM sender');
    }

    var sender = new gcm.Sender(gcmKey);

    var message = new gcm.Message(config.gcm.messageOptions);
    message.addDataWithObject(data);

    sender.send(message, [registrationId], 1, function(err, result) {
        if (_.isFunction(callback)) {
          return callback(err, result);
        }
      });
  };

// store access tokens in memory only
var wnsAccessTokens = [];
pusher.wns = function(clientId, clientSecret, channelUri, dataRaw, callback) {
    if (!wns) {
      debug('wns missing');
      return callback('Unable to send data to WNS');
    }

    var options = {
        client_id: clientId,
        client_secret: clientSecret
      };
    if (_.isString(wnsAccessTokens[clientId])) {
      options.accessToken = wnsAccessTokens[clientId];
    }

    wns.sendRaw(channelUri, dataRaw, options, function(err, result) {
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

        if (_.isFunction(callback)) {
          return callback(err, result);
        }
      });
  };
