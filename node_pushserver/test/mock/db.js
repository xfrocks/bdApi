var db = exports;
var _ = require('lodash');

var devices = {};

db.devices = {

    _reset: function () {
        devices = {};
    },

    save: function (deviceType, deviceId, oauthClientId, hubTopic, extraData, callback) {
        var mock = function () {
            if (hubTopic === 'error') {
                done(false);
            }

            var key = deviceType + deviceId + oauthClientId;

            if (typeof devices[key] === 'object') {
                var device = devices[key];
                if (device.hub_topic.indexOf(hubTopic) == -1) {
                    device.hub_topic.push(hubTopic);
                }
                device.extra_data = _.assign({}, device.extra_data, extraData);

                done('updated');
            } else {
                devices[key] = {
                    device_type: deviceType,
                    device_id: deviceId,
                    oauth_client_id: oauthClientId,
                    hub_topic: [hubTopic],
                    extra_data: extraData
                };

                done('saved');
            }
        };

        var done = function (result) {
            if (typeof callback == 'function') {
                callback(result);
            }
        };

        mock();
    },

    findDevices: function (oauthClientId, hubTopic, callback) {
        var results = _.filter(devices, function (device) {
            if (device.oauth_client_id != oauthClientId) {
                return false;
            }

            if (hubTopic) {
                return _.includes(device.hub_topic, hubTopic);
            } else {
                return true;
            }
        });

        callback(results);
    },

    delete: function (deviceType, deviceId, oauthClientId, hubTopic, callback) {
        var mock = function () {
            var key = deviceType + deviceId + oauthClientId;

            if (typeof devices[key] === 'object') {
                if (hubTopic) {
                    var device = devices[key];
                    device.hub_topic = _.without(device.hub_topic, hubTopic);
                    done('updated');
                } else {
                    delete devices[key];
                    done('removed');
                }
            } else {
                done(false);
            }
        };

        var done = function (result) {
            if (typeof callback == 'function') {
                callback(result);
            }
        };

        mock();
    }
};