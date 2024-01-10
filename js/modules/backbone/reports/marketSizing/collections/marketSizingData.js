define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/dataRow'
], function(
	$, 
	_, 
	Backbone, 
	rowModel
){
	var marketSizingData = Backbone.Collection.extend({
		model: rowModel,
		url: " /reports/market-sizing/service/get-row-requests"
	});

	return marketSizingData;
});