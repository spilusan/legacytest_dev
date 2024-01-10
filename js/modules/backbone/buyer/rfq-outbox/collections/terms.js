define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/rfqDetail'
], function(
	$, 
	_, 
	Backbone, 
	termsModel
){
	var termsCollection = Backbone.Collection.extend({
		model: termsModel,
	});

	return termsCollection;
});