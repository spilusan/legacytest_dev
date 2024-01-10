define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/poRow'
], function(
	$, 
	_, 
	Backbone, 
	rowModel
){
	var poList = Backbone.Collection.extend({
		model: rowModel
	});

	return poList;
});