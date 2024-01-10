define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/kpi'
], function(
	$, 
	_, 
	Backbone, 
	Model
){
	var kpiCollection = Backbone.Collection.extend({
		model: Model
	});

	return kpiCollection;
});
