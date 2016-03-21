'use strict';

var db = exports;
var config = require('./config');
var debug = require('debug')('pushserver:db');
var _ = require('lodash');
var mongoose = require('mongoose');

var mongoUri = config.db.mongoUri;
mongoose.connect(config.db.mongoUri, function (err) {
    if (err) {
        debug('Error connecting to the MongoDb.', err);
    } else {
        debug('Connected', mongoUri);
    }
});

db.devices = require('./db/Device')(mongoose);
db.projects = require('./db/Project')(mongoose);

db.expressMiddleware = function () {
    var mongoExpress = require('mongo-express/lib/middleware');
    var mongoUriParser = require('mongo-uri');

    var mec = require('mongo-express/config.default');
    mec.useBasicAuth = false;
    mec.options.readOnly = true;

    var mongoUriParsed = mongoUriParser.parse(mongoUri);
    _.assign(mec.mongodb, {
        server: _.first(mongoUriParsed.hosts),
        port: _.first(mongoUriParsed.ports),
        useSSL: false
    });
    if (mec.mongodb.port === null) {
        mec.mongodb.port = 27017;
    }
    mec.mongodb.auth = [];
    if (mongoUriParsed.database) {
        var auth = {
            database: mongoUriParsed.database
        };
        if (mongoUriParsed.username !== null
            && mongoUriParsed.password !== null) {
            auth.username = mongoUriParsed.username;
            auth.password = mongoUriParsed.password;
        }
        mec.mongodb.auth.push(auth);
    }

    return mongoExpress(mec);
};