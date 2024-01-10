/*
 * Chart data, 
 */
define([], function () {	
	return {
		url: '/reports/data/supplier-performance-quote/decline-reason',
		data: {
			colors: ['#777777', '#0F5BA2', '#2980D0', '#13B5EA', '#90DCF7'],
			chart: {
				type: 'pie',
				height: window.isPrint ? 250 : 600
			},
			title: {
				text: null
			},
			plotOptions: {
				pie: {
					center: window.isPrint ? ['50%', '75px'] : ['50%', '150px']
				},
				series: {
					dataLabels: {
						format: '{point.percentage:.1f}%'
					}
				},
			},
			legend: {
				layout: 'vertical'
			},
			tooltip: {
				pointFormat: '{point.name}: {point.percentage:,.1f}%',
				useHTML: true,
				shared: false
			},
			series: [{
			}],
			credits: {
				enabled: false
			}
		}
	};

});