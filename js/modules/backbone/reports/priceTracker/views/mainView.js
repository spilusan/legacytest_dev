define([
	'jquery',
	'underscore',
	'Backbone',
    'handlebars',
    'backbone/shared/hbh/general',
	'libs/jquery.tools.overlay.modified',
	'libs/jquery.uniform',
	'../views/filters',
	'../collections/priceTrackerCollection',
	'text!templates/reports/priceTracker/tpl/row.html',
	'text!templates/reports/priceTracker/tpl/explain.html'

], function(
	$, 
	_, 
	Backbone,
    Hb,
    General,
	Modal,
	Uniform,
	filters,
	priceTrackerCollection,
	rowTpl,
	explainTpl
){
	var priceTrackerView = Backbone.View.extend({
		el: 'body',
		endless: false,
		trackerCollection: null,
		rowTemplate: Handlebars.compile(rowTpl),
		explainTemplate: Handlebars.compile(explainTpl),
		
        events: {
           /*  'click .showBuyerBranches' : 'getData' */
           /* 'click .dataBox tr': 'rowClick', */
        },


		initialize: function(){

			var thisView = this;

			this.trackerCollection = new priceTrackerCollection();
			this.trackerCollection.url = '/pricebenchmark/price-tracker/get-savings';

			$('body').ajaxStart(function(){
				if(!thisView.endless){
					$('#waiting').show();
				}
			});

			$('body').ajaxStop(function(){
				if(!thisView.endless){
					$('#waiting').hide();
				}
			});

			this.filtersView = new filters();
			this.filtersView.parent = this;
			//this.getData();
			this.fixHeight();

			$(window).resize(function(){
				thisView.fixHeight();
	    	});
		},
		
		
		getData: function(e) 
		{
        	 var thisView = this;
        	$('.dataBox').html('');
        	/*
        	$('#tableCaption').html('Date range: <b>'+$('select[name="dateRange"] option:selected').text()+ '</b>');
        	*/

        	var dateSub = parseInt($('select[name="dateRange"]').val());
        	var productCount = $('select[name="productCount"]').val();

        	var value = $('input[name="keywordselect"]').val();
        	var data = {
						dayRange: dateSub,
						productCount: productCount,
						};

        	if ('' != value) data.refine = value;

			this.getJsonResult( data );
		},
		
		/*
		// The old getdata, if we have to put autocomp;ete back
        getData: function(e) {

        	var thisView = this;
        	$('.dataBox').html('');
        	$('#tableCaption').html('Date range: <b>'+$('select[name="dateRange"] option:selected').text()+ '</b>');
        	var data = [];

			var dateSub = parseInt($('select[name="dateRange"]').val());
            if (dateSub > 0) {
            		var defaultDate = window.defaultFromDate;
            		var defaultDateParts = defaultDate.split('/');
	                var d = new Date(parseInt(defaultDateParts[2])+2000,parseInt(defaultDateParts[1])-1,parseInt(defaultDateParts[0]));
	                d.setDate(d.getDate()-dateSub);
	                data.dateFrom = d.toISOString().substr(0,10);
            }
        	var i = 0;
             _.each(thisView.filtersView.impaCollection.models, function(item){
             					var products = new Object();
             					products.impa = item.attributes.id;

             		        	if (item.attributes.unitExists) {
             		        		products.unit = item.attributes.selectedUnit;
             		        	} else {
             		        		products.unit= '';
             		        	}
             		        	data.push(products);
             		        	
             		        	i++;

            }, this);

             this.getJsonResult( data );
        },
        */

        render: function(  )
        {
        	if (this.rowTemplate(this.trackerCollection.models[0])) {
        		for (var key in this.trackerCollection.models[0].attributes.products) {
	        		var html = this.rowTemplate(this.trackerCollection.models[0].attributes.products[key]);
					$('.dataBox').append(html);	
        		}
        		var displayKeywords = $('input[name="keywordselect"]').val() != '';
        		var data = {
        		dataRange: $('select[name="dateRange"] option:selected').text(),
        		keywords: $('input[name="keywordselect"]').val(),
        		displayKeywords: displayKeywords,
        		count: this.trackerCollection.models[0].attributes.products.length,
        		totalCount: this.trackerCollection.models[0].attributes.totalCount,
        	};
        	var htnl = this.explainTemplate(data);

        	$('#tableCaption').html(htnl);
				this.fixHeight();
			}
        },

		fixHeight: function() {
				var nHeight = $('#content').height();
				if (nHeight > 0) {
	    		$('#body').height(nHeight + 60);
		    		/* if ($(".benchTab").find('li:first').hasClass('selected') == true) { */
		    			if (true) {
		    			var newWidth = $(window).width()-260;
		    			if (newWidth<980)  {
								newWidth=980;
		    			}
						$('#content').css('width' , newWidth+'px');
		    		} else {
						$('#content').css('width' , 'auto');
		    		}
		    	}
		},

		getJsonResult: function( data )
		{
			//TODO check if why parameters are not sent
			var thisView = this;
			this.trackerCollection.reset();	
			this.trackerCollection.fetch({
				add: true,
				remove: false,
				data: data,
				complete: function() {
					thisView.render();
				}
			});

		},
		/*
		rowClick: function(e)
		{
			document.location = $(e.currentTarget).data('href');
		}
		*/
	});

	return new priceTrackerView();
});