'use strict';

define([
    'jquery',
    'underscore',
    'Backbone',
    'handlebars',
    'backbone/shared/hbh/general',
    'backbone/consortia/modules/findIndex',
    'backbone/consortia/modules/datePickerConfig',
    'text!templates/consortia/tpl/loader.html',
    'text!templates/consortia/tpl/supplierPoTab.html',
    'text!templates/consortia/tpl/supplierPoTable.html',
    'text!templates/consortia/tpl/supplierPoTableRow.html',
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
        tableRowTemplate = Handlebars.compile(tableRowHtml),
        drilldownEnabled = false;

    var View = Backbone.View.extend({
        events: {
            'click .show-children': 'showChildren',
            'click .hide-children': 'hideChildren',
            'click .export-csv': 'export',
            'change .page-size': 'changePageSize',
            'click .pager a': 'changePageNo',
            'click .back': 'navigateBack',
            'click .view-po': 'viewPo'
        },

        initialize: function (router) {
            this.router = router;
            this.startDate = null;
            this.endDate = null;
            this.pageSize = 10;
            this.pageNo = 1;
        },

        render: function (startDate, endDate, tnid, pageSize, pageNo) {
            this.startDate = startDate;
            this.endDate = endDate;
            this.tnid = tnid;
            this.pageSize = pageSize || this.pageSize;
            this.pageNo = pageNo || this.pageNo;

            this.renderMain();
            this.renderPicker();
            this.getTableData();
        },

        renderMain: function (data) {
            var data = _.pick(data || {}, 'total_po_cnt', 'total_po_spend', 'supplier'),
                html = mainTemplate(data);

            this.$el.html(html);

            $('.page-size', this.$el).val(this.pageSize);
            $('.export-csv', this.$el).attr('disabled', true);
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
                tableContainer = $('#buyers-table', this.$el);

            data.drilldownEnabled = drilldownEnabled;
            var html = tableTemplate(data),
                rowElements;

            tableContainer.removeClass('loading');
            tableContainer.html(html);

            if (data.pos.length) {
                rowElements = data.pos.map(function (x) {
                    x.drilldownEnabled = drilldownEnabled;
                    return $(tableRowTemplate(x));
                });

                $('tbody', tableContainer).empty().append(rowElements);
            }

            this.renderPagination(data.total_po_cnt);

            $('.export-csv', this.$el).attr('disabled', data.total_po_cnt === 0);
        },

        renderPagination: function (numOfRows) {
            var pager = $('.pager', this.$el),
                pageLinks = [],
                numOfPages = Math.ceil(numOfRows / this.pageSize),
                currentPage = this.pageNo,
                pageLinksToShow = 8,
                pageLinksPad = pageLinksToShow / 2,
                jumpPad = 2,
                startPage,
                endPage,
                showFirst = false,
                showLast = false;

            if (numOfPages === 0) {
                pager.empty().hide();

                return;
            }

            if (numOfPages < pageLinksToShow) {
                startPage = 1;
                endPage = numOfPages;
            } else if (currentPage - pageLinksPad < 1) {
                showLast = true;
                startPage = 1;
                endPage = pageLinksToShow;
            } else if (currentPage + pageLinksToShow < numOfPages) {
                showFirst = true;
                showLast = true;
                startPage = currentPage - pageLinksPad + jumpPad;
                endPage = currentPage + pageLinksPad - jumpPad;
            } else if (numOfPages < pageLinksToShow) {
                startPage = 1;
                endPage = numOfPages;
            } else {
                showFirst = true;
                startPage = numOfPages - pageLinksToShow + jumpPad;
                endPage = numOfPages;
            }

            function addPageLink(pageNum, currentPage) {
                var isCurrentPage = i === currentPage,
                    li = $('<li></li>').toggleClass('active', isCurrentPage);

                if (isCurrentPage) {
                    $('<span></span>').text(pageNum).appendTo(li);
                } else {
                    $('<a href="#"></a>').text(pageNum).data('page', pageNum).appendTo(li);
                }

                pageLinks.push(li);
            }

            var prevPage = $('<li></li>'),
                nextPage = $('<li></li>');

            if (currentPage === 1) {
                $('<span></span>').text('<').appendTo(prevPage);

                prevPage.addClass('disabled');
            } else {
                $('<a href="#"></a>').text('<').data('page', currentPage - 1).appendTo(prevPage);
            }

            if (currentPage === numOfPages) {
                $('<span></span>').text('>').appendTo(nextPage);

                nextPage.addClass('disabled');
            } else {
                $('<a href="#"></a>').text('>').data('page', currentPage + 1).appendTo(nextPage);
            }

            pageLinks.push(prevPage);

            if (showFirst) {
                var spacer = $('<li></li>').addClass('disabled');

                $('<span></span>').text('...').appendTo(spacer);

                addPageLink(1, currentPage);

                pageLinks.push(spacer);
            }

            var liSpacer = $('<li></li>').text('>').addClass('disabled');

            for (var i = startPage; i <= endPage; i++) {
                addPageLink(i, currentPage);
            }

            if (showLast) {
                var spacer = $('<li></li>').addClass('disabled');

                $('<span></span>').text('...').appendTo(spacer);

                pageLinks.push(spacer);

                addPageLink(numOfPages, currentPage);
            }

            pageLinks.push(nextPage);

            pager.empty().show().append(pageLinks);
        },

        getTableData: function () {
            var _this = this,
                tableContainer = $('#buyers-table', this.$el),
                lowerDate = this.startDate.format('YYYYMMDD'),
                upperDate = this.endDate.format('YYYYMMDD');

            tableContainer.append(loaderHtml);
            tableContainer.addClass('loading');

            var url = [
                null,
                'reports',
                'data',
                'consortia',
                'suppliers',
                this.tnid,
                'post'
            ].join('/') + '?' + [
                'lowerdate=' + lowerDate,
                'upperdate=' + upperDate,
                'pageNo=' + this.pageNo,
                'pageSize=' + this.pageSize
            ].join('&');

            var routerPath = [
                'suppliers-pos',
                lowerDate,
                upperDate,
                this.tnid,
                this.pageSize,
                this.pageNo
            ].join('/');

            this.router.navigate(routerPath);

            setTimeout(function () {
                $.getJSON(url, function (data) {
                        var mainData = _.pick(data, 'supplier', 'total_po_cnt', 'total_po_spend'),
                            tableData = _.pick(data, 'pos', 'total_po_cnt');

                        _this.renderMain(mainData);
                        _this.renderPicker();
                        _this.renderTableData.call(_this, tableData);
                    },
                    function () {
                        tableContainer.removeClass('loading');

                        alert('Error loading table data');
                    });
            }, 1000);
        },

        changePageSize: function (e) {
            var pageSizePrev = this.pageSize;

            this.pageSize = $(e.target).val() * 1;
            this.pageNo = Math.ceil(this.pageNo / Math.ceil(this.pageSize / pageSizePrev));

            this.getTableData();
        },

        changePageNo: function (e) {
            e.preventDefault();

            this.pageNo = $(e.target).data('page') * 1;

            this.getTableData();
        },

        navigateBack: function (e) {
            e.preventDefault();

            var row = $(e.target).closest('tr'),
                lowerDate = this.startDate.format('YYYYMMDD'),
                upperDate = this.endDate.format('YYYYMMDD');

            var routerPath = [
                'suppliers',
                lowerDate,
                upperDate
            ].join('/');

            this.router.navigate(routerPath, true, true);
        },

        export: function (e) {
            e.preventDefault();

            var lowerDate = this.startDate.format('YYYYMMDD'),
                upperDate = this.endDate.format('YYYYMMDD'),
                url = '/reports/data/consortia/export-csv?lowerdate=' + lowerDate + '&upperdate=' + upperDate;

            window.open(url, '_self');
        },

        viewPo: function (e) {
            e.preventDefault();

            var url = $(e.target).data('url');

            window.open(url);
        }
    });

    return View;
});