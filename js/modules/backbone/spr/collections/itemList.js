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
		url: '/module/data?params=params'
	});

	return itemList;
});