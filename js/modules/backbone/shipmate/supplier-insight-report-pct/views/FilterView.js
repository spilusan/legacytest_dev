define([
    'jquery',
    'underscore',
    'Backbone',
    'handlebars',
    'components/dateRangePicker/views/mainView',
    'libs/jquery.shipserv-tooltip',
    'text!templates/shipmate/supplier-insight-report-pct/tpl/filter.html'
], function(
    $,
    _,
    Backbone,
    Hb,
    dateRangePicker,
    shTooltip,
    filterTpl
){
    var filterView = Backbone.View.extend({
        supplier: require('supplier/profile'),
        filterTemplate: Handlebars.compile(filterTpl),
        dateFrom: null,
        dateTo: null,
        formattedDateFrom: null,
        formattedDateTo: null,
        defaultStartDate: null,
        defaultEndDate: null,
        initialize: function() {
            var thisView = this;
            this.renderDefault = this.renderDefault.bind(this);
            $(document).ready(function(){
                thisView.dateTo = new Date();
				thisView.dateFrom = new Date(new Date().setFullYear(thisView.dateTo.getFullYear() - 1));
                thisView.defaultStartDate = dateRangePicker.formattedDate(thisView.dateFrom);
                thisView.defaultEndDate = dateRangePicker.formattedDate(thisView.dateTo);
                thisView.formattedDateFrom = thisView.defaultStartDate;
                thisView.formattedDateTo = thisView.defaultEndDate;
                thisView.render();
                thisView.renderDefault();
                    $('#date-picker').click(function(e) {
                    e.stopPropagation();
                    dateRangePicker.show($(this), 'right', function(a, b, fa, fb){
                        thisView.dateFrom = a;
                        thisView.dateTo = b;
                        thisView.formattedDateFrom = fa;
                        thisView.formattedDateTo = fb;
                        $('#date-picker').val(fa + ' -> ' + fb);
                        $('#report').empty();
                    }, thisView.dateFrom, thisView.dateTo);
                });
            });
        },

        render: function () {
            var thisView = this;
            var premiumListing = parseInt(thisView.supplier.premiumListing) === 1;

            var html = this.filterTemplate({ 
                premium: premiumListing,
                name: this.supplier.name,
                tnid: this.supplier.tnid
             });

            $('#filter').html(html);
            $('.filterTooltip').shTooltip({
                displayType: 'top'
            });

            $('#exelexport').click(
                function (e) {
                    e.preventDefault();
                    startDate = thisView.dateFrom.toISOString().replace(/-/g, '').split('T')[0];
                    toDate = thisView.dateTo.toISOString().replace(/-/g, '').split('T')[0];
                    var href = '/reports/data/sir-pct/export-csv?lowerdate=' + startDate + '&upperdate=' + toDate;
                    thisView.setCookie('showSpinner', 'true', 1);
                    $('#waiting').show();
                    var SpnnerTimer = setInterval(function(){
                        if (thisView.getCookie('showSpinner') === '') {
                            clearInterval(SpnnerTimer);
                            $('#waiting').hide();
                        }
                    }, 1000);
                    window.location.href = href;
                }
            );

            // Apply button
            $('input[name="run"]').click(function(e) {
                e.stopPropagation();
                thisView.parent.render(thisView.formattedDateFrom, thisView.formattedDateTo);
            });

        },

        renderDefault: function() {
            var startDate = this.defaultStartDate;
            var endDate = this.defaultEndDate;
            this.formattedDateFrom = startDate;
            this.formattedDateTo = endDate;
            $('#date-picker').val(startDate+ ' -> ' + endDate);
        },

        setCookie: function(cname, cvalue, exdays){
            var d = new Date();
            d.setTime(d.getTime() + (exdays*24*60*60*1000));
            var expires = "expires="+ d.toUTCString();
            document.cookie = cname + "=" + cvalue + "; path=/; " + expires;
        },

		getCookie: function(cname){
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

    return filterView;
});
