'use strict';

define([], function () {
    return function (startDate, endDate) {
        var date = moment().startOf('day');

        return {
            ranges: {
                "Last Month": [
                    date.clone().startOf('month').subtract(1, 'months'),
                    date.clone().startOf('months')
                ],
                "Last Quarter": [
                    date.clone().startOf('quarter').subtract(1, 'quarters'),
                    date.clone().startOf('months')
                ],
                "Last 12 Months": [
                    date.clone().startOf('month').subtract(12, 'months'),
                    date.clone().startOf('months')
                ]
            },
            showCustomRangeLabel: false,
            linkedCalendars: true,
            //showDropdowns: true,
            alwaysShowCalendars: true,
            startDate: startDate,
            endDate: endDate,
            opens: "left",
            buttonClasses: "btn",
            applyClass: "green",
            cancelClass: "transparent transparent-blue",
            locale: {
                format: 'DD/MM/YYYY'
            }
        };
    }

});
