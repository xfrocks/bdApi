/*jshint expr: true*/
'use strict';

var db = require('../lib/db');
var chai = require('chai');

chai.should();
var expect = chai.expect;

describe('db/Device', function() {

    db.devices._model.collection.drop();

    afterEach(function(done) {
        db.devices._model.collection.drop();
        done();
      });

    it('should save device', function(done) {
        var deviceType = 'dt';
        var deviceId = 'di';
        var oauthClientId = 'oci';
        var hubTopic = 'ht';
        var extraData = {foo: 'bar'};

        var step1 = function() {
            db.devices.save(deviceType, deviceId,
              oauthClientId, hubTopic, extraData,
              function(isSaved) {
                isSaved.should.not.be.false;
                step2();
              });
          };

        var step2 = function() {
            db.devices._model.find({
                device_type: deviceType,
                device_id: deviceId,
                oauth_client_id: oauthClientId
              }, function(err, devices) {
                devices.should.be.a('array');
                devices.length.should.equal(1);

                var device = devices[0];
                device.oauth_client_id.should.equal(oauthClientId);
                device.hub_topic.should.be.a('array');
                device.hub_topic.length.should.equal(1);
                device.hub_topic.should.include(hubTopic);
                device.extra_data.should.be.a('object');
                device.extra_data.foo.should.equal(extraData.foo);

                done();
              });
          };

        step1();
      });

    it('should update device hub topic', function(done) {
        var deviceType = 'dt';
        var deviceId = 'di';
        var oauthClientId = 'oci';
        var hubTopic = 'ht';
        var hubTopic2 = 'ht2';
        var extraData = {foo: 'bar'};
        var theDevice = null;

        var init = function() {
            db.devices._model.create({
                device_type: deviceType,
                device_id: deviceId,
                oauth_client_id: oauthClientId,
                hub_topic: [hubTopic],
                extra_data: extraData
              }, function(err, device) {
                theDevice = device;
                step1();
              });
          };

        var step1 = function() {
            db.devices.save(deviceType, deviceId,
              oauthClientId, hubTopic2, extraData,
              function(isSaved) {
                isSaved.should.not.be.false;
                step2();
              });
          };

        var step2 = function() {
            db.devices._model.findById(theDevice._id, function(err, device) {
                device.hub_topic.should.have.members([hubTopic, hubTopic2]);
                done();
              });
          };

        init();
      });

    it('should update device extra data', function(done) {
        var deviceType = 'dt';
        var deviceId = 'di';
        var oauthClientId = 'oci';
        var hubTopic = 'ht';
        var extraData = {foo: 'bar'};
        var extraData2 = {bar: 'foo'};
        var theDevice = null;

        var init = function() {
            db.devices._model.create({
                device_type: deviceType,
                device_id: deviceId,
                oauth_client_id: oauthClientId,
                hub_topic: [hubTopic],
                extra_data: extraData
              }, function(err, device) {
                theDevice = device;
                step1();
              });
          };

        var step1 = function() {
            db.devices.save(deviceType, deviceId,
                oauthClientId, hubTopic, extraData2,
                function(isSaved) {
                    isSaved.should.not.be.false;
                    step2();
                  });
          };

        var step2 = function() {
            db.devices._model.findById(theDevice._id, function(err, device) {
                device.extra_data.should.has.all.keys('foo', 'bar');
                device.extra_data.foo.should.equal(extraData.foo);
                device.extra_data.bar.should.equal(extraData2.bar);

                done();
              });
          };

        init();
      });

    it('should return saved devices', function(done) {
        var oauthClientId = 'oci';
        var hubTopic = 'ht';

        var init = function() {
            db.devices._model.create({
                device_type: 'dt',
                device_id: 'di1',
                oauth_client_id: oauthClientId,
                hub_topic: [hubTopic]
              }, {
                device_type: 'dt',
                device_id: 'di2',
                oauth_client_id: oauthClientId
              }, function() {
                step1();
              });
          };

        var step1 = function() {
            db.devices.findDevices(oauthClientId, null, function(devices) {
                devices.should.be.a('array');
                devices.length.should.equal(2);

                step2();
              });
          };

        var step2 = function() {
            db.devices.findDevices(oauthClientId, hubTopic, function(devices) {
                devices.should.be.a('array');
                devices.length.should.equal(1);

                done();
              });
          };

        init();
      });

    it('should delete device', function(done) {
        var deviceType = 'dt';
        var deviceId = 'di';
        var deviceId2 = 'di2';
        var oauthClientId = 'oci';
        var hubTopic = 'ht';
        var theDevice = null;
        var theDevice2 = null;

        var init = function() {
            db.devices._model.create({
                device_type: deviceType,
                device_id: deviceId,
                oauth_client_id: oauthClientId,
                hub_topic: [hubTopic]
              }, {
                device_type: deviceType,
                device_id: deviceId2,
                oauth_client_id: oauthClientId
              }, function(err, device, device2) {
                theDevice = device;
                theDevice2 = device2;
                step1();
              });
          };

        var step1 = function() {
            db.devices.delete(deviceType, deviceId,
              oauthClientId, hubTopic,
              function(isDeleted) {
                isDeleted.should.not.be.false;
                step2();
              });
          };

        var step2 = function() {
            db.devices._model.findById(theDevice._id, function(err, device) {
                device.device_id.should.equal(deviceId);
                device.hub_topic.length.should.equal(0);

                step3();
              });
          };

        var step3 = function() {
            db.devices.delete(deviceType, deviceId,
              oauthClientId, null,
              function(isDeleted) {
                isDeleted.should.not.be.false;
                step4();
              });
          };

        var step4 = function() {
            db.devices._model.findById(theDevice._id, function(err, device) {
                expect(device).to.be.null;
                step5();
              });
          };

        var step5 = function() {
            db.devices._model.findById(theDevice2._id, function(err, device) {
                device.should.not.be.null;
                device.device_id.should.equal(deviceId2);

                done();
              });
          };

        init();
      });

    it('should delete devices', function(done) {
        var deviceType = 'dt';
        var deviceId = 'di';
        var oauthClientId = 'oci';
        var oauthClientId2 = 'oci2';
        var theDevice = null;
        var theDevice2 = null;

        var init = function() {
            db.devices._model.create({
                device_type: deviceType,
                device_id: deviceId,
                oauth_client_id: oauthClientId
              }, {
                device_type: deviceType,
                device_id: deviceId,
                oauth_client_id: oauthClientId2
              }, function(err, device, device2) {
                theDevice = device;
                theDevice2 = device2;
                step1();
              });
          };

        var step1 = function() {
            db.devices.delete(deviceType, deviceId,
              null, null, function(isDeleted) {
                isDeleted.should.not.be.false;
                step2();
              });
          };

        var step2 = function() {
            db.devices._model.findById(theDevice._id, function(err, device) {
                expect(device).to.be.null;
                step3();
              });
          };

        var step3 = function() {
            db.devices._model.findById(theDevice2._id, function(err, device) {
                expect(device).to.be.null;
                done();
              });
          };

        init();
      });
  });
