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
	var yourData = Backbone.Collection.extend({
		model: dataModel,
		url: '/pricebenchmark/service/purchased'
	});

	return yourData;
});