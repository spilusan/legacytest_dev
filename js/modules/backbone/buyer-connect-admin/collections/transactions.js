define([
    'underscore',
    'Backbone',
    'backbone/buyer-connect-admin/models/transaction'
], function (
    _,
    Backbone,
    Transaction
) {
        var Transactions = Backbone.Collection.extend({
            model: Transaction,
            url: 'http://beta.json-generator.com/api/json/get/EyTEMQSvQ',
            initialize: function () {
                this.fetch();
            }
        });

        return Transactions;
    });