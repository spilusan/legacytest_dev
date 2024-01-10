define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/shared/hbh/general',
	'text!templates/reports/gmv/tpl/gmvSecChildRow.html',
	'text!templates/reports/gmv/tpl/dataTable.html',
	'text!templates/reports/gmv/tpl/dataRow.html',
	'text!templates/reports/gmv/tpl/dataFooter.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	genHbh,
	gmvSecChildRowTpl,
	dataTableTpl,
	dataRowTpl,
	dataFooterTpl
){
	var gmvSecChildRowView = Backbone.View.extend({
		tagName: 'table',
		className: 'gmv report child',
		template: Handlebars.compile(gmvSecChildRowTpl),
		dataTableTemplate: Handlebars.compile(dataTableTpl),
		dataRowTemplate: Handlebars.compile(dataRowTpl),
		dataFooterTemplate: Handlebars.compile(dataFooterTpl),

		canMarkAsInvalid: require('reports/gmv/canMarkAsInvalid'),

		events: {
			'click' : 'toggleData'
		},

		initialize: function(){
			_.bindAll(this, 'render');
			this.model.view = this;
			//test
		},

	    render: function() {
	    	if(this.canMarkAsInvalid === 0){
	    		this.canMarkAsInvalid = false;
	    	}
	    	else {
	    		this.canMarkAsInvalid = true;
	    	}

	    	var thisView = this;
			var data = this.model;
			data.childSum = data.childSum.toFixed(2);
			data.totalChildSum = data.totalChildSum.toFixed(2);

			var html = this.template(data);
			$(this.el).html(html);
			return this;
	    },

	    toggleData: function(e) {
	    	if(!$(e.target).is('input')){
		    	var data = [];
		    	data.rows = [];
		    	if($(this.el).find('tbody').hasClass('ui-state-active')){
		    		$(this.el).find('tbody').removeClass('ui-state-active');
		    		$(this.el).next('.dataContainer').hide();
		    	}
		    	else {
		    		if(!$(this.el).next('.dataContainer').is(":visible")){
		    			$(this.el).next('.dataContainer').show();
				    	$(this.el).find('tbody').addClass('ui-state-active');
		    		}
		    		else {
		    			_.each(this.model.sortedData, function(item){
				    		_.each(item, function(dataItem){
				    			if(typeof dataItem[''] === "number"){
					    			dataItem['total-cost-usd'] = dataItem['total-cost-usd'].toFixed(2);
					    			dataItem['adjusted-cost'] = dataItem['adjusted-cost'].toFixed(2);
					    			dataItem['total-cost'] = dataItem['total-cost'].toFixed(2);
					    		}
					    		dataItem['canMark'] = this.canMarkAsInvalid;
				    			data.rows.push(dataItem);
				    		}, this);
				    	}, this);

		    			data.totalTrans = this.model.DATA.length;
	    				data.childSum = this.model.childSum;
	    				data.totalChildSum = this.model.totalChildSum;
	    				data.currencies = this.model.currencies;
	    				data.level = "child";

				    	var html = this.dataTableTemplate(data);
				    	$(this.el).next('.dataContainer').html(html);
				    	html = this.dataRowTemplate(data);
				    	$(this.el).next('.dataContainer').find('table tbody').append(html);
				    	html = this.dataFooterTemplate(data);
				    	$(this.el).next('.dataContainer').find('table tbody').append(html);
				    	$(this.el).find('tbody').addClass('ui-state-active');
		    		}
		    	}
		    }
	    }
	});

	return gmvSecChildRowView;
});