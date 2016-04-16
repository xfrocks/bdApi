var apn = exports;
var _ = require('lodash');

var connections = [];
var feedbacks = [];
var latestPush = null;
var pushes = [];

apn._reset = function () {
    connections = [];
    feedbacks = [];
    latestPush = null;
    pushes = [];
};

apn._getLatestPush = function () {
    return latestPush;
};

apn._getPushes = function () {
    return pushes;
};

apn._getConnectionCount = function () {
    return connections.length;
};

apn._getFeedbackCount = function () {
    return feedbacks.length;
};

apn._getFeedbacks = function (packageId) {
    return _.filter(feedbacks, function (feedback) {
        return feedback.options.packageId == packageId;
    });
};

apn.Connection = function (options) {
    var connection = this;
    this.options = options;
    this.terminated = false;

    this.pushNotification = function (notification, device) {
        latestPush = {
            connection: connection,
            device: device,
            notification: notification
        };
        pushes.push(latestPush);
    };

    this.shutdown = function () {
        connection.terminated = true;
    };

    this.on = function() {
        // NOP
    };

    connections.push(this);
};

apn.Feedback = function (options) {
    var feedback = this;
    var listeners = {};

    this.options = options;
    this.interval = 1;

    this.on = function (event, listener) {
        if (typeof listeners[event] == 'undefined') {
            listeners[event] = [];
        }

        listeners[event].push(listener);
    };

    this.emit = function (event) {
        if (typeof listeners[event] == 'undefined') {
            return;
        }

        var eventArguments = Array.prototype.slice.call(arguments, 1);

        _.forEach(listeners[event], function (listener) {
            listener.apply(feedback, eventArguments);
        });
    };

    this.cancel = function () {
        feedback.interval = undefined;
    };

    feedbacks.push(this);
};

apn.Device = function (token) {
    this.token = token;
};

apn.Notification = function (payload) {
    this.payload = payload;
    this.alert = '';
    this.badge = '';
    this.expiry = null;
    this.sound = '';
};