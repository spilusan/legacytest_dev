define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/sirModel'
], function(
	$, 
	_, 
	Backbone, 
	sirModel
){
	var sirCollection = Backbone.Collection.extend({
		model: sirModel
	});

	return sirCollection;
});