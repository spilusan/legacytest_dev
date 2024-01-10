define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/model'
], function(
	$, 
	_, 
	Backbone, 
	model
){
	var collection = Backbone.Collection.extend({
		model: model
	});

	return collection;
});