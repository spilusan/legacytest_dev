define([
    'jquery',
    'underscore',
    'Backbone',
    '../models/catalogueModel'
], function(
    $,
    _,
    Backbone,
    catalogueModel
){
    var catalogueCollection = Backbone.Collection.extend({
        model: catalogueModel,
    });

    return catalogueCollection;
});
