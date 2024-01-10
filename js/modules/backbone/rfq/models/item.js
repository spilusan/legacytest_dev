define([
 	'underscore',
 	'Backbone'
], function(
	_, 
	Backbone
){
	var item = Backbone.Model.extend({
	    defaults: {
	    	id: "",
	        qty: "",
	        partType: "",
	        partNo: "",
	        desc: "",
	        comm: ""
	    }
	});

	return item;
});