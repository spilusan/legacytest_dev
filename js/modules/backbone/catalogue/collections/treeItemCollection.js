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
	var treeItemCollection = Backbone.Collection.extend({
		model: treeItemModel,
	});

	return treeItemCollection;
});
