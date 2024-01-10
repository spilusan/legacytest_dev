define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
], function(
	$, 
	_, 
	Backbone, 
	Hb
){
	var mainView = Backbone.View.extend({
		
		el: $('body'),

		events: {
			'click a' : 'render'
		},

		urlToLoad: require('shipmate/urlToLoad'),
		firstLoad: true,
		
		initialize: function () {
			this.render();
		},

		render: function() {
			var thisView = this;
			// automatically adjusting the height of the iframe
			$( "#shipmateIFrame" ).on('load', function() { 
		        var mydiv = $(this).contents().find("div");
		        var h     = mydiv.height() + 40;
		        $( "#shipmateIFrame" ).css("height", h  + 'px');
		    });
			
			// loading the iframe with a's href and adding state of selected
			$(".iFrameLink").bind('click', function(e){
				e.preventDefault();
				thisView.firstLoad = false;
				$('#shipmateIFrame').attr('src', $(e.target).attr('href'));
				
				$('ul#nav li').removeClass("selected");
				$(e.target).parent('li').addClass("selected");
			});
			
			// on initialise, it'll load the first page
			if(this.firstLoad){
				if (thisView.urlToLoad.length > 0) {
					$('#shipmateIFrame').attr('src', thisView.urlToLoad);
				}
			}
		}
	});

	return new mainView;
});
