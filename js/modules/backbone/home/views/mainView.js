define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'libs/jquery.eventmap',
	'libs/jquery.autocomplete'
], function(
	$,
	_,
	Backbone,
	Hb,
	Eventmap,
	Autocomplete
){
	var homeView = Backbone.View.extend({
		el: $('body'),

		bannerCount: 0,

		initialize: function() {
			var thisView = this;

			$(document).ready(function(){
				thisView.render();
			});
		},

		render: function() {
			var thisView = this;

			$('#searchWhat').bind('keypress', function(e){
				var key = e.which;
				if(key == '13'){
					e.preventDefault();
					thisView.submitForm();
				}
			});

			$('#searchText').bind('keypress', function(e){
				var key = e.which;
				if(key == '13'){
					e.preventDefault();
					thisView.submitForm();
				}
			});

			$('#searchButton').bind('click', function(e){
				e.preventDefault();
				thisView.submitForm();
			});

			$('#searchWhat').bind('focus', function(e){
				thisView.focusSearchWhat();
			});

			$('#searchWhat').bind('blur', function(e){
				thisView.blurSearchWhat();
			});

			$('#searchText').bind('focus', function(e){
				thisView.focusSearchText();
			});

			$('#searchText').bind('blur', function(e){
				thisView.blurSearchText();
			});

			this.startAutoComplete();
			this.slideLogosLoop();
			this.updateStats();
            if (window.google) {
                if ($('#map').eventMap) {
                    $('#map').eventMap();
                }
            }
		},

		focusSearchWhat: function(){
			if($('#searchWhat').val() == "Company, category, brand, product, part no, IMPA/ISSA, etc."){
				$('#searchWhat').removeClass('blur');
				$('#searchWhat').val('');
			}
		},

		blurSearchWhat: function(){
			if($('#searchWhat').val() === ''){
				$('#searchWhat').addClass('blur');
				$('#searchWhat').val('Company, category, brand, product, part no, IMPA/ISSA, etc.');
			}
		},

		focusSearchText: function(){
			if($('#searchText').val() === "Country or port name"){
				$('#searchText').removeClass('blur');
				$('#searchText').val('');
			}
		},

		blurSearchText: function(){
			if($('#searchText').val() === ""){
				$('#searchText').addClass('blur');
				$('#searchText').val('Country or port name');
			}
		},

		submitForm: function(){
			if($('#searchText').val() === "Country or port name"){
				$('#searchText').val('');
			}
			if($('#searchWhat').val() === "Company, category, brand, product, part no, IMPA/ISSA, etc."){
				$('#searchWhat').val('');
			}
			if( typeof window.searchIsOngoing == 'undefined' )
			{
				window.searchIsOngoing = true;
				$('form#anonSearch').submit();
			}

		},

		startAutoComplete: function(){
			$('input[name=searchWhat]').autocomplete({
                serviceUrl: '/search/autocomplete/what/format/json/',
                width: document.getElementById('searchWhat').offsetWidth,
                zIndex: 9999,
                minChars: 3,
                paramName: "value",
                type: "POST",
                params: {
                	"new" : 1
                },
                onStart: function(){
                    //$('.spinnerFwd').css('display', 'inline-block');
                },
                onFinish: function(){
                    //$('.spinnerFwd').hide();
                },
                onSelect: function(response) {
                	$('#searchWhat').val(response.value);
					$('#searchType').val(response.data.type);
					//$('#searchWhere').val(response.data);
					if( response.data.url ){
						location.href = response.data.url;
					}
                },
                beforeRender: function (container) {
                	$(container).addClass('left-side');
                }
            });

            $('input[name=searchText]').autocomplete({
                serviceUrl: '/search/autocomplete/portsAndCountries/format/json/',
                width: document.getElementById('searchText').offsetWidth,
                zIndex: 9999,
                minChars: 2,
                paramName: "value",
                type: "POST",
                params: {
                	"new" : 1
                },
                onStart: function(){
                    //$('.spinnerFwd').css('display', 'inline-block');
                },
                onFinish: function(){
                    $('.autocomplete-suggestions').addClass('margin-setter');
                },
                onSelect: function(response) {
                	$('#searchWhere').val(response.data);
                },
                beforeRender: function (container) {
                	$(container).addClass('right-side');
                }
            });
		},
		
		counterEffect: function($statItem, startCounter, endCounter){
			endCounter= parseInt(endCounter);
			startCounter = parseInt(startCounter);
			$statItem.text($statItem.text().replace(/\B(?=(\d{3})+(?!\d))/g, ","));
			if (isNaN(startCounter) || isNaN(endCounter) || parseInt(endCounter) < parseInt(startCounter)) {
				return;
			}			
			$statItem.prop('Counter', startCounter).animate({
		        Counter: endCounter
		    }, {
		        duration: 4000,
		        easing: 'linear',
		        step: function (now) {
		        	$statItem.text(Math.ceil(now).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ","));
		        }
		    });
		},
		
		updateStats: function(){
			_this = this;
			$.getJSON('/events/fetch-stats', function(stats) {
				['buyers_visited', 'buyers_searches', 'new_suppliers'].forEach(function(item) {
					var $statItem = $('#statItem_' + item + ' .circle');
					var startCounter = (isNaN($statItem.text())? 0 : $statItem.text());
					_this.counterEffect($statItem, startCounter, stats[item]);
				}); 
			});			
		},
		
		slideLogosLoop: function(){
			var slideLogos = function () {
				var $logosContainer = $('.logos-container');
				var $curActiveLogosDiv = $logosContainer.find('.homepage-logos:visible');
				var nextActiveLogosDivIdx = ($logosContainer.find('.homepage-logos').index($curActiveLogosDiv) + 1) % $logosContainer.find('.homepage-logos').length;
				var $nextActiveLogosDiv = $logosContainer.find('.homepage-logos:nth-child(' + (nextActiveLogosDivIdx+1) + ')');
				$curActiveLogosDiv.css({opacity:1}).animate({opacity:0}, 200, function(){
					$curActiveLogosDiv.hide();
					$nextActiveLogosDiv.show();
					$nextActiveLogosDiv.css({opacity:0}).animate({opacity:1}, 1000);
				});
			};
			setInterval(slideLogos, 8000);
		}
	});

	return new homeView();
});
