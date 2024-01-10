define([
    'jquery',
    'underscore',
    'Backbone',
    '../collections/collection',
    './downloadPopupView',
    'text!templates/spr/tpl/orderingView.html',
    'text!templates/spr/tpl/simpleTable.html',
    'highchart-line-tooltip'
], function (
    $,
    _,
    Backbone,
    Collection,
    DownloadPopup,
    quotingTpl,
    simpleTableTpl,
    lineTooltip
) {
    var simpleTableTemplate = Handlebars.compile(simpleTableTpl);
    
    var errorHTML = '<div class="load-error">Error loading data</div>';

    var view = Backbone.View.extend({
        template: Handlebars.compile(quotingTpl),

        initialize: function () {
            this.isDataLoaded = false;
            this.completedRequests = 0;

            this.on('complete', function (event, request, settings) {
                ++this.completedRequests;

                if (this.completedRequests === 13) {
                    this.isDataLoaded = true;
                }
            });
        },

        getData: function (urlParams, reload) {
            var thisView = this;

            if (!reload && this.isDataLoaded) return;

            this.render();

            var params = {
                tnid: urlParams.suppliers.join(","),
                byb: urlParams.buyers.join(","),
                period: urlParams.daterange
            };

            if (urlParams.startdate) {
                params.startdate = urlParams.startdate;
                params.enddate = urlParams.enddate;
            }

            // Total spend table
            (function () {
                var tableDiv = $('#table-total-spend'),
                    loader = tableDiv.parent().parent().find('.waiting-spinner'),
                    query = $.param(params);

                function render(response) {
                    var tableData = Object.keys(response).map(function (key, index) {
                        return {
                            name: key,
                            value: '$' + Highcharts.numberFormat(response[key], 0, '.', ',')
                        };
                    });

                    var tableHtml = simpleTableTemplate(tableData);

                    loader.remove();
                    tableDiv.append(tableHtml);

                    // Total spend line chart, Call after spend chart loaded, as caching data for this call
                    (function () {
                        var chartDiv = $('#chart-total-spend'),
                            loader = $('.waiting-spinner', chartDiv),
                            query = $.param(params);

                        function render(response) {
                            loader.remove();

                            Highcharts.chart('chart-total-spend', {
                                chart: {
                                    type: 'line',
                                    renderTo: 'container',
                                    marginTop: 12
                                },
                                tooltip: lineTooltip('$'),
                                xAxis: {
                                    categories: response.axis,
                                    crosshair: {
                                        label: {
                                            enabled: true
                                        },
                                        useHTML: true,
                                        width: 1,
                                        color: '#ccc',
                                        className: 'crosshair-style',
                                        dashStyle: 'shortdot'
                                    },
                                    tickmarkPlacement: 'on'
                                },
                                yAxis: {
                                    title: {
                                        text: 'Spend (USD)'
                                    },
                                    crosshair: {
                                        label: {
                                            enabled: true
                                        },
                                        useHTML: true,
                                        width: 1,
                                        color: '#ccc',
                                        className: 'crosshair-style',
                                        dashStyle: 'shortdot'
                                    }
                                },
                                series: response.data
                            });
                        }

                        function error() {
                            loader.remove();
                            chartDiv.parent().append(errorHTML);
                        }

                        $.get('/reports/data/supplier-performance-order/spend?' + query)
                            .then(render, error)
                            .done(function () {
                                thisView.trigger('complete');
                            });
                    }());

                    // Average order value table, call after spend as it caches values for this call
                    (function () {
                        var tableDiv = $('#table-average-order-value'),
                            loader = tableDiv.parent().parent().find('.waiting-spinner'),
                            query = $.param(params);

                        function render(response) {
                            var tableData = Object.keys(response).map(function (key, index) {
                                return {
                                    name: key,
                                    value: '$' + Highcharts.numberFormat(response[key], 0, '.', ',')
                                };
                            });

                            var tableHtml = simpleTableTemplate(tableData);

                            loader.remove();
                            tableDiv.append(tableHtml);
                        }

                        function error() {
                            loader.remove();
                            tableDiv.parent().append(errorHTML);
                        }

                        $.get('/reports/data/supplier-performance-order/total-average-order-value?' + query).then(render, error);
                    }());

                    // Average order value line chart total spend caches data for that, calling after
                    (function () {
                        var chartDiv = $('#chart-average-order-value'),
                            loader = $('.waiting-spinner', chartDiv),
                            query = $.param(params);

                        function render(response) {
                            loader.remove();

                            Highcharts.chart('chart-average-order-value', {
                                chart: {
                                    type: 'line',
                                    renderTo: 'container',
                                    marginTop: 12
                                },
                                tooltip: {
                                    headerFormat: '{point.x}<br />',
                                    pointFormat: '{series.name}: ${point.y:,.0f}',
                                    shared: false
                                },
                                xAxis: {
                                    categories: response.axis,
                                    crosshair: {
                                        label: {
                                            enabled: true
                                        },
                                        useHTML: true,
                                        width: 1,
                                        color: '#ccc',
                                        className: 'crosshair-style',
                                        dashStyle: 'shortdot'
                                    },
                                    tickmarkPlacement: 'on'
                                },
                                yAxis: {
                                    title: {
                                        text: 'Order value (USD)'
                                    },
                                    crosshair: {
                                        label: {
                                            enabled: true
                                        },
                                        useHTML: true,
                                        width: 1,
                                        color: '#ccc',
                                        className: 'crosshair-style',
                                        dashStyle: 'shortdot'
                                    }
                                },
                                series: response.data
                            });
                        }

                        function error() {
                            loader.remove();
                            chartDiv.parent().append(errorHTML);
                        }

                        $.get('/reports/data/supplier-performance-order/average-order-value?' + query)
                            .then(render, error)
                            .done(function () {
                                thisView.trigger('complete');
                            });
                    }());

                    // Number of orders table
                    (function () {
                        var tableDiv = $('#table-number-of-orders'),
                            loader = tableDiv.parent().find('.waiting-spinner'),
                            query = $.param(params);

                        function render(response) {
                            var tableData = Object.keys(response).map(function (key, index) {
                                return {
                                    name: key,
                                    value: response[key]
                                };
                            });

                            var tableHtml = simpleTableTemplate(tableData);

                            loader.remove();
                            tableDiv.append(tableHtml);
                        }

                        function error() {
                            loader.remove();
                            tableDiv.parent().append(errorHTML);
                        }

                        $.get('/reports/data/supplier-performance-order/total-number-of-orders?' + query)
                            .then(render, error)
                            .done(function () {
                                thisView.trigger('complete');
                            });
                    }());

                    // Number of orders line chart
                    (function () {
                        var chartDiv = $('#chart-number-of-orders'),
                            loader = $('.waiting-spinner', chartDiv.parent()),
                            query = $.param(params);

                        function render(response) {
                            loader.remove();

                            Highcharts.chart('chart-number-of-orders', {
                                chart: {
                                    type: 'line',
                                    renderTo: 'container',
                                    marginTop: 12
                                },
                                tooltip: lineTooltip(),
                                xAxis: {
                                    categories: response.axis,
                                    crosshair: {
                                        label: {
                                            enabled: true
                                        },
                                        useHTML: true,
                                        width: 1,
                                        color: '#ccc',
                                        className: 'crosshair-style',
                                        dashStyle: 'shortdot'
                                    },
                                    tickmarkPlacement: 'on'
                                },
                                yAxis: {
                                    title: {
                                        text: 'Number of Orders'
                                    },
                                    crosshair: {
                                        label: {
                                            enabled: true
                                        },
                                        useHTML: true,
                                        width: 1,
                                        color: '#ccc',
                                        className: 'crosshair-style',
                                        dashStyle: 'shortdot'
                                    }
                                },
                                series: response.data
                            });
                        }

                        function error() {
                            loader.remove();
                            chartDiv.parent().append(errorHTML);
                        }

                        $.get('/reports/data/supplier-performance-order/number-of-orders?' + query)
                            .then(render, error)
                            .done(function () {
                                thisView.trigger('complete');
                            });
                    }());


                    // Orders confirmed line chart
                    (function () {
                        var chartDiv = $('#chart-orders-confirmed'),
                            loader = $('.waiting-spinner', chartDiv.parent()),
                            query = $.param(params);

                        function render(response) {
                            loader.remove();

                            Highcharts.chart('chart-orders-confirmed', {
                                chart: {
                                    type: 'line',
                                    renderTo: 'container',
                                    marginTop: 12
                                },
                                tooltip: {
                                    headerFormat: '{point.x}<br />',
                                    pointFormat: '{series.name}: {point.y:.0f}%',
                                    shared: false
                                },
                                xAxis: {
                                    categories: response.axis,
                                    crosshair: {
                                        label: {
                                            enabled: true
                                        },
                                        useHTML: true,
                                        width: 1,
                                        color: '#ccc',
                                        className: 'crosshair-style',
                                        dashStyle: 'shortdot'
                                    }
                                },
                                yAxis: {
                                    min: 0,
                                    max: 100,
                                    tickInterval: 10,
                                    minorTickINterval: 2,
                                    title: {
                                        text: '% Orders confirmed'
                                    },
                                    crosshair: {
                                        label: {
                                            enabled: true
                                        },
                                        useHTML: true,
                                        width: 1,
                                        color: '#ccc',
                                        className: 'crosshair-style',
                                        dashStyle: 'shortdot'
                                    }
                                },
                                colors: ['#3494CD'],
                                series: response.data
                            });

                        }

                        function error() {
                            loader.remove();
                            chartDiv.parent().append(errorHTML);
                        }

                        $.get('/reports/data/supplier-performance-order/orders-confirmed?' + query)
                            .then(render, error)
                            .done(function () {
                                thisView.trigger('complete');
                            });
                    }());

                    // Average time to confirm order
                    (function () {
                        var chartDiv = $('#chart-average-time-confirm'),
                            loader = $('.waiting-spinner', chartDiv.parent()),
                            query = $.param(params);

                        function render(response) {
                            loader.remove();

                                Highcharts.chart('chart-average-time-confirm', {
                                    chart: {
                                        type: 'line',
                                        renderTo: 'container',
                                        marginTop: 12
                                    },
                                    tooltip: lineTooltip(null, ' hour', ' hours'),
                                    xAxis: {
                                        categories: response.axis,
                                        crosshair: {
                                            label: {
                                                enabled: true
                                            },
                                            useHTML: true,
                                            width: 1,
                                            color: '#ccc',
                                            className: 'crosshair-style',
                                            tickmarkPlacement: 'on',
                                            dashStyle: 'shortdot'
                                        }
                                    },
                                    tickmarkPlacement: 'on',
                                    yAxis: {
                                        title: {
                                            text: 'Hours'
                                        },
                                        crosshair: {
                                            label: {
                                                enabled: true
                                            },
                                            useHTML: true,
                                            width: 1,
                                            color: '#ccc',
                                            className: 'crosshair-style',
                                            dashStyle: 'shortdot'
                                        },
                                        minRange: 0.1,
                                        min: 0
                                    },
                                    plotOptions: {
                                        line: {
                                            softThreshold: false
                                        }
                                    },
                                    colors: ['#3494CD'],
                                    series: response.data
                            });

                            // Total Average time to confirm order, Avarage time to confirm order caches some values for this
                            (function () {
                                var chartDiv = $('#bar-average-time-confirm'),
                                    loader = $('.waiting-spinner', chartDiv.parent()),
                                    query = $.param(params);

                                function render(response) {
                                    var categories = response.data[0].data.map(function (x) {
                                            return x[0];
                                        }),
                                        seriesData = response.data[0].data.map(function (x) {
                                            return x[1];
                                        });

                                    loader.remove();

                                        Highcharts.chart('bar-average-time-confirm', {
                                            chart: {
                                                type: 'column',
                                                renderTo: 'container',
                                                marginTop: 12
                                            },
                                            tooltip: {
                                                headerFormat: '',
                                                pointFormater: function (point) {
                                                    return Highcharts.numberFormat(point.y, 0, '.', ',') + point.y == 1 ? ' hour' : ' hours';
                                                },
                                                shared: false,
                                                useHTML: true
                                            },
                                            xAxis: {
                                                title: {
                                                    text: ''
                                                },
                                                categories: categories
                                            },
                                            categories: categories,
                                            yAxis: {
                                                title: {
                                                    text: 'Hours'
                                                }
                                            },
                                            colors: ['#3494CD'],
                                            series: [{
                                                name: 'Average time to confirm order (Hours)',
                                                data: seriesData
                                            }]
                                    });

                                }

                                function error() {
                                    loader.remove();
                                    chartDiv.parent().append(errorHTML);
                                }

                                $.get('/reports/data/supplier-performance-order/total-avg-time-to-confirm-order?' + query)
                                    .then(render, error)
                                    .done(function () {
                                        thisView.trigger('complete');
                                    });
                            }());

                        }

                        function error() {
                            loader.remove();
                            chartDiv.parent().append(errorHTML);
                        }

                        $.get('/reports/data/supplier-performance-order/avg-time-to-confirm-order?' + query)
                            .then(render, error)
                            .done(function () {
                                thisView.trigger('complete');
                            });
                    }());


                    //End the block for caching
                }

                function error() {
                    loader.remove();
                    tableDiv.parent().append(errorHTML);
                }

                $.get('/reports/data/supplier-performance-order/total-spend?' + query)
                    .then(render, error)
                    .done(function () {
                        thisView.trigger('complete');
                    });
            }());

            // Orders confirmed bar chart, avg time to comfirm order caching for this
            (function () {
                var chartDiv = $('#bar-orders-confirmed'),
                    loader = $('.waiting-spinner', chartDiv.parent()),
                    query = $.param(params);

                function render(response) {
                    var categories = response.data[0].data.map(function (x) {
                            return x[0];
                        }),
                        seriesData = response.data[0].data.map(function (x) {
                            return x[1];
                        });

                    loader.remove();

                    Highcharts.chart('bar-orders-confirmed', {
                        chart: {
                            type: 'column',
                            renderTo: 'container',
                            marginTop: 12
                        },
                        tooltip: {
                            headerFormat: '',
                            pointFormat: '{point.y:,.0f}%',
                            shared: false,
                            useHTML: true
                        },
                        xAxis: {
                            title: {
                                text: ''
                            },
                            categories: categories
                        },
                        yAxis: {
                            min: 0,
                            max: 100,
                            tickInterval: 10,
                            minorTickINterval: 2,
                            title: {
                                text: 'Percentage (%)'
                            }
                        },
                        colors: ['#3494CD'],
                        series: [{
                            name: '% Orders confirmed',
                            data: seriesData
                        }]
                    });
                }

                function error() {
                    loader.remove();
                    chartDiv.parent().append(errorHTML);
                }

                $.get('/reports/data/supplier-performance-order/total-orders-confirmed?' + query)
                    .then(render, error)
                    .done(function () {
                        thisView.trigger('complete');
                    });
            }());

            //most commonly bought items table
            (function () {
                var page = 1,
                    tableDiv = $('#table-most-commonly-bought-items'),
                    loader = tableDiv.parent().parent().find('.waiting-spinner'),
                    showMore = $('#more-most-commonly-bought-items');
                doExport = $('#more-most-commonly-bought-items-export');

                var table = tableDiv.DataTable({
                    bSort: false,
                    paging: false,
                    bFilter: false,
                    bInfo: false,
                    //scrollCollapse: true,
                    //sScrollX: "100%",
                    //bScrollCollapse: true,
                    responsive: true,
                    autoWidth: false,
                    fixedColumns: {
                        leftColumns: 2
                    },
                    columns: [{
                            title: 'Part No.',
                            data: 'part-no',
                            className: 'small'
                        },
                        {
                            title: 'Description',
                            data: 'description'
                        },
                        {
                            title: 'Unit of measure',
                            data: 'uom',
                            className: 'small text-center'
                        },
                        {
                            title: 'Quantity purchased',
                            data: 'quantity',
                            className: 'small text-right',
                            render: $.fn.dataTable.render.number(',')
                        },
                        {
                            title: 'Average unit price (USD)',
                            data: 'average-unit-price',
                            className: 'small text-right',
                            render: $.fn.dataTable.render.number(',', '.', 2, '$')
                        },
                        {
                            title: 'Total spend (USD)',
                            data: 'total-spend',
                            className: 'small text-right',
                            render: $.fn.dataTable.render.number(',', '.', 2, '$')
                        }
                    ]
                });

                showMore.hide();
                tableDiv.hide();

                function addRows(respone) {
                    var paramsEx = $.extend({
                            page: page++
                        }, params),
                        query = $.param(paramsEx);

                    loader.show();

                    $.get('/reports/data/supplier-performance-order/common-items?' + query).then(function (response) {
                        table.rows.add(response.data);

                        setTimeout(function () {
                            table.draw();
                            loader.hide();
                            if (response.data.length < 10) {
                                showMore.hide();
                            }
                        }, 200);

                        tableDiv.show();
                        showMore.show();
                    }, function () {
                        loader.remove();
                        showMore.hide();
                        tableDiv.parent().append(errorHTML);
                    }).done(function () {
                        thisView.trigger('complete');
                    });
                }

                function exportList(respone) {
                    thisView.exportPopup();
                    query = $.param(params);
                    location.href = '/reports/export/supplier-performance-order?type=common-items&' + query;
                }

                showMore.click(function () {
                    addRows();
                });

                doExport.click(function () {
                    exportList();
                });

                addRows();
            }());

            // Spend by vessel
            (function () {
                var page = 1,
                    tableDiv = $('#table-spend-by-vessel'),
                    loader = tableDiv.parent().parent().find('.waiting-spinner'),
                    showMore = $('#more-spend-by-vessel');
                doExport = $('#more-spend-by-vessel-export');

                var table = tableDiv.DataTable({
                    bSort: false,
                    paging: false,
                    bFilter: false,
                    bInfo: false,
                    //scrollCollapse: true,
                    //sScrollX: "100%",
                    //bScrollCollapse: true,
                    responsive: true,
                    autoWidth: false,
                    fixedColumns: {
                        leftColumns: 2
                    },
                    columns: [{
                            title: 'IMO',
                            data: 'vessel-imo-no',
                            className: 'small'
                        },
                        {
                            title: 'Vessel name',
                            data: 'vessel-name'
                        },
                        {
                            title: 'Vessel type',
                            data: 'vessel-type-name'
                        },
                        {
                            title: 'Orders',
                            data: 'ord-count',
                            className: 'small text-right',
                            render: $.fn.dataTable.render.number(',')
                        },
                        {
                            title: 'Spend (USD)',
                            data: 'ord-total-cost-discounted-usd',
                            className: 'small text-right',
                            render: $.fn.dataTable.render.number(',', '.', 0, '$')
                        }
                    ]
                });

                showMore.hide();
                tableDiv.hide();

                function addRows(respone) {
                    var paramsEx = $.extend({
                            page: page++
                        }, params),
                        query = $.param(paramsEx);

                    loader.show();

                    $.get('/reports/data/supplier-performance-order/spend-by-vessel-items?' + query).then(function (response) {
                        table.rows.add(response.data);

                        setTimeout(function () {
                            table.draw();
                            loader.hide();
                            if (response.data.length < 10) {
                                showMore.hide();
                            }
                        }, 200);

                        tableDiv.show();
                        showMore.show();
                    }, function () {
                        loader.remove();
                        tableDiv.parent().append(errorHTML);
                    }).done(function () {
                        thisView.trigger('complete');
                    });
                }

                function exportList(respone) {
                    thisView.exportPopup();
                    query = $.param(params);
                    location.href = '/reports/export/supplier-performance-order?type=spend-by-vessel-items&' + query;
                }

                showMore.click(function () {
                    addRows();
                });

                doExport.click(function () {
                    exportList();
                });

                addRows();
            }());

            // Spend by vessel type table and chart
            (function () {
                var query = $.param(params),
                    tableDiv = $('#table-spend-by-vessel-type'),
                    chartDiv = $('#pie-spend-by-vessel-type'),
                    tableLoader = tableDiv.parent().parent().find('.waiting-spinner'),
                    chartLoader = $('.waiting-spinner', chartDiv);

                function render(response) {
                    response = response.data.pop();

                    chartLoader.remove();
                    tableLoader.remove();

                    var tableData = response.data.map(function (x) {
                        return {
                            name: x[0],
                            value: '$' + Highcharts.numberFormat(x[1], 0, '.', ',')
                        };
                    });

                    var tableHtml = simpleTableTemplate(tableData);

                    tableDiv
                        .empty()
                        .append(tableHtml)
                        .next('.waiting-spinner').remove();

                    var total = response.data.reduce(function (acc, val) {
                            return acc + parseInt(val[1]);
                        }, 0),
                        pieSeries = response.data.map(function (x) {
                            return {
                                name: x[0],
                                y: x[1] / total * 100
                            };
                        });

                    //chartDiv.css('maxWidth', 600);

                    Highcharts.chart('pie-spend-by-vessel-type', {
                        chart: {
                            type: 'pie'
                        },
                        tooltip: {
                            pointFormat: '{point.name}: {point.percentage:,.1f}%',
                            useHTML: true,
                            shared: false
                        },
                        legend: {
                            align: 'right',
                            layout: 'vertical',
                            verticalAlign: 'middle'
                        },
                        series: [{
                            name: 'Vessel Types',
                            colorByPoint: true,
                            data: pieSeries,
                            dataLabels: {
                                format: '{point.percentage:.1f}%'
                            },
                            showInLegend: true
                        }]
                    });
                }

                function error() {
                    chartLoader.remove();
                    tableLoader.remove();
                    tableDiv.parent().append(errorHTML);
                }

                $.get('/reports/data/supplier-performance-order/spend-by-vessel-type?' + query)
                    .then(render, error)
                    .done(function () {
                        thisView.trigger('complete');
                    });
            }());

            // Spend by purchaser table
            (function () {
                var page = 1,
                    tableDiv = $('#table-spend-by-purchaser'),
                    loader = tableDiv.parent().parent().find('.waiting-spinner'),
                    showMore = $('#more-spend-by-purchaser');
                doExport = $('#more-spend-by-purchaser-export');


                var table = tableDiv.DataTable({
                    bSort: false,
                    paging: false,
                    bFilter: false,
                    bInfo: false,
                    //scrollCollapse: true,
                    //sScrollX: "100%",
                    //bScrollCollapse: true,
                    responsive: true,
                    autoWidth: false,
                    fixedColumns: {
                        leftColumns: 2
                    },
                    columns: [{
                            title: 'Name / email address',
                            data: 'name-and-email'
                        },
                        {
                            title: 'RFQs',
                            data: 'rfq-count-total',
                            className: 'small text-right',
                            render: $.fn.dataTable.render.number(',')
                        },
                        {
                            title: 'Competitive orders',
                            data: 'ord-count-competitive',
                            className: 'small text-right',
                            render: $.fn.dataTable.render.number(',')
                        },
                        {
                            title: 'Direct orders',
                            data: 'ord-count-direct',
                            className: 'small text-right',
                            render: $.fn.dataTable.render.number(',')
                        },
                        {
                            title: 'RFQs no PO',
                            data: 'rfq-count-no-order',
                            className: 'small text-right',
                            render: $.fn.dataTable.render.number(',')
                        }
                    ]
                });

                showMore.hide();
                tableDiv.hide();

                function addRows(respone) {
                    var paramsEx = $.extend({
                            page: page++
                        }, params),
                        query = $.param(paramsEx);

                    loader.show();

                    $.get('/reports/data/supplier-performance-order/spend-by-purchaser-items?' + query).then(function (response) {
                        table.rows.add(response.data);

                        setTimeout(function () {
                            table.draw();
                            loader.hide();
                            if (response.data.length < 10) {
                                showMore.hide();
                            }
                        }, 200);

                        tableDiv.show();
                        showMore.show();
                    }, function () {
                        loader.remove();
                        tableDiv.parent().append(errorHTML);
                    }).done(function () {
                        thisView.trigger('complete');
                    });
                }

                function exportList(respone) {
                    thisView.exportPopup();
                    query = $.param(params);
                    location.href = '/reports/export/supplier-performance-order?type=spend-by-purchaser-items&' + query;
                }

                showMore.click(function () {
                    addRows();
                });

                doExport.click(function () {
                    exportList();
                });

                addRows();
            }());
        },

        render: function () {
            var thisView = this;
            var html = this.template();

            $('#tab-5').html(html);

            $('.tip').shTooltip();
        },

        exportPopup: function() {
            
            var thisView = this;
            this.setCookie('showSpinner', 'true', 1);
            DownloadPopup.render(true);
            var SpnnerTimer = setInterval(function(){
                if (thisView.getCookie('showSpinner') === '') {
                    clearInterval(SpnnerTimer);
                    DownloadPopup.render(false);
                }
            }, 1000);
        },

        setCookie: function(cname, cvalue, exdays) {
            var d = new Date();
            d.setTime(d.getTime() + (exdays*24*60*60*1000));
            var expires = "expires="+ d.toUTCString();
            document.cookie = cname + "=" + cvalue + "; path=/; " + expires;
        },

        getCookie: function(cname) {
            var name = cname + "=";
            var ca = document.cookie.split(';');
            for(var i = 0; i <ca.length; i++) {
                var c = ca[i];
                while (c.charAt(0)==' ') {
                    c = c.substring(1);
                }
                if (c.indexOf(name) === 0) {
                    return c.substring(name.length,c.length);
                }
            }
            return "";
        }
    });

    return new view();
});