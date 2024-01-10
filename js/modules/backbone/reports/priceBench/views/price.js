define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'text!templates/reports/priceBench/tpl/price.html'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	priceTpl
){

	var priceView = Backbone.View.extend({
		el: $('.dataBox'),
		events: {

		},

		priceTemplate: Handlebars.compile(priceTpl),

		initialize: function() {
			var thisView = this;
			/* thisView.render(); */
		},

		render: function() {

			var thisView = this;
			/* var data = this.model.attributes; 
			var html = this.priceTemplate(data);*/
			var data = {
				refineLeftQuery: this.parent.refineLeftQuery,
				refineRightQuery: this.parent.refineRightQuery,
				sortLeft: this.parent.sortLeft,
				sortOrderLeft: this.parent.sortOrderLeft,
				sortRight: this.parent.sortRight,
				sortOrderRight: this.parent.sortOrderRight,
				li_desc: "li_desc",
				li_count: "li_count",
				total_qty: "total_qty",
				unit_cost: "unit_cost",
				ord_ref_no: "ord_ref_no",
				ord_date: "ord_date",
				supplier_name: "supplier_name",
				li_qty: "li_qty",
				uom: "uom",
				total_cost: "total_cost"

			}

			var html = this.priceTemplate(data);
			$(this.el).html(html);
			
			return this;
		},
		 getData : function() {
			var thisView = this;
			thisView.render();
		}

	});

	return priceView;
});