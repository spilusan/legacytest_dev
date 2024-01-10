/**
 * Event Map - jquery.eventmap.js
 * 
 * Creates a Google map on the specified element and adds events
 * 
 * Dependencies: jQuery, Google Maps API
 * 
 * @project ShipServ Pages
 * @author Dave Starling dstarling@shipserv.com
 * @version 0.1
 */
(function($) {
	$.fn.eventMap = function(options) {
		var config = { 
			speed : 5000,
			messageBox : '#message',
			eventUrl : '/events/fetch-recent-events-hp/fetch/',
			eventCount : 10,
			mainTitleTag : 'h3',
			subTitleTag : 'h4',
			mapZoom : 3,
			showTrails: true,
			fullScreen: false,
			iframe: false
		};
		
		if (options) $.extend(config, options);
		
		

		this.each(function() {
			
			var infobox,
				self = this,
				markers = new Array(),
				trails = new Array(),
				gJSON,
				gCounter = 0,
				timerId,
				map,
				marker,
				latlong = new google.maps.LatLng(51.50722,-0.12750),
				mapOptions = {
					zoom: config.mapZoom,
			        center: latlong,
			        mapTypeId: google.maps.MapTypeId.ROADMAP,
			        draggable: false, 
			        zoomControl: false, 
			        scrollwheel: false, 
			        disableDoubleClickZoom: true,
			        mapTypeControl: false,
			        streetViewControl: false
				};

			if(config.fullScreen == true){
				var height = $(window).height();
				height = height - $('#header').height();
				if(height < 200){
					height = 200;
				}
				height =  height + "px";
				var el = '#'+$(this)[0].id;
				$(el).css({'height' : height});
			}

			if(config.iframe == true){
				var height = $(window).height();
				height =  height + "px";
				var el = '#'+$(this)[0].id;
				$(el).css({'height' : height});
			}

			map = new google.maps.Map(document.getElementById($(this)[0].id), mapOptions);

			function clearMarkers() {
				for (var i = 0; i < markers.length; i++ ) {
			    	markers[i].setMap(null);
				}
				markers = new Array();
			}

			function clearTrails() {
				for (var i = 0; i < trails.length; i++ ) {
			    	trails[i].setMap(null);
				}
				trails = new Array();
			}
			
			function addLocation(location) {
				if ((!location.lat || !location.lng) && 'address' in location) {
					var geocoder = new google.maps.Geocoder();
					var address = [location.address.address1, location.address.address2, location.address.city, location.address.state, location.address.zip, location.address.country];
					address = address.map(function(e){return (e? e : '');}).join(', ');
					geocoder.geocode({'address' : address}, function(point) {
						if (point && point.length > 0) {
							marker = new google.maps.Marker({
								map: map,
								position: point[0].geometry.location
							});
							markers.push(marker);
							map.panTo(marker.getPosition());
							showMessage(marker, location);
						}
				   });
				} else {
					if(location.lat && location.lng){
						var point = new google.maps.LatLng(location.lat, location.lng);
						marker = new google.maps.Marker({
							map: map,
							position: point
						});
						markers.push(marker);
						map.panTo(marker.getPosition());
						showMessage(marker, location);
					}
				}
			}
			
			function addSpider(location) {
				// add a marker for the sender
				if ((!location.sender.lat || !location.sender.lng) && 'address' in location.sender) {
					var geocoder = new google.maps.Geocoder();
					var address = [location.sender.address.address1, location.sender.address.address2, location.sender.address.city, location.sender.address.state, location.sender.address.zip, location.sender.address.country];
					address = address.map(function(e){return (e? e : '');}).join(', ');
					geocoder.geocode( {'address':address}, function(point) {
						if (point && point.length > 0) {
							marker = new google.maps.Marker({
								map: map,
								position: point[0].geometry.location
							});
							map.panTo(marker.getPosition());
							showMessage(marker, location);
							markers.push(marker);
							if (config.showTrails)
							{
								showTrails(point[0].geometry.location, location.recipients);
							}
						}
				   });
				} else {
					var point = new google.maps.LatLng(location.sender.lat, location.sender.lng);
					marker = new google.maps.Marker({
						map: map,
						position: point
					});
					map.panTo(marker.getPosition());
					showMessage(marker, location);
					markers.push(marker);
					if (config.showTrails)
					{
						showTrails(point, location.recipients);
					}
				}
			}
			
			function showTrails (point, recipients) {
				for (r in recipients) {
					var recip = recipients[r];
					
					if ((!recip.lat || !recip.lng) && 'address' in recip) {
						var geocoder = new google.maps.Geocoder();
						var address = [recip.address.address1, recip.address.address2, recip.address.city, recip.address.state, recip.address.zip, recip.address.country];
						address = address.map(function(e){return (e? e : '');}).join(', ');
						geocoder.geocode({'address':address}, function(rpoint) {
							if (rpoint && rpoint.length > 0) {
								var rmarker = new google.maps.Marker({
									map: map,
									position: rpoint[0].geometry.location
								});

								markers.push(rmarker);

								var flightPlanCoordinates = [
								    point,
								    rpoint[0].geometry.location
								];

								var flightPath = new google.maps.Polyline({
								    path: flightPlanCoordinates,
								    geodesic: true,
								    strokeColor: '#ffffff',
								    strokeOpacity: 0.7,
								    strokeWeight: 3
								});
								trails.push(flightPath);
								flightPath.setMap(map);
							}
						   });
					} else {
						var rpoint = new google.maps.LatLng(recip.lat, recip.lng);
						rmarker = new google.maps.Marker(rpoint);
						markers.push(rmarker);
						var flightPlanCoordinates = [
						    point,
						    rpoint
						];

						var flightPath = new google.maps.Polyline({
						    path: flightPlanCoordinates,
						    geodesic: true,
						    strokeColor: '#ffffff',
						    strokeOpacity: 0.7,
						    strokeWeight: 3
						});
						trails.push(flightPath);
						flightPath.setMap(map);	
					}
				}
			}
			
			function showMessage(marker, location) {
				var message ='<div id="message"><div class="icon">';
					message += '<' + config.mainTitleTag + '>' + location.mainTitle + '</' + config.mainTitleTag + '>';
					message += '<' + config.subTitleTag + '>' + location.subTitle + '</' + config.subTitleTag + '>';
					message += '</div></div>';

				var bg = 'url(/img/map/icon-' + location.eventType + '.png) 12px 7px no-repeat';
				
				cursor = 'default';

				if (typeof(location.link) != 'undefined') {
					cursor = 'pointer';
				}

				infobox = new InfoBox({
			        content: message,
			        disableAutoPan: false,
			        maxWidth: 178,
			        pixelOffset: new google.maps.Size(-89, 0),
			        zIndex: null,
			        boxStyle: {
			            cursor: cursor,
			            width: "178px",
			            height: "auto"
			        },
			        closeBoxURL: "",
			        infoBoxClearance: new google.maps.Size(1, 1)
			    });

			    infobox.open(map, marker);

			    google.maps.event.addListener(infobox,'domready',function(){ 
			    	$('#message .icon').css({
				    	background: bg
				    });
				    $('#message').fadeIn();
		            $('#message').bind('click',function(){
						if (typeof(location.link) != 'undefined') {
							if (!jQuery.browser.msie) {
								window.location = location.link;
								return false;
							}
							
							var a = document.createElement("a");
							a.setAttribute("href", location.link);
							a.style.display = "none";
							$("body").append(a);
							a.click();
							
							return false;
						}
					});
		        }); 
				
			}
			
			function displayNextEvent() {			
				if (typeof(gJSON) != 'undefined' && gJSON.Locations.length > gCounter) {
					var location = gJSON.Locations[gCounter];
					if ('sender' in location) {
						clearMarkers();
						clearTrails();
						if($('#message').length > 0) {
							$('#message').fadeOut('fast',function(){
								if(infobox){
									infobox.close();
								}
								addSpider(location);
							});
						}
						else {
							addSpider(location);
						}
					} else {
						clearMarkers();
						clearTrails();
						if($('#message').length > 0) {
							$('#message').fadeOut('fast',function(){
								if(infobox){
									infobox.close();
								}
								addLocation(location);
							});
						}
						else {
							addLocation(location);
						}
					}
					
					gCounter++;
					
					setTimeout(displayNextEvent, config.speed);
				} else {
					// run out of data - fetch some more
					gCounter = 0;
					var previewGetParam = '';
					if (window.location.search == "?preview=true") {
						previewGetParam = "?preview=true";
					}	
					$.getJSON(config.eventUrl + config.eventCount + previewGetParam, function(json) {
						if (json.Locations.length > 0) {
							gJSON = json;
							setTimeout(displayNextEvent, 1);
						}
					});
				}
			}
			
			setTimeout(displayNextEvent, 1);
		});
		
		return this;
	}
})(jQuery);
