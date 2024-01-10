define([
	"jquery",
	"underscore",
	"Backbone",
	"handlebars",
	"text!templates/ma/tpl/help.html"
], function(
	$, 
	_, 
	Backbone,
	Hb,
	helpTpl
){
	var helpView = Backbone.View.extend({
		el: $("body"),

		events: {
			"click .help" : "showModal",
			"click .close" : "hideModal",
			"click #ovl" : "hideModal"
		},

		template: Handlebars.compile(helpTpl),
		
		initialize: function() {
			if ( navigator.userAgent.match(/iPhone/i) || navigator.userAgent.match(/iPod/i)) {
				$("meta[name=viewport]").attr('content','initial-scale=1, user-scalable=no');
				$('a.dlgr').attr('href', 'http://itunes.apple.com/app/id306277111');
			}
			if(navigator.userAgent.match(/iPad/i)) {
				$('a.dlgr').attr('href', 'http://itunes.apple.com/app/id363448914');
			}
		},

		render: function(){
			var html = this.template();
			$("#modal").html(html);
			
			$(window).resize(function(){
				$("#ovl").css("height", $(document).height());
			});
		},

		showModal: function(e){
			e.preventDefault();
			this.render();
			$("#modal").fadeIn("fast");
			$("#ovl").fadeIn("fast");
			$("#ovl").css("height", $(document).height());
		},

		hideModal: function(e){
			e.preventDefault();
			$("#modal").fadeOut("fast", function(){
				$("#modal").html("");
			});
			$("#ovl").fadeOut("fast");
		}
	});

	return new helpView;
});
