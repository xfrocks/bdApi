var config = require('../lib/config');
var pushQueue = require('../lib/pushQueue');
var chai = require('chai');

chai.should();

// setup push queue
config.pushQueue.ttlInMs = 500;
config.gcm.defaultKeyId = 'key1';
config.gcm.keys = {
    key1: 'key1',
    key2: 'key2'
};
config.wns.client_id = 'wns_ci';
config.wns.client_secret = 'wns_cs';
var pusher = require('./mock/pusher');
pushQueue.setPusher(pusher);
var db = require('./mock/db');
pushQueue.setProjectDb(db.projects);

var notificationId = 0;
var generatePayload = function () {
    notificationId++;

    return {
        action: 'action',
        notification_id: notificationId,
        notification_html: 'Notification #' + notificationId
    };
};

describe('pushQueue', function () {

    beforeEach(function (done) {
        pusher._reset();
        done();
    });

    it('should process android queue', function (done) {
        var deviceType = 'android';
        var deviceId = 'di';
        var payload = generatePayload();

        pushQueue.enqueue(deviceType, deviceId, payload);

        setTimeout(function () {
            var latestPush = pusher._getLatestPush();
            latestPush.should.not.be.null;
            latestPush.type.should.equal('gcm');
            latestPush.registrationId.should.equal(deviceId);
            latestPush.data.notification_id.should.equal(payload.notification_id);
            latestPush.data.notification.should.not.be.null;

            done();
        }, 1000);
    });

    it('[android] default key', function (done) {
        var deviceType = 'android';
        var deviceId = 'di';
        var payload = generatePayload();

        pushQueue.enqueue(deviceType, deviceId, payload);

        setTimeout(function () {
            var latestPush = pusher._getLatestPush();
            latestPush.type.should.equal('gcm');
            latestPush.gcmKey.should.equal(config.gcm.keys[config.gcm.defaultKeyId]);

            done();
        }, 1000);
    });

    it('[android] specific keys', function (done) {
        this.timeout(3000);

        var deviceType = 'android';
        var deviceId = 'di';
        var payload = generatePayload();
        var extraData = {package: 'key1'};
        var extraData2 = {package: 'key2'};

        var test1 = function () {
            pushQueue.enqueue(deviceType, deviceId, payload, extraData);
            setTimeout(function () {
                var latestPush = pusher._getLatestPush();
                latestPush.type.should.equal('gcm');
                latestPush.gcmKey.should.equal(config.gcm.keys[extraData.package]);

                test2();
            }, 1000);
        };

        var test2 = function () {
            pushQueue.enqueue(deviceType, deviceId, payload, extraData2);
            setTimeout(function () {
                var latestPush = pusher._getLatestPush();
                latestPush.type.should.equal('gcm');
                latestPush.gcmKey.should.equal(config.gcm.keys[extraData2.package]);

                done();
            }, 1000);
        };

        test1();
    });

    it('[android] db key', function (done) {
        var packageId = 'pi-db';
        var apiKey = 'ak-db';
        var deviceType = 'android';
        var deviceId = 'di-db';
        var payload = generatePayload();
        var extraData = {package: packageId};

        var init = function () {
            db.projects.saveGcm(packageId, apiKey, function () {
                test();
            });
        };

        var test = function () {
            pushQueue.enqueue(deviceType, deviceId, payload, extraData);

            setTimeout(function () {
                var latestPush = pusher._getLatestPush();
                latestPush.type.should.equal('gcm');
                latestPush.gcmKey.should.equal(apiKey);

                done();
            }, 1000);
        };

        init();
    });

    it.only('[android] no key', function (done) {
        this.timeout(6000);

        var packageId = 'pi-db-no-client';
        var deviceType = 'android';
        var deviceId = 'di-no-client';
        var payload = generatePayload();
        var extraData = {package: packageId};

        pushQueue.enqueue(deviceType, deviceId, payload, extraData);

        setTimeout(function () {
            var pushes = pusher._getPushes();
            pushes.length.should.equal(0);

            done();
        }, 5000);
    });

    it('should process ios queue', function (done) {
        var deviceType = 'ios';
        var deviceId = 'di';
        var payload = generatePayload();

        pushQueue.enqueue(deviceType, deviceId, payload);

        setTimeout(function () {
            var latestPush = pusher._getLatestPush();
            latestPush.should.not.be.null;
            latestPush.type.should.equal('apn');
            latestPush.token.should.equal(deviceId);
            latestPush.payload.aps.alert.should.not.be.null;

            done();
        }, 1000);
    });

    it('should process windows queue', function (done) {
        var deviceType = 'windows';
        var deviceId = 'di';
        var payload = generatePayload();
        var channelUri = 'https://microsoft.com/wns/channel/uri';
        var extraData = {foo: 'bar', channel_uri: channelUri};

        pushQueue.enqueue(deviceType, deviceId, payload, extraData);

        setTimeout(function () {
            var latestPush = pusher._getLatestPush();
            latestPush.should.not.be.null;
            latestPush.type.should.equal('wns');
            latestPush.channelUri.should.equal(channelUri);

            var data = JSON.parse(latestPush.dataRaw);
            data.should.be.a('object');
            data.action.should.equal(payload.action);
            data.notification_id.should.equal(payload.notification_id);
            data.notification_html.should.equal(payload.notification_html);
            data.extra_data.foo.should.equal(extraData.foo);

            done();
        }, 1000);
    });

    it('[windows] default key', function (done) {
        var deviceType = 'windows';
        var deviceId = 'di';
        var payload = generatePayload();
        var channelUri = 'https://microsoft.com/wns/channel/uri';
        var extraData = {channel_uri: channelUri};

        pushQueue.enqueue(deviceType, deviceId, payload, extraData);

        setTimeout(function () {
            var latestPush = pusher._getLatestPush();
            latestPush.type.should.equal('wns');
            latestPush.clientId.should.equal(config.wns.client_id);
            latestPush.clientSecret.should.equal(config.wns.client_secret);

            done();
        }, 1000);
    });

    it('should retry on text error', function (done) {
        this.timeout(3000);

        var deviceType = 'android';
        var deviceId = 'error';
        var payload = generatePayload();

        pushQueue.enqueue(deviceType, deviceId, payload);

        setTimeout(function () {
            var pushes = pusher._getPushes();
            pushes.length.should.equal(3);

            done();
        }, 2000);
    });

    it('should retry on Error', function (done) {
        this.timeout(3000);

        var deviceType = 'android';
        var deviceId = 'Error';
        var payload = generatePayload();

        pushQueue.enqueue(deviceType, deviceId, payload);

        setTimeout(function () {
            var pushes = pusher._getPushes();
            pushes.length.should.equal(3);

            done();
        }, 2000);
    });
});