define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/dataModel'
], function(
	$, 
	_, 
	Backbone, 
	dataModel
){
	var marketData = Backbone.Collection.extend({
		model: dataModel,
		url: '/pricebenchmark/service/quoted'
	});

	return marketData;
});