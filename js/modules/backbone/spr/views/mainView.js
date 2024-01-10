define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'standardLibs/flexibility',
	'backbone/shared/hbh/general',
	'backbone/spr/views/router',
	'backbone/spr/views/tabsView',
	'backbone/spr/views/profileView',
	'backbone/spr/views/funnelView',
	'backbone/spr/views/quotingView',
	'backbone/spr/views/orderingView',
	'backbone/spr/views/cycleView',
	'backbone/spr/views/qualityPaymentDeliveryView',
	'backbone/spr/views/competitivenessView',
	'components/buyerSelector/views/mainView',
	'components/supplierSelector/views/mainView',
	'text!templates/spr/tpl/index.html',
	'text!templates/spr/tpl/filters.html',
	'text!templates/spr/tpl/itemBuyer.html',
	'text!templates/spr/tpl/itemSupplier.html',
	'backbone/spr/views/printView',
	'text!templates/spr/tpl/itemAnonimSupplier.html',
	'highcharts-defaults'
], function (
	$,
	_,
	Backbone,
	Hb,
	Flexibility,
	HbhGen,
	Router,
	Tabs,
	ProfileView,
	FunnelView,
	QuotingView,
	OrderingView,
	CycleView,
	QualityPaymentDeliveryView,
	CompetitivenessView,
	buyerModal,
	supplierModal,
	Tpl,
	FiltersTpl,
	itemBuyerTpl,
	itemSupplierTpl,
	PrintView,
	itemSupplierAnonimTpl,
	highchartsDefaults
) {
	highchartsDefaults.apply();

	var view = Backbone.View.extend({
		events: {},

		template: Handlebars.compile(Tpl),
		filtersTemplate: Handlebars.compile(FiltersTpl),
		itemBuyerTemplate: Handlebars.compile(itemBuyerTpl),
		itemSupplierTemplate: Handlebars.compile(itemSupplierTpl),
		itemSupplierAnonimTemplate: Handlebars.compile(itemSupplierAnonimTpl),
		buyerList: require('buyer/branches'),
		hideFilter: require('spr/hideFilter'),
		anonim: require('spr/anonim'),
		shipmate: require('spr/shipmate'),
		showCustomRange: require('spr/showCustomRange'),
		selectedBuyers: [],
		selectedSuppliers: [],
		selectedSupplierList: [],
		displayHourGlass: false,
		initialize: function () {
			var thisView = this;

			/* To be able to cancel all AJAX calles	*/
			$.ajaxQ = (function () {
				var id = 0,
					Q = {};

				$(document).ajaxSend(function (e, jqx) {
					jqx._id = ++id;
					Q[jqx._id] = jqx;
				});

				$(document).ajaxComplete(function (e, jqx) {
					delete Q[jqx._id];
				});

				return {
					abortAll: function () {
						$.each(Q, function (i, jqx) {
							jqx.abort();
						});
						return true;
					}
				};

			})();

			this.router = new Router({
				parent: this,
				tabs: Tabs
			});

			$(function () {
				//dom ready codes

				Tabs.parent = thisView;

				thisView.getData();

				if (parseInt(thisView.hideFilter) !== 1) {

					if (thisView.buyerList.buyers.length === 1) {
						thisView.onOneBuyer(thisView.buyerList.buyers[0]);
					} else {
						buyerModal.parent = thisView;
						buyerModal.onSubmit(thisView.buyerListSubmit);
						buyerModal.addBuyerBrances(thisView.buyerList.buyers);
						buyerModal.render('#buyers-modal', '#buyer-btn-filter,#item-buyer-content');
					}

					supplierModal.parent = thisView;
					supplierModal.render('#suppliers-modal', '#supplier-btn-filter,#item-supplier-content');
					supplierModal.onSubmit(thisView.supplierListSubmit);

					//New requirement, when clicking on inactive button, show warning
					supplierModal.onInactiveClick(function () {
						$('#item-supplier-content').addClass('select-warning');
						$('p.select-warning').show();
					});

					$('#run-report').click(function (e) {
						e.preventDefault();
						// DEV-1865 force executing run report button for the first time
						thisView.runReport(true);
					});

					$('.print-report').click(function (e) {
						e.preventDefault();
						$activeTab = $('.spr-tabs-container div.active');
						var dateRange = $("#date-pick").val().split(' ')[0];

						var urlToPrint = '/reports/print-supplier-performance#view/all/' + encodeURIComponent($.unique(thisView.selectedBuyers).join(',')) + '/' + encodeURIComponent($.unique(thisView.selectedSuppliers).join(',')) + '/' + dateRange;
                        var selectedStartDate = $("#date-pick").attr('startdate');
                        var selectedEndDate = $("#date-pick").attr('enddate');

                        if (selectedStartDate) {
                            urlToPrint += '/' + selectedStartDate + '/' + selectedEndDate;
                        }
						window.open(urlToPrint, '_blank');
					});
				}

				Backbone.history.start();
			});
		},

		getData: function () {
			this.render();
		},

		render: function () {
			var thisView = this;
			var html = this.template();

			$('#renderContainer').html(html);
			if (parseInt(thisView.hideFilter) !== 1) {
				this.renderFilterTemplate();

				$('.spr-filter').delegate('.collapsible', 'click', function () {
					$(this).toggleClass('collapsed');
				});
			}
		},

		renderFilterTemplate: function () {
			var filterData = {
				buyerCount: this.buyerList.buyers.length,
				firstBuyer: this.buyerList.buyers[0],
				shipmate: this.shipmate,
				showCustomRange: this.showCustomRange
			};

			var filtersHtml = this.filtersTemplate(filterData);
			var thisView = this;

			$('#filter').html(filtersHtml);

			$("#date-pick").selectmenu({
				classes: {
					"ui-selectmenu-menu": "highlight"
				},
				open: function (e, ui) {
					var buttonPos = $('#date-pick-button').offset(),
						buttonWidth = Math.floor($('#date-pick-button').width());

					$('.ui-selectmenu-menu').css({
						opacity: 0
					});

					$("#date-pick-button").css({
						position: 'relative',
						marginLeft: -(buttonPos.left % 1)
					});

					setTimeout(function () {
						$('#date-pick-menu').css({
							width: buttonWidth,
							height: 'auto',
							marginTop: -1
						});
						$('.ui-selectmenu-menu').css({
							opacity: 1
						});
					}, 10);
				},
				close: function () {
					$("#date-pick-button").css({
						marginLeft: 'auto'
					});
				}
			});

			$("#date-pick").change(function () {
				var datePick = $(this);
				var selectedValue = datePick.val();
				var pickType = null;

				if (selectedValue) {
                    pickType = selectedValue.split(' ')[1];
                }

				var datePicker = $('#date-picker');
				if (pickType === 'custom') {
                    datePicker.show();
				} else {
                    datePick.attr('startdate', null);
                    datePick.attr('enddate', null);
					datePicker.hide();
                    datePicker.val('');
				}

				thisView.runBtnEnableDisable();
				var forceLoad = false;
				if(location.hash !== ""){
					forceLoad = true;
				}
				thisView.runReport(forceLoad);
			});

			this.renderDateRangePicker();
		},

		buyerListSubmit: function (items) {
			var thisView = this.parent;
			var element;
			thisView.selectedBuyers = [];
			var $contentBox = $('#item-buyer-content');
			$contentBox.empty();
			_.each(items, function (item) {
				if (thisView.selectedBuyers.indexOf(parseInt(item.tnid)) === -1) {
					thisView.selectedBuyers.push(parseInt(item.tnid));
					element = $(thisView.itemBuyerTemplate(item));
					$contentBox.append(element);
				}
			}, this);

			supplierModal.setFilterBuyerList(thisView.selectedBuyers);
			supplierModal.preLoadSuppliers();
			thisView.runBtnEnableDisable();

			$('#item-supplier-content').removeClass('select-warning');
			$('p.select-warning').hide();

			thisView.runReport(true);
		},

		supplierListSubmit: function (items) {

			var thisView = this.parent;
			var element;
			thisView.selectedSuppliers = [];
			thisView.selectedSupplierList = [];
			var $contentBox = $('#item-supplier-content');
			$contentBox.empty();
			_.each(items, function (item) {

				if (thisView.selectedSuppliers.indexOf(parseInt(item.attributes.pk)) === -1) {
					thisView.selectedSuppliers.push(parseInt(item.attributes.pk));
					thisView.selectedSupplierList.push(item.attributes);

					if (thisView.anonim) {
						element = $(thisView.itemSupplierAnonimTemplate(item.attributes));
					} else {
						element = $(thisView.itemSupplierTemplate(item.attributes));
					}

					$contentBox.append(element);
				}
			}, this);

			thisView.runBtnEnableDisable();
			thisView.runReport(true);
		},

		runReport: function (forceLoad) {
			if (this.selectedBuyers.length > 0 && this.selectedSuppliers.length > 0 && $("#date-pick").val() !== null && (location.hash !== "" || forceLoad === true)) {
				if ($('#run-report').length > 0) {

					$('.print-report').show();

					var hash = $('.spr-tabs ul li a.active').attr('href');

					if (typeof hash === 'undefined') {
						// if there is no tab selected let's default it to tab-1
						hash = '#tab-1';
						$(hash).addClass('active');
					}

					var reportType = hash.replace('#', '');

					$('a.print-report').show();
                    var dateRange = $("#date-pick").val().split(' ')[0];

					var newHash = '#view/' + reportType + '/' + encodeURIComponent($.unique(this.selectedBuyers).sort().join(',')) + '/' + encodeURIComponent($.unique(this.selectedSuppliers).sort().join(',')) + '/' + dateRange;

                    var selectedStartDate = $("#date-pick").attr('startdate');
                    var selectedEndDate = $("#date-pick").attr('enddate');

                    if (selectedStartDate) {
                        newHash += '/' + selectedStartDate + '/' + selectedEndDate;
                    }


					if (forceLoad && window.location.hash === newHash) {

						var runParams = {
							buyers: this.selectedBuyers,
							suppliers: this.selectedSuppliers,
							daterange: $("#date-pick").val()
						};

						if (selectedStartDate) {
                            runParams.startdate = selectedStartDate;
                            runParams.enddate = selectedEndDate;
                       }

						this.reportRouter(reportType, runParams, true);
					}

					this.forceLoad = forceLoad;

					window.location.hash = newHash;
				}
			}
		},

		runBtnEnableDisable: function () {
			if (this.selectedBuyers.length > 0 && this.selectedSuppliers.length > 0 && $("#date-pick").val() !== null) {
				$('#run-report').removeClass('inactive');
			} else {
				$('#run-report').addClass('inactive');
			}

			if (this.selectedBuyers.length > 0) {
				$('#supplier-btn-filter,#item-supplier-content').removeClass('inactive');
			} else {
				$('#supplier-btn-filter,#item-supplier-content').addClass('inactive');
			}
		},

		onOneBuyer: function (item) {
			var element;
			this.selectedBuyers = [];
			this.selectedBuyers.push(parseInt(item.id));
			supplierModal.setFilterBuyerList(this.selectedBuyers);
			supplierModal.preLoadSuppliers();
			this.runBtnEnableDisable();
		},

		preloadSuppliers: function (buyerList) {
			supplierModal.setFilterBuyerList(buyerList);
			supplierModal.preLoadSuppliers();
		},

		addSelectedSupplier: function (item) {
			var exists = false;
			for (var key in supplierModal.confirmedSupplierList) {
				if (parseInt(supplierModal.confirmedSupplierList[key].attributes.pk) === parseInt(item.attributes.pk)) {
					exists = true;
				}
			}

			if (!exists) {
				supplierModal.confirmedSupplierList.push(item);
			}
		},

		setSupplierLastExecutionParams: function (params) {
			supplierModal.lastExecutionParams = JSON.stringify(params);
		},

		reportRouter: function (type, runParams, reload) {

			runParams.supplierList = this.selectedSupplierList;

			if (this.forceLoad) reload = true;

			switch (type) {
				case 'all':
					PrintView.render(runParams, reload);
					FunnelView.getData(runParams, reload);
					this.runReport();
					QuotingView.getData(runParams, reload);
					CompetitivenessView.getData(runParams, reload);
					OrderingView.getData(runParams, reload);
					CycleView.getData(runParams, reload);
					QualityPaymentDeliveryView.getData(runParams, reload);
					$('[id^="tab-"]').not('#tab-1').show();
					break;
				case 'tab-1':
					Tabs.Tabs.changeTab('#tab-1', reload);
					$('#tab-1').addClass('active');
					$('#tab-1').append($('<div class="spinner"></div>'));
					ProfileView.selectedSupplier = 0;
					ProfileView.anonim = this.anonim;
					ProfileView.getData(runParams, null, reload);
					break;
				case 'tab-2':
					Tabs.Tabs.changeTab('#tab-2', reload);
					$('#tab-2').addClass('active');
					FunnelView.getData(runParams, reload);
					break;
				case 'tab-3':
					Tabs.Tabs.changeTab('#tab-3', reload);
					$('#tab-3').addClass('active');
					QuotingView.getData(runParams, reload);
					break;
				case 'tab-4':
					Tabs.Tabs.changeTab('#tab-4', reload);
					$('#tab-4').addClass('active');
					CompetitivenessView.getData(runParams, reload);
					break;
				case 'tab-5':
					Tabs.Tabs.changeTab('#tab-5', reload);
					$('#tab-5').addClass('active');
					OrderingView.getData(runParams, reload);
					break;
				case 'tab-6':
					Tabs.Tabs.changeTab('#tab-6', reload);
					$('#tab-6').addClass('active');
					CycleView.getData(runParams, reload);
					break;
				case 'tab-7':
					Tabs.Tabs.changeTab('#tab-7', reload);
					$('#tab-7').addClass('active', reload);
					QualityPaymentDeliveryView.getData(runParams, reload);
					break;
				default:
					//TODO should do a header location to a 404 page?
					alert('page does not exists');
					break;
			}

			$('.spr-filter > h4:first-child').addClass('collapsible');

			if (this.forceLoad) reload = false;
		},
        renderDateRangePicker: function () {
            var thisView = this;

            $('#date-picker').daterangepicker({
                opens: 'right',
                showDropdowns: true,
                showCustomRangeLabel: true,
                alwaysShowCalendars: true,
                buttonClasses: "btn",
                applyClass: "green",
                autoUpdateInput: false,
                linkedCalendars: false,
                cancelClass: "transparent transparent-blue",
                ranges: {
                    'Last week': [moment().startOf('isoWeek').subtract(7, 'days'), moment().startOf('isoWeek').subtract(1, 'days')],
                    'Last Month': [moment().startOf('month').subtract(1, 'month'), moment().startOf('month').subtract(1, 'days')],
                    'Last Quarter': [moment().startOf('quarter').subtract(1, 'quarters'), moment().startOf('quarter').subtract(1, 'days')],
                    'Last Year': [moment().startOf('month').subtract(1, 'days').subtract(1, 'years').add(1, 'days'), moment().startOf('month').subtract(1, 'days')],
                    '13 - 24 Month back from now': [moment().startOf('month').subtract(1, 'days').subtract(2, 'years').add(1, 'days'), moment().startOf('month').subtract(1, 'days').subtract(1, 'years')]
                },
                locale: {
                    "format": 'DD-MMM-YYYY',
                }
            });

            $('#date-picker').on('apply.daterangepicker', function(ev, picker) {
                var startDate = picker.startDate.format('YYYYMMDD');
                var endDate = picker.endDate.format('YYYYMMDD');

                var displayStartDate = picker.startDate.format('DD-MMM-YYYY');
                var displayEndDate = picker.endDate.format('DD-MMM-YYYY');

                $("#date-pick").attr('startdate', startDate);
                $("#date-pick").attr('enddate', endDate);
                $("#date-picker").val(displayStartDate + '->' + displayEndDate);

                thisView.runReport(true);
            });

        },


	});

	return new view();
});