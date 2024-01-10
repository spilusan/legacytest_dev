/*
 * Chart data, 
 */
define([], function () {

	return {
		url: '/reports/data/supplier-performance-quote/response-time',
		data: {
			chart: {
				type: 'column',
                renderTo: 'container',
                marginTop: 12
			},
			xAxis: {
				type: 'category'
			},
			yAxis: {
				title: {
					text: 'Average response time (hours)'
				}
			},
			title: {
				text: ''
			},
			tooltip: {
				headerFormat: '',
				pointFormatter: function () {
					var text = 'Average response time -<br />';

					switch (this.category) {
						case 0:
							text += '<strong>This supplier with you:</strong>';
							break;

						case 1:
							text += '<strong>All suppliers with you:</strong>';
							break;

						case 2:
							text += '<strong>ShipServ average:</strong>';
							break;
					}

					text += '<br />' + Math.round(this.y) + (Math.round(this.y) == 1 ? ' hour' : ' hours');

					return text;
				},
				shared: false,
				useHTML: true
			},
			series: [],
			credits: {
				enabled: false
			}
		}

	};

});