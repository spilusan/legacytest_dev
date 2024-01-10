define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/model'
], function(
	$, 
	_, 
	Backbone, 
	Model
){
	var ApprovedSupplierList = Backbone.Collection.extend({
		model: Model
	});

	return ApprovedSupplierList;
});