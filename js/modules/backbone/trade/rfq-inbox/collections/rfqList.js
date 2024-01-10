define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/rfqRow'
], function(
	$, 
	_, 
	Backbone, 
	summaryModel
){
	var rfqList = Backbone.Collection.extend({
		model: summaryModel,
		url: '/trade/rfq-data?type=rfqs'
	});

	return rfqList;
});