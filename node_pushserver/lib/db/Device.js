'use strict';

var debug = require('debug')('pushserver:db:Device');
var _ = require('lodash');

exports = module.exports = function(mongoose) {
    var deviceSchema = new mongoose.Schema({
        device_type: {type: String, required: true},
        device_id: {type: String, required: true},
        oauth_client_id: {type: String, required: true, index: true},
        hub_topic: {type: [String], default: []},
        extra_data: {type: Object, default: {}}
      });
    deviceSchema.index({device_type: 1, device_id: 1});
    deviceSchema.index({oauth_client_id: 1, hub_topic: 1});
    deviceSchema.index({device_type: 1, device_id: 1, oauth_client_id: 1},
      {unique: true});
    var DeviceModel = mongoose.model('devices', deviceSchema);

    return {
        _model: DeviceModel,

        save: function(deviceType, deviceId,
          oauthClientId, hubTopic,
          extraData, callback) {
            var tryUpdating = function() {
                DeviceModel.findOne({
                    device_type: deviceType,
                    device_id: deviceId,
                    oauth_client_id: oauthClientId
                  }, function(err, device) {
                    if (!err && device) {
                      if (device.hub_topic.indexOf(hubTopic) === -1) {
                        device.hub_topic.push(hubTopic);
                      }
                      device.extra_data = _.assign({},
                        device.extra_data, extraData);
                      device.save(function(err, updatedDevice) {
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

            var tryInserting = function() {
                var device = new DeviceModel({
                    device_type: deviceType,
                    device_id: deviceId,
                    oauth_client_id: oauthClientId,
                    hub_topic: [hubTopic],
                    extra_data: extraData
                  });

                device.save(function(err, insertedDevice) {
                    if (!err) {
                      insertDone(insertedDevice);
                    } else {
                      insertFailed(err);
                    }
                  });
              };

            var updateDone = function(device) {
                debug('Updated', deviceType, deviceId, device._id);
                done('updated');
              };

            var updateFailed = function(err) {
                if (err) {
                  debug('Unable to update', deviceType, deviceId, err);
                }
                tryInserting();
              };

            var insertDone = function(device) {
                debug('Saved', deviceType, deviceId, device._id);
                done('inserted');
              };

            var insertFailed = function(err) {
                debug('Unable to insert', deviceType, deviceId, err);
                done(false);
              };

            var done = function(result) {
                if (_.isFunction(callback)) {
                  callback(result);
                }
              };

            tryUpdating();
          },

        findDevices: function(oauthClientId, hubTopic, callback) {
            var done = function(devices) {
                if (_.isFunction(callback)) {
                  callback(devices);
                }
              };

            var findQuery = {oauth_client_id: oauthClientId};
            if (hubTopic) {
              findQuery.hub_topic = hubTopic;
            }

            DeviceModel.find(findQuery, function(err, devices) {
                if (!err) {
                  done(devices);
                } else {
                  debug('Error finding', oauthClientId, hubTopic, err);
                  done([]);
                }
              });
          },

        'delete': function(deviceType, deviceId,
          oauthClientId, hubTopic, callback) {
            var tryUpdating = function() {
                var query = {
                    device_type: deviceType,
                    device_id: deviceId
                  };
                if (oauthClientId) {
                  query.oauth_client_id = oauthClientId;
                }

                DeviceModel.find(query, function(err, devices) {
                    if (err) {
                      return deleteFailed(err);
                    }

                    if (oauthClientId) {
                      var device = _.first(devices);
                      if (!device) {
                        return deleteFailed('Device could not be found.');
                      }

                      if (hubTopic) {
                        device.hub_topic =
                          _.without(device.hub_topic, hubTopic);
                        device.save(function(err) {
                            if (!err) {
                              updateDone();
                            } else {
                              deleteFailed(err);
                            }
                          });
                      } else {
                        device.remove(function(err) {
                            if (!err) {
                              removeDone();
                            } else {
                              deleteFailed(err);
                            }
                          });
                      }
                    } else {
                      var internalIds = [];
                      _.forEach(devices, function(_device) {
                          internalIds.push(_device._id);
                        });

                      DeviceModel.remove({_id: {$in: internalIds}},
                        function(err) {
                          if (!err) {
                            removeAllDone();
                          } else {
                            deleteFailed(err);
                          }
                        });
                    }
                  });
              };

            var updateDone = function() {
                debug('Deleted', deviceType, deviceId, hubTopic);
                done('updated');
              };

            var removeDone = function() {
                debug('Deleted', deviceType, deviceId);
                done('removed');
              };

            var removeAllDone = function() {
                debug('Deleted', deviceType, deviceId);
                done('removed_all');
              };

            var deleteFailed = function(err) {
                debug('Unable to delete', deviceType, deviceId,
                  oauthClientId, hubTopic || 'N/A', err);
                done(false);
              };

            var done = function(result) {
                if (_.isFunction(callback)) {
                  callback(result);
                }
              };

            tryUpdating();
          }
      };
  };
