define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/keywordModel'
], function(
	$, 
	_, 
	Backbone, 
	Model
){
	var list = Backbone.Collection.extend({
		url: "/profile/segment-keyword-list",
		model: Model
	});

	return list;
});