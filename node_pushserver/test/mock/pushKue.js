var pushKue = exports;
var _ = require('lodash');

var queues = {};
var processCallbacks = {};

pushKue._reset = function () {
    queues = {};
};

pushKue._getLatestJob = function (queueId) {
    if (typeof queues[queueId] == 'undefined') {
        return null;
    }

    return _.last(queues[queueId]);
};

pushKue._getJobs = function (queueId) {
    if (typeof queues[queueId] == 'undefined') {
        return [];
    }

    return queues[queueId];
};

pushKue.create = function (queueId, jobData) {
    var remainingAttempts = 1;
    var backOff = null;
    var ttl = 1000;
    var removeOnComplete = false;

    var job = {
        data: jobData,
        result: null,
        attempts: 0,
        logs: [],

        log: function() {
            job.logs.push(arguments);
        }
    };
    if (typeof queues[queueId] == 'undefined') {
        queues[queueId] = [];
    }
    queues[queueId].push(job);

    var attempt = function () {
        if (typeof processCallbacks[queueId] != 'function') {
            console.log('what', processCallbacks);
            return;
        }

        job.attempts++;

        processCallbacks[queueId](job, function (err, result) {
            if (err) {
                remainingAttempts--;
                if (remainingAttempts > 0) {
                    attempt();
                }
            }

            job.result = result;
        });
    };

    return {
        attempts: function (n) {
            remainingAttempts = n;
        },
        backoff: function (o) {
            backOff = o;
        },
        ttl: function (n) {
            ttl = n;
        },
        removeOnComplete: function (b) {
            removeOnComplete = b;
        },

        save: function (callback) {
            if (typeof callback == 'function') {
                callback();
            }

            attempt();
        }
    };
};

pushKue.process = function (queueId, parallel, callback) {
    processCallbacks[queueId] = callback;
};