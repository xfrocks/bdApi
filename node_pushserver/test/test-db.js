/*jshint expr: true*/
'use strict';

var db = require('../lib/db');
var chai = require('chai');

chai.should();

describe('db', function() {

    it('should have middleware', function(done) {
      var middleware = db.expressMiddleware();
      middleware.should.not.be.null;
      done();
    });

  });
