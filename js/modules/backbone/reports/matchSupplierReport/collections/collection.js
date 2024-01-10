define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/model'
], function(
	$, 
	_, 
	Backbone, 
	Model
){
	var collection = Backbone.Collection.extend({
		model: Model
	});

	return collection;
});