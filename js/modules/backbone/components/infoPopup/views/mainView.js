define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'text!templates/components/infoPopup/tpl/index.html'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	Tpl
){
	var view = Backbone.View.extend({
		popup: null,
		template: Handlebars.compile(Tpl),
		show: function(title, content) {
			var thisView = this;
			
			this.popup = $(this.template({
				caption: title,
				content: content
			}));
			
			$('body').append(this.popup);
			$(this.popup).find('.shipservPopupClose').click(function(e){
				e.preventDefault();
				$(thisView.popup).remove();
			});
		}
	});

	return new view();
});

