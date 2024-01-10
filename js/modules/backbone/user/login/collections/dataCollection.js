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
	var dataCollection = Backbone.Collection.extend({
		model: dataModel
	});

	return dataCollection;
});