define([
    'jquery',
    'underscore',
    'Backbone',
    'handlebars',
    'backbone/shared/hbh/general',
    '../views/FilterView',
    '../views/ReportView'
], function(
    $,
    _,
    Backbone,
    Hb,
    generalHbh,
    filterView,
    reportView,
    contentTpl
){
    var mainView = Backbone.View.extend({

        filterView: null,
        reportView: null,

        initialize: function() {
            var thisView = this;
            $(document).ajaxStart(function(){
                $('#waiting').show();
            });

            $(document).ajaxStop(function(){
                $('#waiting').hide();
            });

            $(document).ready(function(){
                thisView.reportView = new reportView();
                thisView.filterView = new filterView();
                thisView.filterView.parent = thisView;
                thisView.filterView.renderDefault();
            });
        },

        render: function(startDate, endDate) {
            this.reportView.getData(startDate, endDate);
        }

    });

    return new mainView();
});
