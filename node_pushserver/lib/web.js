'use strict';

var web = exports;
var config = require('./config');
var helper = require('./helper');
var debug = require('debug')('pushserver:web');
var express = require('express');
var basicAuth = require('basic-auth');
var bodyParser = require('body-parser');
var compression = require('compression');
var _ = require('lodash');
var request = require('request');
var url = require('url');

var app = express();
app.use(compression({}));
app.use(bodyParser.urlencoded({extended: false}));
app.use(bodyParser.json());

if (config.web.username && config.web.password) {
    var requireAuth = function (req, res, next) {
        var unauthorized = function (res) {
            res.set('WWW-Authenticate', 'Basic realm=Authorization Required');
            return res.sendStatus(401);
        };

        var user = basicAuth(req);
        if (!user || !user.name || !user.pass) {
            return unauthorized(res);
        }

        if (user.name === config.web.username
            && user.pass === config.web.password) {
            return next();
        } else {
            return unauthorized(res);
        }
    };

    app.use('/admin', requireAuth);
}

var getCallbackUri = function (req) {
    if (config.web.callback) {
        return config.web.callback;
    }

    return req.protocol + '://' + req.get('host') + '/callback';
};

app.post('/subscribe', function (req, res) {
    var requiredKeys = [];
    requiredKeys.push('hub_uri');
    requiredKeys.push('oauth_client_id');
    requiredKeys.push('oauth_token');
    requiredKeys.push('device_type');
    requiredKeys.push('device_id');
    var data = helper.prepareSubscribeData(req.body, requiredKeys);
    if (!data.has_all_required_keys) {
        debug('POST /subscribe', 'some keys are missing', data.missing_keys);
        return res.sendStatus(400);
    }

    var saveDevice = function () {
        if (!web._deviceDb) {
            debug('POST /subscribe', 'deviceDb has not been setup properly');
            return res.sendStatus(500);
        }

        web._deviceDb.save(
            data.device_type, data.device_id,
            data.oauth_client_id, data.hub_topic,
            data.extra_data, function (isSaved) {
                if (isSaved !== false) {
                    sendPostRequestToHub();
                } else {
                    return res.sendStatus(500);
                }
            }
        );
    };

    var sendPostRequestToHub = function () {
        request.post({
            url: data.hub_uri,
            form: {
                'hub.callback': getCallbackUri(req),
                'hub.mode': 'subscribe',
                'hub.topic': data.hub_topic,

                client_id: data.oauth_client_id,
                oauth_token: data.oauth_token
            }
        }, function (err, httpResponse, body) {
            if (httpResponse) {
                var success = _.inRange(httpResponse.statusCode, 200, 300);
                var txt = success ? 'succeeded' : (body ? body : 'failed');

                debug('POST /subscribe', data.device_type, data.device_id, data.hub_uri, data.hub_topic, txt);
                return res.status(httpResponse.statusCode).send(txt);
            } else {
                debug('POST /subscribe', data.device_type, data.device_id, data.hub_uri, data.hub_topic, err);
                return res.sendStatus(503);
            }
        });
    };

    saveDevice();
});

app.post('/unsubscribe', function (req, res) {
    var requiredKeys = [];
    requiredKeys.push('hub_uri');
    requiredKeys.push('hub_topic');
    requiredKeys.push('oauth_client_id');
    requiredKeys.push('device_type');
    requiredKeys.push('device_id');
    var data = helper.prepareSubscribeData(req.body, requiredKeys);
    if (!data.has_all_required_keys) {
        debug('POST /unsubscribe', 'some keys are missing', data.missing_keys);
        return res.sendStatus(400);
    }

    var deleteDevice = function () {
        if (!web._deviceDb) {
            debug('POST /unsubscribe', 'deviceDb has not been setup properly');
            return res.sendStatus(500);
        }

        web._deviceDb.delete(
            data.device_type, data.device_id,
            data.oauth_client_id, data.hub_topic,
            function (isDeleted) {
                if (isDeleted !== false) {
                    sendPostRequestToHub();
                } else {
                    return res.sendStatus(500);
                }
            }
        );
    };

    var sendPostRequestToHub = function () {
        request.post({
            url: data.hub_uri,
            form: {
                'hub.callback': getCallbackUri(req),
                'hub.mode': 'unsubscribe',
                'hub.topic': data.hub_topic,

                oauth_token: data.oauth_token,
                client_id: data.oauth_client_id
            }
        }, function (err, httpResponse, body) {
            if (httpResponse) {
                var success = _.inRange(httpResponse.statusCode, 200, 300);
                var txt = success ? 'succeeded' : (body ? body : 'failed');

                debug('POST /unsubscribe', data.device_type, data.device_id, data.hub_uri, data.hub_topic, txt);
                return res.status(httpResponse.statusCode).send(txt);
            } else {
                debug('POST /unsubscribe', data.device_type, data.device_id, data.hub_uri, data.hub_topic, err);
                return res.sendStatus(503);
            }
        });
    };

    deleteDevice();
});

app.post('/unregister', function (req, res) {
    var requiredKeys = [];
    requiredKeys.push('oauth_client_id');
    requiredKeys.push('device_type');
    requiredKeys.push('device_id');
    var data = helper.prepareSubscribeData(req.body, requiredKeys);
    if (!data.has_all_required_keys) {
        debug('POST /unregister', 'some keys are missing', data.missing_keys);
        return res.sendStatus(400)
    }

    if (!web._deviceDb) {
        debug('POST /unregister', 'deviceDb has not been setup properly');
        return res.sendStatus(500)
    }

    web._deviceDb.delete(
        data.device_type, data.device_id,
        data.oauth_client_id, null,
        function (isDeleted) {
            debug('POST /unregister', data.device_type, data.device_id, data.oauth_client_id, isDeleted);

            if (isDeleted !== false) {
                return res.send('succeeded');
            } else {
                return res.sendStatus(500)
            }
        }
    );
});

