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
	var marketSizingVesselData = Backbone.Collection.extend({
		model: rowModel,
		url: "/reports/market-sizing/service/get-vessel-types"
	});

	return marketSizingVesselData;
});