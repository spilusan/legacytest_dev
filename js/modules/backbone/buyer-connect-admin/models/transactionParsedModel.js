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
            parsedData: {
                columnDefinitions: "",
                headers: {},
                lineItemRecords: [],
                headerParsingStatus: null,
                lineItemParsingStatus: null,
                status: null
            }
        },

        initialize: function (options) {
            options = options || {};

            this.id = options.id;
        },

        url: function () {
            return '/reports/data/buyer-connect/transaction/' + this.id + '/extractedData';
        },

        model: Transaction,

        parse: function (response, options) {
            return response;
        },

        toJSON: function () {
            var json = Backbone.Model.prototype.toJSON.call(this);
                            
            // for for broken serialisation
            if (json.parsedData.parties) {
                $.each(json.parsedData.parties, function (i, x) {
                    if (x.Contact.constructor == Array) {
                        x.Contact = {};
                    }
                });
            }

            return json;
        },

        fetch : function() {
            var self = this;

            return $.ajax({
                type : 'GET',
                url : self.url(),
                success : function(data) {
                    $.extend(self.attributes, self.defaults, { id: self.id }, data);
                }
            });
        }
    });

    return Transaction;
});
