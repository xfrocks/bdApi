'use strict';

var pushKue = exports;
var _ = require('lodash');

var queues = {};
var processCallbacks = {};

pushKue._reset = function() {
    queues = {};
  };

pushKue._getLatestJob = function(queueId) {
    if (!_.has(queues, queueId)) {
      return null;
    }

    return _.last(queues[queueId]);
  };

pushKue._getJobs = function(queueId) {
    if (!_.has(queues, queueId)) {
      return [];
    }

    return queues[queueId];
  };

pushKue.create = function(queueId, jobData) {
    var remainingAttempts = 1;
    var backOff = null;
    var ttl = 1000;
    var removeOnComplete = false;

    var job = {
        data: jobData,
        error: null,
        result: null,
        attempts: 0,
        logs: [],

        log: function() {
            job.logs.push(arguments);
          }
      };

    var attempt = function() {
        if (!_.isFunction(processCallbacks[queueId])) {
          console.log('what', processCallbacks);
          return;
        }

        job.attempts++;

        processCallbacks[queueId](job, function(err, result) {
            if (err) {
              job.error = err;
              job.result = null;
              remainingAttempts--;
              if (remainingAttempts > 0) {
                attempt();
              }

              return;
            }

            job.error = null;
            job.result = result;
          });
      };

    return {
        attempts: function(n) {
            remainingAttempts = n;
          },
        backoff: function(o) {
            backOff = o;
          },
        ttl: function(n) {
            ttl = n;
          },
        removeOnComplete: function(b) {
            removeOnComplete = b;
          },

        save: function(callback) {
            if (job.data.device_type === 'save' &&
                job.data.device_id === 'error'
            )  {
              return callback('job.save error');
            }

            if (!_.has(queues, queueId)) {
              queues[queueId] = [];
            }
            queues[queueId].push(job);

            if (_.isFunction(callback)) {
              callback();
            }

            attempt();
          }
      };
  };

pushKue.process = function(queueId, parallel, callback) {
    processCallbacks[queueId] = callback;
  };
