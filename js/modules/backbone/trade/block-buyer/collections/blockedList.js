define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/blockedRow'
], function(
	$, 
	_, 
	Backbone, 
	blockedRowModel
){
	var blockedList = Backbone.Collection.extend({
		tnid: require('trade/block-buyer/tnid'),
		model: blockedRowModel,
		url: function(){
			return '/enquiry/blocked-sender/tnid/' + this.tnid
		}
	});

	return blockedList;
});