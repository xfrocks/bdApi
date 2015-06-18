var config = exports;
var _ = require('lodash');
var url = require('url');
var debug = require('debug')('config');

var defaultConfig = {
	web: {
		port: 18080,
		callback: '',
	},
	redis: {
		port: 6379,
		host: '127.0.0.1',
		auth: null
	},
	pushQueue: {
		queueId: 'push',
		attempts: 86400,
		webPort: 18081
	},
	apn: {
		enabled: false,
		connectionOptions: {
			cert: '',
			certData: null,
			key: '',
			keyData: null,
		},
		notificationOptions: {
		},

		feedback: {
			interval: 3600
		}
	},
	gcm: {
		enabled: false,
		keys: {},
		defaultKeyId: '',
		messageOptions: {}
	},
	wns: {
		enabled: false,
		client_id: '',
		client_secret: ''
	}
};

_.merge(config, defaultConfig);

if (process.env.PORT) {
	config.web.port = process.env.PORT;
	config.pushQueue.webPort = 0;
}

if (process.env.REDISTOGO_URL) {
	var redisToGo = url.parse(process.env.REDISTOGO_URL);
	config.redis.port = redisToGo.port;
	config.redis.host = redisToGo.hostname;
	config.redis.auth = redisToGo.auth.split(":")[1];
}

if (process.env.CONFIG_WEB_CALLBACK) {
	config.web.callback = process.env.CONFIG_WEB_CALLBACK;
}

if (process.env.CONFIG_PUSH_QUEUE_ID) {
	config.pushQueue.queueId = process.env.CONFIG_PUSH_QUEUE_ID;
}
if (process.env.CONFIG_PUSH_QUEUE_PORT) {
	config.pushQueue.webPort = process.env.CONFIG_PUSH_QUEUE_PORT;
}

if (process.env.CONFIG_APN_CERT_FILE && process.env.CONFIG_APN_KEY_FILE) {
	config.apn.enabled = true;
	config.apn.connectionOptions.cert = process.env.CONFIG_APN_CERT_FILE;
	config.apn.connectionOptions.key = process.env.CONFIG_APN_KEY_FILE;
}

if (process.env.CONFIG_APN_CERT && process.env.CONFIG_APN_KEY) {
	config.apn.enabled = true;
	config.apn.connectionOptions.certData = process.env.CONFIG_APN_CERT;
	config.apn.connectionOptions.keyData = process.env.CONFIG_APN_KEY;
}

if (process.env.CONFIG_APN_GATEWAY) {
	config.apn.enabled = true;
	config.apn.connectionOptions.gateway = process.env.CONFIG_APN_GATEWAY;
}

if (process.env.CONFIG_GCM_KEY) {
	config.gcm.enabled = true;

	var keyId = '_default_';
	config.gcm.keys[keyId] = process.env.CONFIG_GCM_KEY;
	config.gcm.defaultKeyId = keyId;
}

if (process.env.CONFIG_GCM_KEYS) {
	config.gcm.enabled = true;

	var n = parseInt(process.env.CONFIG_GCM_KEYS);
	for (var i = 0; i < n; i++) {
		if (process.env['CONFIG_GCM_KEYS_' + i]) {
			var keyPair = process.env['CONFIG_GCM_KEYS_' + i].split(',');
			if (keyPair.length == 2) {
				config.gcm.keys[keyPair[0]] = keyPair[1];
				if (!config.gcm.defaultKeyId) {
					config.gcm.defaultKeyId = keyPair[0];
				}
			}
		}
	}

	debug('CONFIG_GCM_KEYS', config.gcm);
}

if (process.env.CONFIG_WNS_CLIENT_ID && process.env.CONFIG_WNS_CLIENT_SECRET) {
	config.wns.enabled = true;
	config.wns.client_id = process.env.CONFIG_WNS_CLIENT_ID;
	config.wns.client_secret = process.env.CONFIG_WNS_CLIENT_SECRET;
}