define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/poDetail'
], function(
	$, 
	_, 
	Backbone, 
	poModel
){
	var poDetail = Backbone.Collection.extend({
		model: poModel
	});

	return poDetail;
});