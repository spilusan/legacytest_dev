define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'text!templates/help/tpl/pagesHelp.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	pagesHelpTpl
){
	var pagesHelpView = Backbone.View.extend({

		el: $('ol li ul.pagesHelpFaq'),

		events: {
			'click a.question' : 'qJump'
		},

		template: Handlebars.compile(pagesHelpTpl),

		render: function() {
			$('ol.pagenav li > ul').hide();
			$('ol.pagenav li > ul').html('');
			$('ol.pagenav li span').removeClass('active');
			$('ol.pagenav li.pHelp span').addClass('active');
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

	return new pagesHelpView;
});