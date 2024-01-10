define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'text!templates/profile/matchSettings/tpl/listItem.html'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	supplierItemTpl
){
	var supplierItemView = Backbone.View.extend({
		tagName: 'span',
		className: 'N',
		supplierItemTemplate: Handlebars.compile(supplierItemTpl),

		events: {
			'click input.remove' 			: 'onRemove',
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
			this.parent.renderBlacklists();
		}
	});

	return supplierItemView;
});