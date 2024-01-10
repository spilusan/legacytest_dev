define([
    'jquery',
    'underscore',
    'Backbone',
    'handlebars',
    './mainViewAuth'
], function(
    $,
    _,
    Backbone,
    Hb,
    mainViewAuth
){
    var mainView = Backbone.View.extend({
        initialize: function () {
            $(document).ajaxStart(function(){
                $('#product-results').hide();
                $('#catalogue-result-loader').show();
            });

            $(document).ajaxStop(function(){
                $('#catalogue-result-loader').hide();
                $('#product-results').show();
            });
            new mainViewAuth();
        }
    });

    return new mainView();
});
