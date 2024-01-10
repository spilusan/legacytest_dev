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
	var filteredCollection = Backbone.Collection.extend({
		model: model
	});

	return filteredCollection;
});