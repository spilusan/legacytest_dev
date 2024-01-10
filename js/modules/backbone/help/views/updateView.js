define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'text!templates/help/tpl/update.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	updateTpl
){
	var updateView = Backbone.View.extend({

		el: $('ol li ul.updateFaq'),

		events: {
			'click a.goReg' : 'goReg',
			'click a.question' : 'qJump'
		},

		template: Handlebars.compile(updateTpl),

		render: function() {
			$('ol.pagenav li > ul').hide();
			$('ol.pagenav li > ul').html('');
			$('ol.pagenav li span').removeClass('active');
			$('ol.pagenav li.update span').addClass('active');
			//return;
			var html = this.template();
			$(this.el).html(html);
			$(this.el).show();
			setTimeout(function(){
			 	$(document).scrollTop(0);  
			}, 10);
		},

		goReg: function(e) {
			e.preventDefault();
			$('a.register').click();
		},

		qJump: function(e) {
			e.preventDefault();
			var ele = e.target.rel;
			$(document).scrollTop($(ele).offset().top - 20);
		}
	});

	return new updateView;
});