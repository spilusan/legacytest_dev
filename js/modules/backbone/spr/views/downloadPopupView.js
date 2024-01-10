define([
    'jquery',
    'underscore',
    'Backbone',
    'handlebars',
    'text!templates/spr/tpl/downloadPopup.html'
], function (
    $,
    _,
    Backbone,
    Hb,
    downloadPopupTpl
) {
    var view = Backbone.View.extend({
        template: Handlebars.compile(downloadPopupTpl),
        initialize: function () {
            this.render = this.render.bind(this);
            $('body').click(function(e){
                if (!$(e.target).hasClass('export')) {
                    $('.downloadPopup').fadeOut();
                }
            });
        },

        render: function (loading) {
            var el = $('#downloadPopupHolder');
            var html = this.template({isLoading: loading});
            el.empty();
            el.html(html);

            $('.downloadPopup').click(function(e) {
                e.stopPropagation();
            });

            $('.downloadClose').click(function(e) {
                e.preventDefault();
                $('.downloadPopup').fadeOut();
            });
        }
    });

    return new view();
});
