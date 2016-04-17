/*jshint expr: true*/
'use strict';

var web = require('../lib/web');
var chai = require('chai');

chai.should();
chai.use(require('chai-http'));

var db = require('./mock/db');
var admin = require('../lib/web/admin');
var webApp = chai.request(web._app);

describe('web/admin', function() {

    before(function(done) {
        admin.setup(web._app, '/admin', null, null, db.projects);
        done();
      });

    beforeEach(function(done) {
        db.projects._reset();
        done();
      });

    it('should return sections', function(done) {
        webApp
            .get('/admin')
            .end(function(err, res) {
                res.should.have.status(200);
                res.body.should.have.all.keys('projects');

                done();
              });
      });

    it('should save apn project', function(done) {
        var appId = 'ai';
        var certData = 'cd';
        var keyData = 'kd';
        var otherOptions = {gateway: 'co.gateway'};

        var step1 = function() {
            webApp
                .post('/admin/projects/apn')
                .send({
                    app_id: appId,
                    cert: certData,
                    key: keyData,
                    other_options: otherOptions
                  })
                .end(function(err, res) {
                    res.should.have.status(202);
                    step2();
                  });
          };

        var step2 = function() {
            db.projects.findConfig('apn', appId, function(projectConfig) {
                projectConfig.should.not.be.null;
                projectConfig.cert.should.equal(certData);
                projectConfig.key.should.equal(keyData);
                projectConfig.gateway.should.equal(otherOptions.gateway);

                done();
              });
          };

        step1();
      });

    it('should not save apn project', function(done) {
        var appId = 'ai';
        var certData = 'cd';
        var keyData = 'kd';

        var test1 = function() {
            webApp
                .post('/admin/projects/apn')
                .send({
                    cert: certData,
                    key: keyData
                  })
                .end(function(err, res) {
                    res.should.have.status(400);
                    test2();
                  });
          };

        var test2 = function() {
            webApp
                .post('/admin/projects/apn')
                .send({
                    app_id: appId,
                    key: keyData
                  })
                .end(function(err, res) {
                    res.should.have.status(400);
                    test3();
                  });
          };

        var test3 = function() {
            webApp
                .post('/admin/projects/apn')
                .send({
                    app_id: appId,
                    cert: certData
                  })
                .end(function(err, res) {
                    res.should.have.status(400);
                    test4();
                  });
          };

        var test4 = function() {
            webApp
                .post('/admin/projects/apn')
                .send({
                    app_id: 'error',
                    cert: certData,
                    key: keyData
                  })
                .end(function(err, res) {
                    res.should.have.status(500);
                    done();
                  });
          };

        test1();
      });

    it('should save gcm project', function(done) {
        var packageId = 'pi';
        var apiKey = 'ak';

        var step1 = function() {
            webApp
                .post('/admin/projects/gcm')
                .send({
                    package_id: packageId,
                    api_key: apiKey
                  })
                .end(function(err, res) {
                    res.should.have.status(202);
                    step2();
                  });
          };

        var step2 = function() {
            db.projects.findConfig('gcm', packageId, function(projectConfig) {
                projectConfig.should.not.be.null;
                projectConfig.api_key.should.equal(apiKey);

                done();
              });
          };

        step1();
      });

    it('should not save gcm project', function(done) {
        var packageId = 'pi';
        var apiKey = 'ak';

        var test1 = function() {
            webApp
                .post('/admin/projects/gcm')
                .send({
                    api_key: apiKey
                  })
                .end(function(err, res) {
                    res.should.have.status(400);
                    test2();
                  });
          };

        var test2 = function() {
            webApp
                .post('/admin/projects/gcm')
                .send({
                    package_id: packageId
                  })
                .end(function(err, res) {
                    res.should.have.status(400);
                    test3();
                  });
          };

        var test3 = function() {
            webApp
                .post('/admin/projects/gcm')
                .send({
                    package_id: 'error',
                    api_key: apiKey
                  })
                .end(function(err, res) {
                    res.should.have.status(500);
                    done();
                  });
          };

        test1();
      });

    it('should save wns project', function(done) {
        var packageId = 'pi';
        var clientId = 'ci';
        var clientSecret = 'cs';

        var step1 = function() {
            webApp
                .post('/admin/projects/wns')
                .send({
                    package_id: packageId,
                    client_id: clientId,
                    client_secret: clientSecret
                  })
                .end(function(err, res) {
                    res.should.have.status(202);
                    step2();
                  });
          };

        var step2 = function() {
            db.projects.findConfig('wns', packageId, function(projectConfig) {
                projectConfig.should.not.be.null;
                projectConfig.client_id.should.equal(clientId);
                projectConfig.client_secret.should.equal(clientSecret);

                done();
              });
          };

        step1();
      });

    it('should not save wns project', function(done) {
        var packageId = 'pi';
        var clientId = 'ci';
        var clientSecret = 'cs';

        var test1 = function() {
            webApp
                .post('/admin/projects/wns')
                .send({
                    client_id: clientId,
                    client_secret: clientSecret
                  })
                .end(function(err, res) {
                    res.should.have.status(400);
                    test2();
                  });
          };

        var test2 = function() {
            webApp
                .post('/admin/projects/wns')
                .send({
                    package_id: packageId,
                    client_secret: clientSecret
                  })
                .end(function(err, res) {
                    res.should.have.status(400);
                    test3();
                  });
          };

        var test3 = function() {
            webApp
                .post('/admin/projects/wns')
                .send({
                    package_id: packageId,
                    client_id: clientId
                  })
                .end(function(err, res) {
                    res.should.have.status(400);
                    test4();
                  });
          };

        var test4 = function() {
            webApp
                .post('/admin/projects/wns')
                .send({
                    package_id: 'error',
                    client_id: clientId,
                    client_secret: clientSecret
                  })
                .end(function(err, res) {
                    res.should.have.status(500);
                    done();
                  });
          };

        test1();
      });

    it('should respond with project info', function(done) {
        var projectType = 'pt';
        var projectId = 'pi';
        var configuration = {foo: 'bar'};

        var init = function() {
            db.projects.save(projectType, projectId, configuration,
              function(isSaved) {
                isSaved.should.not.be.false;
                test();
              });
          };

        var test = function() {
            webApp
                .get('/admin/projects/' + projectType + '/' + projectId)
                .end(function(err, res) {
                    res.should.have.status(200);
                    res.body.should.
                      have.all.keys('internal_id', 'created', 'last_updated');

                    done();
                  });
          };

        init();
      });

    it('should not respond for unknown project', function(done) {
        var projectType = 'pt-unknown';
        var projectId = 'pi-unknown';

        webApp
            .get('/admin/projects/' + projectType + '/' + projectId)
            .end(function(err, res) {
                res.should.have.status(404);
                done();
              });
      });

    it('should require auth', function(done) {
        var adminPrefix = '/admin-auth';
        var username = 'username';
        var password = 'password';
        admin.setup(web._app, adminPrefix, username, password);

        var test1 = function() {
            webApp
                .get(adminPrefix)
                .end(function(err, res) {
                    res.should.have.status(401);
                    test2();
                  });
          };

        var test2 = function() {
            webApp
                .get(adminPrefix)
                .auth(username, password + 'z')
                .end(function(err, res) {
                    res.should.have.status(401);
                    test3();
                  });
          };

        var test3 = function() {
            webApp
                .get(adminPrefix)
                .auth(username, password)
                .end(function(err, res) {
                    res.should.have.status(200);
                    done();
                  });
          };

        test1();
      });

    it('should setup sections', function(done) {
        var adminPrefix = '/admin-sections';
        var sections = {
            one: function(req, res, next) {
                res.send({section: 1});
                next();
              },
            two2: function(req, res, next) {
                res.send({section: 2});
                next();
              },
            three_: function(req, res, next) {
                res.send({section: 3});
                next();
              },
            '!@#$': function(req, res, next) {
                res.send({section: 'invalid'});
                next();
              },
            five: null
          };

        admin.setup(web._app, adminPrefix, null, null, null, sections);

        var test0 = function() {
            webApp
                .get(adminPrefix)
                .end(function(err, res) {
                    res.should.have.status(200);
                    res.body.should.have.all.keys('one', 'two', 'three');
                    test1();
                  });
          };

        var test1 = function() {
            webApp
                .get(adminPrefix + '/one')
                .end(function(err, res) {
                    res.should.have.status(200);
                    res.body.should.deep.equal({section: 1});
                    test2();
                  });
          };

        var test2 = function() {
            webApp
                .get(adminPrefix + '/two')
                .end(function(err, res) {
                    res.should.have.status(200);
                    res.body.should.deep.equal({section: 2});
                    test3();
                  });
          };

        var test3 = function() {
            webApp
                .get(adminPrefix + '/three')
                .end(function(err, res) {
                    res.should.have.status(200);
                    res.body.should.deep.equal({section: 3});
                    done();
                  });
          };

        test0();
      });
  });
