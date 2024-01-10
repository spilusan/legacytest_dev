/**
 * Show google map. 
 * Add this to the phtml view:
 * 		$this->compressedScript()->appendFile('https://maps.googleapis.com/maps/api/js?key=' . $this->googleMapsApiKey . '&sensor=false');
 * Add this to your controller
 * 		$config = Zend_Registry::get('options');
 * $this->view->googleMapsApiKey = $config['google']['services']['maps']['apiKey'];
 */
define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars'
], function(
	$, 
	_, 
	Backbone, 
	Hb
){
	var view = Backbone.View.extend({
		
		initialize: function () {
			
		},
		
		/**
		 * Show the map by address
		 */
		show: function(containerElement, address, title) {
			if (containerElement) {
				if ($('#' + containerElement).length !== 0) {
					geocoder =  new google.maps.Geocoder();
				    geocoder.geocode({ 'address': address}, function(results, status) {
				    	if (status == google.maps.GeocoderStatus.OK) {
				    		var mapOptions = {
				    			zoom: 13,
				    			center: results[0].geometry.location,
				    			mapTypeId: google.maps.MapTypeId.ROADMAP,
				    			fullscreenControl: true
				    		};
	
				      		map = new google.maps.Map(document.getElementById(containerElement), mapOptions);
	
				        	marker = new google.maps.Marker({
				            	map: map,
				            	position: results[0].geometry.location,
				            	title: title
				            });
				        } else {
				        	$('#' + containerElement).hide();
				        }
				    });
				}
			}
		},
		
		/**
		 * Show Google Map by Lattitude, Longtitude. The latLongCoord must be a comma separated string
		 */
		showByLatLong: function(containerElement, lat, long, title) {
			var latlong = new google.maps.LatLng(lat, long);
			var mapOptions = {
    			zoom: 13,
    			center: latlong,
    			mapTypeId: google.maps.MapTypeId.ROADMAP,
    			fullscreenControl: true
        		};

			map = new google.maps.Map(document.getElementById(containerElement), mapOptions);
			marker = new google.maps.Marker({
            	map: map,
            	position: latlong,
            	title: title
            });
		}
	});

	return new view();
});
