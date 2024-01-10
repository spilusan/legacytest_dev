define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/quoteRow'
], function(
	$, 
	_, 
	Backbone, 
	rowModel
){
	var quoteList = Backbone.Collection.extend({
		model: rowModel
	});

	return quoteList;
});