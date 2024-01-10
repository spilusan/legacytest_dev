define([
    'jquery',
    'underscore',
    'Backbone',
    'handlebars',
    'libs/jquery.shipserv-tooltip',
    'text!templates/shipmate/supplier-insight-report-pct/tpl/report.html',
    'text!templates/shipmate/supplier-insight-report-pct/tpl/error.html'
], function(
    $,
    _,
    Backbone,
    Hb,
    shTooltip,
    reportTpl,
    errorTpl
){
    var reportView = Backbone.View.extend({
        reportTemplate: Handlebars.compile(reportTpl),
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
                var onboardInfluencerEx = (thisView.brandAwarenessResult['onboard-influencer-ex'] === "Y") ? "50,000" : "0";
                var profileView = parseInt(thisView.leadGenerationResult['profile-view']);
                var contactView = parseInt(thisView.leadGenerationResult['contact-view']);
                var tnidView = parseInt(thisView.leadGenerationResult['tnid-view']);
                var emailView = parseInt(thisView.leadGenerationResult['contact-email-view']);
                var websiteClick = parseInt(thisView.leadGenerationResult['website-view']);
                var totalActions = contactView + emailView + tnidView + websiteClick;

                var data = {
                    profileView: profileView,
                    contactView: contactView,
                    emailView: emailView,
                    tnidView: tnidView,
                    websiteClick: websiteClick,
                    totalActions: totalActions,
                    bannerImpression: thisView.brandAwarenessResult['banner-impression'],
                    searchImpression: thisView.brandAwarenessResult['search-impression'],
                    onboardInfluencerEx: onboardInfluencerEx
                };

                thisView.render(data);
            }
        },

        render: function (data) {
            var html = '';
            html = this.reportTemplate(data);
            $('#report').html(html);
            $('.tooltip').shTooltip({
                displayType: 'top'
            });
        }
    });

    return reportView;
});
