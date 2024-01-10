define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/model'
], function(
	$, 
	_, 
	Backbone, 
	model
){
	var collection = Backbone.Collection.extend({
		model: model,
		parse : function(response){
       		//api returns objects in the content attribute of response, need to override parse
	        return _.map(response.response, function(model, id) {
	            return model;
	        });
	    }
	});

	return collection;
});