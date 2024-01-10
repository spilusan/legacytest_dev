define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/blackListModel'
], function(
	$, 
	_, 
	Backbone, 
	blackListModel
){
	var blackList = Backbone.Collection.extend({
		model: blackListModel,
		comparator: function(item) {
	    	return item.get("name");
	    }
	});

	return blackList;
});