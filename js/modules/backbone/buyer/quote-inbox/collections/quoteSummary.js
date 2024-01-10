define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/quoteSummary'
], function(
	$, 
	_, 
	Backbone, 
	summaryModel
){
	var quoteSummary = Backbone.Collection.extend({
		model: summaryModel
	});

	return quoteSummary;
});