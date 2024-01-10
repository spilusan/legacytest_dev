define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/shared/hbh/general',
	'https://www.google.com/jsapi',
	'../collections/supplierList',
	'../views/filtersView',
	'text!templates/reports/kpi-trend/tpl/volume.html',
	'text!templates/reports/kpi-trend/tpl/responseRate.html',
	'text!templates/reports/kpi-trend/tpl/winRate.html',
	'text!templates/reports/kpi-trend/tpl/gmv.html',
	'text!templates/reports/kpi-trend/tpl/orderConversion.html'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	general,
	googleApi,
	collection,
	filters,
	volumeTpl,
	responseRateTpl,
	winRateTpl,
	gmvTpl,
	orderConversionTpl

){
	var mainView = Backbone.View.extend({
		
		el: $('body'),

		googleChartSetting : {
		    seriesType: "line",
		    is3D: true,
	        hAxis: {
	          textStyle: {
		         	fontSize: 10,
	        	}
	        },
	        vAxis: {
	        title: '',
	          textStyle: {
		         	fontSize: 10,
	        	}
	        }
		},

		events: {
		},
		volumeTemplate: Handlebars.compile(volumeTpl),
		responseRateTemplage: Handlebars.compile(responseRateTpl),
		winRateTemplage: Handlebars.compile(winRateTpl),
		gmvTemplate: Handlebars.compile(gmvTpl),
		orderConversionTemplate: Handlebars.compile(orderConversionTpl),
		
		initialize: function () {
			var thisView = this;
			
			$('body').ajaxStart(function(){
					$('#waiting').show();
			});

			$('body').ajaxStop(function(){
					$('#waiting').hide();
			});
			this.filtersView = new filters();
			this.filtersView.parent = this;
			this.filtersView.getData();

			this.kpiTrendCollection = new collection();
			this.kpiTrendCollection.url = '/reports/kpi-trend-report';

			this.kpiDirectVolumeCollection = new collection();
			this.kpiDirectVolumeCollection.url = '/reports/kpi-trend-report';

			this.kpiTrendGmvCollection = new collection();
			this.kpiTrendGmvCollection.url = '/reports/kpi-trend-report';

			this.kpiTrendQotCollection = new collection();
			this.kpiTrendQotCollection.url = '/reports/kpi-trend-report';

			google.load('visualization', '1', {packages:["corechart"], callback : function(){
				 	//thisView.draw('ALL');
			}});

			/* New handlebars helper */
			Handlebars.registerHelper("formatCurrencyNa", function(str){
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

		},

		getData: function() {
			$('#result').html('');
			var thisView = this;

			var monthsBack = parseInt($('select[name="dateRange"]').val());
			var today = new Date();
			var dateTo = new Date(today.getFullYear(), today.getMonth(), -1);
			var dateFrom = new Date(dateTo.getFullYear(), dateTo.getMonth()-monthsBack+1, 1);


			var tnid = $(this.filtersView.selectedElement).data('branchCode');
			this.kpiTrendCollection.reset();	
			this.kpiTrendGmvCollection.reset();	
			this.kpiDirectVolumeCollection.reset();
			this.kpiTrendQotCollection.reset();
			var isChildChecked = $("input[name='children'").attr('checked');


			if (monthsBack == 0) {
				var params = {
					tnid: tnid,
					showChild: isChildChecked,
					/*tnid: 52323, */
				};

			} else {
				var params = {
					datefrom: $.format.date(dateFrom, "yyyyMMdd"),
					dateto: $.format.date(dateTo, "yyyyMMdd"),
					tnid: tnid,
					showChild: isChildChecked,
					/* tnid: 52323, */
				};
				
			}	

			params.type = 'directvolume';		
			this.kpiDirectVolumeCollection.fetch({
				data: params,
				add: true,
				remove: false,
				complete: function() {
					thisView.renderDirect();
				}
			});

			
			params.type = 'volume';		
			this.kpiTrendCollection.fetch({
				data: params,
				add: true,
				remove: false,
				complete: function() {
					thisView.render();
				}
			});

			/*  get GMV data */
			params.type = 'gmv';		
			this.kpiTrendGmvCollection.fetch({
				data: params,
				add: true,
				remove: false,
				complete: function() {
					thisView.renderGmv();
				}
			});

			/*  get Qot data */
			params.type = 'qot';		
			this.kpiTrendQotCollection.fetch({
				data: params,
				add: true,
				remove: false,
				complete: function() {
					thisView.renderQot();
				}
			});


		},

		render: function() {
		if (this.kpiTrendCollection.models[0]) {

			var html = this.responseRateTemplage(this.kpiTrendCollection.models[0].attributes);
			$('#responseRateResult').html(html);

			var html = this.winRateTemplage(this.kpiTrendCollection.models[0].attributes);
			$('#winRateResult').html(html);


			/* Render Response rate chart */
			var arr = [];
			arr.push(['Date','Overall Response Rate','Quote Rate','Decline Rate']);
			for (var prop in this.kpiTrendCollection.models[0].attributes.data) {

				var obj= this.kpiTrendCollection.models[0].attributes.data[prop];
				arr.push([
					obj.date,
					parseFloat(obj.overallResponseRate),
					parseFloat(obj.quoteRate),
					parseFloat(obj.declineRate),
					]);
			}
			this.draw(arr, 'response_chart', 'Rate');

			/* Render Win rate chart */
			var arr = [];
			arr.push(['Date','Win Rate']);
			for (var prop in this.kpiTrendCollection.models[0].attributes.data) {

				var obj= this.kpiTrendCollection.models[0].attributes.data[prop];
				arr.push([
					obj.date,
					parseFloat(obj.winRate),
					]);
			}
			this.draw(arr, 'win_chart', "Win rate");
			
			}
			
		},

		renderDirect: function()
		{
			if (this.kpiDirectVolumeCollection.models[0]) {
				var html = this.volumeTemplate(this.kpiDirectVolumeCollection.models[0].attributes);
				$('#volumeResult').html(html);
							/* Render volume chart */
			var arr = [];
			arr.push(['Date','Number of RFQs','Number of Quotes','Number of POs','Number of POCs']);
			for (var prop in this.kpiDirectVolumeCollection.models[0].attributes.data) {

				var obj= this.kpiDirectVolumeCollection.models[0].attributes.data[prop];
				arr.push([
					obj.date,
					parseFloat(obj.rfqCount),
					parseFloat(obj.qotCount),
					parseFloat(obj.poCount),
					parseFloat(obj.pocCount)
					]);

			}
			this.draw(arr, 'volume_chart', 'Volume');
			}
		},

		renderQot: function()
		{
			if (this.kpiTrendQotCollection.models[0]) {
				var html = this.orderConversionTemplate(this.kpiTrendQotCollection.models[0].attributes);
				$('#conversionResult').html(html);
							/* Render volume chart */
				/* Render GMV  chart */
				var arr = [];
				arr.push(['Date','Value Ordered', 'Value Quoted' ,'%quoted GMV ordered']);
				for (var prop in this.kpiTrendQotCollection.models[0].attributes.data) {

					var obj= this.kpiTrendQotCollection.models[0].attributes.data[prop];
					arr.push([
						obj.date,
						parseFloat(obj.sir2Po),
						parseFloat(obj.sir2QOT),
						parseFloat(obj.Conversion),

						]);
				}
				this.drawWithTwoDecimals(arr, 'conversion_chart', 'USD');
			}
		},

		renderGmv: function()
		{
			if (this.kpiTrendGmvCollection.models[0]) {
				var html = this.gmvTemplate(this.kpiTrendGmvCollection.models[0].attributes);
				$('#gmvResult').html(html);

				/* Render GMV  chart */
				var arr = [];
				arr.push(['Date', 'Value Quoted', 'Value Ordered']);
				for (var prop in this.kpiTrendGmvCollection.models[0].attributes.data) {
					var obj= this.kpiTrendGmvCollection.models[0].attributes.data[prop];
					arr.push([
						obj.date,
						parseFloat(obj.sir2ORD),
						parseFloat(obj.sir2GMV),
						]);
				}

				this.drawWithTwoDecimals(arr, 'gmv_chart', 'USD');
			}
		},


		draw: function( chartData, holderId, title ){
		 	var d = google.visualization.arrayToDataTable(chartData);
		 	var chart = new google.visualization.ComboChart(document.getElementById(holderId));
		 	this.googleChartSetting.vAxis.title = title;
		 	chart.draw(d, this.googleChartSetting);
		},

        drawWithTwoDecimals: function( chartData, holderId, title ){
            var d = google.visualization.arrayToDataTable(chartData);
            var formatter = new google.visualization.NumberFormat({pattern: '#,#00.00#'});

            // format number columns
            for (var i = 1; i < d.getNumberOfColumns(); i++) {
                formatter.format(d, i);
            }

            var chart = new google.visualization.ComboChart(document.getElementById(holderId));
            this.googleChartSetting.vAxis.title = title;
            chart.draw(d, this.googleChartSetting);
        }

    });

	return new mainView;
});
