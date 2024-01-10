'use strict';

define([
    'jquery', 'underscore', 'Backbone'

], function ($, _, Backbone) {
    return Backbone.Router.extend({
        initialize: function (view) {
            this.view = view;
            this.view.router = this;
            this.startDate = null;
            this.endDate = null;

            Backbone
                .history
                .start();
        },

        routes: {
            'buyers/': 'displayBuyers',
            'buyers(/:startDate/:endDate)': 'displayBuyers',
            'buyers-pos/:startDate/:endDate/:tnid(/:pageSize/:pageNo)': 'displayBuyersPos',
            'buyer-suppliers/:tnid/:startDate/:endDate': 'displayBuyerSuppliers',
            'suppliers(/:startDate/:endDate)': 'displaySuppliers',
            'buyer-supplier-pos/:supplierId/:buyerId/:startDate/:endDate': 'displayBuyerSupplierPos',
            'supplier-buyer-pos/:supplierId/:buyerId/:startDate/:endDate': 'displaySupplierBuyerPos',
            'supplier-buyers/:tnid/:startDate/:endDate': 'displaySupplierBuyers',
            'suppliers-pos/:startDate/:endDate/:tnid(/:pageSize/:pageNo)': 'displaySuppliersPo',
            '.*': 'index'
        },

        index: function () {
            this.navigate("buyers", true, true);
        },

        displayBuyers: function (startDate, endDate) {
            var startDateObj = startDate ? moment(startDate, "YYYYMMDD") : this.startDate,
                endDateObj = endDate ? moment(endDate, "YYYYMMDD") : this.endDate;

            this.view.displayBuyers(startDateObj, endDateObj);
        },

        displayBuyersPos: function (startDate, endDate, tnid, pageSize, pageNo) {
            var startDateObj = startDate ? moment(startDate, "YYYYMMDD") : this.startDate,
                endDateObj = endDate ? moment(endDate, "YYYYMMDD") : this.endDate,
                pageSize = parseInt(pageSize),
                pageNo = parseInt(pageNo);

            this.view.displayBuyersPos(startDateObj, endDateObj, tnid, pageSize, pageNo);
        },

        displaySuppliers: function (startDate, endDate, pageSize, pageNo) {
            var startDateObj = startDate ? moment(startDate, "YYYYMMDD") : this.startDate,
                endDateObj = endDate ? moment(endDate, "YYYYMMDD") : this.endDate,
                pageSize = parseInt(pageSize),
                pageNo = parseInt(pageNo);

            this.view.displaySuppliers(startDateObj, endDateObj, pageSize, pageNo);
        },

        displaySupplierBuyers: function (tnid, startDate, endDate) {
            var startDateObj = startDate ? moment(startDate, "YYYYMMDD") : this.startDate,
                endDateObj = endDate ? moment(endDate, "YYYYMMDD") : this.endDate;

            this.view.displaySupplierBuyers(startDateObj, endDateObj, tnid);
        },

        displayBuyerSuppliers: function (tnid, startDate, endDate) {
            var startDateObj = startDate ? moment(startDate, "YYYYMMDD") : this.startDate,
                endDateObj = endDate ? moment(endDate, "YYYYMMDD") : this.endDate;

            this.view.displayBuyerSuppliers(startDateObj, endDateObj, tnid);
        },

        displaySupplierBuyerPos: function (supplierId, buyerId, startDate, endDate) {
            var startDateObj = startDate ? moment(startDate, "YYYYMMDD") : this.startDate,
                endDateObj = endDate ? moment(endDate, "YYYYMMDD") : this.endDate;

            this.view.displaySupplierBuyerPos(startDateObj, endDateObj, supplierId, buyerId);
        },

        displayBuyerSupplierPos: function (supplierId, buyerId, startDate, endDate) {
            var startDateObj = startDate ? moment(startDate, "YYYYMMDD") : this.startDate,
                endDateObj = endDate ? moment(endDate, "YYYYMMDD") : this.endDate;

            this.view.displayBuyerSupplierPos(startDateObj, endDateObj, supplierId, buyerId);
        },

        displaySuppliersPo: function (startDate, endDate, tnid, pageSize, pageNo) {
            var startDateObj = startDate ? moment(startDate, "YYYYMMDD") : this.startDate,
                endDateObj = endDate ? moment(endDate, "YYYYMMDD") : this.endDate,
                pageSize = parseInt(pageSize),
                pageNo = parseInt(pageNo);

            this.view.displaySuppliersPo(startDateObj, endDateObj, tnid, pageSize, pageNo);
        },
    });
});