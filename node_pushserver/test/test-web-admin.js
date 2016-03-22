var web = require('../lib/web');
var chai = require('chai');
var express = require('express');
var bodyParser = require('body-parser');
var http = require('http');
var _ = require('lodash');

chai.should();
chai.use(require('chai-http'));

var db = require('./mock/db');
require('../lib/web/admin').setup(web._app, '/admin', null, null, db.projects);
var webApp = chai.request(web._app);

describe('web/admin', function () {

    beforeEach(function (done) {
        db.projects._reset();
        done();
    });

    it('should return sections', function (done) {
        webApp
            .get('/admin')
            .end(function (err, res) {
                res.should.have.status(200);
                res.body.should.have.all.keys('projects');

                done();
            });
    });

    it('should save apn project', function (done) {
        var appId = 'ai';
        var certData = 'cd';
        var keyData = 'kd';
        var otherOptions = {gateway: 'co.gateway'};

        var step1 = function () {
            webApp
                .post('/admin/projects/apn')
                .send({
                    app_id: appId,
                    cert_data: certData,
                    key_data: keyData,
                    other_options: otherOptions
                })
                .end(function (err, res) {
                    res.should.have.status(202);
                    step2();
                });
        };

        var step2 = function () {
            db.projects.findConfig('apn', appId, function (projectConfig) {
                projectConfig.should.not.be.null;
                projectConfig.cert_data.should.equal(certData);
                projectConfig.key_data.should.equal(keyData);
                projectConfig.gateway.should.equal(otherOptions.gateway);

                done();
            });
        };

        step1();
    });

    it('should save gcm project', function (done) {
        var packageId = 'pi';
        var apiKey = 'ak';

        var step1 = function () {
            webApp
                .post('/admin/projects/gcm')
                .send({
                    package_id: packageId,
                    api_key: apiKey
                })
                .end(function (err, res) {
                    res.should.have.status(202);
                    step2();
                });
        };

        var step2 = function () {
            db.projects.findConfig('gcm', packageId, function (projectConfig) {
                projectConfig.should.not.be.null;
                projectConfig.api_key.should.equal(apiKey);

                done();
            });
        };

        step1();
    });

    it('should save wns project', function (done) {
        var packageId = 'pi';
        var clientId = 'ci';
        var clientSecret = 'cs';

        var step1 = function () {
            webApp
                .post('/admin/projects/wns')
                .send({
                    package_id: packageId,
                    client_id: clientId,
                    client_secret: clientSecret
                })
                .end(function (err, res) {
                    res.should.have.status(202);
                    step2();
                });
        };

        var step2 = function () {
            db.projects.findConfig('wns', packageId, function (projectConfig) {
                projectConfig.should.not.be.null;
                projectConfig.client_id.should.equal(clientId);
                projectConfig.client_secret.should.equal(clientSecret);

                done();
            });
        };

        step1();
    });

    it('should respond with project info', function (done) {
        var projectType = 'pt';
        var projectId = 'pi';
        var configuration = {foo: 'bar'};

        var init = function () {
            db.projects.save(projectType, projectId, configuration, function (isSaved) {
                isSaved.should.not.be.false;
                test();
            });
        };

        test = function () {
            webApp
                .get('/admin/projects/' + projectType + '/' + projectId)
                .end(function (err, res) {
                    res.should.have.status(200);
                    res.body.should.have.all.keys('internal_id', 'created', 'last_updated');

                    done();
                });
        };

        init();
    });

    it('should not respond for unknown project', function (done) {
        var projectType = 'pt-unknown';
        var projectId = 'pi-unknown';

        webApp
            .get('/admin/projects/' + projectType + '/' + projectId)
            .end(function (err, res) {
                res.should.have.status(404);
                done();
            });
    });

});