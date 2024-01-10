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
	var availableList = Backbone.Collection.extend({
		model: blackListModel,
		parse: function(response){			
			response.suggestions[0].query = response.query;
			for (var i = 0, length = response.suggestions.length; i < length; i++) {
				this.push(response.suggestions[i]);
			}
			
			return this.models;
		}
	});

	return availableList;
});