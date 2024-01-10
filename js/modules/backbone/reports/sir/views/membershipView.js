define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'text!templates/reports/sir/tpl/membership.html',
], function(
	$,
	_, 
	Backbone,
	Hb,
	membershipTpl
){
	var membershipView = Backbone.View.extend({
		el: $('div.filters'),
		template: Handlebars.compile(membershipTpl),
		
		initialize: function() {
			var thisView = this;
		},

		getData: function()
		{
			this.render();

		},

		render: function()
		{
			var data = {
				isFullMember: this.parent.supplier.premiumListing == 1 || this.parent.supplier.smartSupplier == 1 || this.parent.supplier.smartSupplier == 1
			}

			var html = this.template(data);
			$('div.membership').html(html);
		}
	});

	return membershipView;
});