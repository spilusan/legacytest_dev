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
	var countriesCollection = Backbone.Collection.extend({
		model: model
	});

	return countriesCollection;
});