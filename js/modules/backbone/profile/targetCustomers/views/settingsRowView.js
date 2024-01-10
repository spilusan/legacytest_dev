define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/shared/hbh/general',
	'libs/jquery.tools.overlay.modified',
	'libs/jquery.uniform',
	'text!templates/profile/targetCustomers/tpl/settingsRow.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	HbhGen,
	Modal,
	Uniform,
	settingsRowTpl
){
	var settingsRowView = Backbone.View.extend({
		tagName: 'tr',

		template: Handlebars.compile(settingsRowTpl),
		
		initialize: function(){
			_.bindAll(this, 'render');
			this.model.view = this;
		},

	    render: function() {
			var data = this.model.attributes;

			var html = this.template(data);

			$(this.el).html(html);

	        return this;
	    }
	});

	return settingsRowView;
});