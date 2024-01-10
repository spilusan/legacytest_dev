define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/poSummary'
], function(
	$, 
	_, 
	Backbone, 
	summaryModel
){
	var poSummary = Backbone.Collection.extend({
		model: summaryModel
	});

	return poSummary;
});