define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'text!templates/reports/priceBench/tpl/impaItem.html'
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
			'click input.remove' 			: 'onRemove',
			'change select.munit'			: 'selectUnit',
		},

		render: function() {
			var parentView = this.parent;
			var thisView = this;
			//var data = this.model;
			//thisView.model.selectedUnit = thisView.model.attributes.units[0];
			thisView.model.itemid = thisView.model.attributes.itemid;
			var html = thisView.impaItemTemplate(thisView.model);
			$(this.el).html(html);
			if (thisView.model.attributes.units === false) {
				$(thisView.el).find('select.munit').hide();
				parentView.parent.endless = true;
				$('#waiting').hide();
				$.getJSON('/pricebenchmark/input/get-ordered-product-units?impaCode='+encodeURIComponent(thisView.model.attributes.itemid)+'&onlyMine='+thisView.model.attributes.mine, function(result){
					if (result.products[thisView.model.attributes.itemid]) {
						thisView.model.attributes.units = result.products[thisView.model.attributes.itemid];
						if (thisView.model.attributes.units[0] !== undefined) {
							thisView.model.selectedUnit = thisView.model.attributes.units[0];
							var selectElement = $(thisView.el).find('select.munit');
							for (key in thisView.model.attributes.units) {
								var optionElement = $('<option>');
								optionElement.html(thisView.model.attributes.units[key]); 
								selectElement.append(optionElement);
							}
						}
					}
					selectElement.show();
					$(thisView.el).find('img.smallWaitIcon').hide();
					parentView.parent.endless = false;
					//parentView.parent.itemsRendered = true;

					
	       		 });
			} else {
				$(thisView.el).find('img.smallWaitIcon').hide();
					if (parentView.autoShow) {
						thisView.model.selectedUnit = window.priceTrackerParams.unit;
						parentView.autoShow = false;
						parentView.onShowClicked( null );
					}
					parentView.parent.fixHeight();

			}

			return this;
		},

		onRemove: function(e) {
			e.preventDefault();
			this.model.collection.remove(this.model);
			this.parent.renderImpaItems();
		},

		selectUnit: function(e) {
			this.model.selectedUnit = $(e.currentTarget).val();
		}
	});

	return impaItemView;
});