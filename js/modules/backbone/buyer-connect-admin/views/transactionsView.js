'use strict';

define([
    'jquery',
    'underscore',
    'Backbone'
], function (
    $,
    _,
    Backbone
) {
    var View = Backbone.View.extend({
        initialize: function (options) {
            this.options = options || {};
            this.render();

            if (this.options.delayedRefresh) {
                var view = this;

                setTimeout(function () {
                    view.render.call(view);
                }, 30000);
            }
        },

        render: function () {
            this.$el.empty();
            this.$el.append('<h2>Transactions</h2>');
            this.$el.append('<table id="transactions"></table>');

            var page = 1,
                tableDiv = $('#transactions', this.$el);

            var table = tableDiv.DataTable({
                bSort: false,
                paging: false,
                bFilter: false,
                bInfo: false,
                responsive: true,
                autoWidth: false,
                fixedColumns: {
                    leftColumns: 2
                },
                columns: [{
                        title: 'ID',
                        data: 'id'
                    },
                    {
                        title: 'Date',
                        data: 'transactionDate',
                        render: function (data) {
                            var date = new Date(data);

                            return date.toLocaleDateString('en-GB') + ' ' + date.toLocaleTimeString('en-GB');
                        },
                        className: 'text-center min nowrap'
                    },
                    {
                        title: 'Buyer ID',
                        data: 'buyer.buyerId',
                        cxlassName: 'text-right'
                    },
                    {
                        title: 'Buyer Name',
                        data: 'buyer.name'
                    },
                    {
                        title: 'Supplier ID',
                        data: 'supplier.supplierId',
                        className: 'text-right'
                    },
                    {
                        title: 'Supplier Name',
                        data: 'supplier.name'
                    },
                    {
                        title: 'Type',
                        data: 'docType',
                        className: 'text-center'
                    },
                    {
                        title: 'Format',
                        data: 'fileFormat',
                        className: 'text-center'
                    },
                    {
                        title: 'Workflow Status',
                        data: 'workFlowStatus',
                        className: 'text-center'
                    },
                    {
                        title: 'Status',
                        data: 'status',
                        className: 'text-center'
                    },
                    {
                        title: 'Action',
                        data: null,
                        className: 'text-center min nowrap',
                        render: function (data, type, row, meta) {
                            return '<a href="#" view-details data-id="' + row.id + '">View</a>';
                        }
                    }
                ],
                ajax: {
                    url: '/reports/data/buyer-connect/transaction/workFlowStatus/Open_Processing/',
                    dataSrc: function (data) {
                        return data;
                    }
                }
            });

            return this;
        }
    });

    return View;
});