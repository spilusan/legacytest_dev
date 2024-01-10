define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'text!templates/boiler/basicTpl.html'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	basicTpl
){
	var basicView = Backbone.View.extend({
		
		el: $('body'),

		events: {
			'click a' : 'render'
		},

		basicTemplate: Handlebars.compile(basicTpl),


		initialize: function () {
			_.bindAll(this, 'render');
		},

		render: function() {
			var html = basicTemplate();
			$(this.el).html();
		}
	});

	return new basicView();
});
