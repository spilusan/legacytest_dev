define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'../models/supplier',
	'text!templates/rfq/tpl/supplier.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	supplier,
	supplierTpl
){
	var supplierView = Backbone.View.extend({
		tagName: 'li',
		
		template: Handlebars.compile(supplierTpl),

		initialize: function(){
			_.bindAll(this, 'render');
		},

	    render: function() {
	    	/*if(this.solo == 1) {
	    		this.model.attributes.solo = true;
	    	}*/

			var html = this.template(this.model.attributes);
			$(this.el).html(html);

	        return this;
	    }
	});

	return supplierView;
});