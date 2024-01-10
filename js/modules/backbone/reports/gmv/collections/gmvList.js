define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/gmvRow'
], function(
	$, 
	_, 
	Backbone, 
	rowModel
){
	var gmvList = Backbone.Collection.extend({
		model: rowModel,
		comparator: function(item) {
	      return item.get("NAME");
	    }
	});

	return gmvList;
});