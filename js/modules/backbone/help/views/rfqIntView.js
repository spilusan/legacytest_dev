define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'text!templates/help/tpl/rfqInt.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	rfqIntTpl
){
	var rfqIntView = Backbone.View.extend({

		el: $('ol li ul.rfqIntFaq'),

		template: Handlebars.compile(rfqIntTpl),

		render: function() {
			$('ol.pagenav li > ul').hide();
			$('ol.pagenav li > ul').html('');
			$('ol.pagenav li span').removeClass('active');
			$('ol.pagenav li.rfqInt span').addClass('active');
			var html = this.template();
			$(this.el).html(html);
			$(this.el).show();
			setTimeout(function(){
			 	$(document).scrollTop(0);  
			}, 10);
		}
	});

	return new rfqIntView;
});