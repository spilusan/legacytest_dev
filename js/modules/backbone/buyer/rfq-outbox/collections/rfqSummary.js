define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/rfqSummary'
], function(
	$, 
	_, 
	Backbone, 
	summaryModel
){
	var rfqSummary = Backbone.Collection.extend({
		model: summaryModel,
		url: '/buyer/search/rfq-savings/'
	});

	return rfqSummary;
});