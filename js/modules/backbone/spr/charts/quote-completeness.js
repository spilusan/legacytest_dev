/*
 * Chart data, 
 */
define([], function(){

	return {
		url: '/reports/data/supplier-performance-quote/quote-completeness',
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
				min: 0,
				max: 100,
				tickInterval: 10,
				minorTickINterval: 2,
		        title: {
		            text: 'Percentage of quotes complete (%)'
		        }
		    },
		    title: {
			    text:''
			},
		    tooltip: {
				headerFormat: '',
				pointFormatter: function () {
					var text = 'Percentage of quotes complete -<br />';

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

					text += '<br />' + Math.round(this.y) + '%';

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