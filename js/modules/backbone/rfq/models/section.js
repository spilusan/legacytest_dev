define([
 	'underscore',
 	'Backbone'
], function(
	_, 
	Backbone
){
	var section = Backbone.Model.extend({
	    defaults: {
	    	id: ""
	    }
	});

	return section;
});