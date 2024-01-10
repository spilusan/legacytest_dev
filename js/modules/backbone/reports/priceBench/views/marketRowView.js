define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'text!templates/reports/priceBench/tpl/marketRow.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	marketRowTpl
){
	var marketRowView = Backbone.View.extend({
		tagName: 'tr',
		mTemplate: Handlebars.compile(marketRowTpl),

		events: {
			'click a.delete' : 'onDelete'
		},

		initialize: function(){
			_.bindAll(this, 'render');
		},

	    render: function() {
			var data = this.model.attributes;
			
			var html = this.mTemplate(data),
			thisView = this;

			$(this.el).html(html);

			return this;
	    },

	    onDelete: function() {
	    	this.parent.mCollection.reset();
		$('.rightData .dataContainer .data table tbody').html('');
	    	this.parent.rightPageNo = 1;
	    	this.parent.excludeRight.push(this.model.attributes.descriptionHash);
	    	this.parent.getMarketDataOnly();
	    }
	});

	return marketRowView;
});