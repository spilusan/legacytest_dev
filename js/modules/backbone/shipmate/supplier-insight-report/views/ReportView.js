define([
    'jquery',
    'underscore',
    'Backbone',
    'handlebars',
    'text!templates/shipmate/supplier-insight-report/tpl/basic.html',
    'text!templates/shipmate/supplier-insight-report/tpl/premium.html',
    'text!templates/shipmate/supplier-insight-report/tpl/error.html'
], function(
    $,
    _,
    Backbone,
    Hb,
    basicReportTpl,
    premiumReportTpl,
    errorTpl
){
    var reportView = Backbone.View.extend({

        basicReportTemplate: Handlebars.compile(basicReportTpl),
        premiumReportTemplate: Handlebars.compile(premiumReportTpl),
        errorTemplate: Handlebars.compile(errorTpl),
        leadGenerationResult: null,
        brandAwarenessResult: null,
        renderedFromDate: null,
        renderedToDate: null,
        supplier: require('supplier/profile'),

        getData: function(fromDate, toDate)
        {

            var thisView = this;

            thisView.renderedFromDate = fromDate;
            thisView.renderedToDate = toDate;

            thisView.leadGenerationResult = thisView.brandAwarenessResult  = null;
            var tnid = this.supplier.tnid;

            $.get('/reports/supplier-insight-data?type=lead-generation&tnid=' + tnid + '&startDate=' + fromDate + '&endDate=' + toDate + '&skipTokenCheck=1')
                .then(
                    function(result) {
                        thisView.leadGenerationResult = result;
                        thisView.ajaxFinished(thisView);
                    },
                    function (error) {
                        var html = thisView.errorTemplate(error);
                        $('#report').html(html);
                        console.log('error', error);
                    }
                );

            $.get('/reports/supplier-insight-data?type=brand-awareness&tnid=' + tnid + '&startDate=' + fromDate + '&endDate=' + toDate + '&skipTokenCheck=1')
                .then(
                    function(result) {
                        thisView.brandAwarenessResult = result;
                        thisView.ajaxFinished(thisView);
                    },
                    function (error) {
                        var html = thisView.errorTemplate(error);
                        $('#report').html(html);
                        console.log('error', error);
                    }
                );
        },

        ajaxFinished: function(thisView) {
            // if both request finished then render report page
            if (thisView.brandAwarenessResult !== null && thisView.leadGenerationResult !== null) {


                var premiumListing = parseInt(thisView.supplier.premiumListing) === 1;
                var onboardInfluencerEx = (thisView.brandAwarenessResult['onboard-influencer-ex'] === "Y") ? "50,000" : "0";

                var data = {
                    profileView: thisView.leadGenerationResult['profile-view'],
                    bannerImpression: thisView.brandAwarenessResult['banner-impression'],
                    searchImpression:  thisView.brandAwarenessResult['search-impression'],
                    onboardInfluencerEx: onboardInfluencerEx,
                    premiumListing: premiumListing
                };

                thisView.render(data);
            }
        },

        render: function (data) {
            var html = '';
            if (data.premiumListing === true) {
                html = this.premiumReportTemplate(data);
            } else {
                html = this.basicReportTemplate(data);
            }

            $('#report').html(html);
        }
    });

    return reportView;
});
