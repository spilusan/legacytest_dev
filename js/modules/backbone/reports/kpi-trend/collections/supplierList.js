define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/supplierList'
], function(
	$, 
	_, 
	Backbone, 
	dataModel
){
	var data = Backbone.Collection.extend({
		model: dataModel,

	});

	return data;
});