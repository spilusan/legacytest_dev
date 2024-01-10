define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/rfqRow'
], function(
	$, 
	_, 
	Backbone, 
	rowModel
){
	var rfqList = Backbone.Collection.extend({
		model: rowModel,
		url: "/buyer/rfq"
	});

	return rfqList;
});