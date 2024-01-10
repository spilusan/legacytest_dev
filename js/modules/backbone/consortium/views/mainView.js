'use strict';

define([
    'jquery',
    'underscore',
    'Backbone',
    'handlebars',
    'backbone/shared/hbh/general',
    'backbone/consortium/modules/router',
    'text!templates/consortium/tpl/main.html',
    'templates/consortium/views/buyersTabView',
    'templates/consortium/views/buyersPoTabView',
    'templates/consortium/views/buyerSuppliersTabView',
    'templates/consortium/views/buyerSupplierPosTabView',
    'templates/consortium/views/supplierBuyerPosTabView',
    'templates/consortium/views/suppliersTabView',
    'templates/consortium/views/supplierBuyersTabView',
    'templates/consortium/views/suppliersPoTabView'
], function (
    $,
    _,
    Backbone,
    Hb,
    genHbh,
    Router,
    mainHtml,
    BuyersTabView,
    BuyersPoTabView,
    BuyerSuppliersTabView,
    BuyerSupplierPosTabView,
    SupplierBuyerPosTabView,
    SuppliersTabView,
    SupplierBuyersTabView,
    SuppliersPoTabView
) {
    var View = Backbone.View.extend({
        events: {
            'click .show-buyers': 'displayBuyers',
            'click .show-suppliers': 'displaySuppliers'
        },

        initialize: function () {
            this.render();
            this.router = new Router(this);
            this.activeTab = null;
            this.startDate = null;
            this.endDate = null;
        },

        render: function () {
            this.$el.html(mainHtml);

            return this;
        },

        displayBuyers: function (startDate, endDate) {
            $('#main-tabs a', this.$el).removeClass('active');
            $('#main-tabs a[href=#buyers]', this.$el).addClass('active');

            this.activeTab = new BuyersTabView(this.router);
            this.activeTab.render(startDate, endDate);

            $('#tab-content', this.$el).html(this.activeTab.el);
        },

        displayBuyersPos: function (startDate, endDate, tnid, pageSize, pageNo) {
            $('#main-tabs a', this.$el).removeClass('active');
            $('#main-tabs a[href=#buyers]', this.$el).addClass('active');

            this.activeTab = new BuyersPoTabView(this.router);
            this.activeTab.render(startDate, endDate, tnid, pageSize, pageNo);

            $('#tab-content', this.$el).html(this.activeTab.el);
        },

        displayBuyerSuppliers: function (startDate, endDate, tnid) {
            $('#main-tabs a', this.$el).removeClass('active');
            $('#main-tabs a[href=#buyers]', this.$el).addClass('active');

            this.activeTab = new BuyerSuppliersTabView(this.router);
            this.activeTab.render(startDate, endDate, tnid);

            $('#tab-content', this.$el).html(this.activeTab.el);
        },

        displaySuppliers: function (startDate, endDate) {
            $('#main-tabs a', this.$el).removeClass('active');
            $('#main-tabs a[href=#suppliers]', this.$el).addClass('active');

            this.activeTab = new SuppliersTabView(this.router);
            this.activeTab.render(startDate, endDate);

            $('#tab-content', this.$el).html(this.activeTab.el);
        },

        displaySupplierBuyers: function (startDate, endDate, tnid) {
            $('#main-tabs a', this.$el).removeClass('active');
            $('#main-tabs a[href=#suppliers]', this.$el).addClass('active');

            this.activeTab = new SupplierBuyersTabView(this.router);
            this.activeTab.render(startDate, endDate, tnid);

            $('#tab-content', this.$el).html(this.activeTab.el);
        },

        displaySupplierBuyerPos: function (startDate, endDate, supplierId, buyerId) {
            $('#main-tabs a', this.$el).removeClass('active');
            $('#main-tabs a[href=#suppliers]', this.$el).addClass('active');

            this.activeTab = new SupplierBuyerPosTabView(this.router);
            this.activeTab.render(startDate, endDate, supplierId, buyerId);

            $('#tab-content', this.$el).html(this.activeTab.el);
        },

        displayBuyerSupplierPos: function (startDate, endDate, supplierId, buyerId) {
            $('#main-tabs a', this.$el).removeClass('active');
            $('#main-tabs a[href=#buyers]', this.$el).addClass('active');

            this.activeTab = new BuyerSupplierPosTabView(this.router);
            this.activeTab.render(startDate, endDate, supplierId, buyerId);

            $('#tab-content', this.$el).html(this.activeTab.el);
        },

        displaySuppliersPo: function (startDate, endDate, tnid, pageSize, pageNo) {
            $('#main-tabs a', this.$el).removeClass('active');
            $('#main-tabs a[href=#suppliers]', this.$el).addClass('active');

            this.activeTab = new SuppliersPoTabView(this.router);
            this.activeTab.render(startDate, endDate, tnid, pageSize, pageNo);

            $('#tab-content', this.$el).html(this.activeTab.el);
        },


    });

    $(document).ready(function () {
        var mainView = new View();

        $('#mainContent').append(mainView.$el);
    });
});