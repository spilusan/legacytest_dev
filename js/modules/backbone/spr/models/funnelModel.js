define([
    'underscore',
    'Backbone'
], function (
    _,
    Backbone
) {
        var model = Backbone.Model.extend({
            url: '/reports/data/supplier-performance-funnel/data',
            defaults: {
                directOrdersCount: '?',
                directOrdersValue: '?',

                rfqsDeclinedCount: '?',
                rfqsDeclinedValue: '?',
                rfqsDeclinedPercent: '?',

                rfqsIgnoredCount: '?',
                rfqsIgnoredValue: '?',
                rfqsIgnoredPercent: '?',

                quotedLostCount: '?',
                quotedLostValue: '?',
                quotedLostPercent: '?',

                rfqsSentCount: '?',
                rfqsPendingCount: '?',
                rfqsPendingPercent: '?',

                rfqsQuotedUnknownCount: '?',
                rfqsQuotedUnknownPercent: '?',
                rfqsQuotedUnknownValue: '?',

                competitiveOrdersSentCount: '?',
                competitiveOrdersSentValue: '?',

                quotedPercent: '?',
                winPercent: '?',
                quotesReceived: '?',
                // added by Yuriy Akopov on 2017-07-21, S20542 to include assumed quotes
                quotesAssumed: '?',
                quotesReceivedTotal: '?',

                totalOrdersSentCount: '?',
                totalOrdersSentValue: '?',

                selectedBuyersCount: null,
                selectedSuppliersCount: null
            },
            initialize: function () {

            },
            parse: function (data) {
                for (var i in data) {
                    data[i] = parseInt(data[i]).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                }

                return data;
            }
        });

        return model;
    });