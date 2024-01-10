/*
 * Chart data, 
 */
define([], function(){

	return  {
		url: '/reports/data/supplier-performance-quote/quote-variance-trend',
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
				min: 0,
				max: 100,
				tickInterval: 10,
				minorTickINterval: 2,
		    	title: {
		    		text: 'Quotes exactly as RFQ (%)'
		    	}
		    },
		    tooltip: {
		    	headerFormat: '{point.x}<br />',
			    pointFormat: '{series.name}: {point.y:.0f}%',
		        shared: false
		    },
		    plotOptions: {
		        series: {
		            color: '#4990e2',
		            connectNulls: true
		        }
		    },
		    series: [],
		    credits: {
		   		enabled: false
		   	}
		}
	};

});