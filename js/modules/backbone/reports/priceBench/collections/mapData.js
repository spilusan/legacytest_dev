define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/mapModel'
], function(
	$, 
	_, 
	Backbone, 
	dataModel
){
	var mapData = Backbone.Collection.extend({
		model: dataModel,
		url: '/pricebenchmark/service/order-locations'
	});

	return mapData;
});