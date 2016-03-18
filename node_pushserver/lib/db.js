'use strict';

var db = exports;
var config = require('./config');
var debug = require('debug')('pushserver:db');
var _ = require('lodash');
var mongoose = require("mongoose");

var mongoUri = config.db.mongoUri;
mongoose.connect(config.db.mongoUri, function (err) {
    if (err) {
        debug('Error connecting to the MongoDb.', err);
    } else {
        debug('Connected', mongoUri);
    }
});

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
var Device = mongoose.model('devices', deviceSchema);

db.devices = {
    _model: Device,

    save: function (deviceType, deviceId, oauthClientId, hubTopic, extraData, callback) {
        var tryUpdating = function () {
            Device.findOne({
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
            var device = new Device({
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

        Device.find(findQuery, function (err, devices) {
            if (!err) {
                done(devices);
            } else {
                debug('Error finding devices', oauthClientId, hubTopic, err);
                done([]);
            }
        });
    },

    delete: function (deviceType, deviceId, oauthClientId, hubTopic, callback) {
        var tryUpdating = function () {
            var query = {
                device_type: deviceType,
                device_id: deviceId
            };
            if (oauthClientId) {
                query.oauth_client_id = oauthClientId;
            }

            Device.find(query, function (err, devices) {
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

                    Device.remove({_id: {$in: internalIds}}, function (err) {
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

db.expressMiddleware = function () {
    var mongoExpress = require('mongo-express/lib/middleware');
    var mongoUriParser = require('mongo-uri');

    var mec = require('mongo-express/config.default');
    mec.useBasicAuth = false;
    mec.options.readOnly = true;

    var mongoUriParsed = mongoUriParser.parse(mongoUri);
    _.assign(mec.mongodb, {
        server: _.first(mongoUriParsed.hosts),
        port: _.first(mongoUriParsed.ports),
        useSSL: false
    });
    if (mec.mongodb.port === null) {
        mec.mongodb.port = 27017;
    }
    mec.mongodb.auth = [];
    if (mongoUriParsed.database) {
        var auth = {
            database: mongoUriParsed.database
        };
        if (mongoUriParsed.username !== null
            && mongoUriParsed.password !== null) {
            auth.username = mongoUriParsed.username;
            auth.password = mongoUriParsed.password;
        }
        mec.mongodb.auth.push(auth);
    }

    return mongoExpress(mec);
};