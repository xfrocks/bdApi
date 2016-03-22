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
            cert_data: 'cd',
            key_data: 'kd'
        };
        var token = 't';
        var payload = {foo: 'bar'};

        pusher.apn(connectionOptions, token, payload, function (err, result) {
            expect(err).to.be.undefined;

            var push = apn._getLatestPush();
            push.connection.options.should.deep.equal(connectionOptions);
            push.device.token.should.equal(token);
            push.notification.payload.should.deep.equal(payload);

            apn._getConnectionCount().should.equal(1);
            apn._getFeedbackCount().should.equal(1);

            done();
        });
    });

    it('[apn] should reuse connection', function (done) {
        var connectionOptions = {
            packageId: 'pi',
            cert_data: 'cd',
            key_data: 'kd'
        };
        var token = 't';
        var token2 = 't';
        var payload = {foo: 'bar'};

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
            cert_data: 'cd',
            key_data: 'kd'
        };
        var token = 't';
        var connectionOptions2 = {
            packageId: 'pi2',
            cert_data: 'cd2',
            key_data: 'kd2'
        };
        var token2 = 't';
        var payload = {foo: 'bar'};

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
            cert_data: 'cd',
            key_data: 'kd'
        };
        var token = 't';
        var connectionOptions2 = {
            packageId: 'pi2',
            cert_data: 'cd2',
            key_data: 'kd2'
        };
        var token2 = 't';
        var payload = {foo: 'bar'};
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
