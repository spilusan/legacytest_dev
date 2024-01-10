define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'text!templates/reports/marketSizing/tpl/itemView.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	itemViewTpl
){
	var itemView = Backbone.View.extend({
		tagName: 'tr',
		template: Handlebars.compile(itemViewTpl),
		first: false,
		last: false,

		events: {
			'click img.remove'   : 'removeItem',
			'click img.moveUp'   : 'moveUp',
			'click img.moveDown' : 'moveDown'
		},

		initialize: function(){
			_.bindAll(this, 'render');
			this.model.view = this;
		},

	    render: function() {
	    	if(this.first == true){
	    		$(this.el).addClass('first');
	    	}

			var data = this.model;

			data.first = this.first;
			data.last = this.last;
			//data.orders.totalLineItemQuantity = data.orders.totalLineItemQuantity.toFixed(2);
			//data.orders.totalLineItemCost = data.orders.totalLineItemCost.toFixed(2);
			//data.orders.totalCost = data.orders.totalCost.toFixed(2);
			var html = this.template(data);
			$(this.el).html(html);

			return this;
	    },

	    removeItem: function(){
	    	this.parent.ordering = true;
	    	this.parent.editAction = "remove";
	    	this.parent.keywordEdit = this.model.keywords;
	    	this.parent.getData();
	    },

	    moveUp: function(){
	    	this.parent.ordering = true;
	    	this.parent.editAction = "up";
	    	this.parent.keywordEdit = this.model.keywords;
	    	this.parent.getData();
	    },

	    moveDown: function(){
	    	this.parent.ordering = true;
	    	this.parent.editAction = "down";
	    	this.parent.keywordEdit = this.model.keywords;
	    	this.parent.getData();
	    }
	});

	return itemView;
});