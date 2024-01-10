/*
 * Chart data, 
 */
define([], function(){

	return  {
		url: '/reports/data/supplier-performance-quote/quote-variance',
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
		            text: 'Quotes exactly as RFQ (%)'
		        }
		    },
		    title: {
		    	text: ''
		    },
		    legend: {
		        symbolHeight: 18,
		        symbolWidth: 18,
		        symbolRadius: 0
		    },
		    tooltip: {
				headerFormat: '',
				pointFormatter: function () {
					var text = 'Quotes exactly as RFQ -<br />';

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
				shared: true,
				useHTML: true
		    },
		    series: [],
		   	credits: {
		   		enabled: false
		   	}
		}		
	};
});