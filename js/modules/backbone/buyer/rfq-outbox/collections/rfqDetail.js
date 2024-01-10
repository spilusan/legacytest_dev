define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/rfqDetail'
], function(
	$, 
	_, 
	Backbone, 
	rfqModel
){
	var rfqDetail = Backbone.Collection.extend({
		model: rfqModel,
		url: "/buyer/search/terms/"
	});

	return rfqDetail;
});