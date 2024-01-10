define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/shared/hbh/general',
	'text!templates/shipmate/buyer-usage-dashboard/tpl/failedSignInsRow.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	HbhGen,
	failedSignInsRowTpl
){
	var succSignInsRowView = Backbone.View.extend({
		tagName: 'tr',

		template: Handlebars.compile(failedSignInsRowTpl),

		events: {

		},
		
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

	return succSignInsRowView;
});