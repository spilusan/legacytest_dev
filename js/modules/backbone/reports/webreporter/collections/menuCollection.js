define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/menuModel'
], function(
	$, 
	_, 
	Backbone, 
	menuModel
){
	var menuCollection = Backbone.Collection.extend({
		model: menuModel,

	});

	return menuCollection;
});