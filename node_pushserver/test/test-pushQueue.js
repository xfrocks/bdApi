var config = require('../lib/config');
var pushQueue = require('../lib/pushQueue');
var chai = require('chai');

chai.should();

// setup push queue
config.gcm.defaultKeyId = 'key1';
config.gcm.keys = {
    key1: 'key1',
    key2: 'key2'
};
config.wns.client_id = 'wns_ci';
config.wns.client_secret = 'wns_cs';
var pushKue = require('./mock/pushKue');
var pusher = require('./mock/pusher');
var db = require('./mock/db');
pushQueue.start(pushKue, pusher, db.projects);

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
        pushKue._reset();
        pusher._reset();
        db.projects._reset();
        done();
    });

    it('should process android queue', function (done) {
        var deviceType = 'android';
        var deviceId = 'di';
        var payload = generatePayload();

        pushQueue.enqueue(deviceType, deviceId, payload);

        var latestPush = pusher._getLatestPush();
        latestPush.should.not.be.null;
        latestPush.type.should.equal('gcm');
        latestPush.registrationId.should.equal(deviceId);
        latestPush.data.notification_id.should.equal(payload.notification_id);
        latestPush.data.notification.should.not.be.null;

        done();
    });

    it('[android] default key', function (done) {
        var deviceType = 'android';
        var deviceId = 'di';
        var payload = generatePayload();

        pushQueue.enqueue(deviceType, deviceId, payload);

        var latestPush = pusher._getLatestPush();
        latestPush.type.should.equal('gcm');
        latestPush.gcmKey.should.equal(config.gcm.keys[config.gcm.defaultKeyId]);

        done();
    });

    it('[android] specific keys', function (done) {
        var deviceType = 'android';
        var deviceId = 'di';
        var payload = generatePayload();
        var extraData = {package: 'key1'};
        var extraData2 = {package: 'key2'};

        var test1 = function () {
            pushQueue.enqueue(deviceType, deviceId, payload, extraData);

            var latestPush = pusher._getLatestPush();
            latestPush.type.should.equal('gcm');
            latestPush.gcmKey.should.equal(config.gcm.keys[extraData.package]);

            test2();
        };

        var test2 = function () {
            pushQueue.enqueue(deviceType, deviceId, payload, extraData2);

            var latestPush = pusher._getLatestPush();
            latestPush.type.should.equal('gcm');
            latestPush.gcmKey.should.equal(config.gcm.keys[extraData2.package]);

            done();
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

            var latestPush = pusher._getLatestPush();
            latestPush.type.should.equal('gcm');
            latestPush.gcmKey.should.equal(apiKey);

            done();
        };

        init();
    });

    it('[android] no key', function (done) {
        var packageId = 'pi-db-no-client';
        var deviceType = 'android';
        var deviceId = 'di-no-client';
        var payload = generatePayload();
        var extraData = {package: packageId};

        pushQueue.enqueue(deviceType, deviceId, payload, extraData);

        var jobs = pushKue._getJobs(config.pushQueue.queueId);
        jobs.length.should.equal(1);

        var job = pushKue._getLatestJob(config.pushQueue.queueId);
        job.should.not.be.null;
        job.data.device_type.should.equal(deviceType);
        job.data.device_id.should.equal(deviceId);
        job.data.payload.should.deep.equal(payload);
        job.data.extra_data.should.deep.equal(extraData);
        job.attempts.should.equal(config.pushQueue.attempts);

        var pushes = pusher._getPushes();
        pushes.length.should.equal(0);

        done();
    });

    it('should process ios queue', function (done) {
        var deviceType = 'ios';
        var deviceId = 'di';
        var payload = generatePayload();

        pushQueue.enqueue(deviceType, deviceId, payload);

        var latestPush = pusher._getLatestPush();
        latestPush.should.not.be.null;
        latestPush.type.should.equal('apn');
        latestPush.token.should.equal(deviceId);
        latestPush.payload.aps.alert.should.not.be.null;

        done();
    });

    it('[ios] default client', function (done) {
        var deviceType = 'ios';
        var deviceId = 'di';
        var payload = generatePayload();

        pushQueue.enqueue(deviceType, deviceId, payload);

        var latestPush = pusher._getLatestPush();
        latestPush.type.should.equal('apn');
        latestPush.connectionOptions.should.equal(config.apn.connectionOptions);

        done();
    });

    it('[ios] db client', function (done) {
        var packageId = 'pi-db';
        var certData = 'cd-db';
        var keyData = 'kd-db';
        var deviceType = 'ios';
        var deviceId = 'di';
        var payload = generatePayload();
        var extraData = {package: packageId};

        var init = function () {
            db.projects.saveApn(packageId, certData, keyData, {}, function () {
                test();
            });
        };

        var test = function () {
            pushQueue.enqueue(deviceType, deviceId, payload, extraData);

            var latestPush = pusher._getLatestPush();
            latestPush.type.should.equal('apn');
            latestPush.connectionOptions.packageId.should.equal(packageId);
            latestPush.connectionOptions.cert_data.should.equal(certData);
            latestPush.connectionOptions.key_data.should.equal(keyData);

            done();
        };

        init();
    });

    it('[ios] no client', function (done) {
        var packageId = 'pi-db-no-client';
        var deviceType = 'ios';
        var deviceId = 'di-no-client';
        var payload = generatePayload();
        var extraData = {package: packageId};

        pushQueue.enqueue(deviceType, deviceId, payload, extraData);

        var jobs = pushKue._getJobs(config.pushQueue.queueId);
        jobs.length.should.equal(1);

        var job = pushKue._getLatestJob(config.pushQueue.queueId);
        job.should.not.be.null;
        job.data.device_type.should.equal(deviceType);
        job.data.device_id.should.equal(deviceId);
        job.data.payload.should.deep.equal(payload);
        job.data.extra_data.should.deep.equal(extraData);
        job.attempts.should.equal(config.pushQueue.attempts);

        var pushes = pusher._getPushes();
        pushes.length.should.equal(0);

        done();
    });

    it('should process windows queue', function (done) {
        var deviceType = 'windows';
        var deviceId = 'di';
        var payload = generatePayload();
        var channelUri = 'https://microsoft.com/wns/channel/uri';
        var extraData = {foo: 'bar', channel_uri: channelUri};

        pushQueue.enqueue(deviceType, deviceId, payload, extraData);

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
    });

    it('[windows] default client', function (done) {
        var deviceType = 'windows';
        var deviceId = 'di';
        var payload = generatePayload();
        var channelUri = 'https://microsoft.com/wns/channel/uri';
        var extraData = {channel_uri: channelUri};

        pushQueue.enqueue(deviceType, deviceId, payload, extraData);

        var latestPush = pusher._getLatestPush();
        latestPush.type.should.equal('wns');
        latestPush.clientId.should.equal(config.wns.client_id);
        latestPush.clientSecret.should.equal(config.wns.client_secret);

        done();
    });

    it('[windows] db client', function (done) {
        var packageId = 'pi-db';
        var clientId = 'ci-db';
        var clientSecret = 'cs-db';
        var deviceType = 'windows';
        var deviceId = 'di';
        var payload = generatePayload();
        var channelUri = 'https://microsoft.com/wns/channel/uri';
        var extraData = {channel_uri: channelUri, package: packageId};

        var init = function () {
            db.projects.saveWns(packageId, clientId, clientSecret, function () {
                test();
            });
        };

        var test = function () {
            pushQueue.enqueue(deviceType, deviceId, payload, extraData);

            var latestPush = pusher._getLatestPush();
            latestPush.type.should.equal('wns');
            latestPush.clientId.should.equal(clientId);
            latestPush.clientSecret.should.equal(clientSecret);

            done();
        };

        init();
    });

    it('[windows] no client', function (done) {
        var packageId = 'pi-db-no-client';
        var deviceType = 'windows';
        var deviceId = 'di-no-client';
        var payload = generatePayload();
        var channelUri = 'https://microsoft.com/wns/channel/uri';
        var extraData = {channel_uri: channelUri, package: packageId};

        pushQueue.enqueue(deviceType, deviceId, payload, extraData);

        var jobs = pushKue._getJobs(config.pushQueue.queueId);
        jobs.length.should.equal(1);

        var job = pushKue._getLatestJob(config.pushQueue.queueId);
        job.should.not.be.null;
        job.data.device_type.should.equal(deviceType);
        job.data.device_id.should.equal(deviceId);
        job.data.payload.should.deep.equal(payload);
        job.data.extra_data.should.deep.equal(extraData);
        job.attempts.should.equal(config.pushQueue.attempts);

        var pushes = pusher._getPushes();
        pushes.length.should.equal(0);

        done();
    });

    it('should retry on text error', function (done) {
        var deviceType = 'android';
        var deviceId = 'error';
        var payload = generatePayload();

        pushQueue.enqueue(deviceType, deviceId, payload);

        var pushes = pusher._getPushes();
        pushes.length.should.equal(config.pushQueue.attempts);

        done();
    });

    it('should retry on Error', function (done) {
        var deviceType = 'android';
        var deviceId = 'Error';
        var payload = generatePayload();

        pushQueue.enqueue(deviceType, deviceId, payload);

        var pushes = pusher._getPushes();
        pushes.length.should.equal(config.pushQueue.attempts);

        done();
    });
});