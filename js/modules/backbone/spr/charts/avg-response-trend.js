/*
 * Chart data, 
 */
define([], function () {

	return {
		url: '/reports/data/supplier-performance-quote/response-time-trend',
		data: {
            chart: {
                renderTo: 'container',
                marginTop: 12
            },
			title: false,
			xAxis: {
				categories: ['Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'Maj', 'Jun'],
				tickInterval: 1,
				labels: {
					formatter: function () {
						return this.value.replace(' ', '<br />');
					}
				},
				tickmarkPlacement: 'on'
			},
			yAxis: {
				title: {
					text: 'Average response time (hours)'
				},
				crosshair: {
					label: {
						enabled: true
					},
					useHTML: true,
					width: 1,
					color: '#ccc',
					className: 'crosshair-style',
					dashStyle: 'shortdot'
				}
			},
			tooltip: {
				headerFormat: '{point.x}<br />',
				pointFormater: function (point) {
					return Highcharts.numberFormat(point.y, 0, '.', ',') + point.y == 1 ? ' hour' : ' hours';
				},
				shared: false
			},
			plotOptions: {
				series: {
					color: '#13B5EA',
					connectNulls: true
				},
				pie: {

				}
			},
			series: [{
				color: "#13B5EA",
				pointStart: 1
			}],
			credits: {
				enabled: false
			}
		}
	};

});