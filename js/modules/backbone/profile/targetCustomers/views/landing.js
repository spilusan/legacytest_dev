define([
    'jquery',
    'underscore',
    'Backbone'
], function(
    $, 
    _, 
    Backbone
){
    var landingView = Backbone.View.extend({
        el: $('body'),
        buyerId: require('profile/targetCustomers/buyerId'),
        supplierId: require('profile/targetCustomers/supplierId'),
        events: {
            'click .button.promote' : 'promote',
            'click .button.exclude'   : 'exclude'
        },

        initialize: function(){
        	/*
            $('body').ajaxStart(function(){
                $('#waiting').show();
            });
            */

            this.render();
        },

        render: function(){

        },

        promote: function(e){
            var thisView = this;
            e.preventDefault();
            $('#waiting').show();
            $.ajax({
                method: "GET",
                url: "/profile/target-customers-request",
                data: { 
                    type: "add",
                    buyerId: thisView.buyerId
                }
            })
            .done(function( msg ) {
                window.location.assign('/profile/target-customers/type/v/id/' + thisView.supplierId + '?tab=promo');
            })
            .fail(function(msg){
            	$('#waiting').hide();
                alert('An error occurred.');
            });
        },

        exclude: function(e){
            e.preventDefault();

            var thisView = this;

            $.ajax({
                method: "GET",
                url: "/profile/target-customers-request",
                data: { 
                    type: "exclude",
                    buyerId: thisView.buyerId
                }
            })
            .done(function(msg) {
                window.location.assign('/profile/target-customers/type/v/id/' + thisView.supplierId + '?tab=exclude');
            })
            .fail(function(msg){
                alert('An error occurred.');
            });
        }
    });
    return new landingView;
});