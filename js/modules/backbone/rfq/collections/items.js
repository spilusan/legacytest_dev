define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/item'
], function(
	$, 
	_, 
	Backbone, 
	itemModel
){
	var itemList = Backbone.Collection.extend({
		model: itemModel,
		url: '/enquiry/backbone/lineItems'
	});

	return itemList;
});