define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/section'
], function(
	$, 
	_, 
	Backbone, 
	sectionModel
){
	var sectionList = Backbone.Collection.extend({
		model: sectionModel,
		url: '/enquiry/backbone/lineItems'
	});

	return sectionList;
});