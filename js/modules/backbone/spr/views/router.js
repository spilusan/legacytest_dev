/*
 * SPR router
 */

define(['Backbone'], function (Backbone) {
    var sprRouter = Backbone.Router.extend({

        viewList: null,
        firstLoad: true,

        initialize: function (viewList) {
            this.viewList = viewList;

            this.isDataLoaded = false;
            this.completedRequests = 0;

            this.on('complete', function (event, request, settings) {
                ++this.completedRequests;

                if (this.completedRequests === 2) {
                    this.isDataLoaded = true;
                }
            });
        },

        routes: {
            "": "handleProfileRoute",
            "view/:type/:selectedBuyers/:selectedSuppliers/:selectRange(/:startDate)(/:endDate)": "handleRoutes",
        },

		convertRouteParams: function (selectedBuyers, selectedSuppliers, selectRange, startDate, endDate) {
            var result = {
                buyers: _.map($.unique(selectedBuyers.split(',')).sort(), Number),
                suppliers: _.map($.unique(selectedSuppliers.split(',')).sort(), Number),
                supplierList: [],
                daterange: selectRange
            };

            if (startDate) {
                result.startdate = startDate;
                result.enddate = endDate;
            }

            if (startDate) {
                $("#date-pick").attr("startdate", startDate);
                $("#date-pick").attr("enddate", endDate);
            } else {
                $("#date-pick").attr("startdate", null);
                $("#date-pick").attr("enddate", null);
            }

        	return result;
		},

        handleProfileRoute: function () {
            //Currently do nothing here
        },

        handleRoutes: function (type, selectedBuyers, selectedSuppliers, selectRange, startDate, endDate) {

            if (this.viewList.tabs.isRendered() === false) {
                this.viewList.tabs.render();
            }

            var runParams = this.convertRouteParams(selectedBuyers, selectedSuppliers, selectRange, startDate, endDate);

            $('.spr-tabs-container div').each(function () {
                $(this).removeClass('active');
            });

            var mainView = this.viewList.parent;

            if (this.firstLoad && parseInt(mainView.hideFilter) !== 1) {
                this.firstLoad = false;

                // we have to preload the filter boxes if they are not already pre-loaded
                mainView.selectedSuppliers = runParams.suppliers;

                var element;
                var $contentBox = $('#item-buyer-content');
                $contentBox.empty();
                for (var key in runParams.buyers) {
                    if (mainView.selectedBuyers.indexOf(parseInt(runParams.buyers[key])) === -1) {
                        mainView.selectedBuyers.push(parseInt(runParams.buyers[key]));
                    }
                    for (var buyerKey in mainView.buyerList.buyers) {
                        if (mainView.buyerList.buyers[buyerKey].id == runParams.buyers[key]) {
                            element = $(mainView.itemBuyerTemplate(mainView.buyerList.buyers[buyerKey]));
                            $contentBox.append(element);
                        }
                    }
                }

                // we have to preload supplier(s) if they are not already pre-loaded
                (function () {

                    function render(response) {

                        var element;
                        mainView.selectedSuppliers = [];
                        mainView.selectedSupplierList = [];
                        var $contentBox = $('#item-supplier-content');
                        $contentBox.empty();
                        for (var key in response) {

                            if (mainView.selectedSuppliers.indexOf(parseInt(response[key].pk)) === -1) {
                                mainView.selectedSuppliers.push(parseInt(response[key].pk));
                                mainView.selectedSupplierList.push(response[key]);
                                mainView.addSelectedSupplier({
                                    attributes: response[key]
                                });

                                if (mainView.anonim) {
                                    element = $(mainView.itemSupplierAnonimTemplate(response[key]));
                                } else {

                                    element = $(mainView.itemSupplierTemplate(response[key]));
                                }

                                $contentBox.append(element);
                            }
                        }

                        if (startDate &&  mainView.shipmate) {
                            $("#date-pick").val(selectRange + ' custom');
                            var datePicker = $("#date-picker");
                            var displayStartDate = moment(startDate).format('DD-MMM-YYYY');
                            var displayendDate = moment(endDate).format('DD-MMM-YYYY');
                            datePicker.show();
                            datePicker.val(displayStartDate + '->' + displayendDate);
                        } else {
                            $("#date-pick").val(selectRange + ' default');
                        }

                        // as selectmenu refresh is buggy i have to implement this fix to update the label as well
                        $("#date-pick").next('span').find('.ui-selectmenu-status').html($("#date-pick option:selected").text());

                        mainView.preloadSuppliers(runParams.buyers);
                        mainView.runBtnEnableDisable();

                        $('#item-supplier-content').removeClass('select-warning');
                        $('p.select-warning').hide();

                        mainView.reportRouter(type, runParams);
                    }

                    function error() {

                    }

                    var params = {
                        keywords: '',
                        byo: runParams.buyers,
                        pevMonths: 12,
                        limit: 50
                    };

                    mainView.setSupplierLastExecutionParams(params);
                    params.spb = runParams.suppliers;

                    $.ajax({
                        url: '/reports/data/supplier-performance-data/supplier-branches',
                        type: 'POST',
                        data: params
                    }).then(render).then(error);

                }());
            } else {
                mainView.reportRouter(type, runParams);
            }
        }

    });

    return sprRouter;

});