/*
 * Chart data, 
 */
define(['highchart-line-tooltip'], function (lineTooltip) {

	return {
		url: '/reports/data/supplier-performance-quote/transactions',
		data: {
			chart: {
                renderTo: 'container',
                marginTop: 12
			},
			title: {
				text: '',
				style: {
					display: 'none'
				}
			},
			xAxis: {
				categories: [],
				crosshair: {
					label: {
						enabled: true
					},
					useHTML: true,
					width: 1,
					color: '#ccc',
					className: 'crosshair-style',
					dashStyle: 'shortdot'
				},
				tickmarkPlacement: 'on'
			},
			yAxis: {
				min: 0,
				title: {
					text: 'Transactions'
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
			tooltip: lineTooltip(),
			plotOptions: {
				series: {
					connectNulls: true
				}
			},
			series: [{}],
			credits: {
				enabled: false
			}
		}
	};
});