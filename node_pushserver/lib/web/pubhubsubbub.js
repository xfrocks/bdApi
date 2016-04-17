'use strict';

var pubhubsubbub = exports;
var helper = require('../helper');
var debug = require('debug')('pushserver:web');
var _ = require('lodash');
var request = require('request');
var url = require('url');

pubhubsubbub.setup = function(app, deviceDb, pushQueue) {
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
            if (!deviceDb) {
                debug('POST /subscribe', 'deviceDb has not been setup properly');
                return res.sendStatus(500);
            }

            deviceDb.save(
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
                    'hub.callback': helper.getCallbackUri(req),
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
            if (!deviceDb) {
                debug('POST /unsubscribe', 'deviceDb has not been setup properly');
                return res.sendStatus(500);
            }

            deviceDb.delete(
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
                    'hub.callback': helper.getCallbackUri(req),
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
            return res.sendStatus(400);
        }

        if (!deviceDb) {
            debug('POST /unregister', 'deviceDb has not been setup properly');
            return res.sendStatus(500);
        }

        deviceDb.delete(
            data.device_type, data.device_id,
            data.oauth_client_id, null,
            function (isDeleted) {
                debug('POST /unregister', data.device_type, data.device_id, data.oauth_client_id, isDeleted);

                if (isDeleted !== false) {
                    return res.send('succeeded');
                } else {
                    return res.sendStatus(500);
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

        if (!deviceDb) {
            debug('GET /callback', 'deviceDb has not been setup properly');
            return res.sendStatus(500);
        }

        deviceDb.findDevices(parsed.query.client_id, hubTopic, function (devices) {
            var isSubscribe = (parsed.query['hub.mode'] === 'subscribe');
            var devicesFound = devices.length > 0;

            if (isSubscribe !== devicesFound) {
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

            if (!deviceDb) {
                debug('POST /callback', 'deviceDb has not been setup properly');
                return res.sendStatus(500);
            }

            deviceDb.findDevices(ping.client_id, ping.topic, function (devices) {
                _.forEach(devices, function (device) {
                    if (pushQueue) {
                        pushQueue.enqueue(device.device_type, device.device_id, ping.object_data, device.extra_data);
                    } else {
                        debug('POST /callback', 'pushQueue has not been setup properly');
                    }
                });
            });
        });

        return res.sendStatus(200);
    });
};