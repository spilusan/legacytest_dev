'use strict';

define([
    'jquery',
    'underscore',
    'Backbone',
    'handlebars',
    'backbone/shared/hbh/general',
    'backbone/consortia/modules/router',
    'text!templates/consortia/tpl/main.html',
    'templates/consortia/views/buyersTabView',
    'templates/consortia/views/buyersPoTabView',
    'templates/consortia/views/buyerSuppliersTabView',
    'templates/consortia/views/buyerSupplierPosTabView',
    'templates/consortia/views/supplierBuyerPosTabView',
    'templates/consortia/views/suppliersTabView',
    'templates/consortia/views/supplierBuyersTabView',
    'templates/consortia/views/suppliersPoTabView'
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
        rateManagementUrl: require('salesforce/rateManagament'),
        events: {
            'click .show-buyers': 'displayBuyers',
            'click .show-suppliers': 'displaySuppliers',
            'click .rate-management': 'showRateManagement'
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

        showRateManagement: function (e) {
            e.preventDefault();

            window.open(this.rateManagementUrl, '_self');
        }
    });

    $(document).ready(function () {
        var mainView = new View();

        $('#mainContent').append(mainView.$el);
    });
});