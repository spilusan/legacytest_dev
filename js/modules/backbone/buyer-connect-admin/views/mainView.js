'use strict';

define([
    'jquery',
    'underscore',
    'Backbone',
    'backbone/buyer-connect-admin/views/transactionsView',
    'backbone/buyer-connect-admin/views/detailsView',
    'backbone/buyer-connect-admin/views/router'
    
], function (
    $,
    _,
    Backbone,
    TransactionsView,
    DetailsView,
    Router
) {
        var View = Backbone.View.extend({
            events: {
                'click [view-details]': 'transactionClicked',
                'click [show-transactions]': 'showTransactions'
            },

            initialize: function () {
                this.render();
            },

            render: function () {
                this.showTransactions();

                return this;
            },

            showTransactions: function (options) {
                var transactionView = new TransactionsView(options);

                this.$el.empty().append(transactionView.$el);
            },

            showDetails: function (transactionId) {
                var detailsView = new DetailsView({
                    id: transactionId,
                    parentView: this
                });

                this.$el.empty().append(detailsView.$el);
            },

            transactionClicked: function(e) {
                var transactionId = $(e.target).data('id');

                this.showDetails(transactionId);
            }
        });

        var mainView = new View();

        $('#mainContent').append(mainView.el);
    });
