define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'text!templates/help/tpl/rfqStart.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	rfqStartTpl
){
	var rfqStartView = Backbone.View.extend({

		el: $('ol li ul.rfqStartFaq'),

		events: {
			'click a.goMax'    : 'goMax',
			'click a.goUpd'    : 'goUpd',
			'click a.question' : 'qJump'
		},

		template: Handlebars.compile(rfqStartTpl),

		render: function() {
			$('ol.pagenav li > ul').hide();
			$('ol.pagenav li > ul').html('');
			$('ol.pagenav li span').removeClass('active');
			$('ol.pagenav li.rfqStart span').addClass('active');
			var html = this.template();
			$(this.el).html(html);
			$(this.el).show();
			setTimeout(function(){
			 	$(document).scrollTop(0);  
			}, 10);
		},

		goMax: function(e) {
			e.preventDefault();
			$('a.max').click();
		},

		goUpd: function(e) {
			e.preventDefault();
			$('a.update').click();
		},

		qJump: function(e) {
			e.preventDefault();
			var ele = e.target.rel;
			$(document).scrollTop($(ele).offset().top - 20);
		}
	});

	return new rfqStartView;
});