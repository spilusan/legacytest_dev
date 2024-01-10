define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/supplier'
], function(
	$, 
	_, 
	Backbone, 
	supplierModel
){
	var supplierList = Backbone.Collection.extend({
		model: supplierModel
	});

	return supplierList;
});