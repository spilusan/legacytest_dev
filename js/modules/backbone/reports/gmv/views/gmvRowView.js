define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/shared/hbh/general',
	'../views/gmvChildRowView',
	'text!templates/reports/gmv/tpl/gmvRow.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	genHbh,
	gmvChildRowView,
	gmvRowTpl
){
	var gmvRowView = Backbone.View.extend({
		tagName: 'table',
		className: 'gmv report group',
		template: Handlebars.compile(gmvRowTpl),

		events: {
			'click' : 'toggleChild'
		},

		initialize: function(){
			_.bindAll(this, 'render');
			this.model.view = this;
		},

	    render: function() {
	    	var thisView = this;
			var data = this.model.attributes;
			data.totalSum = data.totalSum.toFixed(2);
			var html = this.template(data);
			$(this.el).html(html);
			return this;
	    },

	    renderItem: function(data) {
		    var gmvChildRow = new gmvChildRowView({
		        model: data
		    });
		    $(this.el).next('.firstChildContainer').append(gmvChildRow.render().el);
		    $(this.el).next('.firstChildContainer').append('<div class="dataContainer"></div>');
		    $(this.el).next('.firstChildContainer').append('<div class="secChildContainer"></div>');
		},

	    toggleChild: function(e) {
	    	if(!$(e.target).is('input')){
	    		var data = [];
		    	data.children = [];
		    	if($(this.el).find('tbody').hasClass('ui-state-active')){
		    		$(this.el).find('tbody').removeClass('ui-state-active');
		    		$(this.el).next('.firstChildContainer').hide();
		    	}
		    	else {
		    		if(!$(this.el).next('.firstChildContainer').is(":visible")){
		    			$(this.el).next('.firstChildContainer').show();
				    	$(this.el).find('tbody').addClass('ui-state-active');
		    		}
		    		else {
		    			_.each(this.model.attributes.CHILDREN, function(item){
				    		if(item.ID === this.model.attributes.ID){
				    			data.NAME = item.NAME;
				    			data.ID = item.ID;
				    			data.childSum = item.childSum;
				    			data.totalChildSum = item.totalChildSum;
				    			if(item.DATA){
				    				data.DATA = item.DATA;
				    				data.sortedData = item.sortedData;
				    			}
				    		}
				    		else {
				    			data.children.push(item);
				    		}
				    	}, this);
				    	if(!data['NAME']){
				    		data.NAME = this.model.attributes.NAME;
				    		data.ID = this.model.attributes.ID;
				    		data.childSum = 0;
				    		data.totalChildSum = 0;
				    	}

				    	this.renderItem(data);
				    	$(this.el).find('tbody').addClass('ui-state-active');
		    		}
		    	}
	    	}
	    }
	});

	return gmvRowView;
});