'use strict';

var debug = require('debug')('pushserver:db:Project');
var _ = require('lodash');

exports = module.exports = function (mongoose) {
    var projectSchema = new mongoose.Schema({
        project_type: {type: String, required: true},
        project_id: {type: String, required: true},
        configuration: {type: Object, required: true},
        created: {type: Date, default: Date.now},
        last_updated: {type: Date, default: Date.now}
    });
    projectSchema.index({project_type: 1, project_id: 1});
    var projectModel = mongoose.model('projects', projectSchema);

    return {
        _model: projectModel,

        saveApn: function (appId, certData, keyData, otherOptions, callback) {
            var configuration = _.assign({}, {
                cert: certData,
                key: keyData
            }, otherOptions);

            return this.save('apn', appId, configuration, callback);
        },

        saveGcm: function (packageId, apiKey, callback) {
            return this.save('gcm', packageId, {api_key: apiKey}, callback);
        },

        saveWns: function (packageId, clientId, clientSecret, callback) {
            return this.save('wns', packageId, {
                client_id: clientId,
                client_secret: clientSecret
            }, callback);
        },

        save: function (projectType, projectId, configuration, callback) {
            var tryUpdating = function () {
                projectModel.findOne({
                    project_type: projectType,
                    project_id: projectId
                }, function (err, project) {
                    if (!err && project) {
                        project.configuration = _.assign({}, project.configuration, configuration);
                        project.last_updated = Date.now();
                        project.save(function (err, updatedProject) {
                            if (!err && updatedProject) {
                                updateDone(updatedProject);
                            } else {
                                updateFailed(err);
                            }
                        });
                    } else {
                        updateFailed(err);
                    }
                });
            };

            var tryInserting = function () {
                var project = new projectModel({
                    project_type: projectType,
                    project_id: projectId,
                    configuration: configuration
                });

                project.save(function (err, insertedProject) {
                    if (!err) {
                        insertDone(insertedProject);
                    } else {
                        insertFailed(err);
                    }
                });
            };

            var updateDone = function (project) {
                debug('Updated project', projectType, projectId, project._id);
                done('updated');
            };

            var updateFailed = function (err) {
                if (err) {
                    debug('Unable to update project', projectType, projectId, err);
                }
                tryInserting();
            };

            var insertDone = function (project) {
                debug('Saved project', projectType, projectId, project._id);
                done('inserted');
            };

            var insertFailed = function (err) {
                debug('Unable to insert project', projectType, projectId, err);
                done(false);
            };

            var done = function (result) {
                if (_.isFunction(callback)) {
                    callback(result);
                }
            };

            tryUpdating();
        },

        findProject: function (projectType, projectId, callback) {
            var done = function (project) {
                if (_.isFunction(callback)) {
                    callback(project);
                }
            };

            projectModel.findOne({
                project_type: projectType,
                project_id: projectId
            }, function (err, project) {
                if (!err && project) {
                    done(project);
                } else {
                    debug('Error finding project', projectType, projectId, err);
                    done(null);
                }
            });
        },

        findConfig: function (projectType, projectId, callback) {
            this.findProject(projectType, projectId, function (project) {
                if (_.isFunction(callback)) {
                    if (project) {
                        callback(project.configuration);
                    } else {
                        callback(null);
                    }

                }
            });
        }
    };
};