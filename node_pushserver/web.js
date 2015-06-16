var web = exports;
var config = require('./config');
var deviceDb = require('./db').devices;
var pushQueue = require('./pushQueue');
var debug = require('debug')('web');
var express = require('express');
var request = require('request');
var url = require('url');

var app = express();

app.use(express.compress());
app.use(express.bodyParser());

app.get('/', function(req, res) {
    res.send('Hi, I am ' + req.headers.host);
});

var prepareSubscribeData = function(req, res) {
    var hubUri = req.body.hub_uri;
    var hubTopic = req.body.hub_topic;
    if (!hubUri) {
        debug('`hub_uri` is missing');
        return res.status(400).send();
    }
    if (!hubTopic) {
        // try to get hub topic from hub uri
        var hubUriParsed = url.parse(hubUri, true);
        if (!!hubUriParsed.query || !!hubUriParsed.query['hub.topic']) {
            debug('`hub_topic` is determined from `hub_uri`');
            hubTopic = hubUriParsed.query['hub.topic'];
        }
    }
    if (!hubTopic) {
        debug('`hub_topic` is missing');
        return res.status(400).send();
    }

    var oauthClientId = req.body.oauth_client_id;
    var oauthToken = req.body.oauth_token;
    if (!oauthClientId || !oauthToken) {
        debug('OAuth information is missing');
        return res.status(401).send();
    }

    var deviceType = req.body.device_type;
    var deviceId = req.body.device_id;
    if (!deviceType || !deviceId) {
        debug('Device data is missing');
        return res.status(403).send();
    }

    var extraData = req.body.extra_data;
    if (!extraData) {
        extraData = null;
    }

    var callback = req.protocol + '://' + req.get('host') + '/callback';
    

    var data = {
        'callback': callback,

        'hub_uri': hubUri,
        'hub_topic': hubTopic,
        'oauth_client_id': oauthClientId,
        'oauth_token': oauthToken,

        'device_type': deviceType,
        'device_id': deviceId,
        'extra_data': extraData,
    };

    return data;
};

app.post('/subscribe', function (req, res) {
    var data = prepareSubscribeData(req, res);
    if (typeof data.oauth_token !== 'string') {
        return false;
    }

    var formData = {
        'hub.callback': data.callback,
        'hub.mode': 'subscribe',
        'hub.topic': data.hub_topic,

        'oauth_token': data.oauth_token,
    };

    // save the device first, so when server verifies intent, we can look it up
    deviceDb.save(data.device_type, data.device_id, data.oauth_client_id, data.hub_topic, data.extra_data);

    debug('/subscribe before request.post', data.hub_uri);
    request.post({
        'url': data.hub_uri,
        'formData': formData
    }, function(err, httpResponse, body) {
        if (httpResponse) {
            var success = false;
            if (httpResponse.statusCode >= 200 && httpResponse.statusCode < 300) {
                success = true;
            }

            debug('/subscribe', success, data.hub_uri, formData);
            return res.status(httpResponse.statusCode).send(body);
        } else {
            debug('/subscribe', data.hub_uri, formData, err);
            return res.status(500).send();
        }
    });
});

app.post('/unsubscribe', function (req, res) {
    var data = prepareSubscribeData(req, res);
    if (typeof data.oauth_token !== 'string') {
        return false;
    }

    var formData = {
        'hub.callback': data.callback,
        'hub.mode': 'unsubscribe',
        'hub.topic': data.hub_topic,

        'oauth_token': data.oauth_token,
    };

    debug('/unsubscribe before request.post', data.hub_uri);
    request.post({
        'url': data.hub_uri,
        'formData': formData
    }, function(err, httpResponse, body) {
        if (httpResponse) {
            var success = false;
            if (httpResponse.statusCode >= 200 && httpResponse.statusCode < 300) {
                deviceDb.delete(data.device_type, data.device_id, data.oauth_client_id);
                success = true;
            }

            debug('/unsubscribe', success, data.hub_uri, formData);
            return res.status(httpResponse.statusCode).send(body);
        } else {
            debug('/unsubscribe', data.hub_uri, formData, err);
            return res.status(500).send();
        }
    });
});

app.get('/callback', function (req, res) {
    var parsed = url.parse(req.url, true);

    if (!parsed.query || !parsed.query['hub.challenge']) {
        debug('`hub.challenge` is missing');
        return res.status(400).send();
    }

    if (!parsed.query['client_id'] || !parsed.query['hub.topic']) {
        debug('Client or `hub.topic` is missing');
        return res.status(401).send();
    }

    deviceDb.findDevices(parsed.query['client_id'], parsed.query['hub.topic'], function(devices) {
        if (devices.length == 0) {
            debug('No devices could be found');
            return res.status(403).send();
        }

        var challenge = parsed.query['hub.challenge'];

        debug('/callback', challenge);
        return res.send(challenge);
    });
});

app.post('/callback', function (req, res) {
    if (typeof req.body == 'object') {
        for (var i in req.body) {
            var ping = req.body[i];

            if (typeof ping != 'object') {
                debug('ping is not an object');
                continue;
            } 

            if (!ping.client_id || !ping.topic) {
                debug('ping does not has client or topic information');
                continue;
            }

            if (ping.topic.indexOf('user_notification_') !== 0) {
                // TODO: support other pings
                debug('ping is not user_notification_* topic, ignore for now');
                continue;
            }

            deviceDb.findDevices(ping.client_id, ping.topic, function(devices) {
                for (var i in devices) {
                    pushQueue.enqueue(devices[i].device_type, devices[i].device_id, ping.object_data, devices[i].extra_data);
                }
            });
        }
    }

    res.send();
});

web.start = function () {
    var port = config.web.port;
    app.listen(port);
    debug('Listening on port ' + port + '...');
};
