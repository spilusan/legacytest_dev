/*
 * Chart data, 
 */
define([], function(){

	return  {
		url: '/reports/data/supplier-performance-quote/response-rate',
		data: {
			// colors: ['#f8e71d', '#7ed320', '#4fe3c1', '#4990e2'],
		 	chart: {
		        type: 'column',
                renderTo: 'container',
                marginTop: 12
		    },
		    xAxis: {
		        categories: ['This supplier<br>with you', 'All suppliers<br>with you', 'ShipServ<br>average']
		    },
		    yAxis: {
		        min: 0,
		        title: {
		            text: 'Response rate (%)'
		        }
		    },
		    title: {
			    text:''
			},
		    tooltip: {
				headerFormat: '{series.name}<br />',
			    pointFormat: '{point.percentage:.0f}%',
				shared: false,
				useHTML: true
		    },
		    plotOptions: {
		        column: {
		            stacking: 'percent'
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