/*jshint expr: true*/
'use strict';

var config = require('../lib/config');
var chai = require('chai');
var _ = require('lodash');

chai.should();
var env = _.merge({}, process.env);

describe('config', function() {

    beforeEach(function(done) {
        process.env = _.merge({}, env);
        done();
      });

    afterEach(function(done) {
        process.env = _.merge({}, env);
        config._reload();

        done();
      });

    it('should load default values', function(done) {
        config._reload();
        _.forEach(config._defaultConfig, function(value, key) {
            config[key].should.deep.equal(value);
          });
        done();
      });

    it('should handle MONGOLAB_URI', function(done) {
        process.env.MONGOLAB_URI = 'mongodb://localhost/mongolab';
        config._reload();
        config.db.mongoUri.should.equal(process.env.MONGOLAB_URI);

        done();
      });

    it('should handle PORT', function(done) {
        process.env.PORT = 123456;
        config._reload();
        config.web.port.should.equal(process.env.PORT);

        done();
      });

    it('should handle REDISCLOUD_URL', function(done) {
        var port = '123456';
        var host = 'rediscloud.server';
        var auth = 'rediscloud';
        process.env.REDISCLOUD_URL = 'redis://rediscloud:' +
            auth + '@' + host + ':' + port;
        config._reload();

        config.redis.port.should.equal(port);
        config.redis.host.should.equal(host);
        config.redis.auth.should.equal(auth);

        done();
      });

    it('should handle REDISTOGO_URL', function(done) {
        var port = '123457';
        var host = 'redistogo.server';
        var auth = 'redistogo';
        process.env.REDISTOGO_URL = 'redis://redistogo:' +
            auth + '@' + host + ':' + port;
        config._reload();

        config.redis.port.should.equal(port);
        config.redis.host.should.equal(host);
        config.redis.auth.should.equal(auth);

        done();
      });

    it('should handle CONFIG_WEB_CALLBACK', function(done) {
        process.env.CONFIG_WEB_CALLBACK = 'http://push.server/callback';
        config._reload();
        config.web.callback.should.equal(process.env.CONFIG_WEB_CALLBACK);

        done();
      });

    it('should handle CONFIG_WEB_USERNAME/PASSWORD', function(done) {
        process.env.CONFIG_WEB_USERNAME = 'username';
        process.env.CONFIG_WEB_PASSWORD = 'password';
        config._reload();

        config.web.username.should.equal(process.env.CONFIG_WEB_USERNAME);
        config.web.password.should.equal(process.env.CONFIG_WEB_PASSWORD);
        config.db.web.should.be.true;
        config.pushQueue.web.should.be.true;

        done();
      });

    it('should handle CONFIG_WEB_USERNAME no PASSWORD', function(done) {
        process.env.CONFIG_WEB_USERNAME = 'username';
        process.env.CONFIG_WEB_PASSWORD = '';
        config._reload();

        config.web.username.should.equal('');
        config.web.password.should.equal('');
        config.db.web.should.be.false;
        config.pushQueue.web.should.be.false;

        done();
      });

    it('should handle CONFIG_WEB_PASSWORD no USERNAME', function(done) {
        process.env.CONFIG_WEB_USERNAME = '';
        process.env.CONFIG_WEB_PASSWORD = 'password';
        config._reload();

        config.web.username.should.equal('');
        config.web.password.should.equal('');
        config.db.web.should.be.false;
        config.pushQueue.web.should.be.false;

        done();
      });

    it('should handle CONFIG_PUSH_QUEUE_ID', function(done) {
        process.env.CONFIG_PUSH_QUEUE_ID = 'cpqi';
        config._reload();
        config.pushQueue.queueId.
            should.equal(process.env.CONFIG_PUSH_QUEUE_ID);

        done();
      });

    it('should handle CONFIG_APN_CERT/KEY', function(done) {
        process.env.CONFIG_APN_CERT = 'cac';
        process.env.CONFIG_APN_KEY = 'cak';
        config._reload();

        config.apn.connectionOptions.cert.
            should.equal(process.env.CONFIG_APN_CERT);
        config.apn.connectionOptions.key.
            should.equal(process.env.CONFIG_APN_KEY);

        done();
      });

    it('should handle CONFIG_APN_CERT no KEY', function(done) {
        process.env.CONFIG_APN_CERT = 'cac';
        process.env.CONFIG_APN_KEY = '';
        config._reload();

        config.apn.connectionOptions.cert.should.equal('');
        config.apn.connectionOptions.key.should.equal('');

        done();
      });

    it('should handle CONFIG_APN_KEY no CERT', function(done) {
        process.env.CONFIG_APN_CERT = '';
        process.env.CONFIG_APN_KEY = 'cak';
        config._reload();

        config.apn.connectionOptions.cert.should.equal('');
        config.apn.connectionOptions.key.should.equal('');

        done();
      });

    it('should handle CONFIG_APN_GATEWAY', function(done) {
        process.env.CONFIG_APN_GATEWAY = 'cag';
        config._reload();
        config.apn.connectionOptions.gateway.
            should.equal(process.env.CONFIG_APN_GATEWAY);

        done();
      });

    it('should handle CONFIG_GCM_KEY', function(done) {
        process.env.CONFIG_GCM_KEY = 'cgk';
        config._reload();
        config.gcm.keys[config.gcm.defaultKeyId].
            should.equal(process.env.CONFIG_GCM_KEY);

        done();
      });

    it('should handle CONFIG_GCM_KEYS', function(done) {
        var i;

        process.env.CONFIG_GCM_KEYS = 10;
        for (i = 0; i < process.env.CONFIG_GCM_KEYS; i += 1) {
          process.env['CONFIG_GCM_KEYS_' + i] = 'app_' + i + ',key_' + i;
        }
        config._reload();

        config.gcm.defaultKeyId.should.equal('app_0');
        _.keys(config.gcm.keys).length.
            should.equal(process.env.CONFIG_GCM_KEYS);
        for (i = 0; i < process.env.CONFIG_GCM_KEYS; i += 1) {
          config.gcm.keys['app_' + i].should.equal('key_' + i);
        }

        done();
      });

    it('should handle incomplete CONFIG_GCM_KEYS', function(done) {
        var appId = 'appId';
        var appKey = 'appKey';

        process.env.CONFIG_GCM_KEYS = 3;
        // no process.env.CONFIG_GCM_KEYS_0
        process.env.CONFIG_GCM_KEYS_1 = 'malformed';
        process.env.CONFIG_GCM_KEYS_2 = appId + ',' + appKey;
        config._reload();

        config.gcm.defaultKeyId.should.equal(appId);
        _.keys(config.gcm.keys).length.should.equal(1);
        config.gcm.keys[appId].should.equal(appKey);

        done();
      });

    it('should handle CONFIG_GCM_KEY & CONFIG_GCM_KEYS', function(done) {
        var i;

        process.env.CONFIG_GCM_KEY = 'cgk';
        process.env.CONFIG_GCM_KEYS = 10;
        for (i = 0; i < process.env.CONFIG_GCM_KEYS; i += 1) {
          process.env['CONFIG_GCM_KEYS_' + i] = 'app_' + i + ',key_' + i;
        }
        config._reload();

        config.gcm.keys[config.gcm.defaultKeyId].
            should.equal(process.env.CONFIG_GCM_KEY);
        _.keys(config.gcm.keys).length.
            should.equal(process.env.CONFIG_GCM_KEYS + 1);
        for (i = 0; i < process.env.CONFIG_GCM_KEYS; i += 1) {
          config.gcm.keys['app_' + i].should.equal('key_' + i);
        }

        done();
      });

    it('should handle CONFIG_WNS_CLIENT_ID/SECRET', function(done) {
        process.env.CONFIG_WNS_CLIENT_ID = 'cwci';
        process.env.CONFIG_WNS_CLIENT_SECRET = 'cwcs';
        config._reload();

        config.wns.client_id.should.equal(process.env.CONFIG_WNS_CLIENT_ID);
        config.wns.client_secret.
            should.equal(process.env.CONFIG_WNS_CLIENT_SECRET);

        done();
      });

    it('should handle CONFIG_WNS_CLIENT_ID no SECRET', function(done) {
        process.env.CONFIG_WNS_CLIENT_ID = 'cwci';
        process.env.CONFIG_WNS_CLIENT_SECRET = '';
        config._reload();

        config.wns.client_id.should.equal('');
        config.wns.client_secret.should.equal('');

        done();
      });

    it('should handle CONFIG_WNS_CLIENT_SECRET no ID', function(done) {
        process.env.CONFIG_WNS_CLIENT_ID = '';
        process.env.CONFIG_WNS_CLIENT_SECRET = 'cwcs';
        config._reload();

        config.wns.client_id.should.equal('');
        config.wns.client_secret.should.equal('');

        done();
      });
  });
