'use strict';

var config = exports;
var _ = require('lodash');
var url = require('url');

var defaultConfig = {
    db: {
        mongoUri: 'mongodb://localhost/pushserver',
        web: false
    },
    web: {
        port: 18080,
        callback: '',
        adminPrefix: '/admin',
        username: '',
        password: ''
    },
    redis: {
        port: 6379,
        host: '127.0.0.1',
        auth: null
    },
    pushQueue: {
        queueId: 'push',
        attempts: 3,
        ttlInMs: 5000,
        web: false
    },
    apn: {
        connectionTtlInMs: 3600000,

        connectionOptions: {
            packageId: 'default',
            cert: '',
            certData: null,
            key: '',
            keyData: null
        },
        notificationOptions: {},

        feedback: {
            interval: 3600
        }
    },
    gcm: {
        keys: {},
        defaultKeyId: '',
        messageOptions: {}
    },
    wns: {
        client_id: '',
        client_secret: ''
    }
};

_.merge(config, defaultConfig);

if (process.env.MONGOLAB_URI) {
    config.db.mongoUri = process.env.MONGOLAB_URI;
}

if (process.env.PORT) {
    config.web.port = process.env.PORT;
}

_.forEach([
    'REDISCLOUD_URL',
    'REDISTOGO_URL'
], function (redisUrlKey) {
    if (process.env[redisUrlKey]) {
        var redisUrlParsed = url.parse(process.env[redisUrlKey]);
        config.redis.port = redisUrlParsed.port;
        config.redis.host = redisUrlParsed.hostname;
        config.redis.auth = redisUrlParsed.auth.split(":")[1];
    }
});

if (process.env.CONFIG_WEB_CALLBACK) {
    config.web.callback = process.env.CONFIG_WEB_CALLBACK;
}

if (process.env.CONFIG_WEB_USERNAME && process.env.CONFIG_WEB_PASSWORD) {
    config.web.username = process.env.CONFIG_WEB_USERNAME;
    config.web.password = process.env.CONFIG_WEB_PASSWORD;
    config.db.web = true;
    config.pushQueue.web = true;
}

if (process.env.CONFIG_PUSH_QUEUE_ID) {
    config.pushQueue.queueId = process.env.CONFIG_PUSH_QUEUE_ID;
}

if (process.env.CONFIG_APN_CERT_FILE && process.env.CONFIG_APN_KEY_FILE) {
    config.apn.connectionOptions.cert = process.env.CONFIG_APN_CERT_FILE;
    config.apn.connectionOptions.key = process.env.CONFIG_APN_KEY_FILE;
}

if (process.env.CONFIG_APN_CERT && process.env.CONFIG_APN_KEY) {
    config.apn.connectionOptions.certData = process.env.CONFIG_APN_CERT;
    config.apn.connectionOptions.keyData = process.env.CONFIG_APN_KEY;
}

if (process.env.CONFIG_APN_GATEWAY) {
    config.apn.connectionOptions.gateway = process.env.CONFIG_APN_GATEWAY;
}

if (process.env.CONFIG_GCM_KEY) {
    // single gcm key
    var keyId = '_default_';
    config.gcm.keys[keyId] = process.env.CONFIG_GCM_KEY;
    config.gcm.defaultKeyId = keyId;
}

if (process.env.CONFIG_GCM_KEYS) {
    // multiple gcm keys
    var n = parseInt(process.env.CONFIG_GCM_KEYS);
    for (var i = 0; i < n; i++) {
        if (!_.has(process.env, 'CONFIG_GCM_KEYS_' + i)) {
            continue;
        }

        var keyPair = process.env['CONFIG_GCM_KEYS_' + i].split(',');
        if (keyPair.length !== 2) {
            continue;
        }

        config.gcm.keys[keyPair[0]] = keyPair[1];
        if (!config.gcm.defaultKeyId) {
            config.gcm.defaultKeyId = keyPair[0];
        }
    }
}

if (process.env.CONFIG_WNS_CLIENT_ID && process.env.CONFIG_WNS_CLIENT_SECRET) {
    config.wns.client_id = process.env.CONFIG_WNS_CLIENT_ID;
    config.wns.client_secret = process.env.CONFIG_WNS_CLIENT_SECRET;
}

config.hasApnConfig = function () {
    return config.apn.connectionOptions.cert || config.apn.connectionOptions.certData;
};

config.hasGcmConfig = function () {
    return !!config.gcm.defaultKeyId;
};

config.hasWnsConfig = function () {
    return config.wns.client_id && config.wns.client_secret;
};