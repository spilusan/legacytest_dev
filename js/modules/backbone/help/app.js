define([
	'jquery',
	'underscore',
	'Backbone',
	'backbone/help/router'
], function(
	$, 
	_, 
	Backbone, 
	Router
){
	var initialize = function(){
		var AppRouter = new Router();

	 	$("ol.pagenav li a")
			.bind('click', function(e) {
			    e.preventDefault();

			    var uri = e.target.getAttribute('href');
			    var ua = $.browser;

				if ( ua.msie && ua.version.slice(0,1) == "7" || ua.version.slice(0,1) == "8") {
				    uri = uri.split('/');
				    uri = uri[uri.length-1];
				}

			    if (uri === Backbone.history.fragment) {
			    	uri = '';
			    }

			    AppRouter.navigate(uri, true);
			}
		);

		Backbone.history.start({
			pushState: true,
			root: '/help/sellerfaq/'
		});

		$('ol.pagenav li span').bind('click', function(e){
			e.preventDefault();
			var ele = $(e.target).prev('a');
			$(ele).click();
		});
	}

	initialize();
});
