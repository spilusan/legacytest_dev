define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'libs/zingchart',
	'text!templates/reports/priceBench/tpl/unitsOvertime.html',
	'../collections/poqData',
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	zingchart,
	unitsOvertimeTpl,
	poqData
){

	var unitsOvertimeView = Backbone.View.extend({
		el: $('.dataBox'),

		events: {

		},

		unitsOvertimeTemplate: Handlebars.compile(unitsOvertimeTpl),

		initialize: function() {
			var thisView = this;
			this.poqChartDataCollection = new poqData();
		},

		render: function() {
			var thisView = this;
			/* var data = this.model.attributes; 
			var html = this.unitsOvertimeTemplate(data);*/
			var html = this.unitsOvertimeTemplate();
			$(this.el).html(html);

			this.startPoqZingchart();
			this.startAvgZingchart();

			return this;
		},


		getData: function(e) {

			var thisView = this;

			this.poqChartDataCollection.reset();
			if (this.parent.hasImpaCodeList()) {
				this.poqChartDataCollection.fetch({
					add: true,
					remove: false,
					data: $.param({
						products: this.parent.getImpaCodeList(),
						//query: this.keywords,
						pageNo: this.parent.rightPageNo,
						pageSize: this.parent.pageSize,
						filter: {
							dateFrom: this.parent.dateFrom,
							dateTo: this.parent.dateTo,
							vessel: this.parent.vessel,
							location: this.parent.location,
							excludeRight: this.parent.excludeRight,
							refineQuery: this.parent.refineRightQuery
						},
						sortBy: this.parent.sortRight,
						sortDir: this.parent.sortOrderRight
					}),
					complete: function() {
						thisView.render();
					}
				});
			} else {
				thisView.render();
			}
		},

		//Purchase order zing chart
		startPoqZingchart: function(){

			var thisView = this;

			if (this.poqChartDataCollection.models[0]) {

				var valueList = [];
				var xTextValueList = [];
				_.each(this.poqChartDataCollection.models[0].attributes.months, function(item, key) {

					var value = item.orderQuantity;
					value = value.toFixed(2);
					valueList.push(parseFloat(value));
					xTextValueList.push(key);
			    }, this);

							var seriesData = [
								{
									"text" : "Your purchase order quantity",
									"values": valueList
								},
								];


				/* Render Chart 1 */
				zingchart.exec("poqChart","destroy");	
				zingchart.render(
					{
				        id:"poqChart",
				        height:320,
				        width:"100%",
				        data: {
						"graphset" : [
						{
							"background-color":"#ffffff",
							"type" : "bar",
							"labels":[
					                {
										"text":"<strong>Purchase order quantity</strong>",
										"text-align":"left",
										"x":"25px",
										"y":"5px",
										"height":"30px",
										"width":"400px",
										"font-family":"Arial",
										"font-size":"20px",
										"border-width":"0px",
										"background-color":"white",
										"shadow":false,
					                }
					            ],
							"chart": {
							    "background-color": "#fff"
							},
							"plot": {
								"background-color": "#90dcf7",
								"bar-width":"90%",
								    "value-box":{
									        "visible":true,
									        "text":"&nbsp; %v &nbsp;",
									        "border-color":"#13b5ea",
	                						"border-width":2,
	                						"border-radius":"6px 6px",
	                						"font-color":"#000",
	                						"font-size": "9px",
	                						"font-weight": "normal",
	                						"background-color":"white",
	                						"text-align":"center",
	                						"behavior":"url(css/PIE.htc)"
									    }
							},

							"plotarea" : {
								"margin" : "60 20 60 60",
								 "offset-y":"-20px"
							},
							"legend" : {
								"border-color" : "transparent",
								"border-width" : 0,
								"background-color" : "transparent",
								"shadow" : 0,
								"layout":"float",

							},

							"scale-x": {
								"auto-fit": false,
								"values": xTextValueList,
								"items-overlap": true,
								"item":{
									"font-size":"10px",
									"font-angle":90, 
									"auto-align":true
								},
							},

							"tooltip" : {
								"visible" : true
							},
							"scale-y" : {
								"zooming" : 0
							},

							"series" : seriesData,
						}
					]
					}
	    			}
	    		);
			}
		},

		//Avg price chart
		startAvgZingchart: function(){

			var thisView = this;

			if (this.poqChartDataCollection.models[0]) {

				var pValueList = [];
				var qValueList = [];
				var xTextValueList = [];

				_.each(this.poqChartDataCollection.models[0].attributes.months, function(item, key) {
					//pValueList.push(item.get('pPrice'));
					//qValueList.push(item.get('qPrice'));
					var orderWeightedUnitPrice = item.orderUnitCost;
					orderWeightedUnitPrice = orderWeightedUnitPrice.toFixed(2);

					var quoteWeightedUnitPrice = item.quoteUnitCost;
					quoteWeightedUnitPrice = quoteWeightedUnitPrice.toFixed(2);


					pValueList.push(parseFloat(orderWeightedUnitPrice));
					qValueList.push(parseFloat(quoteWeightedUnitPrice));
					xTextValueList.push(key);
			    }, this);


							var seriesData = [
								{
									"text" : "Your avg. purchase price",
									"values": pValueList
								},
								{
									"text" : "Market avg. quoted price",
									"values": qValueList
								}
								];

				/* Render Chart 2 */
				zingchart.exec("avgChart","destroy");		
				zingchart.render(
					{
				        id:"avgChart",
				        height:320,
				        width:"100%",
				        data: {
						"graphset" : [
						{
							"background-color":"#ffffff",
							"type" : "line",
							"labels":[
					                {
										"text":"<strong>Your avg. purchase price vs. Market avg. quoted price</strong> [USD per unit]",
										"text-align":"left",
										"x":"25px",
										"y":"5px",
										"height":"30px",
										"width":"450px",
										"font-family":"Arial",
										"font-size":"20px",
										"border-width":"0px",
										"background-color":"white",
										"shadow":false,
					                }
					            ],

							"chart": {
							    "background-color": "#fff"
							},

							"plot":{
								"marker":{
									"size":6,

								}
							},
		

							"plotarea" : {
								"margin" : "60 20 60 60",
								 "offset-y":"-20px"
							},

							"legend" : {
								"border-color" : "transparent",
								"border-width" : 0,
								"background-color" : "transparent",
								"shadow" : 0,
								"layout":"float",

									"marker":{
										"border-width":0,
										"type":"circle"
								},

							},

							"scale-x": {
								"values": xTextValueList,
								"items-overlap":true,
								"item":{
									"font-size":"10px",
									"font-angle":90, 
									"auto-align":true
								}
							},
							"tooltip" : {
								"visible" : true
							},
							"scale-y" : {
								"zooming" : 0
							},

							 "series": seriesData,
						}
					]
					}
	    			}
	    		);
			}
		},

	});

	return unitsOvertimeView;
});