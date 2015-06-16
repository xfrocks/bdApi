var debug = require('debug')('app');
debug('Booting up...');

var config = require('./config');
if (config.apn.enabled) {
	if (!config.apn.connectionOptions.cert && !config.apn.connectionOptions.certData) {
		debug('No APN cert.');
		return;
	}
} else {
	debug('APN is disabled.');
}

if (config.gcm.enabled) {
	if (!config.gcm.key) {
		debug('No GCM key.');
		return;
	}
} else {
	debug('GCM is disabled.');
}

if (config.wns.enabled) {
	if (!config.wns.client_id && !config.wns.client_secret) {
		debug('No WNS client credentials.');
		return;
	}
} else {
	debug('WNS is disabled.');
}

debug('Starting web...');
var web = require('./web');
web.start();