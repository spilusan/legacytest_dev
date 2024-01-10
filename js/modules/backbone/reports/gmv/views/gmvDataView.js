define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'text!templates/reports/gmv/tpl/gmvSecChildRow.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	gmvSecChildRowTpl
){
	var gmvChildRowView = Backbone.View.extend({
		tagName: 'table',
		className: 'gmv report parent',
		template: Handlebars.compile(gmvChildRowTpl),
		secChildTemplate: Handlebars.compile(gmvSecChildRowTpl),
		events: {
			'click' : 'toggleChildren'
		},

		initialize: function(){
			_.bindAll(this, 'render');
			this.model.view = this;
		},

	    render: function() {
	    	var thisView = this;
			var data = this.model;
			data.childSum = data.childSum.toFixed(2);

			var html = this.template(data);
			$(this.el).html(html);
			return this;
	    },

	    toggleChildren: function() {
	    	if($(this.el).find('tbody').hasClass('ui-state-active')){
	    		$(this.el).find('tbody').removeClass('ui-state-active');
	    		$(this.el).next('.secChildContainer').hide();
	    	}
	    	else {
	    		if(!$(this.el).next('.secChildContainer').is(":visible")){
	    			$(this.el).next('.secChildContainer').show();
			    	$(this.el).find('tbody').addClass('ui-state-active');
	    		}
	    		else {
			    	var html = this.secChildTemplate(this.model);
			    	$(this.el).next('.secChildContainer').html(html);
			    	$(this.el).find('tbody').addClass('ui-state-active');
	    		}
	    	}
	    }
	});

	return gmvChildRowView;
});