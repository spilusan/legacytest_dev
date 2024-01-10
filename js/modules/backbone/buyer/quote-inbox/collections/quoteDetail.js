define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/quoteDetail'
], function(
	$, 
	_, 
	Backbone, 
	quoteModel
){
	var quoteDetail = Backbone.Collection.extend({
		model: quoteModel
	});

	return quoteDetail;
});