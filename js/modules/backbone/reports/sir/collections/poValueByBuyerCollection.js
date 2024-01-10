define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/poValueByBuyerModel'
], function(
	$, 
	_, 
	Backbone, 
	poValueByBuyerModel
){
	var poValueByBuyerCollection = Backbone.Collection.extend({
		model: poValueByBuyerModel,
		url: '/reports/supplier-insight-data'
	});

	return poValueByBuyerCollection;
});