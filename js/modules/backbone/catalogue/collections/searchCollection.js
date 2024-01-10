define([
    'jquery',
    'underscore',
    'Backbone',
    '../models/treeItemModel'
], function(
    $,
    _,
    Backbone,
    treeItemModel
){
    var searchCollection = Backbone.Collection.extend({
        model: treeItemModel,
    });

    return searchCollection;
});
