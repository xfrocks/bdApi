'use strict';

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

            if (_.has(devices, key)) {
                var device = devices[key];
                if (device.hub_topic.indexOf(hubTopic) === -1) {
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
            if (_.isFunction(callback)) {
                callback(result);
            }
        };

        mock();
    },

    findDevices: function (oauthClientId, hubTopic, callback) {
        var results = _.filter(devices, function (device) {
            if (device.oauth_client_id !== oauthClientId) {
                return false;
            }

            if (hubTopic) {
                return _.includes(device.hub_topic, hubTopic);
            } else {
                return true;
            }
        });

        if (_.isFunction(callback)) {
            callback(results);
        }
    },

    delete: function (deviceType, deviceId, oauthClientId, hubTopic, callback) {
        var mock = function () {
            var key = deviceType + deviceId + oauthClientId;

            if (_.has(devices, key)) {
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
            if (_.isFunction(callback)) {
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
            cert: certData,
            key: keyData
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

            if (_.has(projects, key)) {
                var project = projects[key];
                project.configuration = _.assign({}, project.configuration, configuration);
                project.last_updated = Date.now();

                done('updated');
            } else {
                projects[key] = {
                    _id: _.keys(projects).length + 1,
                    project_type: projectType,
                    project_id: projectId,
                    configuration: configuration,
                    created: new Date(),
                    last_updated: new Date()
                };

                done('saved');
            }
        };

        var done = function (result) {
            if (_.isFunction(callback)) {
                callback(result);
            }
        };

        mock();
    },

    findProject: function (projectType, projectId, callback) {
        var found = null;

        _.forEach(projects, function (project) {
            if (project.project_type === projectType &&
                project.project_id === projectId) {
                found = project;
                return false;
            }
        });

        if (_.isFunction(callback)) {
            callback(found);
        }
    },

    findConfig: function (projectType, projectId, callback) {
        this.findProject(projectType, projectId, function (project) {
            if (_.isFunction(callback)) {
                if (project) {
                    callback(project.configuration);
                } else {
                    callback(null);
                }

            }
        });
    }
};