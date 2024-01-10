/*
 * Chart data, 
 */
define([], function () {

	return {
		url: '/reports/data/supplier-performance-quote/response-rate-trend',
		data: {
			// colors: ['#f8e71d', '#7ed320', '#4fe3c1', '#4990e2'],
			chart: {
				type: 'column',
                renderTo: 'container',
                marginTop: 12
			},
			title: false,
			xAxis: {
				categories: [],
				labels: {
					formatter: function () {
						return this.value.replace(' ', '<br />');
					}
				}
			},
			yAxis: {
				min: 0,
				max: 100,
				tickInterval: 10,
				minorTickINterval: 2,
				title: {
					text: 'Response rate (%)'
				}
			},
			tooltip: {
				headerFormat: '{series.name}<br />',
				pointFormat: '{point.percentage:.0f}%',
				shared: false
			},
			plotOptions: {
				column: {
					stacking: 'percent',
				}
			},
			series: [{
			}],
			credits: {
				enabled: false
			}
		}
	};

});