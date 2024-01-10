define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/shared/hbh/general',
	'text!templates/shipmate/buyer-usage-dashboard/tpl/nonVerifiedUserAccountRow.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	HbhGen,
	nonVerifiedUserAccountRowTpl
){
	var nonVerifiedUserAccountsRowView = Backbone.View.extend({
		tagName: 'tr',

		template: Handlebars.compile(nonVerifiedUserAccountRowTpl),

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

	return nonVerifiedUserAccountsRowView;
});