app.get('/callback', function (req, res) {
    var parsed = url.parse(req.url, true);

    if (!parsed.query.client_id) {
        debug('/callback', '`client_id` is missing');
        return res.sendStatus(401);
    }

    if (!parsed.query['hub.challenge']) {
        debug('/callback', '`hub.challenge` is missing');
        return res.sendStatus(403);
    }

    if (!parsed.query['hub.mode']) {
        debug('/callback', '`hub.mode` is missing');
        return res.sendStatus(404);
    }

    var hubTopic = parsed.query['hub.topic'];
    if (!hubTopic) {
        hubTopic = '';
    }

    if (!web._deviceDb) {
        debug('GET /callback', 'deviceDb has not been setup properly');
        return res.sendStatus(500);
    }

    web._deviceDb.findDevices(parsed.query.client_id, hubTopic, function (devices) {
        var isSubscribe = (parsed.query['hub.mode'] === 'subscribe');
        var devicesFound = devices.length > 0;

        if (isSubscribe != devicesFound) {
            return res.sendStatus(405);
        }

        debug('GET /callback', parsed.query.client_id, parsed.query['hub.mode'], hubTopic);

        return res.send(parsed.query['hub.challenge']);
    });
});

app.post('/callback', function (req, res) {
    _.forEach(req.body, function (ping) {
        if (!_.isObject(ping)) {
            debug('POST /callback', 'Unexpected data in callback', ping);
            return;
        }

        if (!ping.client_id || !ping.topic || !ping.object_data) {
            debug('POST /callback', 'Insufficient data in callback', ping);
            return;
        }

        if (!web._deviceDb) {
            debug('POST /callback', 'deviceDb has not been setup properly');
            return res.sendStatus(500);
        }

        web._deviceDb.findDevices(ping.client_id, ping.topic, function (devices) {
            _.forEach(devices, function (device) {
                if (web._pushQueue) {
                    web._pushQueue.enqueue(device.device_type, device.device_id, ping.object_data, device.extra_data);
                } else {
                    debug('POST /callback', 'pushQueue has not been setup properly');
                }
            });
        });
    });

    return res.sendStatus(200);
});

app.get('/', function (req, res) {
    res.send('Hi, I am ' + getCallbackUri(req));
});

app.get('/admin', function (req, res) {
    var output = {};
    _.forEach(web._adminSections, function (adminSection) {
        output[adminSection] = req.protocol + '://' + req.get('host') + '/admin/' + adminSection;
    });

    res.send(output);
});

app.post('/admin/apn', function (req, res) {
    if (!req.body.app_id
        || !req.body.cert_data
        || !req.body.key_data) {
        return res.sendStatus(400);
    }
    var appId = req.body.app_id;
    var certData = req.body.cert_data;
    var keyData = req.body.key_data;

    var otherOptions = {};
    if (req.body.other_options) {
        _.forEach(req.body.other_options, function (value, key) {
            otherOptions[key] = value;
        });
    }

    if (!web._projectDb) {
        debug('POST /admin/apn', 'projectDb has not been setup properly');
        return res.sendStatus(500);
    }

    web._projectDb.saveApn(appId, certData, keyData, otherOptions,
        function (isSaved) {
            if (isSaved !== false) {
                debug('POST /admin/apn', 'Saved APN project', appId);
                return res.sendStatus(202);
            } else {
                return res.sendStatus(500);
            }
        });
});

app.post('/admin/gcm', function (req, res) {
    if (!req.body.package_id
        || !req.body.api_key) {
        return res.sendStatus(400);
    }
    var packageId = req.body.package_id;
    var apiKey = req.body.api_key;

    if (!web._projectDb) {
        debug('POST /admin/gcm', 'projectDb has not been setup properly');
        return res.sendStatus(500);
    }

    web._projectDb.saveGcm(packageId, apiKey,
        function (isSaved) {
            if (isSaved !== false) {
                debug('POST /admin/gcm', 'Saved GCM project', packageId);
                return res.sendStatus(202);
            } else {
                return res.sendStatus(500);
            }
        });
});

app.post('/admin/wns', function (req, res) {
    if (!req.body.package_id
        || !req.body.client_id
        || !req.body.client_secret) {
        return res.sendStatus(400);
    }
    var packageId = req.body.package_id;
    var clientId = req.body.client_id;
    var clientSecret = req.body.client_secret;

    if (!web._projectDb) {
        debug('POST /admin/wns', 'projectDb has not been setup properly');
        return res.sendStatus(500);
    }

    web._projectDb.saveWns(packageId, clientId, clientSecret,
        function (isSaved) {
            if (isSaved !== false) {
                debug('POST /admin/wns', 'Saved WNS project', packageId);
                return res.sendStatus(202);
            } else {
                return res.sendStatus(500);
            }
        });
});

web._app = app;
web._deviceDb = null;
web._projectDb = null;
web._pushQueue = null;
web._adminSections = [];
web.start = function (port, deviceDb, projectDb, pushQueue, adminSections) {
    web._deviceDb = deviceDb;
    web._projectDb = projectDb;
    web._pushQueue = pushQueue;

    _.forEach(adminSections, function (middleware, route) {
        route = route.replace(/[^a-z]/g, '');
        if (route && middleware) {
            app.use('/admin/' + route, middleware);
            web._adminSections.push(route);
        }
    });

    app.listen(port);
    debug('Listening on port', port, '…');

    return web;
};