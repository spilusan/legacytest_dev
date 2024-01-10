/*
 * Quoting Charts
 */

'use strict';

define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'../charts/avg-response-time',
	'../charts/avg-response-trend',
	'../charts/decline-reason.sa',
	'../charts/decline-reason-stu',
	'../charts/decline-reason-swu',
	'../charts/quote-completeness',
	'../charts/quote-completeness-trend',
	'../charts/quote-variance',
	'../charts/quote-variance-trend',
	'../charts/response-rate',
	'../charts/response-rate-trend',
	'../charts/rfq-qoute',
	'../collections/collection',
	'text!templates/spr/tpl/quotingView.html',
	'text!templates/spr/tpl/rfqQotSummary.html'
], function (
	$,
	_,
	Backbone,
	Hb,
	AvgResponseTime,
	AvgResponseTrend,
	DeclineReasonSa,
	DeclineReasonStu,
	DeclineReasonSwu,
	QuoteCompleteness,
	QuoteCompletenessTrend,
	QuoteVariance,
	QuoteVarianceTrend,
	ResponseRate,
	ResponseRateTrend,
	rfqQoute,
	Collection,
	quotingTpl,
	rfqQotSummaryTpl
) {
	var errorHTML = '<div class="load-error">Error loading data</div>';
	var view = Backbone.View.extend({
		events: {
			/* 'click a' : 'render' */
		},
		selectedSupplier: 0,
		params: [],
		chartListData: null,
		quotingTemplate: Handlebars.compile(quotingTpl),
		rfqQotSummaryTemplate: Handlebars.compile(rfqQotSummaryTpl),
		initialize: function () {
			var thisView = this;
			this.collection = new Collection();
			this.chartListData = {
				chart2: rfqQoute,
				chart3: ResponseRate,
				chart4: ResponseRateTrend,
				chart5: AvgResponseTime,
				chart6: AvgResponseTrend,
				chart7: QuoteCompleteness,
				chart8: QuoteCompletenessTrend,
				chart9: QuoteVariance,
				chart10: QuoteVarianceTrend,
				chart11: DeclineReasonSwu,
				chart12: DeclineReasonStu,
				chart13: DeclineReasonSa
			};

			this.isDataLoaded = false;
			this.completedRequests = 0;

			this.on('complete', function (event, request, settings) {
				++this.completedRequests;

				if (this.completedRequests === 2) {
					this.isDataLoaded = true;
				}
			});
		},

		getData: function (params, reload) {

			if (!reload && this.isDataLoaded) return;

			this.params = params;

			var html = this.quotingTemplate();
			$('#tab-3').empty();
			$('#tab-3').html(html);
			this.render();
		},

		render: function () {
			//TODO load all chart data and render them all one by one
			var thisView = this;

			var filterData = {
				tnid: this.params.suppliers.join(","),
				byb: this.params.buyers.join(","),
				period: this.params.daterange
			};

			if (this.params.startdate) {
				filterData.startdate = this.params.startdate;
				filterData.enddate = this.params.enddate;
			}

			this.loadRfqAndQuote(filterData);

			$.each(thisView.chartListData, function (key, listData) {
				$.ajax({
					url: listData.url,
					type: 'GET',
					dataType: 'text',
					data: filterData,
					success: function (result) {
						var chart = null;
						var cData = listData.data;
						var jsonResult = JSON.parse(result);
						cData.series = thisView.mergeRecursive(cData.series, jsonResult.data);

						if (jsonResult.axis) {
							cData.xAxis.categories = jsonResult.axis;
						}

						$('#' + key).empty();

						switch (key) {

							case 'chart5':
							case 'chart7':
							case 'chart9':
								// cData.series = cData.series[0].data.map(function (x, i) {
								// 	return {
								// 		name: x[0],
								// 		data: [x[1]]
								// 	}
								// });

								chart = Highcharts.chart(key, cData);
								break;

							case 'chart2':
								chart = Highcharts.chart(key, cData);
								break;

							case 'chart4':
								chart = Highcharts.chart(key, cData);
								thisView.customLegend(chart, key);
								break;

							case 'chart11':
							case 'chart12':
							case 'chart13':
								if (jsonResult.data[0].data.length === 0) {
									chart = Highcharts.chart(key, {
										chart: {
											type: 'pie',
											height: 350
										},
										title: {
											text: 'No data',
											floating: true,
											verticalAlign: 'middle',
											y: 5
										},
										series: [{
											data: [{
												y: 100,
												dataLabels: {
													enabled: false
												},
												color: '#ccc'
											}]
										}],
										legend: false
									});
								} else {
									chart = Highcharts.chart(key, cData);
								}

								break;

							default:
								chart = Highcharts.chart(key, cData);
								break;
						}

					},
					error: function (e, etxt) {
						$('#' + key).empty();
						$('#' + key).append(errorHTML);
					}
				}).done(function () {
					thisView.trigger('complete');
				});
			});

			$('.tip').shTooltip();
		},

		/*
		 * Recursively merge properties of two objects 
		 */
		mergeRecursive: function (obj1, obj2) {
			for (var p in obj2) {
				try {
					if (obj2[p].constructor == Object) {
						obj1[p] = mergeRecursive(obj1[p], obj2[p]);
					} else {
						obj1[p] = obj2[p];
					}
				} catch (e) {
					obj1[p] = obj2[p];
				}
			}

			return obj1;
		},

		customLegend: function (chart, elementName) {
			var legend = $('<div>');

			legend.addClass('customLegend');

			$('#' + elementName).append(legend);

			$.each(chart.series, function (j, data) {
				legend.append('<div class="item"><div class="symbol" style="background-color:' + data.color + '"></div><div class="serieName" id="">' + data.name + '</div></div>');
			});

			$('.customLegend .item').click(function () {
				var inx = $(this).index(),
					point = chart.series[inx];
				if (point.visible) {
					point.setVisible(false);
					$(this).css('opacity', '0.3');
				} else {
					point.setVisible(true);
					$(this).css('opacity', '1');
				}
			});
		},

		loadRfqAndQuote: function (filterData) {
			//rfqQotSummaryTemplate
			var thisView = this;
			$.ajax({
				url: '/reports/data/supplier-performance-quote/rfq-and-quote-summary',
				type: 'GET',
				dataType: 'text',
				data: filterData,
				success: function (result) {
					var html = thisView.rfqQotSummaryTemplate(JSON.parse(result));
					$('#chart1').empty();
					$('#chart1').append($(html));
				},
				error: function (e, etxt) {
					$('#chart1').empty();
					$('#chart1').append(errorHTML);
				}
			}).done(function () {
				thisView.trigger('complete');
			});
		}



	});
	return new view();
});