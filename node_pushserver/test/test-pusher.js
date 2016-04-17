/*jshint expr: true*/
'use strict';

var config = require('../lib/config');
var pusher = require('../lib/pusher');
var chai = require('chai');

chai.should();
var expect = chai.expect;

var apn = require('./mock/_modules-apn');
var db = require('./mock/db');
pusher.setup(apn, null, null, db.devices);

describe('pusher', function() {

    beforeEach(function(done) {
        config.apn.notificationOptions = {};
        apn._reset();
        pusher._resetApnConnections();
        done();
      });

    it('should push apn', function(done) {
        var connectionOptions = {
            packageId: 'pi',
            cert: 'cd',
            key: 'kd'
          };
        var token = 't';
        var payload = {aps: {alert: 'foo'}};

        pusher.apn(connectionOptions, token, payload, function(err) {
            expect(err).to.be.undefined;

            var push = apn._getLatestPush();
            push.connection.options.should.deep.equal(connectionOptions);
            push.device.token.should.equal(token);
            push.notification.alert.should.equal(payload.aps.alert);
            push.notification.expiry.should.equal(0);

            apn._getConnectionCount().should.equal(1);
            apn._getFeedbackCount().should.equal(1);

            done();
          });
      });

    it('[apn] should guard against missing data', function(done) {
        var test1 = function() {
          pusher.apn({
              cert: 'cd',
              key: 'kd'
            }, 't', {aps: {alert: 'foo'}}, function(err) {
              err.should.be.string;
              test2();
            });
        };

        var test2 = function() {
          pusher.apn({
              packageId: 'pi',
              key: 'kd'
            }, 't', {aps: {alert: 'foo'}}, function(err) {
              err.should.be.string;
              test3();
            });
        };

        var test3 = function() {
          pusher.apn({
              packageId: 'pi',
              cert: 'cd',
              key: 'kd'
            }, 't', {}, function(err) {
              err.should.be.string;
              done();
            });
        };

        test1();
      });

    it('[apn] should configure notification directly', function(done) {
        var connectionOptions = {
            packageId: 'pi',
            cert: 'cd',
            key: 'kd'
          };
        var token = 't';
        var payload = {
            aps: {
                alert: 'foo',
                badge: 'b',
                sound: 's'
              },
            expiry: 123,
            'content-available': 1
          };

        pusher.apn(connectionOptions, token, payload, function(err) {
            expect(err).to.be.undefined;

            var push = apn._getLatestPush();
            push.notification.alert.should.equal(payload.aps.alert);
            push.notification.badge.should.equal(payload.aps.badge);
            push.notification.sound.should.equal(payload.aps.sound);
            push.notification.expiry.should.equal(payload.expiry);
            push.notification.payload.should.deep.equal({
                'content-available': payload['content-available']
              });

            done();
          });
      });

    it('[apn] should configure notification via config', function(done) {
        config.apn.notificationOptions = {
          badge: 'b',
          sound: 's',
          expiry: 123
        };

        var payload = {aps: {alert: 'foo'}};

        pusher.apn({
            packageId: 'pi',
            cert: 'cd',
            key: 'kd'
          }, 't', payload, function(err) {
            expect(err).to.be.undefined;

            var push = apn._getLatestPush();
            push.notification.alert.should.equal(payload.aps.alert);
            push.notification.badge.
              should.equal(config.apn.notificationOptions.badge);
            push.notification.sound.
              should.equal(config.apn.notificationOptions.sound);
            push.notification.expiry.
              should.equal(config.apn.notificationOptions.expiry);

            done();
          });
      });

    it('[apn] should reuse connection', function(done) {
        var connectionOptions = {
            packageId: 'pi',
            cert: 'cd',
            key: 'kd'
          };
        var token = 't';
        var token2 = 't';
        var payload = {aps: {alert: 'foo'}};

        var test1 = function() {
            pusher.apn(connectionOptions, token, payload, function() {
                test2();
              });
          };

        var test2 = function() {
            pusher.apn(connectionOptions, token2, payload, function() {
                apn._getConnectionCount().should.equal(1);
                apn._getFeedbackCount().should.equal(1);

                done();
              });
          };

        test1();
      });

    it('[apn] should create connections (diff packageIds)', function(done) {
        var connectionOptions = {
            packageId: 'pi',
            cert: 'cd',
            key: 'kd'
          };
        var token = 't';
        var connectionOptions2 = {
            packageId: 'pi2',
            cert: 'cd',
            key: 'kd'
          };
        var token2 = 't';
        var payload = {aps: {alert: 'foo'}};

        var test1 = function() {
            pusher.apn(connectionOptions, token, payload, function() {
                test2();
              });
          };

        var test2 = function() {
            pusher.apn(connectionOptions2, token2, payload, function() {
                apn._getConnectionCount().should.equal(2);
                apn._getFeedbackCount().should.equal(2);

                done();
              });
          };

        test1();
      });

    it('[apn] should create connections (diff certs)', function(done) {
        var connectionOptions = {
            packageId: 'pi',
            cert: 'cd',
            key: 'kd'
          };
        var token = 't';
        var connectionOptions2 = {
            packageId: 'pi',
            cert: 'cd2',
            key: 'kd2'
          };
        var token2 = 't';
        var payload = {aps: {alert: 'foo'}};

        var test1 = function() {
            pusher.apn(connectionOptions, token, payload, function() {
                test2();
              });
          };

        var test2 = function() {
            pusher.apn(connectionOptions2, token2, payload, function() {
                apn._getConnectionCount().should.equal(2);
                apn._getFeedbackCount().should.equal(2);

                done();
              });
          };

        test1();
      });

    it('[apn] should clean up connections', function(done) {
        this.timeout(100);

        var connectionOptions = {
            packageId: 'pi',
            cert: 'cd',
            key: 'kd'
          };
        var token = 't';
        var connectionOptions2 = {
            packageId: 'pi2',
            cert: 'cd2',
            key: 'kd2'
          };
        var token2 = 't';
        var payload = {aps: {alert: 'foo'}};
        var push1 = null;
        var push2 = null;

        var test1 = function() {
            pusher.apn(connectionOptions, token, payload, function() {
                push1 = apn._getLatestPush();

                setTimeout(test2, 20);
              });
          };

        var test2 = function() {
            pusher.apn(connectionOptions2, token2, payload, function() {
                push2 = apn._getLatestPush();
                setTimeout(test3, 20);
              });
          };

        var test3 = function() {
            pusher.cleanUpApnConnections(30);

            push1.connection.terminated.should.be.true;
            var fbs1 = apn._getFeedbacks(push1.connection.options.packageId);
            fbs1.length.should.equal(1);
            var fb1 = fbs1[0];
            expect(fb1.interval).to.be.undefined;

            push2.connection.terminated.should.be.false;
            var fbs2 = apn._getFeedbacks(push2.connection.options.packageId);
            fbs2.length.should.equal(1);
            var fb2 = fbs2[0];
            expect(fb2.interval).to.not.be.undefined;

            done();
          };

        test1();
      });

    it('[apn] feedback should delete device', function(done) {
      var connectionOptions = {
            packageId: 'pi',
            cert: 'cd',
            key: 'kd'
          };
      var token = 't';
      var payload = {aps: {alert: 'foo'}};
      var push = null;
      var deviceId = 'di';
      var oauthClientId = 'oci';
      var hubTopic = 'ht';

      var init = function() {
        db.devices.save('ios', deviceId,
          oauthClientId, hubTopic, null,
          function() {
          step1();
        });
      };

      var step1 = function() {
        pusher.apn(connectionOptions, token, payload, function() {
            push = apn._getLatestPush();
            step2();
          });
      };

      var step2 = function() {
        var fbs = apn._getFeedbacks(push.connection.options.packageId);
        fbs.length.should.equal(1);
        var fb = fbs[0];

        fb.emit('feedback', [{device: deviceId}]);

        db.devices.findDevices(oauthClientId, hubTopic, function(devices) {
          devices.length.should.equal(0);
          done();
        });
      };

      init();
    });

  });
