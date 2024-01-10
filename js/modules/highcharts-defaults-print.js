define([], function () {
    var isPrint = false;

    function apply() {
        Highcharts.setOptions({
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
                height: 250,
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
                    lineWidth: 1,
                    radius: 1,
                    animation: {
                        duration: 0
                    },
                    events: {},
                    marker: {
                        lineWidth: 0,
                        radius: 1,
                        states: {
                            hover: {
                                radiusPlus: 2,
                                lineWidthPlus: 1
                            },
                            select: {
                                radius: 1,
                            }
                        }
                    },
                    point: {
                        events: {}
                    },
                    dataLabels: {
                        style: {
                            fontSize: "6px",
                            textOutline: "1px contrast"
                        },
                        verticalAlign: "bottom",
                        x: 0,
                        y: 0,
                        padding: 2.5
                    },
                    cropThreshold: 300,
                    pointRange: 0,
                    softThreshold: true,
                    states: {
                        hover: {
                            lineWidthPlus: 1,
                            marker: {},
                            halo: {
                                size: 2.5,
                                opacity: 0.25
                            }
                        },
                        select: {
                            marker: {}
                        }
                    },
                    turboThreshold: 500
                },
                column: {
                    radius: 1,
                    point: {
                        events: {}
                    },
                    dataLabels: {
                        align: null,
                        style: {
                            fontSize: "11px",
                            textOutline: "1px contrast"
                        },
                        verticalAlign: null,
                        x: 0,
                        y: null,
                        padding: 5
                    },
                    cropThreshold: 50,
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
                    borderWidth: 0,
                    shadow: false
                },
                bar: {
                    radius: 1,
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
                    threshold: 0
                },
                scatter: {
                    lineWidth: 0,
                    events: {},
                    marker: {
                        lineWidth: 0,
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
                                radius: 1
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
                                size: 2.5,
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
                    point: {
                        events: {}
                    },
                    dataLabels: {
                        align: "center",
                        style: {
                            fontSize: "7px",
                            textOutline: "1px contrast"
                        },
                        verticalAlign: "bottom",
                        x: 0,
                        y: 0,
                        padding: 2.5,
                        distance: 10,
                        enabled: true,
                        connectorColor: "#000",
                        softConnector: false,
                        crop: false
                    },
                    cropThreshold: 300,
                    pointRange: 0,
                    softThreshold: true,
                    states: {
                        hover: {
                            lineWidthPlus: 1,
                            marker: {},
                            halo: {
                                size: 2.5,
                                opacity: 0.25
                            },
                            brightness: 0.1,
                            shadow: false
                        },
                        select: {
                            marker: {}
                        }
                    },
                    turboThreshold: 500,
                    center: [null, null],
                    legendType: "point",
                    size: "50%",
                    slicedOffset: 2.5,
                    borderWidth: 0,
                    innerSize: "50%"
                },
                series: {
                    marker: {
                        symbol: "circle",
                        radius: 3
                    }
                }
            },

            legend: {
                enabled: true,
                align: "center",
                layout: "horizontal",
                borderRadius: 0,
                itemStyle: {
                    fontSize: "6px",
                    cursor: "pointer"
                },
                shadow: false,
                itemCheckboxStyle: {
                    width: "6px",
                    height: "6px"
                },
                squareSymbol: true,
                symbolPadding: 2.5,
                verticalAlign: "bottom",
                x: 0,
                y: 0,
                title: {
                    style: {
                        fontWeight: "bold"
                    }
                },
                symbolHeight: 6,
                symbolWidth: 6,
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
                borderRadius: 3,
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
                snap: 2.5,
                backgroundColor: "rgba(255,255,255,1)",
                borderWidth: 1,
                headerFormat: "<span style=\"font-size: 5px\">{point.key}</span><br/>",
                pointFormat: "<span style=\"color:{point.color}\">●</span> {series.name}: <b>{point.y}</b><br/>",
                shadow: true,
                style: {
                    color: "#333333",
                    cursor: "default",
                    fontSize: "6px",
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
                lineWidth: 1,
                tickPixelInterval: 60,
                tickwidth: 1,
                tickLength: 5,
                gridLineWidth: 0,
                title: {
                    style: {
                        fontSize: "8px"
                    }
                },
                labels: {
                    style: {
                        fontSize: "6px"
                    }
                }
            },
            yAxis: {
                lineWidth: 1,
                tickPixelInterval: 30,
                tickWidth: 1,
                tickLength: 5,
                gridLineWidth: 0,
                title: {
                    style: {
                        fontSize: "8px"
                    }
                },
                labels: {
                    style: {
                        fontSize: "6px"
                    }
                },
                min: 0
            },
            responsive: {
                rules: []
            }
        });

        isPrint = true;
    }

    return {
        apply: apply,
        isPrint: isPrint
    };
});