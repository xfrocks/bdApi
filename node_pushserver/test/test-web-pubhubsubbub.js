/*jshint expr: true*/
'use strict';

var config = require('../lib/config');
var web = require('../lib/web');
var pubhubsubbub = require('../lib/web/pubhubsubbub');
var chai = require('chai');
var express = require('express');
var bodyParser = require('body-parser');
var http = require('http');
var _ = require('lodash');

chai.should();
chai.use(require('chai-http'));
var expect = chai.expect;

var db = require('./mock/db');
var pushQueue = require('./mock/pushQueue');
var webApp = chai.request(web._app);

var testApp = express();
testApp.use(bodyParser.urlencoded({extended: false}));
testApp.post('/status/:code', function(req, res) {
    res.status(req.params.code).end();
  });
var testServer = http.createServer(testApp).listen();
var testAppPort = testServer.address().port;
var testAppUri = 'http://localhost:' + testAppPort;

describe('web/pubhubsubbub', function() {

    before(function(done) {
        pubhubsubbub.setup(web._app, '', db.devices, pushQueue);
        done();
      });

    beforeEach(function(done) {
        db.devices._reset();
        pushQueue._reset();
        done();
      });

    it('should say hi', function(done) {
        webApp
            .get('/')
            .end(function(err, res) {
                res.should.have.status(200);
                res.text.should.have.string('Hi, I am');

                done();
              });
      });

    it('should say hi with config.web.callback', function(done) {
        var callback = 'http://push.server/callback';
        config.web.callback = callback;

        webApp
            .get('/')
            .end(function(err, res) {
                res.should.have.status(200);
                res.text.should.have.string('Hi, I am ' + callback);
                config._reload();

                done();
              });
      });

    it('should subscribe', function(done) {
        var hubTopic = 'ht';
        var oauthClientId = 'oci';
        var oauthToken = 'ot';
        var deviceType = 'dt';
        var deviceId = 'di';
        var extraData = {foo: 'bar'};

        var step1 = function() {
            webApp
                .post('/subscribe')
                .send({
                    hub_uri: testAppUri + '/status/202',
                    hub_topic: hubTopic,
                    oauth_client_id: oauthClientId,
                    oauth_token: oauthToken,
                    device_type: deviceType,
                    device_id: deviceId,
                    extra_data: extraData
                  })
                .end(function(err, res) {
                    res.should.have.status(202);
                    res.text.should.equal('succeeded');
                    step2();
                  });
          };

        var step2 = function() {
            db.devices.findDevices(oauthClientId, hubTopic, function(devices) {
                devices.length.should.equal(1);

                var device = devices[0];
                device.device_type.should.equal(deviceType);
                device.device_id.should.equal(deviceId);
                device.extra_data.foo.should.equal(extraData.foo);
              });

            done();
          };

        step1();
      });

    it('should not subscribe with missing data', function(done) {
        var seed = [
            {},
            {oauth_client_id: 'oci'},
            {oauth_token: 'ot'},
            {device_type: 'dt'},
            {device_id: 'di'}
        ];

        var data = [];
        _.forEach(seed, function(dataPiece) {
            var prevData = {};
            if (data.length > 0) {
              prevData = _.last(data);
            }
            data.push(_.assign({}, prevData, dataPiece));
          });

        var test = function() {
            var testData = data.shift();

            webApp
                .post('/subscribe')
                .send(testData)
                .end(function(err, res) {
                    res.should.have.status(400);

                    if (data.length > 0) {
                      test();
                    } else {
                      done();
                    }
                  });
          };

        test();
      });

    it('should not subscribe with db error', function(done) {
        webApp
            .post('/subscribe')
            .send({
                hub_uri: testAppUri + '/status/202',
                hub_topic: 'ht',
                oauth_client_id: 'oci',
                oauth_token: 'ot',
                device_type: 'dt',
                device_id: 'error'
              })
            .end(function(err, res) {
                res.should.have.status(500);
                done();
              });
      });

    it('should not subscribe with hub error', function(done) {
        webApp
            .post('/subscribe')
            .send({
                hub_uri: testAppUri + '/status/403',
                oauth_client_id: 'oci',
                oauth_token: 'ot',
                device_type: 'dt',
                device_id: 'di'
              })
            .end(function(err, res) {
                res.should.have.status(403);
                res.text.should.equal('failed');
                done();
              });
      });

    it('should not subscribe with invalid hub', function(done) {
        webApp
            .post('/subscribe')
            .send({
                hub_uri: 'http://a.b.c/hub',
                oauth_client_id: 'oci',
                oauth_token: 'ot',
                device_type: 'dt',
                device_id: 'di'
              })
            .end(function(err, res) {
                res.should.have.status(503);
                done();
              });
      });

    it('should unsubscribe', function(done) {
        var hubTopic = 'ht';
        var oauthClientId = 'oci';
        var deviceType = 'dt';
        var deviceId = 'di';

        var init = function() {
            db.devices.save(deviceType, deviceId,
              oauthClientId, hubTopic, null,
              function(isSaved) {
                isSaved.should.equal('saved');
                step1();
              });
          };

        var step1 = function() {
            webApp
                .post('/unsubscribe')
                .send({
                    hub_uri: testAppUri + '/status/202',
                    hub_topic: hubTopic,
                    oauth_client_id: oauthClientId,
                    device_type: deviceType,
                    device_id: deviceId
                  })
                .end(function(err, res) {
                    res.should.have.status(202);
                    res.text.should.equal('succeeded');
                    step2();
                  });
          };

        var step2 = function() {
            db.devices.findDevices(oauthClientId, null, function(devices) {
                devices.length.should.equal(1);

                var device = devices[0];
                device.device_type.should.equal(deviceType);
                device.device_id.should.equal(deviceId);
                device.hub_topic.length.should.equal(0);
              });

            done();
          };

        init();
      });

    it('should not unsubscribe with missing data', function(done) {
        var seed = [
            {},
            {hub_topic: 'ht'},
            {oauth_client_id: 'oci'},
            {device_type: 'dt'},
            {device_id: 'di'}
        ];

        var data = [];
        _.forEach(seed, function(dataPiece) {
            var prevData = {};
            if (data.length > 0) {
              prevData = _.last(data);
            }
            data.push(_.assign({}, prevData, dataPiece));
          });

        var test = function() {
            var testData = data.shift();

            webApp
                .post('/unsubscribe')
                .send(testData)
                .end(function(err, res) {
                    res.should.have.status(400);

                    if (data.length > 0) {
                      test();
                    } else {
                      done();
                    }
                  });
          };

        test();
      });

    it('should not unsubscribe with unknown device', function(done) {
        webApp
            .post('/unsubscribe')
            .send({
                hub_uri: testAppUri + '/status/202',
                hub_topic: 'ht',
                oauth_client_id: 'oci',
                device_type: 'dt',
                device_id: 'di'
              })
            .end(function(err, res) {
                res.should.have.status(500);
                done();
              });
      });

    it('should not unsubscribe with hub error', function(done) {
        var hubTopic = 'ht';
        var oauthClientId = 'oci';
        var deviceType = 'dt';
        var deviceId = 'di';

        var init = function() {
            db.devices.save(deviceType, deviceId,
              oauthClientId, hubTopic, null,
              function(isSaved) {
                isSaved.should.equal('saved');
                step1();
              });
          };

        var step1 = function() {
            webApp
                .post('/unsubscribe')
                .send({
                    hub_uri: testAppUri + '/status/403',
                    hub_topic: hubTopic,
                    oauth_client_id: oauthClientId,
                    device_type: deviceType,
                    device_id: deviceId
                  })
                .end(function(err, res) {
                    res.should.have.status(403);
                    res.text.should.equal('failed');
                    done();
                  });
          };

        init();
      });

    it('should not unsubscribe with invalid hub', function(done) {
        var hubTopic = 'ht';
        var oauthClientId = 'oci';
        var deviceType = 'dt';
        var deviceId = 'di';

        var init = function() {
            db.devices.save(deviceType, deviceId,
                oauthClientId, hubTopic, null,
                function(isSaved) {
                    isSaved.should.equal('saved');
                    step1();
                  });
          };

        var step1 = function() {
            webApp
                .post('/unsubscribe')
                .send({
                    hub_uri: 'http://a.b.c/hub',
                    hub_topic: hubTopic,
                    oauth_client_id: oauthClientId,
                    device_type: deviceType,
                    device_id: deviceId
                  })
                .end(function(err, res) {
                    res.should.have.status(503);
                    done();
                  });
          };

        init();
      });

    it('should unregister', function(done) {
        var oauthClientId = 'oci';
        var deviceType = 'dt';
        var deviceId = 'di';

        var init = function() {
            db.devices.save(deviceType, deviceId, oauthClientId,
              null, null, function(isSaved) {
                isSaved.should.equal('saved');
                step1();
              });
          };

        var step1 = function() {
            webApp
                .post('/unregister')
                .send({
                    oauth_client_id: oauthClientId,
                    device_type: deviceType,
                    device_id: deviceId
                  })
                .end(function(err, res) {
                    res.should.have.status(200);
                    res.text.should.equal('succeeded');
                    step2();
                  });
          };

        var step2 = function() {
            db.devices.findDevices(oauthClientId, null, function(devices) {
                devices.length.should.equal(0);
              });

            done();
          };

        init();
      });

    it('should not unregister with missing data', function(done) {
        var seed = [
            {},
            {device_type: 'dt'},
            {device_id: 'di'}
        ];

        var data = [];
        _.forEach(seed, function(dataPiece) {
            var prevData = {};
            if (data.length > 0) {
              prevData = _.last(data);
            }
            data.push(_.assign({}, prevData, dataPiece));
          });

        var test = function() {
            var testData = data.shift();

            webApp
                .post('/unregister')
                .send(testData)
                .end(function(err, res) {
                    res.should.have.status(400);

                    if (data.length > 0) {
                      test();
                    } else {
                      done();
                    }
                  });
          };

        test();
      });

    it('should not unregister with db error', function(done) {
            webApp
                .post('/unregister')
                .send({
                    oauth_client_id: 'oci',
                    device_type: 'dt',
                    device_id: 'error'
                  })
                .end(function(err, res) {
                    res.should.have.status(500);
                    done();
                  });
          });

    it('should answer subscribe challenge', function(done) {
        var hubTopic = 'ht';
        var oauthClientId = 'oci';
        var deviceType = 'dt';
        var deviceId = 'di';
        var challenge = '' + Math.random();

        var init = function() {
            db.devices.save(deviceType, deviceId,
              oauthClientId, hubTopic, null,
              function(isSaved) {
                isSaved.should.equal('saved');
                test();
              });
          };

        var test = function() {
            webApp
                .get('/callback')
                .query({
                    client_id: oauthClientId,
                    'hub.challenge': challenge,
                    'hub.mode': 'subscribe',
                    'hub.topic': hubTopic
                  })
                .send()
                .end(function(err, res) {
                    res.should.have.status(200);
                    res.text.should.equal(challenge);

                    done();
                  });
          };

        init();
      });

    it('should answer unsubscribe challenge', function(done) {
        var challenge = '' + Math.random();

        webApp
            .get('/callback')
            .query({
                client_id: 'oci-unsubscribe',
                'hub.challenge': challenge,
                'hub.mode': 'unsubscribe',
                'hub.topic': 'ht-unsubscribe'
              })
            .send()
            .end(function(err, res) {
                res.should.have.status(200);
                res.text.should.equal(challenge);

                done();
              });
      });

    it('should not answer challenge with missing data', function(done) {
        var seed = [
            {},
            {'hub.topic': 'ht'},
            {client_id: 'ci'},
            {'hub.challenge': '' + Math.random()},
            {'hub.mode': 'subscribe'}
        ];

        var data = [];
        _.forEach(seed, function(dataPiece) {
            var prevData = {};
            if (data.length > 0) {
              prevData = _.last(data);
            }
            data.push(_.assign({}, prevData, dataPiece));
          });

        var test = function() {
            var testData = data.shift();

            webApp
                .get('/callback')
                .query(testData)
                .send()
                .end(function(err, res) {
                    res.status.should.be.within(400, 499);

                    if (data.length > 0) {
                      test();
                    } else {
                      done();
                    }
                  });
          };

        test();
      });

    it('should not answer subscribe challenge without device', function(done) {
        var challenge = '' + Math.random();

        webApp
            .get('/callback')
            .query({
                client_id: 'oci-unknown-device',
                'hub.challenge': challenge,
                'hub.mode': 'subscribe',
                'hub.topic': 'ht-unknown-device'
              })
            .send()
            .end(function(err, res) {
                res.should.have.status(405);
                done();
              });
      });

    it('should enqueue push', function(done) {
        var hubTopic = 'ht';
        var oauthClientId = 'oci';
        var deviceType = 'dt';
        var deviceId = 'di';
        var extraData = {foo: 'bar'};
        var payload = 'p';

        var init = function() {
            db.devices.save(deviceType, deviceId,
              oauthClientId, hubTopic, extraData,
              function(isSaved) {
                isSaved.should.equal('saved');
                test();
              });
          };

        var test = function() {
            webApp
                .post('/callback')
                .send([
                    {
                        client_id: oauthClientId,
                        topic: hubTopic,
                        object_data: payload
                      }
                ])
                .end(function(err, res) {
                    res.should.have.status(202);

                    var job = pushQueue._getLatestJob();
                    job.should.not.be.null;
                    job.device_type.should.equal(deviceType);
                    job.device_id.should.equal(deviceId);
                    job.extra_data.foo.should.equal(extraData.foo);
                    job.payload.should.equal(payload);

                    done();
                  });
          };

        init();
      });

    it('should enqueue pushes for all devices', function(done) {
        var hubTopic = 'ht';
        var oauthClientId = 'oci';
        var deviceType = 'dt';
        var deviceId = 'di';
        var deviceId2 = 'di2';

        var init = function() {
            db.devices.save(deviceType, deviceId,
                oauthClientId, hubTopic, null,
                function(isSaved) {
                isSaved.should.equal('saved');

                db.devices.save(deviceType, deviceId2,
                    oauthClientId, hubTopic, null,
                    function(isSaved) {
                    isSaved.should.equal('saved');
                    test();
                  });
              });
          };

        var test = function() {
            webApp
                .post('/callback')
                .send([
                    {
                        client_id: oauthClientId,
                        topic: hubTopic,
                        object_data: 'od'
                      }
                ])
                .end(function() {
                    var jobs = pushQueue._getJobs();
                    jobs.length.should.equal(2);
                    jobs[0].device_type.should.equal(deviceType);
                    jobs[0].device_id.should.equal(deviceId);
                    jobs[1].device_type.should.equal(deviceType);
                    jobs[1].device_id.should.equal(deviceId2);

                    done();
                  });
          };

        init();
      });

    it('should enqueue pushes for all pings', function(done) {
        var hubTopic = 'ht';
        var oauthClientId = 'oci';
        var deviceType = 'dt';
        var deviceId = 'di';
        var payload = 'p';
        var payload2 = 'p2';

        var init = function() {
            db.devices.save(deviceType, deviceId,
              oauthClientId, hubTopic, null,
              function(isSaved) {
                isSaved.should.equal('saved');
                test();
              });
          };

        var test = function() {
            webApp
                .post('/callback')
                .send([
                    {
                        client_id: oauthClientId,
                        topic: hubTopic,
                        object_data: payload
                      },
                    {
                        client_id: oauthClientId,
                        topic: hubTopic,
                        object_data: payload2
                      }
                ])
                .end(function() {
                    var jobs = pushQueue._getJobs();
                    jobs.length.should.equal(2);
                    jobs[0].payload.should.equal(payload);
                    jobs[1].payload.should.equal(payload2);

                    done();
                  });
          };

        init();
      });

    it('should not enqueue invalid data', function(done) {
        var hubTopic = 'ht';
        var oauthClientId = 'oci';
        var payload = 'p';

        var test1 = function() {
            webApp
                .post('/callback')
                .send('text')
                .end(function(err, res) {
                    res.should.have.status(200);
                    var job = pushQueue._getLatestJob();
                    expect(job).to.be.null;

                    test2();
                  });
          };

        var test2 = function() {
            webApp
                .post('/callback')
                .send([
                    {
                        topic: hubTopic,
                        object_data: payload
                      }
                ])
                .end(function(err, res) {
                    res.should.have.status(200);
                    var job = pushQueue._getLatestJob();
                    expect(job).to.be.null;

                    test3();
                  });
          };

        var test3 = function() {
            webApp
                .post('/callback')
                .send([
                    {
                        client_id: oauthClientId,
                        object_data: payload
                      }
                ])
                .end(function(err, res) {
                    res.should.have.status(200);
                    var job = pushQueue._getLatestJob();
                    expect(job).to.be.null;

                    test4();
                  });
          };

        var test4 = function() {
            webApp
                .post('/callback')
                .send([
                    {
                        client_id: oauthClientId,
                        topic: hubTopic
                      }
                ])
                .end(function(err, res) {
                    res.should.have.status(200);
                    var job = pushQueue._getLatestJob();
                    expect(job).to.be.null;

                    done();
                  });
          };

        test1();
      });

    it('should not register some routes without db', function(done) {
        var pubhubsubbubPrefix = '/no-device-db';
        pubhubsubbub.setup(web._app, pubhubsubbubPrefix);

        var test1 = function() {
            webApp
                .post(pubhubsubbubPrefix + '/subscribe')
                .end(function(err, res) {
                    res.should.have.status(404);
                    test2();
                  });
          };

        var test2 = function() {
            webApp
                .post(pubhubsubbubPrefix + '/unsubscribe')
                .end(function(err, res) {
                    res.should.have.status(404);
                    test3();
                  });
          };

        var test3 = function() {
            webApp
                .post(pubhubsubbubPrefix + '/unregister')
                .end(function(err, res) {
                    res.should.have.status(404);
                    test4();
                  });
          };

        var test4 = function() {
            webApp
                .get(pubhubsubbubPrefix + '/callback')
                .end(function(err, res) {
                    res.should.have.status(404);
                    test5();
                  });
          };

        var test5 = function() {
            webApp
                .post(pubhubsubbubPrefix + '/callback')
                .end(function(err, res) {
                    res.should.have.status(404);
                    done();
                  });
          };

        test1();
      });

    it('should not register /callback without queue', function(done) {
        var pubhubsubbubPrefix = '/no-device-db';
        pubhubsubbub.setup(web._app, pubhubsubbubPrefix, db.devices);

        webApp
            .post(pubhubsubbubPrefix + '/callback')
            .end(function(err, res) {
                res.should.have.status(404);
                done();
              });
      });

  });
