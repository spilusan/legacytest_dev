define([
    'jquery',
    'underscore',
    'Backbone',
    '../collections/collection',
    'text!templates/spr/tpl/competitivenessView.html',
    'text!templates/spr/tpl/competitiveQuoteSituationsView.html',
    'text!templates/spr/tpl/coQuotersView.html',
    'text!templates/spr/tpl/alternativeSuppliers.html',
    'highchart-line-tooltip',
    'highcharts-defaults-print'
], function (
    $,
    _,
    Backbone,
    Collection,
    competitivenessTpl,
    competitiveQuoteSituationsTpl,
    coQuotersTpl,
    alternativeSuppliersTpl,
    lineTooltip,
    highchartsDefaultsPrint
) {
    var coQuotersTemplate = Handlebars.compile(coQuotersTpl);
    var competitiveQuoteSituationsTemplate = Handlebars.compile(competitiveQuoteSituationsTpl);
    var alternativeSuppliersTemplate = Handlebars.compile(alternativeSuppliersTpl);
    var loaderHTML = '<div class="waiting-spinner">Loading...</div>';
    var errorHTML = '<div class="load-error">Error loading data</div>';

    var view = Backbone.View.extend({
        template: Handlebars.compile(competitivenessTpl),

        initialize: function () {
            this.isDataLoaded = false;
            this.completedRequests = 0;

            this.on('complete', function (event, request, settings) {
                ++this.completedRequests;

                if (this.completedRequests === 6) {
                    this.isDataLoaded = true;
                }
            });
        },

        getData: function (urlParams, reload) {
            var thisView = this;

            if (!reload && this.isDataLoaded) return;

            $('#tab-4').empty();

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

            // Competitive quote situations
            //=============================

            // Table
            (function () {
                var tableDiv = $('#table-quote-situations'),
                    loader = $(loaderHTML).appendTo(tableDiv.parent()),
                    query = $.param(params);

                function render(response) {
                    var tableHtml = competitiveQuoteSituationsTemplate(response.data);

                    loader.remove();
                    tableDiv.append(tableHtml);
                }

                function error() {
                    loader.remove();
                    tableDiv.parent().append(errorHTML);
                }

                $.get('/reports/data/supplier-performance-competitiveness/competitive-quote-situations?' + query)
                    .then(render, error)
                    .done(function () {
                        thisView.trigger('complete');
                    });
            }());


            // Line chart
            (function () {
                var chartDiv = $('#line-quote-situation'),
                    loader = $(loaderHTML).appendTo(chartDiv.parent()),
                    query = $.param(params);

                function render(response) {
                    loader.remove();

                    Highcharts.chart('line-quote-situation', {
                        chart: {
                            type: 'line',
                            renderTo: 'container',
                            marginTop: 12
                        },
                        tooltip: lineTooltip(),
                        plotOptions: {
                            series: {
                                connectNulls: true
                            }
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
                                text: 'Number of CQS'
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

                $.get('/reports/data/supplier-performance-competitiveness/quote-situation-trend?' + query)
                    .then(render, error)
                    .done(function () {
                        thisView.trigger('complete');
                    });
            }());


            // Sensitivity
            //=========================

            // Price sensitivity column
            (function () {
                var chartDiv = $('#column-price-sensitivity'),
                    loader = $(loaderHTML).appendTo(chartDiv.parent()),
                    query = $.param(params);

                function render(response) {
                    var categories = response.data[0].data.map(function (x) {
                            return x[0];
                        }),
                        seriesData = response.data[0].data.map(function (x) {
                            return x[1];
                        });

                    loader.remove();

                    Highcharts.chart('column-price-sensitivity', {
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
                            max: 100,
                            title: {
                                text: 'Win rate when cheapest (%)'
                            }
                        },
                        legend: false,
                        colors: ['#3494ce'],
                        series: response.data
                    });
                }

                function error() {
                    loader.remove();
                    chartDiv.parent().append(errorHTML);
                }

                $.get('/reports/data/supplier-performance-competitiveness/price-sensitivity?' + query)
                    .then(render, error)
                    .done(function () {
                        thisView.trigger('complete');
                    });
            }());


            // Time sensitivity column
            (function () {
                var chartDiv = $('#column-time-sensitivity'),
                    loader = $(loaderHTML).appendTo(chartDiv.parent()),
                    query = $.param(params);

                function render(response) {
                    var categories = response.data[0].data.map(function (x) {
                            return x[0];
                        }),
                        seriesData = response.data[0].data.map(function (x) {
                            return x[1];
                        });

                    loader.remove();

                    Highcharts.chart('column-time-sensitivity', {
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
                            max: 100,
                            title: {
                                text: 'Win rate when fastest (%)'
                            }
                        },
                        legend: false,
                        colors: ['#00b5ee'],
                        series: response.data
                    });
                }

                function error() {
                    loader.remove();
                    chartDiv.parent().append(errorHTML);
                }

                $.get('/reports/data/supplier-performance-competitiveness/time-sensitivity?' + query)
                    .then(render, error)
                    .done(function () {
                        thisView.trigger('complete');
                    });
            }());

            // Co-quoters
            //===========

            // Pie chart
            (function () {
                var chartDiv = $('#pie-co-quoters'),
                    chartLoader = $(loaderHTML).appendTo(chartDiv.parent());
                query = $.param(params);

                function render(response) {
                    chartLoader.remove();

                    var pieSeriesData = response.data.map(function (x) {
                        x.y = x['group-pct'];
                        return x;
                    });

                    pieSeriesData.sort(function (a, b) {
                        return a['group-count'] - b['group-count'];
                    });

                    Highcharts.chart('pie-co-quoters', {
                        chart: {
                            type: 'pie',
                            y: 100
                        },
                        plotOptions: {
                            pie: {
                                data: pieSeriesData,
                                borderWidth: 0,
                                showInLegend: false
                            }
                        },
                        tooltip: {
                            pointFormat: '{point.group-name}<br>{point.group-pct:,.1f}%',
                            useHTML: true,
                            shared: false
                        },
                        legend: false,
                        series: [{
                                type: 'pie',
                                name: 'Co-quoted',
                                dataLabels: {
                                    color: '#fff',
                                    distance: window.isPrint ? -15 : -30,
                                    style: {
                                        textOutline: false,
                                        fontSize: window.isPrint ? '0.75em' : '1.5em'
                                    },
                                    formatter: function () {
                                        return this.point['group-count'];
                                    },
                                    format: null
                                }
                            },
                            {
                                type: 'pie',
                                name: 'Co-quoted',
                                dataLabels: {
                                    softConnector: false,
                                    connectorWidth: 1,
                                    verticalAlign: 'top',
                                    distance: 20,
                                    formatter: function () {
                                        return Math.round(this.point['group-pct'] * 100) / 100 + '%';
                                    }
                                }
                            }
                        ]
                    });
                }

                function error() {
                    chartLoader.remove();
                    chartDiv.parent().append(errorHTML);
                }

                $.get('/reports/data/supplier-performance-competitiveness/co-quoters?' + query)
                    .then(render, error)
                    .done(function () {
                        thisView.trigger('complete');
                    });
            }());

            // Table
            (function () {
                var tableDiv = $('#table-co-quoters'),
                    tableLoader = $(loaderHTML).appendTo(tableDiv.parent()),
                    responseData,
                    tableData = [],
                    page = 0;

                function render(response) {
                    tableLoader.hide();

                    var nextTen = response.data;

                    tableData = tableData.concat(nextTen.map(function (x) {
                        x['pot-saving'] = '$' + Highcharts.numberFormat(x['pot-saving'], 0, '.', ',');

                        return x;
                    }));

                    var tableHtml = coQuotersTemplate(tableData);

                    tableDiv.html(tableHtml);

                    $('#more-table-co-quoters').toggle(nextTen.length == 10);
                }

                function error() {
                    tableLoader.remove();
                    tableDiv.parent().append(errorHTML);
                }

                function fetchData() {
                    var queryParams = $.extend({
                        page: ++page
                    }, params);

                    var query = $.param(queryParams);

                    tableLoader.show();

                    $.get('/reports/data/supplier-performance-competitiveness/table-co-quoters?' + query)
                        .then(render, error)
                        .done(function () {
                            thisView.trigger('complete');
                        });
                }

                fetchData();

                $('#more-table-co-quoters').hide().click(fetchData);
            }());
        },

        render: function () {
            var thisView = this;
            var html = this.template();

            $('#tab-4').html(html);
            $('.tip').shTooltip();
        }
    });

    return new view();
});