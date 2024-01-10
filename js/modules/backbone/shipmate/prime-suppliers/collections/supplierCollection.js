define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/supplierModel'
], function(
	$, 
	_, 
	Backbone, 
	supplierModel
){
	var supplierCollection = Backbone.Collection.extend({
		model: supplierModel,
		parse : function(response){
       		//api returns objects in the content attribute of response, need to override parse
	        return _.map(response.response, function(model, id) {
	            return model;
	        });
	    }
	});

	return supplierCollection;
});