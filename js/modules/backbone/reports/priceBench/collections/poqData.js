define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/poqModel'
], function(
	$, 
	_, 
	Backbone, 
	dataModel
){
	var poqData = Backbone.Collection.extend({
		model: dataModel,
		url: '/pricebenchmark/service/price-history'
	});

	return poqData;
});