var db = exports;
var _ = require('lodash');

var devices = {};
var projects = {};

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

        if (typeof callback == 'function') {
            callback(results);
        }
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

db.projects = {

    _reset: function () {
        projects = {};
    },

    saveApn: function (appId, certData, keyData, otherOptions, callback) {
        var configuration = _.assign({}, {
            cert_data: certData,
            key_data: keyData
        }, otherOptions);

        return this.save('apn', appId, configuration, callback);
    },

    saveGcm: function (packageId, apiKey, callback) {
        return this.save('gcm', packageId, {api_key: apiKey}, callback);
    },

    saveWns: function (packageId, clientId, clientSecret, callback) {
        return this.save('wns', packageId, {
            client_id: clientId,
            client_secret: clientSecret
        }, callback);
    },

    save: function (projectType, projectId, configuration, callback) {
        var mock = function () {
            if (projectId === 'error') {
                done(false);
            }

            var key = projectType + projectId;

            if (typeof projects[key] === 'object') {
                var project = projects[key];
                project.configuration = _.assign({}, project.configuration, extraData);

                done('updated');
            } else {
                projects[key] = {
                    project_type: projectType,
                    project_id: projectId,
                    configuration: configuration
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

    findConfig: function (projectType, projectId, callback) {
        var projectConfig = null;

        _.forEach(projects, function (project) {
            if (project.project_type == projectType
                && project.project_id == projectId) {
                projectConfig = project.configuration;
                return false;
            }
        });

        if (typeof callback == 'function') {
            callback(projectConfig);
        }
    }
};