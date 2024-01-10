define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/rfq'
], function(
	$, 
	_, 
	Backbone, 
	rfq
){
	var rfqData = Backbone.Collection.extend({
		model: rfq,
		url: '/trade/rfq-data?type=rfq'
	});

	return rfqData;
});