define([], function () {
    function apply() {
        // set global default options
        $.extend(true, $.fn.dataTable.defaults, {
            language: {
                emptyTable: "No data available"
            }
        });

        Highcharts.setOptions({
            colors: ["#3494CD", "#9171B1", "#829621", "#F2AF69", "#EA8787", "#7989A0", "#4ECCCB", "#D5CD6C", "#99D3E7", "#C0A1DC"],
            symbols: ["circle", "diamond", "square", "triangle", "triangle-down"],
            lang: {
                loading: "Loading...",
                months: ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"],
                shortMonths: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"],
                weekdays: ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"],
                decimalPoint: ".",
                numericSymbols: ["k", "M", "G", "T", "P", "E"],
                resetZoom: "Reset zoom",
                resetZoomTitle: "Reset zoom level 1:1",
                thousandsSep: ","
            },
            global: {
                useUTC: true,
                VMLRadialGradientURL: "http://code.highcharts.com/5.0.7/gfx/vml-radial-gradient.png"
            },
            chart: {
                borderRadius: 0,
                defaultSeriesType: "line",
                ignoreHiddenSeries: true,
                spacing: [5, 0, 0, 0],
                resetZoomButton: {
                    theme: {
                        zIndex: 20
                    },
                    position: {
                        align: "right",
                        x: -5,
                        y: 5
                    }
                },
                width: null,
                height: 400,
                borderColor: "#335cad",
                backgroundColor: "#ffffff",
                plotBorderColor: "#cccccc",
                animation: false,
                reflow: true
            },
            title: {
                text: null,
                align: "center",
                margin: 15,
                widthAdjust: -44
            },
            subtitle: {
                text: "",
                align: "center",
                widthAdjust: -44
            },
            plotOptions: {
                line: {
                    lineWidth: 2,
                    radius: 1,
                    allowPointSelect: false,
                    showCheckbox: false,
                    animation: {
                        duration: 0
                    },
                    events: {},
                    marker: {
                        lineWidth: 0,
                        lineColor: "#ffffff",
                        radius: 2,
                        states: {
                            hover: {
                                animation: {
                                    duration: 50
                                },
                                enabled: true,
                                radiusPlus: 2,
                                lineWidthPlus: 1
                            },
                            select: {
                                fillColor: "#cccccc",
                                lineColor: "#000000",
                                radius: 1,
                            }
                        },
                        symbol: "square"
                    },
                    point: {
                        events: {}
                    },
                    dataLabels: {
                        align: "center",
                        style: {
                            fontSize: "12px",
                            fontWeight: "bold",
                            color: "contrast",
                            textOutline: "1px contrast"
                        },
                        verticalAlign: "bottom",
                        x: 0,
                        y: 0,
                        padding: 5
                    },
                    cropThreshold: 300,
                    pointRange: 0,
                    softThreshold: true,
                    states: {
                        hover: {
                            lineWidthPlus: 1,
                            marker: {},
                            halo: {
                                size: 5,
                                opacity: 0.25
                            }
                        },
                        select: {
                            marker: {}
                        }
                    },
                    stickyTracking: true,
                    turboThreshold: 500
                },
                area: {
                    radius: 1,
                    allowPointSelect: false,
                    showCheckbox: false,
                    events: {},
                    marker: {
                        lineWidth: 0,
                        lineColor: "#ffffff",
                        radius: 2,
                        states: {
                            hover: {
                                animation: {
                                    duration: 50
                                },
                                enabled: true,
                                radiusPlus: 2,
                                lineWidthPlus: 1
                            },
                            select: {
                                fillColor: "#cccccc",
                                lineColor: "#000000",
                                radius: 1,
                            }
                        }
                    },
                    point: {
                        events: {}
                    },
                    dataLabels: {
                        align: "center",
                        style: {
                            fontSize: "12px",
                            fontWeight: "bold",
                            color: "contrast",
                            textOutline: "1px contrast"
                        },
                        verticalAlign: "bottom",
                        x: 0,
                        y: 0,
                        padding: 5
                    },
                    cropThreshold: 300,
                    pointRange: 0,
                    softThreshold: false,
                    states: {
                        hover: {
                            lineWidthPlus: 1,
                            marker: {},
                            halo: {
                                size: 5,
                                opacity: 0.25
                            }
                        },
                        select: {
                            marker: {}
                        }
                    },
                    stickyTracking: true,
                    turboThreshold: 500,
                    threshold: 0
                },
                spline: {
                    radius: 1,
                    allowPointSelect: false,
                    showCheckbox: false,
                    events: {},
                    marker: {
                        lineWidth: 0,
                        lineColor: "#ffffff",
                        radius: 2,
                        states: {
                            hover: {
                                animation: {
                                    duration: 50
                                },
                                enabled: true,
                                radiusPlus: 2,
                                lineWidthPlus: 1
                            },
                            select: {
                                fillColor: "#cccccc",
                                lineColor: "#000000",
                                radius: 1,
                            }
                        }
                    },
                    point: {
                        events: {}
                    },
                    dataLabels: {
                        align: "center",
                        style: {
                            fontSize: "11px",
                            fontWeight: "bold",
                            color: "contrast",
                            textOutline: "1px contrast"
                        },
                        verticalAlign: "bottom",
                        x: 0,
                        y: 0,
                        padding: 5
                    },
                    cropThreshold: 300,
                    pointRange: 0,
                    softThreshold: true,
                    states: {
                        hover: {
                            lineWidthPlus: 1,
                            marker: {},
                            halo: {
                                size: 5,
                                opacity: 0.25
                            }
                        },
                        select: {
                            marker: {}
                        }
                    },
                    stickyTracking: true,
                    turboThreshold: 500
                },
                areaspline: {
                    radius: 1,
                    allowPointSelect: false,
                    showCheckbox: false,
                    events: {},
                    marker: {
                        lineWidth: 0,
                        lineColor: "#ffffff",
                        radius: 2,
                        states: {
                            hover: {
                                animation: {
                                    duration: 50
                                },
                                enabled: true,
                                radiusPlus: 2,
                                lineWidthPlus: 1
                            },
                            select: {
                                fillColor: "#cccccc",
                                lineColor: "#000000",
                                radius: 1,
                            }
                        }
                    },
                    point: {
                        events: {}
                    },
                    dataLabels: {
                        align: "center",
                        style: {
                            fontSize: "11px",
                            fontWeight: "bold",
                            color: "contrast",
                            textOutline: "1px contrast"
                        },
                        verticalAlign: "bottom",
                        x: 0,
                        y: 0,
                        padding: 5
                    },
                    cropThreshold: 300,
                    pointRange: 0,
                    softThreshold: false,
                    states: {
                        hover: {
                            lineWidthPlus: 1,
                            marker: {},
                            halo: {
                                size: 5,
                                opacity: 0.25
                            }
                        },
                        select: {
                            marker: {}
                        }
                    },
                    stickyTracking: true,
                    turboThreshold: 500,
                    threshold: 0
                },
                column: {
                    radius: 1,
                    allowPointSelect: false,
                    showCheckbox: false,
                    events: {},
                    marker: null,
                    point: {
                        events: {}
                    },
                    dataLabels: {
                        align: null,
                        style: {
                            fontSize: "11px",
                            fontWeight: "bold",
                            color: "contrast",
                            textOutline: "1px contrast"
                        },
                        verticalAlign: null,
                        x: 0,
                        y: null,
                        padding: 5
                    },
                    cropThreshold: 50,
                    pointRange: null,
                    softThreshold: false,
                    states: {
                        hover: {
                            lineWidthPlus: 1,
                            marker: {},
                            halo: false,
                            brightness: 0.1,
                            shadow: false
                        },
                        select: {
                            marker: {},
                            color: "#cccccc",
                            borderColor: "#000000",
                            shadow: false
                        }
                    },
                    stickyTracking: false,
                    turboThreshold: 500,
                    borderRadius: 0,
                    groupPadding: 0,
                    pointPadding: 0.1,
                    minPointLength: 0,
                    startFromThreshold: true,
                    tooltip: {
                        distance: 6
                    },
                    threshold: 0,
                    borderColor: "#ffffff",
                    borderWidth: 0,
                    shadow: false
                },
                bar: {
                    radius: 1,
                    allowPointSelect: false,
                    showCheckbox: false,
                    events: {},
                    marker: null,
                    point: {
                        events: {}
                    },
                    dataLabels: {
                        align: null,
                        style: {
                            fontSize: "11px",
                            fontWeight: "bold",
                            color: "contrast",
                            textOutline: "1px contrast"
                        },
                        verticalAlign: null,
                        x: 0,
                        y: null,
                        padding: 5
                    },
                    cropThreshold: 50,
                    pointRange: null,
                    softThreshold: false,
                    states: {
                        hover: {
                            lineWidthPlus: 1,
                            marker: {},
                            halo: false,
                            brightness: 0.1,
                            shadow: false
                        },
                        select: {
                            marker: {},
                            color: "#cccccc",
                            borderColor: "#000000",
                            shadow: false
                        }
                    },
                    stickyTracking: false,
                    turboThreshold: 500,
                    borderRadius: 0,
                    groupPadding: 0.2,
                    pointPadding: 0.1,
                    minPointLength: 0,
                    startFromThreshold: true,
                    tooltip: {
                        distance: 6
                    },
                    threshold: 0,
                    borderColor: "#ffffff"
                },
                scatter: {
                    lineWidth: 0,
                    allowPointSelect: false,
                    showCheckbox: false,
                    events: {},
                    marker: {
                        lineWidth: 0,
                        lineColor: "#ffffff",
                        radius: 2,
                        states: {
                            hover: {
                                animation: {
                                    duration: 50
                                },
                                enabled: true,
                                radiusPlus: 2,
                                lineWidthPlus: 1
                            },
                            select: {
                                fillColor: "#cccccc",
                                lineColor: "#000000",
                                radius: 1,
                            }
                        },
                        enabled: true
                    },
                    point: {
                        events: {}
                    },
                    dataLabels: {
                        align: "center",
                        style: {
                            fontSize: "11px",
                            fontWeight: "bold",
                            color: "contrast",
                            textOutline: "1px contrast"
                        },
                        verticalAlign: "bottom",
                        x: 0,
                        y: 0,
                        padding: 5
                    },
                    cropThreshold: 300,
                    pointRange: 0,
                    softThreshold: true,
                    states: {
                        hover: {
                            lineWidthPlus: 1,
                            marker: {},
                            halo: {
                                size: 5,
                                opacity: 0.25
                            }
                        },
                        select: {
                            marker: {}
                        }
                    },
                    stickyTracking: true,
                    turboThreshold: 500,
                    tooltip: {
                        headerFormat: "<span style=\"color:{point.color}\">●</span> <span style=\"font-size: 0.85em\"> {series.name}</span><br/>",
                        pointFormat: "x: <b>{point.x}</b><br/>y: <b>{point.y}</b><br/>"
                    }
                },
                pie: {
                    radius: 1,
                    allowPointSelect: true,
                    showCheckbox: false,
                    events: {},
                    marker: null,
                    point: {
                        events: {}
                    },
                    dataLabels: {
                        align: "center",
                        style: {
                            fontSize: "12px",
                            fontWeight: "normal",
                            color: "contrast",
                            textOutline: "1px contrast"
                        },
                        verticalAlign: "bottom",
                        x: 0,
                        y: 0,
                        padding: 5,
                        distance: 20,
                        enabled: true,
                        connectorColor: "#000",
                        softConnector: false,
                        crop: false,
                        // formatter: function () {
                        //     var wordsA = this.point.name.replace('<br>', ' ').split(' '),
                        //         wordsB = wordsA.splice(Math.ceil(wordsA.length / 2));

                        //     return wordsA.join(' ') + '<br />' + wordsB.join(' ');
                        // },
                        format: '{point.percentage:.1f}%'
                        // useHTML: true
                    },
                    cropThreshold: 300,
                    pointRange: 0,
                    softThreshold: true,
                    states: {
                        hover: {
                            lineWidthPlus: 1,
                            marker: {},
                            halo: {
                                size: 5,
                                opacity: 0.25
                            },
                            brightness: 0.1,
                            shadow: false
                        },
                        select: {
                            marker: {}
                        }
                    },
                    stickyTracking: false,
                    turboThreshold: 500,
                    center: [null, null],
                    clip: false,
                    colorByPoint: true,
                    ignoreHiddenPoint: true,
                    legendType: "point",
                    size: "50%",
                    showInLegend: true,
                    slicedOffset: 5,
                    tooltip: {
                        followPointer: true,
                        headerFormat: "<span style=\"font-size:11px\">{series.name}</span><br>",
                        pointFormat: "<span>{point.name}</span>: <br> <b>{point.y:.0f}%</b> of total<br/>",
                        shared: false
                    },
                    borderColor: "#ffffff",
                    borderWidth: 0,
                    innerSize: "50%",
                    cursor: "pointer",
                    series: {
                        dataLabels: {
                            enabled: true,
                            connectorWidth: 1,
                            connectorColor: '#000',
                            format: '{point.percentage:.1f}%'
                        },
                        showInLegend: true
                    }
                },
                series: {
                    events: {},
                    point: {
                        events: {}
                    },
                    marker: {
                        symbol: "circle",
                        radius: 6
                    },
                    connectNulls: true,
                    clip: false
                }
            },
            labels: {
                style: {
                    position: "absolute",
                    color: "#333333"
                }
            },
            legend: {
                enabled: true,
                align: "center",
                layout: "horizontal",
                borderColor: "#999999",
                borderRadius: 0,
                navigation: {
                    activeColor: "#003399",
                    inactiveColor: "#cccccc"
                },
                itemStyle: {
                    color: "#333333",
                    fontSize: "12px",
                    fontWeight: "normal",
                    cursor: "pointer"
                },
                itemHoverStyle: {
                    color: "#000000"
                },
                itemHiddenStyle: {
                    color: "#cccccc"
                },
                shadow: false,
                itemCheckboxStyle: {
                    position: "absolute",
                    width: "12px",
                    height: "12px"
                },
                squareSymbol: true,
                symbolPadding: 5,
                verticalAlign: "bottom",
                x: 0,
                y: 0,
                title: {
                    style: {
                        fontWeight: "bold"
                    }
                },
                symbolHeight: 12,
                symbolWidth: 12,
                symbolRadius: 0,
                itemMarginTop: 2,
                itemMarginBottom: 2
            },
            loading: {
                labelStyle: {
                    fontWeight: "bold",
                    position: "relative",
                    top: "45%"
                },
                style: {
                    position: "absolute",
                    backgroundColor: "#ffffff",
                    opacity: 0.5,
                    textAlign: "center"
                }
            },
            tooltip: {
                enabled: true,
                animation: true,
                borderRadius: 6,
                dateTimeLabelFormats: {
                    millisecond: "%A, %b %e, %H:%M:%S.%L",
                    second: "%A, %b %e, %H:%M:%S",
                    minute: "%A, %b %e, %H:%M",
                    hour: "%A, %b %e, %H:%M",
                    day: "%A, %b %e, %Y",
                    week: "Week from %A, %b %e, %Y",
                    month: "%B %Y",
                    year: "%Y"
                },
                footerFormat: "",
                padding: 8,
                snap: 5,
                backgroundColor: "rgba(255,255,255,1)",
                borderWidth: 1,
                headerFormat: "<span style=\"font-size: 5px\">{point.key}</span><br/>",
                pointFormat: "<span style=\"color:{point.color}\">●</span> {series.name}: <b>{point.y}</b><br/>",
                shadow: true,
                style: {
                    color: "#333333",
                    cursor: "default",
                    fontSize: "12px",
                    pointerEvents: "none",
                    whiteSpace: "nowrap"
                }
            },
            credits: {
                enabled: false
            },
            url: null,
            subTitle: {
                text: null
            },
            xAxis: {
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
                lineWidth: 1,
                lineColor: "#9BA7BA",
                tickColor: "#9BA7BA",
                tickPixelInterval: 100,
                tickwidth: 1,
                tickLength: 10,
                gridLineWidth: 0,
                title: {
                    style: {
                        fontSize: "16px"
                    }
                },
                labels: {
                    style: {
                        fontSize: "12px"
                    },
                    formatter: function () {
                        var wordsA = this.value.replace('<br>', ' ').split(' '),
                            wordsB = wordsA.splice(Math.ceil(wordsA.length / 2));

                        return wordsA.join(' ') + '<br />' + wordsB.join(' ');
                    }
                }
            },
            yAxis: {
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
                lineWidth: 1,
                lineColor: "#9BA7BA",
                tickColor: "#9BA7BA",
                tickPixelInterval: 50,
                tickWidth: 1,
                tickLength: 10,
                gridLineWidth: 0,
                title: {
                    style: {
                        fontSize: "16px"
                    }
                },
                labels: {
                    style: {
                        fontSize: "12px"
                    }
                },
                min: 0
            },
            responsive: {
                rules: [{
                    condition: {
                        minWidth: 350
                    },
                    chartOptions: {
                        plotOptions: {
                            pie: {
                                size: 250
                            }
                        }
                    }
                }, {
                    condition: {
                        minWidth: 600
                    },
                    chartOptions: {
                        plotOptions: {
                            pie: {
                                size: 250
                            }
                        }
                    }
                }]
            }
        });

    }

    return {
        apply: apply
    };
});