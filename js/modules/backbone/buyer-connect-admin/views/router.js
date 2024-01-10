'use strict';

define([
    'jquery',
    'underscore',
    'Backbone'
], function (
    $,
    _,
    Backbone
) {
        var Router = Backbone.Router.extend({
            initialize: function (view) {
                this.view = view;
            },

            routes: {
                '': 'index',
                'transactions/:transactionId': 'transactionDetails'
            },

            index: function () {
                this.view.showTransactions();
            },

            transactionDetails: function (transactionId) {
                this.view.showDetails(transactionId);
            }

        });

        return Router;
    });
