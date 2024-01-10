define([
 	'underscore',
 	'Backbone'
], function(
	_, 
	Backbone
){
	var hostname = require('shipmate/targetSegments/hostname');
	var protocol = (window.location.protocol === "https:") ? 'https://' : 'http://';

	var baseUrl = hostname.split(".");
	var url = baseUrl[1];

	if(url == "myshipserv"){
		url = protocol + "match" + "." + baseUrl[1] + "." + baseUrl[2] + "/";
	}
	// @todo: replace with the URL supplier to Require.js from PHP backend in the view
	else if (/^(ukdev\d)$/.test(baseUrl[0])) {
		this.url = protocol + "ukdev" + "." + baseUrl[1] + "." + baseUrl[2] + "/match-app/";
	} else {
		url = protocol + baseUrl[0] + "." + baseUrl[1] + "." + baseUrl[2] + "/match-app/";
	}

	var model = Backbone.Model.extend({
		urlRoot: url + "supplier/segment",
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