define([
 	'underscore',
 	'Backbone'
], function(
	_, 
	Backbone
){
	var model = Backbone.Model.extend({
		parse : function(response){
			if(response.response) {
				if(typeof response.respoonse === 'object'){
					this.attributes = response.response;	
				}
			}
			else {
				return response;
			}
       		//api returns objects in the content attribute of response, need to override parse
	        /*return _.map(response.response, function(model, id) {
	            return model;
	        });*/
	    }
	});

	return model;
});