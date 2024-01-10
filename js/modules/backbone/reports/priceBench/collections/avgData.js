/* Can be removed */
define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/avgModel'
], function(
	$, 
	_, 
	Backbone, 
	dataModel
){
	var avgData = Backbone.Collection.extend({
		model: dataModel,
		//url: '/pricebenchmark/service/purchased'
	});

	return avgData;
});