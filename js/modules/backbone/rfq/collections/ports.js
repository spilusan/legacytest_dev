define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/port'
], function(
	$, 
	_, 
	Backbone, 
	portModel
){
	var ports = Backbone.Collection.extend({
		model: portModel,
		url: '/enquiry/data/type/port'
	});

	return ports;
});