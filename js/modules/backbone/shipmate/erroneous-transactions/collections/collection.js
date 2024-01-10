define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/model'
], function(
	$, 
	_, 
	Backbone, 
	customersModel
){
	var customersCollection = Backbone.Collection.extend({
		model: customersModel
	});

	return customersCollection;
});