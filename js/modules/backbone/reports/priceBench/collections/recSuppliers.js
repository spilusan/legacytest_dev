define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/recSuppliers'
], function(
	$, 
	_, 
	Backbone, 
	recSuppliersModel
){
	var recSuppliers = Backbone.Collection.extend({
		model: recSuppliersModel,
		url: '/pricebenchmark/service/recommended-suppliers',
	});

	return recSuppliers;
});