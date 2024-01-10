'use strict';

define([
    'underscore',
    'Backbone',
    'backbone/lib/backbone-deep-model'
], function (
    _,
    Backbone,
    DeepModel
) {
    var Transaction = Backbone.DeepModel.extend({
        defaults: {
            id: null,
            transactionDate: null,
            docType: null,
            fileFormat: null,
            status: null,
            workFlowStatus: null,
            filename: null,
            configId: null,
            remarks: null,
            supplier: {
                suppierId: null,
                name: null
            },
            buyer: {
                buyerId: null,
                name: null
            }
        },

        initialize: function (options) {
            options = options || {};

            this.id = options.id;
        },


        url: function () {
            var params = {};

            if (this.action) {
                params.action = '"' + this.action + '"';
            }

            return '/reports/data/buyer-connect/transaction/' + this.id + '?' + $.param(params);
        },

        model: Transaction,

        parse: function (response, options) {
            return response;
        }
    });

    return Transaction;
});