'use strict';

define([], function () {
    return Backbone.Router.extend({
        initialize: function (view) {
            this.view = view;
            this.view.router = this;

            Backbone
                .history
                .start();
        },

        routes: {
            'buyers(/:startDate/:endDate)': 'displayBuyers',
            'buyers/:startDate/:endDate/:tnid(/:page)': 'displayBuyersPos',
            'suppliers(/:startDate/:endDate)': 'displaySuppliers',
            '.*': 'index'
        },

        index: function () {
            this.navigate("buyers", true, true);
        },

        displayBuyers: function (startDate, endDate) {
            var startDateObj = new Date(startDate),
                endDateObj = new Date(endDate);

            this.view.displayBuyers(startDateObj, endDateObj);
        },

        displayBuyersPos: function (startDate, endDate, tnid, page) {
            var startDateObj = new Date(startDate),
                endDateObj = new Date(endDate)

            this.view.displayBuyersPos(startDateObj, endDateObj, tnid, page);
        },

        displaySuppliers: function (startDate, endDate) {
            var startDateObj = new Date(startDate),
                endDateObj = new Date(endDate);

            this.view.displaySuppliers(startDateObj, endDateObj);
        }
    });
});