define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'text!templates/help/tpl/brandOwner.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	brandOwnerTpl
){
	var brandOwnerView = Backbone.View.extend({

		el: $('ol li ul.brandOwnerFaq'),

		events: {
			'click a.goUpd'    : 'goUpd',
			'click a.question' : 'qJump'
		},

		template: Handlebars.compile(brandOwnerTpl),

		render: function() {
			$('ol.pagenav li > ul').hide();
			$('ol.pagenav li > ul').html('');
			$('ol.pagenav li span').removeClass('active');
			$('ol.pagenav li.brandOwn span').addClass('active');
			var html = this.template();
			$(this.el).html(html);
			$(this.el).show();
			setTimeout(function(){
			 	$(document).scrollTop(0);  
			}, 10);
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

	return new brandOwnerView;
});