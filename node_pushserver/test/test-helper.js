/*jshint expr: true*/
'use strict';

var helper = require('../lib/helper.js');
var chai = require('chai');
var _ = require('lodash');

var expect = chai.expect;

describe('helper', function() {

    it('should strip html', function(done) {
        expect(helper.stripHtml('\t<b>Hello</b> ' +
            '<em>World</em>!\r\n')).to.equal('Hello World!');
        done();
      });

    it('should prepare apn message', function(done) {
        var string229 = _.repeat('a', 229);
        expect(helper.prepareApnMessage(string229)).to.equal(string229);

        var string230 = _.repeat('a', 230);
        expect(helper.prepareApnMessage(string230)).to.equal(string230);

        var string231 = _.repeat('a', 231);
        expect(helper.prepareApnMessage(string231)).to.equal(string229 + '…');

        done();
      });

    it('should prepare subscribe data', function(done) {
        var hubTopic = 'ht';
        var hubUri = 'http://domain.com/hub';
        var hubUriWithTopic = hubUri + '?hub.topic=' + hubTopic;
        var oauthClientId = 'oci';
        var oauthToken = 'ot';
        var deviceType = 'dt';
        var deviceId = 'di';
        var extraData = {foo: 'bar'};

        expect(helper.prepareSubscribeData({
            hub_uri: hubUri,
            hub_topic: hubTopic,
            oauth_client_id: oauthClientId,
            oauth_token: oauthToken,

            device_type: deviceType,
            device_id: deviceId,
            extra_data: extraData
          })).to.deep.equal({
            hub_uri: hubUri,
            hub_topic: hubTopic,
            oauth_client_id: oauthClientId,
            oauth_token: oauthToken,

            device_type: deviceType,
            device_id: deviceId,
            extra_data: extraData,

            has_all_required_keys: true
          });

        expect(helper.prepareSubscribeData({})).to.deep.equal({
            hub_uri: '',
            hub_topic: '',
            oauth_client_id: '',
            oauth_token: '',

            device_type: '',
            device_id: '',
            extra_data: null,

            has_all_required_keys: true
          });

        expect(helper.prepareSubscribeData({
            hub_uri: hubUriWithTopic
          })).to.deep.equal({
            hub_uri: hubUriWithTopic,
            hub_topic: hubTopic,
            oauth_client_id: '',
            oauth_token: '',

            device_type: '',
            device_id: '',
            extra_data: null,

            has_all_required_keys: true
          });

        expect(helper.prepareSubscribeData({}, ['hub_uri']))
            .to.have.property('has_all_required_keys')
            .that.is.false;

        expect(helper.prepareSubscribeData({}, ['extra_data']))
            .to.have.property('has_all_required_keys')
            .that.is.true;

        done();
      });

  });
