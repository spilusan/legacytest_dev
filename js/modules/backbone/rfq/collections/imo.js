define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/imo'
], function(
	$, 
	_, 
	Backbone, 
	imoModel
){
	var imo = Backbone.Collection.extend({
		model: imoModel,
		url: '/enquiry/data/type/imo'
	});

	return imo;
});