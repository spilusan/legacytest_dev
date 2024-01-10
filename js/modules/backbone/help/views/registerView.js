define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'text!templates/help/tpl/register.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	registerTpl
){
	var registerView = Backbone.View.extend({

		el: $('ol.pagenav li ul.registerFaq'),
		events: {
			'click a.question' : 'qJump'
		},

		template: Handlebars.compile(registerTpl),

		render: function() {
			$('ol.pagenav li > ul').hide();
			$('ol.pagenav li > ul').html('');
			$('ol.pagenav li span').removeClass('active');
			$('ol.pagenav li.register span').addClass('active');
			var html = this.template();
			$(this.el).html(html);
			$(this.el).show();
			setTimeout(function(){
			 	$(document).scrollTop(0);  
			}, 10);
		},

		qJump: function(e) {
			e.preventDefault();
			var ele = e.target.rel;
			$(document).scrollTop($(ele).offset().top - 20);
		}
	});

	return new registerView;
});