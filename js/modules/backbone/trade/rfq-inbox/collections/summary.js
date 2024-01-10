define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/summary'
], function(
	$, 
	_, 
	Backbone, 
	summaryModel
){
	var summary = Backbone.Collection.extend({
		model: summaryModel,
		url: '/trade/rfq-data?type=stats',
		parse: function(response){
			this.push(response.data);
			return this.models;	
		}
	});

	return summary;
});