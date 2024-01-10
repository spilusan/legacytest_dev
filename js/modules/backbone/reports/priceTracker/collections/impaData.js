define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/impaModel'
], function(
	$, 
	_, 
	Backbone, 
	impaModel
){
	var impaList = Backbone.Collection.extend({
		model: impaModel,
		comparator: function(item) {
	    	return item.get("name");
	    }
	});

	return impaList;
});