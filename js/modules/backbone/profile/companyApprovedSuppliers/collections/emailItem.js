define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/model'
], function(
	$, 
	_, 
	Backbone, 
	Model
){
	var EmailList = Backbone.Collection.extend({
		model: Model
	});

	return EmailList;
});