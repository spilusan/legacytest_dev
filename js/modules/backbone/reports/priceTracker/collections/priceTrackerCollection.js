define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/priceTrackerModel'
], function(
	$, 
	_, 
	Backbone, 
	dataModel
){
	var mapData = Backbone.Collection.extend({
		model: dataModel,

	});

	return mapData;
});