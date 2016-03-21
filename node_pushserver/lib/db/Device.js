'use strict';

var debug = require('debug')('pushserver:db:Device');
var _ = require('lodash');

var Device = exports = module.exports = function (mongoose) {
    var deviceSchema = new mongoose.Schema({
        device_type: {type: String, required: true},
        device_id: {type: String, required: true},
        oauth_client_id: {type: String, required: true, index: true},
        hub_topic: {type: [String], default: []},
        extra_data: {type: Object, default: {}}
    });
    deviceSchema.index({device_type: 1, device_id: 1});
    deviceSchema.index({oauth_client_id: 1, hub_topic: 1});
    deviceSchema.index({device_type: 1, device_id: 1, oauth_client_id: 1}, {unique: true});
    var deviceModel = mongoose.model('devices', deviceSchema);

    return {
        _model: deviceModel,

        save: function (deviceType, deviceId, oauthClientId, hubTopic, extraData, callback) {
            var tryUpdating = function () {
                deviceModel.findOne({
                    device_type: deviceType,
                    device_id: deviceId,
                    oauth_client_id: oauthClientId
                }, function (err, device) {
                    if (!err && device) {
                        if (device.hub_topic.indexOf(hubTopic) == -1) {
                            device.hub_topic.push(hubTopic);
                        }
                        device.extra_data = _.assign({}, device.extra_data, extraData);
                        device.save(function (err, updatedDevice) {
                            if (!err && updatedDevice) {
                                updateDone(updatedDevice);
                            } else {
                                updateFailed(err);
                            }
                        });
                    } else {
                        updateFailed(err);
                    }
                });
            };

            var tryInserting = function () {
                var device = new deviceModel({
                    device_type: deviceType,
                    device_id: deviceId,
                    oauth_client_id: oauthClientId,
                    hub_topic: [hubTopic],
                    extra_data: extraData
                });

                device.save(function (err, insertedDevice) {
                    if (!err) {
                        insertDone(insertedDevice);
                    } else {
                        insertFailed(err);
                    }
                });
            };

            var updateDone = function (device) {
                debug('Updated device', deviceType, deviceId, device._id);
                done('updated');
            };

            var updateFailed = function (err) {
                if (err) {
                    debug('Unable to update device', deviceType, deviceId, err);
                }
                tryInserting();
            };

            var insertDone = function (device) {
                debug('Saved device', deviceType, deviceId, device._id);
                done('inserted');
            };

            var insertFailed = function (err) {
                debug('Unable to insert device', deviceType, deviceId, err);
                done(false);
            };

            var done = function (result) {
                if (typeof callback == 'function') {
                    callback(result);
                }
            };

            tryUpdating();
        },

        findDevices: function (oauthClientId, hubTopic, callback) {
            var done = function (devices) {
                debug('findDevices', oauthClientId, hubTopic ? hubTopic : 'N/A', devices.length);
                if (typeof callback == 'function') {
                    callback(devices);
                }
            };

            var findQuery = {oauth_client_id: oauthClientId};
            if (hubTopic) {
                findQuery.hub_topic = hubTopic;
            }

            deviceModel.find(findQuery, function (err, devices) {
                if (!err) {
                    done(devices);
                } else {
                    debug('Error finding devices', oauthClientId, hubTopic, err);
                    done([]);
                }
            });
        },

        'delete': function (deviceType, deviceId, oauthClientId, hubTopic, callback) {
            var tryUpdating = function () {
                var query = {
                    device_type: deviceType,
                    device_id: deviceId
                };
                if (oauthClientId) {
                    query.oauth_client_id = oauthClientId;
                }

                deviceModel.find(query, function (err, devices) {
                    if (err) {
                        return deleteFailed(err);
                    }

                    if (oauthClientId) {
                        var device = _.first(devices);
                        if (!device) {
                            return deleteFailed('Device could not be found.');
                        }

                        if (hubTopic) {
                            device.hub_topic = _.without(device.hub_topic, hubTopic);
                            device.save(function (err) {
                                if (!err) {
                                    updateDone();
                                } else {
                                    deleteFailed(err);
                                }
                            });
                        } else {
                            device.remove(function (err) {
                                if (!err) {
                                    removeDone();
                                } else {
                                    deleteFailed(err);
                                }
                            });
                        }
                    } else {
                        var internalIds = [];
                        _.forEach(devices, function (_device) {
                            internalIds.push(_device._id);
                        });

                        deviceModel.remove({_id: {$in: internalIds}}, function (err) {
                            if (!err) {
                                removeAllDone();
                            } else {
                                deleteFailed(err);
                            }
                        });
                    }
                });
            };

            var updateDone = function () {
                debug('Deleted device', deviceType, deviceId, hubTopic);
                done('updated');
            };

            var removeDone = function () {
                debug('Deleted device', deviceType, deviceId);
                done('removed');
            };

            var removeAllDone = function () {
                debug('Deleted devices', deviceType, deviceId);
                done('removed_all');
            };

            var deleteFailed = function (err) {
                debug('Unable to delete device', deviceType, deviceId, oauthClientId, hubTopic ? hubTopic : 'N/A', err);
                done(false);
            };

            var done = function (result) {
                if (typeof callback == 'function') {
                    callback(result);
                }
            };

            tryUpdating();
        }
    };
};