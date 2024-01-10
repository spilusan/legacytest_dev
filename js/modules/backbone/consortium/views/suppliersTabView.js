'use strict';

define([
    'jquery',
    'underscore',
    'Backbone',
    'handlebars',
    'backbone/shared/hbh/general',
    'backbone/consortium/modules/findIndex',
    'backbone/consortium/modules/datePickerConfig',
    'text!templates/consortium/tpl/loader.html',
    'text!templates/consortium/tpl/buyersTab.html',
    'text!templates/consortium/tpl/supplierTable.html',
    'text!templates/consortium/tpl/supplierTableRow.html',
], function (
    $,
    _,
    Backbone,
    Hb,
    genHbh,
    findIndex,
    datePickerConfig,
    loaderHtml,
    mainHtml,
    tableHtml,
    tableRowHtml
) {
    var mainTemplate = Handlebars.compile(mainHtml),
        tableTemplate = Handlebars.compile(tableHtml),
        tableRowTemplate = Handlebars.compile(tableRowHtml);

    var View = Backbone.View.extend({
        events: {
            'click .show-children': 'showChildren',
            'click .hide-children': 'hideChildren',
            'click .view-pos': 'viewPos',
            'click .view-buyers': 'viewBuyers',
            'click .export-csv': 'export'
        },

        initialize: function (router) {
            this.router = router;
            this.startDate = null;
            this.endDate = null;
        },

        render: function (startDate, endDate) {
            this.startDate = startDate;
            this.endDate = endDate;

            this.renderMain();
            this.renderPicker();
            this.getTableData();

            $('.export-csv', this.$el).attr('disabled', true);
        },

        renderMain: function () {
            var data = {
                    title: 'All Suppliers'
                },
                html = mainTemplate(data);

            this.$el.html(html);
        },

        renderPicker: function () {
            var _this = this,
                date = moment().startOf('day'),
                picker,
                config,
                startDate = _this.startDate,
                endDate = _this.endDate;

            startDate = startDate || date.clone().subtract(30, 'days');
            endDate = endDate || date.clone();

            _this.startDate = startDate;
            _this.endDate = endDate;

            config = datePickerConfig(startDate, endDate);

            picker = $('#date-picker', _this.$el).daterangepicker(config, function (start, end, label) {
                _this.router.startDate = start;
                _this.router.endDate = end;

                _this.startDate = start;
                _this.endDate = end;

                _this.getTableData.call(_this);
            });

            return picker;
        },

        renderTableData: function (data) {
            var _this = this,
                tableContainer = $('#buyers-table', this.$el),
                html,
                rowElements;

            var tableData = _.pick(data, 'total_po_cnt', 'total_po_spend');

            if (data.suppliers.length) {
                tableData.showTotals = true;
            }

            html = tableTemplate(tableData);

            tableContainer
                .removeClass('loading')
                .html(html);

            if (data.suppliers.length) {
                rowElements = data.suppliers.map(function (x) {
                    return $(tableRowTemplate(x));
                });

                $('tbody', tableContainer).empty().append(rowElements);
            }

            $('.export-csv', this.$el).attr('disabled', data.total_po_cnt === 0);
        },

        loadChildren: function (url, tnid, parentRow) {
            var _this = this,
                tableContainer = $('#buyer-table', this.$el),
                parentChildLevel = (parentRow.data('childLevel') || 1) + 1;

            tableContainer.addClass('loading');
            parentRow.addClass('loading-children');

            setTimeout(function () {
                $.getJSON(url, function (data) {
                    var rowElements

                    tableContainer.removeClass('loading');

                    rowElements = data.suppliers.map(function (x) {
                        x.isChild = true;
                        x.parentId = tnid;

                        var row = $(tableRowTemplate(x));

                        row.data('childLevel', parentChildLevel);

                        $('.name', row).css({
                            paddingLeft: parentChildLevel * 20,
                            backgroundPositionX: (parentChildLevel - 1) * 20 - 10
                        });

                        return row;
                    });

                    $(rowElements.slice(-1)[0]).addClass('last-child');

                    parentRow
                        .removeClass('loading-children')
                        .toggleClass('open')
                        .toggleClass('closed');

                    $(rowElements).insertAfter(parentRow);
                }, function () {
                    tableContainer.removeClass('loading');

                    alert('Error loading table data');
                });
            }, 250);
        },

        getTableData: function () {
            var _this = this,
                tableContainer = $('#buyers-table', this.$el),
                lowerDate = this.startDate.format('YYYYMMDD'),
                upperDate = this.endDate.format('YYYYMMDD');

            var url = [
                null,
                'reports',
                'data',
                'consortium',
                'suppliers'
            ].join('/') + '?' + [
                'lowerdate=' + lowerDate,
                'upperdate=' + upperDate
            ].join('&');

            var routerPath = [
                'suppliers',
                lowerDate,
                upperDate
            ].join('/');

            this.router.navigate(routerPath);

            tableContainer.html(loaderHtml);
            tableContainer.addClass('loading');

            setTimeout(function () {
                $.getJSON(url, function (data) {
                    _this.renderMain();
                    _this.renderPicker();
                    _this.renderTableData.call(_this, data);
                }, function () {
                    tableContainer.removeClass('loading');

                    alert('Error loading table data');
                });
            }, 250);
        },

        showChildren: function (e) {
            e.preventDefault();

            var parentRow = $(e.target).closest('tr'),
                lowerDate = this.startDate.format('YYYYMMDD'),
                upperDate = this.endDate.format('YYYYMMDD'),
                tnid = $(e.target).closest('tr').data('tnid');

            var url = [
                null,
                'reports',
                'data',
                'consortium',
                'suppliers',
                tnid,
                'child-suppliers'
            ].join('/') + '?' + [
                'lowerdate=' + lowerDate,
                'upperdate=' + upperDate
            ].join('&');

            this.loadChildren(url, tnid, parentRow);
        },

        hideChildren: function (e) {
            e.preventDefault();

            var row = $(e.target).closest('tr'),
                tnid = row.data('tnid');

            row.toggleClass('open').toggleClass('closed');

            $('tr[data-parent-tnid=' + tnid + ']').remove();
        },

        viewPos: function (e) {
            var row = $(e.target).closest('tr'),
                lowerDate = this.startDate.format('YYYYMMDD'),
                upperDate = this.endDate.format('YYYYMMDD'),
                tnid = $(e.target).closest('tr').data('tnid');

            var routerPath = [
                'suppliers-pos',
                lowerDate,
                upperDate,
                tnid
            ].join('/');

            this.router.navigate(routerPath, true, true);
        },

        viewBuyers: function (e) {
            var row = $(e.target).closest('tr'),
                lowerDate = this.startDate.format('YYYYMMDD'),
                upperDate = this.endDate.format('YYYYMMDD'),
                tnid = row.data('tnid');

            var routerPath = [
                'supplier-buyers',
                tnid,
                lowerDate,
                upperDate
            ].join('/');

            this.router.navigate(routerPath, true, true);
        },

        export: function (e) {
            e.preventDefault();

            var lowerDate = this.startDate.format('YYYYMMDD'),
                upperDate = this.endDate.format('YYYYMMDD'),
                url = '/reports/data/consortium/export-csv?lowerdate=' + lowerDate + '&upperdate=' + upperDate;

            window.open(url, '_self');
        }
    });

    return View;
});