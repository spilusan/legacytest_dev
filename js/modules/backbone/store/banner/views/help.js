define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'libs/jquery.tools.overlay.modified',
	'text!templates/store/banner/tpl/img.html'

], function(
	$, 
	_, 
	Backbone,
	Hb,
	Modal,
	imgTpl
){
	var helpView = Backbone.View.extend({
		el: $('body'),

		events: {
			'click a.popup' : 'render',
		},

		template: Handlebars.compile(imgTpl),

		render: function(e){
			e.preventDefault();
			if($(e.target).is('a')){
				var el = e.target;
			}
			else {
				var el = $(e.target).parent('a');
			}
			var data = {};
			data.url = $(el).attr('href');
			data.title = $(el).attr('rel');
			var html = this.template(data);
			$('#modal .modalBody').html(html);
			this.openDialog();
		},

        openDialog: function() { 
            thisView = this;
            $('#modal').removeClass('tags');
            $('#modal').overlay({
                mask: 'black',
                left: 'center',
                fixed: false,

                onBeforeLoad: function() {
                    var windowWidth = $(window).width();
                    var modalWidth = $('#modal').width();
                    var posLeft = windowWidth/2 - modalWidth/2;

                    $('#modal').css('left', posLeft);
                },

                onLoad: function() {
                    $(window).resize(function(){
                        var windowWidth = $(window).width();
                        var modalWidth = $('#modal').width();
                        var posLeft = windowWidth/2 - modalWidth/2;

                        $('#modal').css('left', posLeft);
                    });
                },

                onClose: function() {
                    $('#modal .modalBody').html('');
                }
            });

            $('#modal').overlay().load();
        }


	});

	return new helpView;
});