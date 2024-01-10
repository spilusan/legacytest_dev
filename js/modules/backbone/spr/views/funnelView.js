/*
 * Funnel View
 */

define([
    'jquery',
    'underscore',
    'Backbone',
    'handlebars',
    'text!templates/spr/tpl/funnelView.html',
    'text!/images/spr/funnel.svg',
    'backbone/spr/models/funnelRfqModel',
    'backbone/spr/models/funnelQotModel',
    'backbone/spr/models/funnelOrdModel',
    'backbone/spr/models/funnelSummaryModel',
    'libs/jquery.shipserv-tooltip'
], function (
    $,
    _,
    Backbone,
    Hb,
    funnelTpl,
    funnelSVG,
    funnelRfqModel,
    funnelQotModel,
    funnelOrdModel,
    funnelSummaryModel,
    shTooltip
) {
    var view = Backbone.View.extend({
        funnelRfqModel: new funnelRfqModel(),
        funnelQotModel: new funnelQotModel(),
        funnelOrdModel: new funnelOrdModel(),
        funnelSummaryModel: new funnelSummaryModel(),
        params: [],
        initialize: function () {
            this.suppliersCount = 0;
            this.buyersCount = 0;
            this.selectedSupplier = 0;
            this.funnelTemplate = Handlebars.compile(funnelTpl);

            this.isDataLoaded = false;
            this.completedRequests = 0;

            this.on('complete', function (event, request, settings) {
                ++this.completedRequests;

                if (this.completedRequests === 3) {
                    this.isDataLoaded = true;
                }
            });
        },

        close: function () {},

        getData: function (params, reload) {
            if (!reload && this.isDataLoaded) return;

            $('#tab-2').empty();

            var thisView = this;

            this.suppliersCount = params.suppliers.length;
            this.buyersCount = params.buyers.length;

            thisView.render();

            this.params = {
                tnid: params.suppliers.join(","),
                byb: params.buyers.join(","),
                period: params.daterange
            };

            if (params.startdate) {
                this.params.startdate = params.startdate;
                this.params.enddate = params.enddate;
            }

            this.funnelRfqModel.fetched = false;
            this.funnelQotModel.fetched = false;
            this.funnelOrdModel.fetched = false;

            var a, b, c;

            $('#sprFunnel .loader').fadeIn(function () {
                a = thisView.funnelRfqModel.fetch({
                    data: $.param(thisView.params),
                    type: 'GET',
                    complete: function (result) {
                        thisView.funnelRfqModel.fetched = true;
                        thisView.populateData(thisView, thisView.funnelRfqModel);
                    }
                }).done(function () {
                    thisView.trigger('complete');
                });

                c = thisView.funnelQotModel.fetch({
                    data: $.param(thisView.params),
                    type: 'GET',
                    complete: function (result) {
                        thisView.funnelQotModel.fetched = true;
                        thisView.populateData(thisView, thisView.funnelQotModel);
                    }
                }).done(function () {
                    thisView.trigger('complete');
                });

                b = thisView.funnelOrdModel.fetch({
                    data: $.param(thisView.params),
                    type: 'GET',
                    complete: function (result) {
                        thisView.funnelOrdModel.fetched = true;
                        thisView.populateData(thisView, thisView.funnelOrdModel);
                    }
                }).done(function () {
                    thisView.trigger('complete');
                });

                $.when(a, b, c).then(function () {
                    //Calculate summary and percentages
                    thisView.funnelSummaryModel.set('selectedBuyersCount', thisView.buyersCount);
                    thisView.funnelSummaryModel.set('selectedSuppliersCount', thisView.suppliersCount);

                    thisView.funnelSummaryModel.calculateSummary(
                        thisView.funnelRfqModel,
                        thisView.funnelQotModel,
                        thisView.funnelOrdModel
                    );

                    thisView.populateData(thisView, thisView.funnelSummaryModel);

                    $('#sprFunnel .loader').fadeOut();
                });
            });
        },

        render: function () {
            var view = this,
                $el = $('#tab-2'),
                innerHtml;

            innerHtml = this.funnelTemplate({
                funnelSVG: funnelSVG
            });

            $el.html(innerHtml);

            this.sprFunnelEl = $('#sprFunnel', $el);

            $('#text311').shTooltip({
                displayType: 'top',
                tooltipelement: 'direct-orders-tip'
            });

            $('#text315').shTooltip({
                displayType: 'top',
                tooltipelement: 'competative-orders-tip'
            });

            $('#sprFunnel svg').attr({
                height: '100%',
                width: '100%'
            });

            $('#sprFunnel .loader').hide();

            $.each(view.bindings, function (elementId, modelAttr) {
                $('#' + elementId).hide();
            });
        },

        bindings: { // Model to SVG elements mapping
            'tspan90': 'directOrdersCount',
            'tspan138': 'directOrdersValue',

            'tspan233': 'rfqsIgnoredCount',
            'tspan239': 'rfqsIgnoredValue',
            'tspan281': 'rfqsIgnoredPercent',

            'tspan587': 'totalOrdersSentCount',
            'tspan573': 'totalOrdersSentValue',

            'tspan1258': 'rfqsSentCount',
            'tspan742': 'rfqsPendingCount',
            'tspan736': 'rfqsPendingPercent',

            'tspan1298': 'quotedPercent',
            'tspan1304': 'winPercent',

            'tspan1316': 'quotesReceived',
            'tspan126': 'quotesAssumed',

            'tspan318': 'quotedUnknownCount',
            'tspan315': 'quotedUnknownPercent',
            'tspan4083': 'quotedUnknownValue',

            'tspan242': 'rfqsDeclinedCount',
            'tspan248': 'rfqsDeclinedValue',
            'tspan246': 'rfqsDeclinedPercent',

            'tspan283': 'quotedLostCount',
            'tspan291': 'quotedLostValue',
            'tspan280': 'quotedLostPercent',

            'tspan1346': 'competitiveOrdersSentCount',
            'tspan1352': 'competitiveOrdersSentValue',

            'tspan282': 'selectedBuyersCount',
            'tspan699': 'selectedSuppliersCount'
        },

        populateData: function (view, model) {
            $.each(view.bindings, function (elementId, modelAttr) {
                if (modelAttr in model.attributes) {
                    var value = model.getAttributeAsFormattedInt(modelAttr),
                        targetElement = document.getElementById(elementId);

                    switch (modelAttr) {
                        case 'rfqsDeclinedValue':
                        case 'rfqsIgnoredValue':
                        case 'quotedLostValue':
                        case 'directOrdersValue':
                        case 'totalOrdersSentValue':
                        case 'quotedUnknownValue':
                        case 'competitiveOrdersSentValue':
                            value = '$' + value;
                            break;
                    }
                    
                    if (targetElement) {      
                        targetElement.textContent = value;

                        $(targetElement).fadeIn();
                    } else {
                        console.error('SVG element not found: ' + elementId);
                    }
                }
            });
        },

        directInfoClicked: function () {
            alert('directInfoClicked');
        },

        competitiveInfoClicked: function () {
            alert('competitiveInfoClicked');
        }

    });

    return new view();
});