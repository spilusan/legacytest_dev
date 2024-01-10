define([
	'jquery',
	'underscore',
	'Backbone'
], function(
	$, 
	_, 
	Backbone
){
	var helpHomeView = Backbone.View.extend({

		render: function() {
			$('ol.pagenav li > ul').hide();
			$('ol.pagenav li > ul').html('');
			$('ol.pagenav li span').removeClass('active');
			setTimeout(function(){
			 	$(document).scrollTop(0);  
			}, 10);
		}
	});

	return new helpHomeView;
});