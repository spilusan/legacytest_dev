define([
    'underscore',
    'Backbone'
], function (
    _,
    Backbone
) {
        var model = Backbone.Model.extend({
            defaults: {
                 rfqsDeclinedPercent: '?',
                 rfqsIgnoredPercent: '?',
                 quotedLostPercent: '?',
                 rfqsPendingPercent: '?',
                 quotedUnknownPercent: '?',
                 quotedPercent: '?',
                 winPercent: '?',
                 selectedBuyersCount: null,
                 selectedSuppliersCount: null
            },
            initialize: function () {

            },
            getAttributeAsFormattedInt: function(attributeKey) {
            	if (attributeKey in this.attributes) {
            		return Math.round(parseFloat(this.attributes[attributeKey]),0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            	}
            	
            	return null;
            },
            calculateSummary: function(rfqModel, qotModel, ordModel) {
            	
            	var rfqCount = rfqModel.attributes['rfqsSentCount'],
            		quoteCount = qotModel.attributes['quotesReceived'],
					quoteCountAll = quoteCount + qotModel.attributes['quotesAssumed'];

            	//Setting default values (if rfqCount or quoteCount is 0, avoiding division 0)
            	var quotedPercent = 0,
            		winPercent = 0,
            		rfqsDeclinedPercent = 0,
	    			rfqsIgnoredPercent = 0,
	    			rfqsPendingPercent = 0,
	    			quotedUnknownPercent = 0,
	            	quotedLostPercent = 0;

            	if (quoteCountAll > 0) {
            		winPercent = ordModel.attributes['competitiveOrdersSentCount'] / quoteCountAll * 100;
                    // BUY-641: prevent multiple latest-in-the-chain orders from the same quote to send win rate through the roof
					winPercent = Math.min(100, winPercent);

                    quotedUnknownPercent = qotModel.attributes['quotedUnknownCount']  / quoteCountAll * 100;
                    quotedLostPercent = qotModel.attributes['quotedLostCount'] / quoteCountAll * 100;
            	}
            	
            	if (rfqCount > 0) {
            		quotedPercent = quoteCountAll / rfqCount * 100;

        			rfqsDeclinedPercent = rfqModel.attributes['rfqsDeclinedCount']  / rfqCount * 100;
        			rfqsIgnoredPercent = rfqModel.attributes['rfqsIgnoredCount']  / rfqCount * 100;
        			rfqsPendingPercent = rfqModel.attributes['rfqsPendingCount']  / rfqCount * 100;
            	}
            	
            	this.attributes.winPercent = winPercent;

            	this.attributes.quotedPercent = quotedPercent;
                this.attributes.quotedUnknownPercent = quotedUnknownPercent;
                this.attributes.quotedLostPercent = quotedLostPercent;

            	this.attributes.rfqsDeclinedPercent = rfqsDeclinedPercent;
            	this.attributes.rfqsIgnoredPercent = rfqsIgnoredPercent;
            	this.attributes.rfqsPendingPercent = rfqsPendingPercent;
            }
        });

        return model;
    });