define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	 '../collections/recSuppliers',
	 //'../views/recItem'
	 'text!templates/reports/priceBench/tpl/recItem.html'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	recItem,
	recView
){
	var recItemView = Backbone.View.extend({

		recItemTemplate: Handlebars.compile(recView),

		events: {
			
		},

		render: function() {
			
			var data = this.model;
			var html = this.recItemTemplate(data);
			$(this.el).html(html);

			return this;
		},

	});

	return recItemView;
});