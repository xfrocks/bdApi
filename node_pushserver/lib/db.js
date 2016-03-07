var db = exports;
var config = require('./config');
var debug = require('debug')('db');
var redis = require('redis');

debug('redisClient is connecting...');
var redisClient = redis.createClient(config.redis.port, config.redis.host, {
    'auth_pass': config.redis.auth
});
redisClient.on('ready', function () {
    debug('redisClient is ready');
});

db.devices = {
    findDevices: function (oauthClientId, hubTopic, callback) {
        var found = [];

        var done = function () {
            debug('findDevices', oauthClientId, hubTopic ? hubTopic : 'N/A', 'found.length =', found.length);
            if (typeof callback == 'function') {
                callback(found);
            }
        };

        var indexKey = [];
        if (hubTopic) {
            indexKey = db.devices.getIndexKey(oauthClientId, hubTopic);
        } else {
            indexKey = db.devices.getIndexKey2(oauthClientId);
        }

        redisClient.smembers(indexKey, function (err, deviceKeys) {
            var deviceLeft = deviceKeys.length;
            if (deviceLeft == 0) {
                return done();
            }

            deviceKeys.forEach(function (deviceKey) {
                redisClient.hgetall(deviceKey, function (err, device) {
                    var extraDataKey = db.devices.getExtraDataKey(deviceKey);
                    redisClient.hgetall(extraDataKey, function (err, extraData) {
                        deviceLeft--;
                        device.extra_data = extraData;

                        if (!hubTopic
                            || device.hub_topic === hubTopic
                        ) {
                            found.push(device);
                        }

                        if (deviceLeft == 0) {
                            return done();
                        }
                    });
                });
            });
        });
    },

    save: function (deviceType, deviceId, oauthClientId, hubTopic, extraData) {
        var deviceKey = db.devices.getDeviceKey(deviceType, deviceId, oauthClientId);
        var device = {
            'device_type': deviceType,
            'device_id': deviceId,
            'oauth_client_id': oauthClientId,
            'hub_topic': hubTopic
        };
        redisClient.hmset(deviceKey, device);

        if (extraData != null && typeof extraData == 'object') {
            var extraDataKey = db.devices.getExtraDataKey(deviceKey);
            redisClient.hmset(extraDataKey, extraData);
        }

        if (hubTopic) {
            var indexKey = db.devices.getIndexKey(oauthClientId, hubTopic);
            redisClient.sadd(indexKey, deviceKey);
        }

        var indexKey2 = db.devices.getIndexKey2(oauthClientId);
        redisClient.sadd(indexKey2, deviceKey);

        debug('saved', device, extraData);
    },

    delete: function (deviceType, deviceId, oauthClientId) {
        debug('deleting', deviceType, deviceId);

        var deviceKey = db.devices.getDeviceKey(deviceType, deviceId, oauthClientId);
        redisClient.hgetall(deviceKey, function (err, device) {
            if (!device) {
                debug('could not delete', deviceType, deviceId);
                return false;
            }

            db.devices._hdel(deviceKey);

            var extraDataKey = db.devices.getExtraDataKey(deviceKey);
            db.devices._hdel(extraDataKey);

            var indexKey = db.devices.getIndexKey(device.oauth_client_id, device.hub_topic);
            redisClient.srem(indexKey, deviceKey);

            var indexKey2 = db.devices.getIndexKey2(device.oauth_client_id);
            redisClient.srem(indexKey2, deviceKey);

            debug('deleted', deviceType, deviceId);
        });
    },

    getDeviceKey: function (deviceType, deviceId, oauthClientId) {
        return 'db:d:' + deviceType + '_' + deviceId + '_' + oauthClientId;
    },

    getExtraDataKey: function (deviceKey) {
        return 'db:ed:' + deviceKey.substr(5);
    },

    getIndexKey: function (oauthClientId, hubTopic) {
        return 'db:i:' + oauthClientId + '_' + hubTopic;
    },

    getIndexKey2: function (oauthClientId) {
        return 'db:i2:' + oauthClientId;
    },

    _hdel: function (key) {
        redisClient.hkeys(key, function (err, replies) {
            replies.forEach(function (field) {
                debug('hdel', key, field);
                redisClient.hdel(key, field);
            });
        });
    }
};