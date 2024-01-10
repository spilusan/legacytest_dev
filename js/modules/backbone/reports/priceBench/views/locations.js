define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'text!templates/reports/priceBench/tpl/locations.html',
	'../collections/mapData',
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	locationsTpl,
	mapData
){

	var locationsView = Backbone.View.extend({
		el: $('.dataBox'),
        showMap: require('benchmark/showMap'),

		events: {

		},

		locationsTemplate: Handlebars.compile(locationsTpl),

		initialize: function() {

            if (parseInt(this.showMap) === 1) {

                /* Label google map overlay class */
				// Define the overlay, derived from google.maps.OverlayView
                this.label = function(opt_options) {
                    // Initialization
                    this.setValues(opt_options);

                    // Label specific
                    var span = this.span_ = document.createElement('span');
                    var verticalOffset = this.boxHeight + 50;
                    span.style.cssText = 'position: relative; left: 15px; top: -' + verticalOffset + 'px; background-color: transparent;z-index: 999';


                    var div = this.div_ = document.createElement('div');
                    div.appendChild(span);
                    div.style.cssText = 'position: absolute; display: none';
                };

                this.label.prototype = new google.maps.OverlayView();

                // Implement onAdd
                this.label.prototype.onAdd = function () {
                    var pane = this.getPanes().overlayLayer;
                    pane.appendChild(this.div_);

                    // Ensures the label is redrawn if the text or position is changed.
                    var me = this;
                    this.listeners_ = [
                        google.maps.event.addListener(this, 'position_changed',
                            function () {
                                me.draw();
                            }),
                        google.maps.event.addListener(this, 'text_changed',
                            function () {
                                me.draw();
                            })
                    ];
                };

                // Implement onRemove
                this.label.prototype.onRemove = function () {
                    this.div_.parentNode.removeChild(this.div_);

                    // Label is removed from the map, stop updating its position/text.
                    for (var i = 0, I = this.listeners_.length; i < I; ++i) {
                        google.maps.event.removeListener(this.listeners_[i]);
                    }
                };

                // Implement draw
                this.label.prototype.draw = function () {
                    var projection = this.getProjection();
                    var position = projection.fromLatLngToDivPixel(this.get('position'));

                    var div = this.div_;
                    div.style.left = position.x + 'px';
                    div.style.top = position.y + 'px';
                    div.style.display = 'block';


                    this.span_.innerHTML = this.htmlText;
                };

                this.mapData = new mapData();
            }
		},

		render: function() {

			if (parseInt(this.showMap) === 1) {
                var html = this.locationsTemplate();
                $(this.el).html(html);

                this.renderMap();
                this.parent.fixHeight();
            }

			return this;
		},

		getData: function(e) {

            if (parseInt(this.showMap) !== 1) {
                return;
            }

			var thisView = this;

			this.mapData.reset();
			if (this.parent.hasImpaCodeList()) {
				this.mapData.fetch({
					add: true,
					remove: false,
					data: $.param({
						products: this.parent.getImpaCodeList(),
						//query: this.keywords,
						pageNo: this.parent.rightPageNo,
						pageSize: this.parent.pageSize,
						filter: {
							dateFrom: this.parent.dateFrom,
							dateTo: this.parent.dateTo,
							vessel: this.parent.vessel,
							location: this.parent.location,
							excludeRight: this.parent.excludeRight,
							refineQuery: this.parent.refineRightQuery
						},
						sortBy: this.parent.sortRight,
						sortDir: this.parent.sortOrderRight
					}),
					complete: function() {
						thisView.render();
					}
				});
			} else {
				thisView.render();
			}
		},

		renderMap: function() {

            if (parseInt(this.showMap) !== 1) {
            	return;
            }
            // variable for marker info window
			this.defaultLatlng = new google.maps.LatLng(49.00,10.00); 
			  // zoom level of the map 
			this.defaultZoom = 2;

			this.infowindow = null;  // List with all marker to check if exist

			this.markerList = {}; 

			var styleArray = 
					[
					    {
					        "featureType": "administrative",
					        "elementType": "labels.text.fill",
					        "stylers": [
					            {
					                "color": "#444444"
					            }
					        ]
					    },
					    {
					        "featureType": "administrative.country",
					        "elementType": "geometry.stroke",
					        "stylers": [
					            {
					                "visibility": "off"
					            }
					        ]
					    },
					    {
					        "featureType": "administrative.country",
					        "elementType": "labels",
					        "stylers": [
					            {
					                "visibility": "off"
					            }
					        ]
					    },
					    {
					        "featureType": "administrative.province",
					        "elementType": "all",
					        "stylers": [
					            {
					                "visibility": "off"
					            }
					        ]
					    },
					    {
					        "featureType": "administrative.locality",
					        "elementType": "all",
					        "stylers": [
					            {
					                "visibility": "off"
					            }
					        ]
					    },
					    {
					        "featureType": "administrative.neighborhood",
					        "elementType": "all",
					        "stylers": [
					            {
					                "visibility": "off"
					            }
					        ]
					    },
					    {
					        "featureType": "administrative.land_parcel",
					        "elementType": "all",
					        "stylers": [
					            {
					                "visibility": "off"
					            }
					        ]
					    },
					    {
					        "featureType": "landscape",
					        "elementType": "all",
					        "stylers": [
					            {
					                "color": "#f2f2f2"
					            }
					        ]
					    },
					    {
					        "featureType": "landscape",
					        "elementType": "geometry.stroke",
					        "stylers": [
					            {
					                "visibility": "off"
					            }
					        ]
					    },
					    {
					        "featureType": "poi",
					        "elementType": "all",
					        "stylers": [
					            {
					                "visibility": "off"
					            }
					        ]
					    },
					    {
					        "featureType": "road",
					        "elementType": "all",
					        "stylers": [
					            {
					                "saturation": -100
					            },
					            {
					                "lightness": 45
					            },
					            {
					                "visibility": "off"
					            }
					        ]
					    },
					    {
					        "featureType": "road.highway",
					        "elementType": "all",
					        "stylers": [
					            {
					                "visibility": "off"
					            }
					        ]
					    },
					    {
					        "featureType": "road.arterial",
					        "elementType": "labels.icon",
					        "stylers": [
					            {
					                "visibility": "off"
					            }
					        ]
					    },
					    {
					        "featureType": "transit",
					        "elementType": "all",
					        "stylers": [
					            {
					                "visibility": "off"
					            }
					        ]
					    },
					    {
					        "featureType": "water",
					        "elementType": "all",
					        "stylers": [
					            {
					                "color": "#ffffff"
					            },
					            {
					                "visibility": "on"
					            }
					        ]
					    }
					];


			var mapOptions = {
				zoom: this.defaultZoom,
				center: this.defaultLatlng,
				maxZoom: 7,
				minZoom: 2,
				styles: styleArray,
			};

			this.map = new google.maps.Map(document.getElementById('map-canvas'),mapOptions);

			this.infowindow = new google.maps.InfoWindow();
			// load markers
			this.loadMarkers();

			//var markerCluster = new MarkerClusterer(this.map, markers);
		},

		loadMarkers: function() {

			if (this.mapData.models[0]) {
				var maxValue = 0;
				_.each(this.mapData.models[0].attributes.countries, function(item, key) {
					var currValue = 0;
					for (var itemKey in item.units) {
						currValue = currValue + parseInt(item.units[itemKey]);
					}

					if (currValue > maxValue) {
							maxValue = currValue;
					}
			    }, this);

				this.maxDeliveryValue = maxValue;

				_.each(this.mapData.models[0].attributes.countries, function(item, key) {
					var currValue = 0;
					for (var itemKey in item.units) {
						currValue = currValue + parseInt(item.units[itemKey]);
					}
					this.getGeoLcations(item.name,currValue);
			    }, this);
			}

		},

		createMarker: function( markerData ) {
			//TODO add, or in case modify this function to add marker to the map

			/** * Load marker to map */ 
			// create new marker location

			//create new map label


			var thisView = this;

			var myLatlng = new google.maps.LatLng(markerData['lat'],markerData['long']);


			// create new marker
				var marker = new google.maps.Marker({
					id: markerData['id'],
					map: this.map,
					title: markerData['name'] ,
					position: myLatlng,
					'icon': '/img/icons/bullets/map-marker-t.png',
					//'icon': 'http://maps.google.com/mapfiles/kml/shapes/schools_maps.png',
					
					//icon: markerData['icon'],
		
			});
				var colHeight = parseInt((50/this.maxDeliveryValue)*markerData['value']);
				//var innerText = '<div class="mapInfo"><span class="mapValue">'+markerData['value']+'</span><div style="height:'+colHeight+'px">&nbsp</div><span class="mapLabel">'+markerData['name']+'</span></div>';
				var innerText = '<div class="mapInfo"><span class="mapValue">'+markerData['value']+'</span><span class="mapLine"></span><span class="mapBullet"></span><span class="mapTriangle"></span><span class="mapLabel">'+markerData['name']+'</span></div>';
				//var innerText = '<div>X</div>';
				var label = new this.label({
				       map: this.map,
				       htmlText: innerText,
				       boxHeight: colHeight
				     });

		     label.bindTo('position', marker, 'position');


			// add marker to list used later to get content and additional marker information
			this.markerList[marker.id] = marker;
			// add event listener when marker is clicked // currently the marker data contain a dataurl field this can of course be done different
			google.maps.event.addListener(marker, 'click', function() { 
			// show marker when clicked
				thisView.showMarker(marker.id);
			});
			// add event when marker window is closed to reset map location
			google.maps.event.addListener(this.infowindow,'closeclick', function() {
				//thisView.map.setCenter(this.defaultLatlng);
				//thisView.map.setZoom(this.defaultZoom);
			}); 

			//TODO add google map marker clusterer
			//var markerCluster = new MarkerClusterer(google.maps, marker);

		},

		getGeoLcations : function( gLocationName, gValue ) {
		//TODO use, or modify this function to retrieve the geo pos in the map if we have the address, or city name only
		//this one gets the location of the address lat and long

  	    // create new geocoder for dynamic map lookup
  	    var thisView = this;
		var geocoder = new google.maps.Geocoder();

				geocoder.geocode( {
				'address': gLocationName
  		        }, function(results, status) { 

					// check response status
					if (status == google.maps.GeocoderStatus.OK) { 
							// set new maker id via timestamp
							var newDate = new Date();
							var markerId = newDate.getTime();
							// get name of creator
							var markerCreator = 'Price benchmark tool'; 
  		        	            // create new marker data object
							var markerData = { 
								'id': markerId,
								'lat': results[0].geometry.location.lat(),
								'long': results[0].geometry.location.lng(),
								'creator': markerCreator,
								'name': gLocationName,
								'value':  gValue,
								/*
								'icon': '/img/icons/bullets/map-marker.png',
								'icon': 'http://maps.google.com/mapfiles/kml/shapes/schools_maps.png'  //For custom marker icon
								*/
							};
							//TODO call add marker to the google map
							thisView.createMarker(markerData);

						}
					});
				
 		},

 		showMarker : function( markerId ) {
 		/** * Show marker info window */
 		// get marker information from marker list
		var marker = this.markerList[markerId];
		// check if marker was found
			if( marker ){
				var data = 'TODO get marker HTML doc here';
				this.infowindow.setContent(data);
				this.infowindow.open(this.map,marker);
 			}
		}
	});

	return locationsView;
});



