var config = require('../lib/config');
var pusher = require('../lib/pusher');
var chai = require('chai');

chai.should();
var expect = chai.expect;

var apn = require('./mock/modules/apn');
pusher.setup(apn);

describe('pusher', function () {

    beforeEach(function (done) {
        apn._reset();
        pusher._resetApnConnections();
        done();
    });

    it('should push apn', function (done) {
        var connectionOptions = {
            packageId: 'pi',
            cert: 'cd',
            key: 'kd'
        };
        var token = 't';
        var payload = {aps: {alert: 'foo'}};

        pusher.apn(connectionOptions, token, payload, function (err, result) {
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

    it('should not push apn without payload.aps.alert', function (done) {
        var connectionOptions = {
            packageId: 'pi',
            cert: 'cd',
            key: 'kd'
        };
        var token = 't';
        var payload = {};

        pusher.apn(connectionOptions, token, payload, function (err, result) {
            err.should.be.string;
            done();
        });
    });

    it('[apn] should configure notification correctly', function(done) {
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

        pusher.apn(connectionOptions, token, payload, function (err, result) {
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

    it('[apn] should reuse connection', function (done) {
        var connectionOptions = {
            packageId: 'pi',
            cert: 'cd',
            key: 'kd'
        };
        var token = 't';
        var token2 = 't';
        var payload = {aps: {alert: 'foo'}};

        var test1 = function () {
            pusher.apn(connectionOptions, token, payload, function () {
                test2();
            });
        };

        var test2 = function () {
            pusher.apn(connectionOptions, token2, payload, function () {
                apn._getConnectionCount().should.equal(1);
                apn._getFeedbackCount().should.equal(1);

                done();
            });
        };

        test1();
    });

    it('[apn] should create connections', function (done) {
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

        var test1 = function () {
            pusher.apn(connectionOptions, token, payload, function () {
                test2();
            });
        };

        var test2 = function () {
            pusher.apn(connectionOptions2, token2, payload, function () {
                apn._getConnectionCount().should.equal(2);
                apn._getFeedbackCount().should.equal(2);

                done();
            });
        };

        test1();
    });

    it('[apn] should clean up connections', function (done) {
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

        var test1 = function () {
            pusher.apn(connectionOptions, token, payload, function () {
                push1 = apn._getLatestPush();

                setTimeout(test2, 20);
            });
        };

        var test2 = function () {
            pusher.apn(connectionOptions2, token2, payload, function () {
                push2 = apn._getLatestPush();
                setTimeout(test3, 20);
            });
        };

        var test3 = function () {
            pusher.cleanUpApnConnections(30);

            push1.connection.terminated.should.be.true;
            var feedbacks1 = apn._getFeedbacks(push1.connection.options.packageId);
            feedbacks1.length.should.equal(1);
            var feedback1 = feedbacks1[0];
            expect(feedback1.interval).to.be.undefined;

            push2.connection.terminated.should.be.false;
            var feedbacks2 = apn._getFeedbacks(push2.connection.options.packageId);
            feedbacks2.length.should.equal(1);
            var feedback2 = feedbacks2[0];
            expect(feedback2.interval).to.not.be.undefined;

            done();
        };

        test1();
    });

});
