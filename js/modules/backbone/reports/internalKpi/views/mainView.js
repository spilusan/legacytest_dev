define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/shared/hbh/general',
	'../collections/kpi',
	'https://www.google.com/jsapi',
	'text!templates/reports/internalKpi/tpl/gmv.html'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	generalHbh,
	kpiColl,
	googleApi,
	gmvTpl
){
	var mainView = Backbone.View.extend({

		events: {
			'click .gmvTable' : 'rowClick'
		},

		googleChartSetting : {
		     vAxis: { 
		     	viewWindowMode: 'explicit',
		     	minValue: 0,
		        viewWindow: {
		            min:0
		        },
		    },
		    seriesType: "line",
		    is3D: false,
	        hAxis: {
			    minValue: 0,
				viewWindow: {
		            min:0,
		        },
			    textStyle: {
			       	fontSize: 10,
		        },
	        	slantedText:true,
	        	slantedTextAngle:90,
	        },
	       	titleTextStyle: {
		         	fontSize: 16,
		         	color: '#005aad',
        	},
	    },

		gmvTemplate: Handlebars.compile(gmvTpl),
		initialize: function(){
			var thisView = this;

			this.kpiCollection = new kpiColl();
			this.kpiCollection.url = '/reports/internal-supplier-kpi-report';
			
			/*
				$('body').ajaxStart(function(){
					$('#waiting').show();
				});

				$('body').ajaxStop(function(){
					$('#waiting').hide();
				});
			*/


			Handlebars.registerHelper("formatNumTwoDigits", function(str){
				if(str){
					var type = typeof str;
					if(type !== 'number'){
						str = parseFloat(str);
					}

					str = str.toFixed(2);
					str = str.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
					return str;
				} else {
					return 0;
				}
			});

			this.getData();
		},

		getData: function()
		{
			var thisView = this;
			this.kpiCollection.reset();
			this.kpiCollection.fetch({
				success: function() {
					thisView.render();
				},
				error: function(collection, response, options){
					data = JSON.parse( response.responseText);
					alert(data.error );
				}
			});
			
		},

		render: function()
		{
			thisView = this;
			if (this.kpiCollection.models[0]) {	
				var data = this.kpiCollection.models[0].attributes;
				data.colSpan = this.kpiCollection.models[0].attributes.gmv.length;
				html = this.gmvTemplate(data);
				$('#innerContent').html(html);

				google.load('visualization', '1', {packages:["corechart" , "line"], callback : function(){
				 	thisView.draw('totalGmv');
				 }});
				$(".gmvTable tbody tr").click(function(e){
					thisView.rowClick(e);
				});
				this.fixHeight();
			}


		},
		/*  For google map API */
		 draw: function( statsName ){

		 	if (this.googleChartSetting.vAxis.title) {
		 		delete this.googleChartSetting.vAxis.title;
		 	}
		 	
			var data = this.getStatsArray(statsName);
			var d = google.visualization.arrayToDataTable(data);
			if ( statsName == 'totalGmv' || statsName == 'trailingtotalGmv' || statsName == 'totalRevenue' || statsName == 'trailingrunRate')
			{
			 	var formatter = new google.visualization.NumberFormat({pattern: '#,### USD'});
				formatter.format(d, 1);			
				this.googleChartSetting.vAxis.title = 'USD';
			} else if ( statsName == 'spbCount' || statsName == 'spbInterface' )
			{
			 	var formatter = new google.visualization.NumberFormat({pattern: '#,###'});
				formatter.format(d, 1);	
			} else if (statsName == 'payingGmvPercentage' || statsName == 'trailinggmvPercentage')
			{
			 	var formatter = new google.visualization.NumberFormat({pattern: '#.##\'%\''});
				formatter.format(d, 1);		
			}

		 	var chart = new google.visualization.ComboChart(document.getElementById('chart_div'));
		 	chart.draw(d, this.googleChartSetting);

		},   

		redraw: function(trObject){
			data = getData(trObject);
		  	var d = google.visualization.arrayToDataTable(data);

		    var chart = new google.visualization.ComboChart(document.getElementById('chart_div'));
		    chart.draw(d, this.googleChartSetting);
		},

		getStatsArray: function( statName )
		{
			var result = new Array();
			var validFields = ["totalGmv","trailingtotalGmv","trailinggmvPercentage","payingGmvPercentage","annulisedRunRate","trailingrunRate","spbCount","payingTotalGmv","totalRevenue","avgMonetisation","avgPayingMonetisation", "trailingavgPayingMonetisation", "trailingavgMonetisation", "spbInterface"];
			var validFieldNames = {
				"totalGmv" : 'Total GMV (Month)'
				,"trailingtotalGmv" : 'Total GMV (trailing 12 months)'
				,"trailinggmvPercentage" : 'Percentage GMV To Paying Supplier Based On Trailing12 Months'
				,"payingGmvPercentage" : 'Percentage GMV To Paying Supplier For The Current Month'
				,"annulisedRunRate" : 'Total GMV Annualised Run Rate'
				,"trailingrunRate" : 'Total GMV Annualised Run Rate (based on previous 3 months)'
				,"spbCount" : 'Suppliers Ready To Trade'
				,"payingTotalGmv" : 'Published Supplier Sistings'
				,"totalRevenue" : 'Estimated Total Supplier Revenue'
				,"avgMonetisation" : 'Average Monetisation (Trailing 12 months)'
				,"avgPayingMonetisation" : 'Average Monetisation Of Paying Supplier Per Month'
				,"trailingavgPayingMonetisation" : 'Average Monetisation Of Payed GMV (trailing 12 months)'
				,"trailingavgMonetisation" : 'Average Monetisation (trailing 12 months)'
				,"spbInterface" : 'Published Supplier Listings'
			};

			this.googleChartSetting.title = validFieldNames[statName];

			
			var header = new Array();
			header.push('Date');
			for (fieldName in validFields) {
				var fName = validFields[fieldName];
				if (statName == 'ALL' || statName == fName) {
					header.push(validFieldNames[fName]);
				}
			}
			result.push(header);

			if (this.kpiCollection.models[0]) {	
				if (statName.substr(0,8) == 'trailing') {
					var node = this.kpiCollection.models[0].attributes.trailing;
					statFieldName = statName.substr(8);

				} else {
					var node = this.kpiCollection.models[0].attributes.gmv;
					statFieldName = statName;
				}

				for (key in node) 
						{
							var data = new Array();
							var obj = node[key];
							data.push(obj.dispDat);

							for (fieldName in validFields) {
								var fName = validFields[fieldName];
								if (statName == 'ALL' || statName == fName) {
									if (fName == 'payingGmvPercentage' || fName == 'trailinggmvPercentage') {

										var value = (parseFloat(obj.totalGmv) != 0) ? obj.payingTotalGmv / obj.totalGmv * 100 : 0;
										data.push(parseFloat(value.toFixed(2)));
									} else {
										data.push(parseFloat(obj[statFieldName]));
									}
								}
							}
							result.push(data);
						}

			}

			return result;
		},

		rowClick: function(e)
		{
			var selectedField = $(e.currentTarget).data('field');
			if (selectedField !== undefined) {
				this.draw(selectedField);
			}

		},

		fixHeight: function() {
				//fix content widht and height

				var nHeight = $('#content').height();

				if (nHeight > 0) {
	    		$('#body').height(nHeight);
		    		/* if ($(".benchTab").find('li:first').hasClass('selected') == true) { */
		    			if (true) {
		    			var newWidth = $(window).width()-260;
		    			if (newWidth<980)  {
								newWidth=980
		    			}
						$('#content').css('width' , newWidth+'px');
		    		} else {
						$('#content').css('width' , 'auto');
		    		}
		    	}
		}

	});

	return new mainView;
});