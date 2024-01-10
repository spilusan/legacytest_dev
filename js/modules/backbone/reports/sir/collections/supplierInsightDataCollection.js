define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/supplierInsightDataModel'
], function(
	$, 
	_, 
	Backbone, 
	supplierInsightDataModel
){
	var supplierInsightDataCollection = Backbone.Collection.extend({
		model: supplierInsightDataModel,
		url: '/reports/supplier-insight-data'
	});

	return supplierInsightDataCollection;
});
