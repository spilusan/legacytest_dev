define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/filter'
], function(
	$, 
	_, 
	Backbone, 
	filterModel
){
	var filters = Backbone.Collection.extend({
		model: filterModel,
		url: "/buyer/filtes"
	});

	return filters;
});