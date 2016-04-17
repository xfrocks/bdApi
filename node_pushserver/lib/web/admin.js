'use strict';

var admin = exports;
var basicAuth = require('basic-auth');
var debug = require('debug')('pushserver:web:admin');
var _ = require('lodash');

var sections = ['projects'];

admin.setup = function(app, adminPrefix,
  username, password, projectDb, _sections) {
    if (username && password) {
      var requireAuth = function(req, res, next) {
          var unauthorized = function(res) {
              res.set('WWW-Authenticate', 'Basic realm=Authorization Required');
              return res.sendStatus(401);
            };

          var user = basicAuth(req);
          if (!user || !user.name || !user.pass) {
            return unauthorized(res);
          }

          if (user.name === username &&
              user.pass === password) {
            return next();
          } else {
            return unauthorized(res);
          }
        };

      app.use(adminPrefix, requireAuth);
    }

    _.forEach(_sections, function(middleware, route) {
        route = route.replace(/[^a-z]/g, '');
        if (route && middleware) {
          app.use(adminPrefix + '/' + route, middleware);
          sections.push(route);
        }
      });

    app.get(adminPrefix, function(req, res) {
        var output = {};
        _.forEach(sections, function(section) {
            output[section] = req.protocol + '://' + req.get('host') +
             adminPrefix + '/' + section;
          });

        res.send(output);
      });

    app.post(adminPrefix + '/projects/apn', function(req, res) {
        if (!req.body.app_id ||
            !req.body.cert ||
            !req.body.key) {
          return res.sendStatus(400);
        }
        var appId = req.body.app_id;
        var certData = req.body.cert;
        var keyData = req.body.key;

        var otherOptions = {};
        if (req.body.other_options) {
          _.forEach(req.body.other_options, function(value, key) {
              otherOptions[key] = value;
            });
        }

        if (!projectDb) {
          debug('POST /projects/apn', 'projectDb missing');
          return res.sendStatus(500);
        }

        projectDb.saveApn(appId, certData, keyData, otherOptions,
            function(isSaved) {
                if (isSaved !== false) {
                  debug('POST /projects/apn', 'Saved APN project', appId);
                  return res.sendStatus(202);
                } else {
                  return res.sendStatus(500);
                }
              });
      });

    app.post(adminPrefix + '/projects/gcm', function(req, res) {
        if (!req.body.package_id ||
            !req.body.api_key) {
          return res.sendStatus(400);
        }
        var packageId = req.body.package_id;
        var apiKey = req.body.api_key;

        if (!projectDb) {
          debug('POST /projects/gcm', 'projectDb missing');
          return res.sendStatus(500);
        }

        projectDb.saveGcm(packageId, apiKey,
            function(isSaved) {
                if (isSaved !== false) {
                  debug('POST /projects/gcm', 'Saved GCM project', packageId);
                  return res.sendStatus(202);
                } else {
                  return res.sendStatus(500);
                }
              });
      });

    app.post(adminPrefix + '/projects/wns', function(req, res) {
        if (!req.body.package_id ||
            !req.body.client_id ||
            !req.body.client_secret) {
          return res.sendStatus(400);
        }
        var packageId = req.body.package_id;
        var clientId = req.body.client_id;
        var clientSecret = req.body.client_secret;

        if (!projectDb) {
          debug('POST /projects/wns', 'projectDb missing');
          return res.sendStatus(500);
        }

        projectDb.saveWns(packageId, clientId, clientSecret,
            function(isSaved) {
                if (isSaved !== false) {
                  debug('POST /projects/wns', 'Saved WNS project', packageId);
                  return res.sendStatus(202);
                } else {
                  return res.sendStatus(500);
                }
              });
      });

    app.get(adminPrefix + '/projects/:projectType/:projectId',
      function(req, res) {
        if (!projectDb) {
          debug('GET /projects', 'projectDb missing');
          return res.sendStatus(500);
        }

        projectDb.findProject(req.params.projectType, req.params.projectId,
          function(project) {
            if (project) {
              return res.send({
                  internal_id: project._id,
                  created: Math.floor(project.created.getTime() / 1000),
                  last_updated: Math.floor(
                    project.last_updated.getTime() / 1000)
                });
            } else {
              return res.sendStatus(404);
            }
          });
      });
  };
