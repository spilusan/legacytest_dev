define([
    'underscore',
    'Backbone'
], function (
    _,
    Backbone
) {
        var model = Backbone.Model.extend({
            url: '/reports/data/supplier-performance-funnel/order',
            fetched: false,
            defaults: {
                directOrdersCount: '?',
                directOrdersValue: '?',
                competitiveOrdersSentCount: '?',
                competitiveOrdersSentValue: '?',
                totalOrdersSentCount: '?',
                totalOrdersSentValue: '?'
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