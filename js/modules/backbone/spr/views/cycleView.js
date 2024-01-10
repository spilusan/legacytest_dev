define([
    'jquery',
    'underscore',
    'Backbone',
    '../collections/collection',
    'text!templates/spr/tpl/cycleView.html',
    'highchart-line-tooltip'
], function (
    $,
    _,
    Backbone,
    Collection,
    cycleTpl,
    lineTooltip
) {
    var loaderHTML = '<div class="waiting-spinner">Loading...</div>';
    var errorHTML = '<div class="load-error">Error loading data</div>';
    var urlParams = {};

    var view = Backbone.View.extend({
        template: Handlebars.compile(cycleTpl),

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

        getData: function (urlParameters, reload) {
            if (!reload && this.isDataLoaded) return;

            $('#tab-6').empty();

            var params = {
                tnid: urlParameters.suppliers.join(","),
                byb: urlParameters.buyers.join(","),
                period: urlParameters.daterange
            };

            if (urlParameters.startdate) {
                params.startdate = urlParameters.startdate;
                params.enddate = urlParameters.enddate;
            }

            urlParams = params;

            this.render();
        },

        render: function () {
            var thisView = this;
            // load main chart
            (function () {

                var loader = $(loaderHTML).appendTo($('#tab-6')),
                    query = $.param(urlParams);

                function render(response) {
                    loader.remove();

                    response.showRequisionToQuote = response['req-quote'].days && response['req-quote-asu'].days;
                    response.showRequisionToOrder = response['req-ord'].days && response['req-ord-asu'].days;
                    response.showRequisionToConfirmation = response['req-poc'].days && response['req-poc-asu'].days;

                    $.each(response, function (i, x) {
                        $.each(x, function (j, y) {
                            response[i][j] = Highcharts.numberFormat(y, 0, '.', ',');
                        });
                    });

                    var html = thisView.template(response);

                    $('#tab-6').html(html);

                    // cycle time line chart
                    (function () {
                        var chartDiv = $('#chart-cycle-time'),
                            loader = $(loaderHTML).appendTo(chartDiv.parent()),
                            query = $.param(urlParams);

                        function render(response) {
                            loader.remove();

                            Highcharts.chart('chart-cycle-time', {
                                chart: {
                                    type: 'line',
                                    renderTo: 'container',
                                    marginTop: 12
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
                                    title: {
                                        text: 'Average Time (days)'
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
                                tooltip: lineTooltip(null, ' days'),
                                series: response.data
                            });

                            $('.tip').shTooltip();
                        }

                        function error() {
                            loader.remove();
                            chartDiv.append(errorHTML);
                        }

                        $.get('/reports/data/supplier-performance-cycle/cycle-time-data?' + query)
                            .then(render, error)
                            .done(function () {
                                thisView.trigger('complete');
                            });
                    }());

                }

                function error() {
                    loader.remove();
                    $('#tab-6').append(errorHTML);
                }

                $.get('/reports/data/supplier-performance-cycle/avg-cycle-data?' + query)
                    .then(render, error)
                    .done(function () {
                        thisView.trigger('complete');
                    });
            }());

        }
    });

    return new view();
});