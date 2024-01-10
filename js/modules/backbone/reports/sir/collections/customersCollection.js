/*
* TODO This file can be deleted
*/
define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/customersModel'
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