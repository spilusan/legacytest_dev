define([
    'underscore',
    'Backbone'
], function (
    _,
    Backbone
) {
        var model = Backbone.Model.extend({
            url: '/reports/data/supplier-performance-funnel/rfq',
            fetched: false,
            defaults: {
                rfqsSentCount: '?',
                rfqsPendingCount: '?',
                rfqsIgnoredCount: '?',
                rfqsIgnoredValue: '?',
                rfqsDeclinedCount: '?',
                rfqsDeclinedValue: '?'
            },
            initialize: function () {

            },
            getAttributeAsFormattedInt: function(attributeKey) {
            	if (attributeKey in this.attributes) {
            		return Math.round(parseFloat(this.attributes[attributeKey]),0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            	}
            	
            	return null;
            }
        });

        return model;
    });