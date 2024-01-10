define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'text!templates/profile/matchSettings/tpl/availableListItem.html'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	availableSupplierItemTpl
){
	var availableSupplierItemView = Backbone.View.extend({
		tagName: 'li',
		className: 'selected',
		supplierItemTemplate: Handlebars.compile(availableSupplierItemTpl),

		events: {
			'click' 			: 'onRemove',
		},

		render: function() {
			var data = this.model.attributes;
			var html = this.supplierItemTemplate(data);

			$(this.el).html(html);

			return this;
		},

		onRemove: function(e) {
			e.preventDefault();
			this.model.collection.remove(this.model);
			this.parent.renderAvailableFwdSuppliers();
		}
	});

	return availableSupplierItemView;
});