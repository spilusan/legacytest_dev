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
	var checkList = Backbone.Collection.extend({
		model: Model
	});

	return checkList;
});