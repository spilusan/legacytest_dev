define([
    'jquery',
    'underscore',
    'Backbone',
    'handlebars',
    'components/dateRangePicker/views/mainView',
    'text!templates/shipmate/supplier-insight-report/tpl/filter.html'
], function(
    $,
    _,
    Backbone,
    Hb,
    dateRangePicker,
    filterTpl
){
    var filterView = Backbone.View.extend({
        filterTemplate: Handlebars.compile(filterTpl),
        dateFrom: null,
        dateTo: null,
        formattedDateFrom: null,
        formattedDateTo: null,
        defaultStartDate: null,
        defaultEndDate: null,
        defaultAlreadyRendered: false,
        initialize: function() {
            var thisView = this;
            this.renderDefault = this.renderDefault.bind(this);
            $(document).ready(function(){
                thisView.dateTo = new Date();
				thisView.dateFrom = new Date(new Date().setFullYear(thisView.dateTo.getFullYear() - 1));
                thisView.defaultStartDate = dateRangePicker.formattedDate(thisView.dateFrom);
                thisView.defaultEndDate = dateRangePicker.formattedDate(thisView.dateTo);
                thisView.render();
                thisView.renderDefault();
                $('#date-picker').click(function(e) {
                    e.stopPropagation();
                    dateRangePicker.show($(this), 'left', function(a, b, fa, fb){
                        thisView.dateFrom = a;
                        thisView.dateTo = b;
                        thisView.formattedDateFrom = fa;
                        thisView.formattedDateTo = fb;
                        $('#date-picker').val(fa + ' -> ' + fb);
                        $('#report').empty();
                        thisView.parent.render(fa, fb);
                    }, thisView.dateFrom, thisView.dateTo);
                });
            });
        },

        render: function () {
            var thisView = this;
            var html = this.filterTemplate();
            $('#filter').html(html);
            $('#jpegdownload').click(
                function (e) {
                    e.preventDefault();
                    var startDate = thisView.parent.reportView.renderedFromDate;
                    var endDate = thisView.parent.reportView.renderedToDate;
                    var href = '/shipmate/supplier-insight-report-img-download?startDate=' + startDate + '&endDate=' + endDate;
                    window.location.href = href;
                }
            );
        },

        renderDefault: function() {

            var startDate = this.defaultStartDate;
            var endDate = this.defaultEndDate;
            this.formattedDateFrom = startDate;
            this.formattedDateTo = endDate;
            $('#date-picker').val(startDate+ ' -> ' + endDate);

            if (this.defaultAlreadyRendered === false && startDate && this.parent && this.parent.render) {
                //need the above conditioin as require JS renders different order in each browser and we should avoind duplicate rendering of the page
                this.defaultAlreadyRendered  = true;
                this.parent.render(startDate, endDate);
            }
        }

    });

    return filterView;
});
