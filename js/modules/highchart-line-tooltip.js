define([], function () {
    return function (valuePrefix, valueSuffix, valueSuffixPlural) {
        valuePrefix = valuePrefix || '';
        valueSuffix = valueSuffix || '';

        return {
            shared: false,
            useHTML: true,
            formatter: function () {
                var _this = this,
                    extremes = _this.series.chart.yAxis[0].getExtremes(),
                    tolerance = (extremes.max - extremes.min) / 100 * 5,
                    tooltipPoints = [],
                    tooltipHtml = '';

                for (var seriesIndex = 0; seriesIndex < _this.series.chart.series.length; seriesIndex++) {
                    var currentPoint = _this.series.chart.series[seriesIndex],
                        currentY = currentPoint.processedYData[_this.point.index];

                    if (currentPoint && currentY !== null && currentY > _this.y - tolerance && currentY < _this.y + tolerance) {
                        tooltipPoints.push(currentPoint);
                    }
                }

                tooltipHtml += '<div class="date">' + _this.key + '</div><table>';

                tooltipHtml += '<tr>' + tooltipPoints.map(function (p) {
                    var value = p.processedYData[_this.point.index],
                        suffix = valueSuffixPlural ? (Math.round(value) === 1 ? valueSuffix : valueSuffixPlural) : valueSuffix,
                        valueText = valuePrefix + Highcharts.numberFormat(value, 0, '.', ',') + suffix;

                    return '<td class="name">' + p.name + ':</td><td class="value">' + valueText + '</td>';
                }).join('</tr><tr>') + '</tr>';

                tooltipHtml += '</table>';

                return tooltipHtml;
            }
        };
    };
});