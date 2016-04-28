'use strict';

var helper = exports;
var debug = require('debug')('pushserver:helper');
var _ = require('lodash');
var string = require('string');
var url = require('url');

helper.stripHtml = function(html) {
    return string(html).stripTags().trim().s;
  };

helper.prepareApnMessage = function(originalMessage) {
    var message = '';

    if (originalMessage.length > 230) {
      message = originalMessage.substr(0, 229) + '…';
      debug('prepareApnMessage', originalMessage, '->', message);
    } else {
      message = originalMessage;
    }

    return message;
  };

helper.prepareSubscribeData = function(reqBody, requiredKeys) {
    var hubUri = reqBody.hub_uri;
    if (!_.isString(hubUri)) {
      hubUri = '';
    }

    var hubTopic = reqBody.hub_topic;
    if (!_.isString(hubTopic) && hubUri.length > 0) {
      // try to get hub topic from hub uri
      var hubUriParsed = url.parse(hubUri, true);
      if (hubUriParsed.query && _.isString(hubUriParsed.query['hub.topic'])) {
        debug('prepareSubscribeData', 'extracted `hub_topic` from `hub_uri`');
        hubTopic = hubUriParsed.query['hub.topic'];
      }
    }
    if (!_.isString(hubTopic)) {
      hubTopic = '';
    }

    var oauthClientId = reqBody.oauth_client_id;
    if (!_.isString(oauthClientId)) {
      oauthClientId = '';
    }

    var oauthToken = reqBody.oauth_token;
    if (!_.isString(oauthToken)) {
      oauthToken = '';
    }

    var deviceType = reqBody.device_type;
    if (!_.isString(deviceType)) {
      deviceType = '';
    }

    var deviceId = reqBody.device_id;
    if (!_.isString(deviceId)) {
      deviceId = '';
    }

    var extraData = reqBody.extra_data;
    if (!_.isPlainObject(extraData) || _.isEmpty(extraData)) {
      extraData = null;
    }

    var data = {
        hub_uri: hubUri,
        hub_topic: hubTopic,
        oauth_client_id: oauthClientId,
        oauth_token: oauthToken,

        device_type: deviceType,
        device_id: deviceId,
        extra_data: extraData
      };

    var missingKeys = [];
    _.forEach(requiredKeys, function(requiredKey) {
        var value = _.get(data, requiredKey, '');

        if (_.isString(value) && value.length === 0) {
          missingKeys.push(requiredKey);
        }
      });

    data.has_all_required_keys = true;
    if (missingKeys.length > 0) {
      data.has_all_required_keys = false;
      data.missing_keys = missingKeys;
    }

    return data;
  };
