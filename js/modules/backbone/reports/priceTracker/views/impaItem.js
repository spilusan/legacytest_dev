define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'text!templates/reports/priceTracker/tpl/impaItem.html'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	impaItemTpl
){
	var impaItemView = Backbone.View.extend({
		tagName: 'li',
		className: 'N',
		impaItemTemplate: Handlebars.compile(impaItemTpl),

		events: {
			'click input.remove': 'onRemove',
			'change select.unit': 'onUnitSelect',
		},

		render: function() {
			var data = this.model.attributes;
			var html = this.impaItemTemplate(data);
			$(this.el).html(html);
			if ($(this.el).find('select').first().length !== 0) {
				this.model.attributes.selectedUnit = $(this.el).find('select').first().val();
			}
			return this;
		},

		onRemove: function(e) {
			e.preventDefault();
			this.model.collection.remove(this.model);
			this.parent.renderImpaItems();
		},
		onUnitSelect: function(e)
		{
			this.model.attributes.selectedUnit = $(e.target).val();

		}

	});

	return impaItemView;
});