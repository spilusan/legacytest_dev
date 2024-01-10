define([
    'jquery',
    'underscore',
    'Backbone',
    '../collections/collection',
    'text!templates/spr/tpl/qualityPaymentDeliveryView.html',
    'text!templates/spr/tpl/legendTable.html',
    'text!templates/spr/tpl/ptqLegendTable.html',
    'highchart-line-tooltip'
], function (
    $,
    _,
    Backbone,
    Collection,
    qualityPaymentDeliveryTpl,
    legendTableTpl,
    ptqLegendTableTpl,
    lineTooltip
) {
    var legendTableTemplate = Handlebars.compile(legendTableTpl);
    var ptqLegendTableTemplate = Handlebars.compile(ptqLegendTableTpl);
    var loaderHTML = '<div class="waiting-spinner">Loading...</div>';
    var errorHTML = '<div class="load-error">Error loading data</div>';

    var view = Backbone.View.extend({
        template: Handlebars.compile(qualityPaymentDeliveryTpl),

        initialize: function () {
            this.isDataLoaded = false;
            this.completedRequests = 0;

            this.on('complete', function (event, request, settings) {
                ++this.completedRequests;

                if (this.completedRequests === 2) {
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

            // Average quoted lead time
            //=========================

            // Column chart
            (function () {
                var chartDiv = $('#column-average-lead-time'),
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

                    Highcharts.chart('column-average-lead-time', {
                        chart: {
                            type: 'column',
                            renderTo: 'container',
                            marginTop: 12
                        },
                        tooltip: {
                            headerFormat: '',
                            pointFormat: '{point.y:,.1f} days',
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
                            title: {
                                text: 'Average quoted lead time (days)'
                            }
                        },
                        colors: ['#3494CD'],
                        series: response.data
                    });
                }

                function error() {
                    loader.remove();
                    chartDiv.append(errorHTML);
                }

                $.get('/reports/data/supplier-performance-quality/get-avg-quote-lead-time-summary?' + query)
                    .then(render, error)
                    .done(function () {
                        thisView.trigger('complete');
                    });
            }());


            // Line chart
            (function () {
                var chartDiv = $('#line-average-lead-time'),
                    loader = $(loaderHTML).appendTo(chartDiv.parent()),
                    query = $.param(params);

                function render(response) {
                    loader.remove();

                    Highcharts.chart('line-average-lead-time', {
                        chart: {
                            type: 'line',
                            renderTo: 'container',
                            marginTop: 12
                        },
                       tooltip: lineTooltip(null, ' day', ' days'),
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
                            title: {
                                text: 'Average quoted lead time (days)'
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
                        plotOptions: {
                            series: {
                                connectNulls: true
                            }
                        },
                        series: response.data
                    });

                }

                function error() {
                    loader.remove();
                    chartDiv.append(errorHTML);
                }

                $.get('/reports/data/supplier-performance-quality/get-avg-quote-lead-time?' + query)
                    .then(render, error)
                    .done(function () {
                        thisView.trigger('complete');
                    });
            }());

            // Number of orders
            //=================

            // Pie chart
            (function () {
                var chartDiv = $('#pie-quality-level'),
                    chartLoader = $(loaderHTML).appendTo(chartDiv.parent()),
                    tableDiv = $('#table-quality-level'),
                    tableLoader = $(loaderHTML).appendTo(tableDiv.parent());
                query = $.param(params);

                chartDiv.parent().parent().show();

                function render(response) {
                    chartLoader.remove();

                    if (response.data.length === 0) {
                        chartDiv.parent().parent().hide();
                    }

                    $.each(response.data, function (i, x) {
                        x['percent-of-total-qot'] = Math.round(x['percent-of-total-qot']);
                        x['percent-of-conv-order'] = Math.round(x['percent-of-conv-order']);
                    });

                    var tableData = response.data.map(function (x, i) {
                            x.color = Highcharts.getOptions().colors[i];

                            return x;
                        }),
                        seriesData = tableData.map(function (x) {
                            return {
                                name: x['quality-level'],
                                y: x['qot-count']
                            };
                        });

                    //chartDiv.css('maxWidth', 500);

                    Highcharts.chart('pie-quality-level', {
                        chart: {
                            type: 'pie'
                        },
                        plotOptions: {
                            pie: {
                                dataLabels: {
                                    format: '{point.percentage:,.0f}%'
                                }
                            }
                        },
                        legend: false,
                        tooltip: {
                            pointFormat: '{point.name}: {point.percentage:,.0f}%'
                        },
                        series: [{
                            "name": "Quality level quoted",
                            "data": seriesData
                        }]
                    });


                    var tableHtml = legendTableTemplate(tableData);

                    tableLoader.remove();
                    tableDiv.append(tableHtml);
                }

                function error() {
                    chartLoader.remove();
                    chartDiv.append(errorHTML);
                }

                $.get('/reports/data/supplier-performance-quality/get-quality-level-quoted?' + query)
                    .then(render, error)
                    .done(function () {
                        thisView.trigger('complete');
                    });
            }());

            // Payment terms quoted
            //=====================

            // Pie chart
            (function () {
                var chartDiv = $('#pie-terms-quoted'),
                    chartLoader = $(loaderHTML).appendTo(chartDiv.parent()),
                    tableDiv = $('#table-terms-quoted'),
                    tableLoader = $(loaderHTML).appendTo(tableDiv.parent());
                query = $.param(params);

                chartDiv.parent().parent().show();

                function render(response) {
                    chartLoader.remove();

                    if (response.data.length === 0) {
                        chartDiv.parent().parent().hide();
                    }

                    $.each(response.data, function (i, x) {
                        x['percent-of-total-qot'] = Math.round(x['percent-of-total-qot']);
                        x['percent-of-conv-order'] = Math.round(x['percent-of-conv-order']);
                    });

                    var tableData = response.data.map(function (x, i) {
                            x.color = Highcharts.getOptions().colors[i];

                            return x;
                        }),
                        seriesData = tableData.map(function (x) {
                            return {
                                name: x['payment-terms'],
                                y: x['qot-count']
                            };
                        });

                    chartDiv.css('maxWidth', 500);

                    Highcharts.chart('pie-terms-quoted', {
                        chart: {
                            type: 'pie'
                        },
                        plotOptions: {
                            pie: {
                                dataLabels: {
                                    format: '{point.percentage:,.0f}%'
                                }
                            }
                        },
                        legend: false,
                        tooltip: {
                            pointFormat: '{point.name}: {point.percentage:,.0f}%',
                            useHTML: true,
                            shared: false
                        },
                        series: [{
                            "name": "Payment terms quoted",
                            "data": seriesData
                        }]
                    });


                    var tableHtml = ptqLegendTableTemplate(tableData);

                    tableLoader.remove();
                    tableDiv.append(tableHtml);
                }

                function error() {
                    chartLoader.remove();
                    chartDiv.append(errorHTML);
                }

                $.get('/reports/data/supplier-performance-quality/get-payment-terms-quoted?' + query)
                    .then(render, error)
                    .done(function () {
                        thisView.trigger('complete');
                    });
            }());
        },

        render: function () {
            var thisView = this;
            var html = this.template();

            $('#tab-7').html(html);

            $('.tip').shTooltip();
        }
    });

    return new view();
});