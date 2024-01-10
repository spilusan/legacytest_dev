/*
	TODO discuss why old backend does not equal to the new one, Allan in Manila
*/
define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/shared/hbh/general',
	'libs/jquery.validity.min',
    'libs/jquery.validity.custom.output.sir',
	'libs/jquery.tools.overlay.modified',
	'libs/jquery.uniform',
	'libs/zingchart',
	'libs/license',
	'libs/cookie',
	'../collections/sirCollection',
	'../collections/supplierInsightDataCollection',
	'../views/filtersView',
	'../views/transactionView',
	'../views/analysisView',
	'../views/calculatorView',
	'../views/customersView',
	'text!templates/reports/sir/tpl/summary.html',
	'text!templates/reports/sir/tpl/detailed.html',
	'text!templates/reports/sir/tpl/unactioned.html',
	'text!templates/reports/sir/tpl/unactionedrate.html',
	'text!templates/reports/sir/tpl/onboard.html',
	'text!templates/reports/sir/tpl/banner.html',
	'text!templates/reports/sir/tpl/search.html',
	'text!templates/reports/sir/tpl/profile.html',
	'text!templates/reports/sir/tpl/contact.html',
	'text!templates/reports/sir/tpl/rfq.html',
	'text!templates/reports/sir/tpl/quotes.html',
	'text!templates/reports/sir/tpl/po.html',
	'text!templates/reports/sir/tpl/tpo.html',
	'text!templates/reports/sir/tpl/customer.html',
	'text!templates/reports/sir/tpl/market.html'

], function(
	$,
	_, 
	Backbone,
	Hb,
	generalHbh,
	validity,
	validityCustom,
	Modal,
	Uniform,
	zingchart,
	license,
	cookie,
	sirCollection,
	supplierInsightData,
	filters,
	transaction,
	analysisView,
	calculatorView,
	customers,
	summaryTpl,
	detailsTpl,
	unactionedTpl,
	unactionedrateTpl,
	onboardTpl,
	bannerTpl,
	searchTpl,
	profileTpl,
	contactTpl,
	rfqTpl,
	quotesTpl,
	poTpl,
	tpoTpl,
	customerTpl,
	marketTpl
){
	var sirView = Backbone.View.extend({
		el: $('body'),

		summaryTemplate: Handlebars.compile(summaryTpl),
		detailsTemplate: Handlebars.compile(detailsTpl),
		unactionedTemplate: Handlebars.compile(unactionedTpl),
		unactionedrateTemplate: Handlebars.compile(unactionedrateTpl),
		onboardTemplate: Handlebars.compile(onboardTpl),
		bannerTemplate: Handlebars.compile(bannerTpl),
		searchTemplate: Handlebars.compile(searchTpl),
		profileTemplate: Handlebars.compile(profileTpl),
		contactTemplate: Handlebars.compile(contactTpl),
		rfqTemplate: Handlebars.compile(rfqTpl),
		quotesTemplate: Handlebars.compile(quotesTpl),
		poTemplate: Handlebars.compile(poTpl),
		tpoTemplate: Handlebars.compile(tpoTpl),
		customerTemplate: Handlebars.compile(customerTpl),
		marketTemplate: Handlebars.compile(marketTpl),
		dateFrom: '',
		dateTo: '',
		dateFromComp: '',
		dateToComp: '',
		comparePeriod: false,
		compareMarket: true,
		categories: [],
		brands: [],
		locations: [],
		products: [],
		supplier: require('supplier/profile'),
		details: false,
		round: 0,
		debug: 0,

		initialize: function() {
			if(this.debug === 1){
				this.ajaxType = "GET";
			}
			else {
				this.ajaxType = "POST";
			}
			$('input[name="agree"]').uniform();

			this.analysisView = new analysisView();
			this.analysisView.parent = this;

			this.calculatorView =  new calculatorView;
			this.calculatorView.parent = this;


			this.supplierCollection = new sirCollection();
			this.supplierCollection.url = "/reports/api/supplier";

			this.compareCollection = new sirCollection();
			this.compareCollection.url = "/reports/api/supplier";

			this.marketCollection = new sirCollection();
			this.marketCollection.url = "/reports/api/market";

			this.marketCompCollection = new sirCollection();
			this.marketCompCollection.url = "/reports/api/market";

			var thisView = this;
			/*
			$('body').ajaxStart(function(){
				$('#waiting').show();
			});

			$('body').ajaxStop(function(){
				$('#waiting').hide();
			});
			*/

			this.filtersView = new filters();
			this.filtersView.parent = this;
			this.filtersView.getDate();

			this.transactionView = new transaction();
			this.transactionView.parent = this;

			this.customersView = new customers();
			this.customersView.parent = this;

			this.getSupplierData();

		},


		getSupplierData: function(){
			this.supplierCollection.reset();
			this.compareCollection.reset();
			this.marketCollection.reset();
			this.marketCompCollection.reset();
			var thisView = this;

			this.supplierCollection.fetch({
				type: this.ajaxType,
				data: $.param({
					tnid: this.supplier.tnid,
					start: this.dateFrom,
					end: this.dateTo
				}),
				complete: function(){
					if(thisView.comparePeriod){
						thisView.getCompareData();
					}
					else {
						if(thisView.compareMarket){
							thisView.getMarketData();
						}
						else {
							if(!thisView.details){
								thisView.render();
							}
							else {
								thisView.renderDetails();
							}
						}
					}
					
				}
			});
		},

		getCompareData: function(){
			var thisView = this;
			this.compareCollection.fetch({
				type: this.ajaxType,
				data: $.param({
					tnid: this.supplier.tnid,
					start: this.dateFromComp,
					end: this.dateToComp
				}),
				complete: function(){
					if(thisView.compareMarket){
						thisView.getMarketData();
					}
					else {
						if(!thisView.details){
							thisView.render();
						}
						else {
							thisView.renderDetails();
						}
					}					
				}
			});
		},

		getMarketData: function(){
			var thisView = this;
			
			this.marketCollection.fetch({
				type: this.ajaxType,
				data: $.param({
					start: this.dateFrom,
					end: this.dateTo,
					categories: this.categories,
					brands: this.brands,
					location: this.locations,
					products: this.products
				}),
				complete: function(){
					if(thisView.comparePeriod){
						thisView.getMarketCompData();
					}
					else {
						if(!thisView.details){
							thisView.render();
						}
						else {
							thisView.renderDetails();
						}
					}
				}
			});
		},

		getMarketCompData: function(){
			var thisView = this;

			this.marketCompCollection.fetch({
				type: this.ajaxType,
				data: $.param({
					start: this.dateFromComp,
					end: this.dateToComp,
					categories: this.categories,
					brands: this.brands,
					location: this.locations,
					products: this.products
				}),
				complete: function(){
					if(!thisView.details){
						thisView.render();
					}
					else {
						thisView.renderDetails();
					}
				}
			});
		},

		render: function(){

		$(".filters").css('display','block'); /* TODO remove it, when all header filters are done */

			this.details = false;
			var data = {},
			thisView = this;

			data.supplierInfo = this.supplier;
			data.supplierInfo.profileCompletionScore = parseInt(data.supplierInfo.profileCompletionScore);

			data.supplier = this.supplierCollection.models[0].attributes;
			data.supplier.allRfqs = data.supplier['enquiry-summary']['enquiry-sent'].count + data.supplier['tradenet-summary'].RFQ.count;
			data.dateFromPretty = this.dateFromPretty;
			data.dateToPretty = this.dateToPretty;

			$('.rfqCount.count').html(data.supplier['enquiry-summary']['unactioned-rfq'].count);
			$('.rfqCount.rate').html(data.supplier['enquiry-summary']['unactioned-rfq'].rate.toFixed(1)+"%");
			if(this.compareCollection.models[0]){
				data.dateFromCompPretty = this.dateFromCompPretty;
				data.dateToCompPretty = this.dateToCompPretty;

				data.compare = this.compareCollection.models[0].attributes;
				data.compare.allRfqs = data.compare['enquiry-summary']['enquiry-sent'].count + data.compare['tradenet-summary'].RFQ.count;
			}

			if(this.marketCollection.models[0]){
				data.market = this.marketCollection.models[0].attributes;
			}

			if(this.marketCompCollection.models[0]){
				data.marketComp = this.marketCompCollection.models[0].attributes;
			}

			data.comparePeriod = this.comparePeriod;
			data.compareMarket = this.compareMarket;

			var html = this.summaryTemplate(data);
			$('.dataContainer').html(html);
			$('#showSummary').addClass('selected');
			$('#showDetailed').removeClass('selected');
			$('#showTransaction').removeClass('selected');
			$('#showCustomers').removeClass('selected');
			
			$('body').delegate('a.cHelp', 'click', function(e){
				e.preventDefault();
				thisView.displayHelp(e);
			});
			$('.valueBox').bind('mouseover', function(e){
				$(this).addClass('hovered');
				setTimeout(function(){
					$(e.target).parent().find('.tooltipHolder').show();
				}, 300);
			});

			$('.tooltipHolder').hover(function(){
				$(this).addClass('hovered');
			});

			$('.valueBox').bind('mouseleave', function(e){
				$(this).removeClass('hovered');
				var isHovered = $(e.target).parent().find('.tooltipHolder').hasClass('hovered');
				if(!isHovered){
					setTimeout(function(){
						$(e.target).parent().find('.tooltipHolder').hide();
					}, 300);
				}
			});

			$('.tooltipHolder').bind('mouseleave', function(e){
				$(this).removeClass('hovered');
				if($(e.target).is('.tooltip')){
					var isHovered = $(e.target).parent().parent().find('.valueBox').hasClass('hovered');
					if(!isHovered){
						setTimeout(function(){
							$(e.target).parent().parent().find('.tooltipHolder').hide();
						}, 300);
					}
				}
				else {
					var isHovered = $(e.target).parent().parent().parent().find('.valueBox').hasClass('hovered');
					if(!isHovered){
						setTimeout(function(){
							$(e.target).parent().parent().parent().find('.tooltipHolder').hide();
						}, 300);
					}
				}
				
			});

			/*
			var cookieData = cookie.getJSON('sirinfo');
        	var cookieTTL = 30;

        	if(cookieData === null) {
        		this.openDialog('#modalChanges');
        		cookieData = {'seen': 1};
        		cookie.setJSON('sirinfo', cookieData, cookieTTL);
        	}
        	else if(cookieData.seen === 1){
        		this.openDialog('#modalChanges');
        		cookieData = {'seen': 2};
        		cookie.setJSON('sirinfo', cookieData, cookieTTL);
        	}
        	*/

        	/*
        	$('body').ajaxStart(function(){
				$('#waiting').show();
			});

			$('body').ajaxStop(function(){
				$('#waiting').hide();
			});
			*/

			$('body').undelegate('li#showDetailed', 'click').delegate('li#showDetailed', 'click', function(e){
				e.preventDefault();
				thisView.renderDetails();

			});
			$('body').delegate('li#showSummary', 'click', function(){
				thisView.renderSummary();
				//thisView.render();
			});
			$('body').delegate('li#showTransaction', 'click', function(){
				thisView.renderTransactions();
			});
			$('body').delegate('li#showCustomers', 'click', function(){
				thisView.renderCustomer();
			});
			$('body').delegate('a.export', 'click', function(e){
				thisView.exportXls(e);
			});
			$('body').delegate('a.sendEmail', 'click', function(e){
				thisView.openSendDialog(e);
			});
			$('body').delegate('a.viewGmv', 'click', function(e){
				thisView.openViewGmvDialog(e);
			});
			$('body').delegate('#modalContact input[type="submit"]', 'click', function(e){
				thisView.sendAsEmail(e);
			});

			//redner analyze part of the document

			/* Get SIR3 new  separated data */
			this.analysisView.getData(); 
			this.calculatorView.getData();

		},

		renderSummary: function() 
		{
			$('#showSummary').addClass('selected');
			$('#showDetailed').removeClass('selected');
			$('#showTransaction').removeClass('selected');
			$('#showCustomers').removeClass('selected');
			this.details = false;
			this.getSupplierData();
		},
				
		renderDetails: function(){
			$(".filters").css('display','block'); /* TODO remove it, when all header filters are done */

			this.details = true;
			
			var data = {};
			
			data = this.setCurrentData();

			data.fromDate = this.dateFrom.slice(6,8) + '/' + this.dateFrom.slice(4,6) + '/' + this.dateFrom.slice(2,4);
			data.toDate = this.dateTo.slice(6,8) + '/' + this.dateTo.slice(4,6) + '/' + this.dateTo.slice(2,4);

			if(this.marketCollection.models[0]){
				data.market = this.marketCollection.models[0].attributes;
			}

			if(this.marketCompCollection.models[0]){
				data.marketComp = this.marketCompCollection.models[0].attributes;

				if(data.market['impression']['count'] > 0 && data.market['impression']['count'] < 1){
					data.market['impression']['count'] = 1;
				}
				if(data.marketComp['impression']['count'] > 0 && data.marketComp['impression']['count'] < 1){
					data.marketComp['impression']['count'] = 1;
				}
				data.market['impression']['change'] = this.setChangeData(
					data.market['impression']['count'], 
					data.marketComp['impression']['count']
				);

				if(data.market['impression-by-unique-user']['count'] > 0 && data.market['impression-by-unique-user']['count'] < 1){
					data.market['impression-by-unique-user']['count'] = 1;
				}
				if(data.marketComp['impression-by-unique-user']['count'] > 0 && data.marketComp['impression-by-unique-user']['count'] < 1){
					data.marketComp['impression-by-unique-user']['count'] = 1;
				}
				data.market['impression-by-unique-user']['change'] = this.setChangeData(
					data.market['impression-by-unique-user']['count'], 
					data.marketComp['impression-by-unique-user']['count']
				);

				if(data.market['contact-view']['count'] > 0 && data.market['contact-view']['count'] < 1){
					data.market['contact-view']['count'] = 1;
				}
				if(data.marketComp['contact-view']['count'] > 0 && data.marketComp['contact-view']['count'] < 1){
					data.marketComp['contact-view']['count'] = 1;
				}
				data.market['contact-view']['change'] = this.setChangeData(
					data.market['contact-view']['count'], 
					data.marketComp['contact-view']['count']
				);

				if(data.market['contact-view-by-unique-user']['count'] > 0 && data.market['contact-view-by-unique-user']['count'] < 1){
					data.market['contact-view-by-unique-user']['count'] = 1;
				}
				if(data.marketComp['contact-view-by-unique-user']['count'] > 0 && data.marketComp['contact-view-by-unique-user']['count'] < 1){
					data.marketComp['contact-view-by-unique-user']['count'] = 1;
				}
				data.market['contact-view-by-unique-user']['change'] = this.setChangeData(
					data.market['contact-view-by-unique-user']['count'], 
					data.marketComp['contact-view-by-unique-user']['count']
				);

				if(data.market['enquiry-sent']['count'] > 0 && data.market['enquiry-sent']['count'] < 1){
					data.market['enquiry-sent']['count'] = 1;
				}
				if(data.marketComp['enquiry-sent']['count'] > 0 && data.marketComp['enquiry-sent']['count'] < 1){
					data.marketComp['enquiry-sent']['count'] = 1;
				}
				data.market['enquiry-sent']['change'] = this.setChangeData(
					data.market['enquiry-sent']['count'], 
					data.marketComp['enquiry-sent']['count']
				);

				if(data.market['enquiry-sent-by-unique-user']['count'] > 0 && data.market['enquiry-sent-by-unique-user']['count'] < 1){
					data.market['enquiry-sent-by-unique-user']['count'] = 1;
				}
				if(data.marketComp['enquiry-sent-by-unique-user']['count'] > 0 && data.marketComp['enquiry-sent-by-unique-user']['count'] < 1){
					data.marketComp['enquiry-sent-by-unique-user']['count'] = 1;
				}
				data.market['enquiry-sent-by-unique-user']['change'] = this.setChangeData(
					data.market['enquiry-sent-by-unique-user']['count'], 
					data.marketComp['enquiry-sent-by-unique-user']['count']
				);
			}

			if(this.compareCollection.models[0]){
				data.fromDateComp = this.dateFromComp.slice(6,8) + '/' + this.dateFromComp.slice(4,6) + '/' + this.dateFromComp.slice(2,4);
				data.toDateComp = this.dateToComp.slice(6,8) + '/' + this.dateToComp.slice(4,6) + '/' + this.dateToComp.slice(2,4);

				data.compare = this.setCompareData(data);
			}

			data.topSearch = this.joinSummaryArrays(
				data.supplier["impression-summary"]["impression-by-search-keywords"], 
				(data.compare)?data.compare["impression-summary"]["impression-by-search-keywords"]:null
			);

			data.userType = this.joinSummaryArrays(
				data.supplier["impression-summary"]["impression-by-user-type"],
				(data.compare)?data.compare["impression-summary"]["impression-by-user-type"]:null
			);

			data.rfqKeyword = this.joinSummaryArrays(
				data.supplier["enquiry-summary"]["enquiries-sent-by-search-keywords"],
				(data.compare)?data.compare["enquiry-summary"]["enquiries-sent-by-search-keywords"]:null
			);

			data.rfqUser = this.joinSummaryArrays(
				data.supplier["enquiry-summary"]["enquiries-sent-by-user-type"],
				(data.compare)?data.compare["enquiry-summary"]["enquiries-sent-by-user-type"]:null
			);

			if(this.compareCollection.models[0]){
				_.each(data.topSearch, function(item){
					item.change = this.setChangeData(
						item.period1.var1,
						item.period2.var1
					);
				}, this);

				_.each(data.userType, function(item){
					item.change = this.setChangeData(
						item.period1.var1,
						item.period2.var1
					);
				}, this);

				_.each(data.rfqKeyword, function(item){
					item.change = this.setChangeData(
						item.period1.var1,
						item.period2.var1
					);
				}, this);

				_.each(data.rfqUser, function(item){
					item.change = this.setChangeData(
						item.period1.var1,
						item.period2.var1
					);
				}, this);
			}

			data.comparePeriod = this.comparePeriod;
			data.compareMarket = this.compareMarket;

			var html = this.detailsTemplate(data);

			$('.dataContainer').html(html);
			$('#showDetailed').addClass('selected');
			$('#showSummary').removeClass('selected');
			$('#showTransaction').removeClass('selected');
			$('#showCustomers').removeClass('selected');

			this.startZingchart();

			var thisView = this;
			$('body').delegate('h3', 'click', function(e){
				$(e.target).next('div').toggle();
				$(e.target).toggleClass('collapsed');
			});

			/*
			$('body').ajaxStart(function(){
				$('#waiting').show();
			});

			$('body').ajaxStop(function(){
				$('#waiting').hide();
			});
			*/
		},

		setCurrentData: function(){
			var data = {};
			data.catSearch = [];
			data.brandSearch =[];
			data.supplier = this.supplierCollection.models[0].attributes;

			count = 0;
			_.each(data.supplier['search-summary']['category-searches-global'], function(item){
				data.catSearch[count] = {};
				data.catSearch[count].current = {};
				data.catSearch[count].current.global = {};
				data.catSearch[count].current.local = {};

				data.catSearch[count].current.global.name = item.name;
				data.catSearch[count].current.global.click = item.click;
				data.catSearch[count].current.global.search = item.search;
				count++;
			}, this);

			count = 0;

			_.each(data.supplier['search-summary']['category-searches-local'], function(item){
				data.catSearch[count].current.local.click = item.click;
				data.catSearch[count].current.local.search = item.search;
				count++;
			}, this);

			count = 0;
			_.each(data.supplier['search-summary']['brand-searches-global'], function(item){
				data.brandSearch[count] = {};
				data.brandSearch[count].current = {};
				data.brandSearch[count].current.global = {};
				data.brandSearch[count].current.local = {};

				data.brandSearch[count].current.global.name = item.name;
				data.brandSearch[count].current.global.click = item.click;
				data.brandSearch[count].current.global.search = item.search;
				count++;
			}, this);


			count = 0;
			_.each(data.supplier['search-summary']['brand-searches-local'], function(item){
				data.brandSearch[count].current.local.name = item.name;
				data.brandSearch[count].current.local.click = item.click;
				data.brandSearch[count].current.local.search = item.search;
				count++;
			}, this);
			return data;
		},

		setCompareData: function(data){
			data.compare = this.compareCollection.models[0].attributes;

			data.supplier['impression-summary']['impression']['change'] = this.setChangeData(
				data.supplier['impression-summary']['impression']['count'], 
				data.compare['impression-summary']['impression']['count']
			);

			data.supplier['impression-summary']['impression-by-unique-user']['change'] = this.setChangeData(
				data.supplier['impression-summary']['impression-by-unique-user']['count'], 
				data.compare['impression-summary']['impression-by-unique-user']['count']
			);

			data.supplier['impression-summary']['contact-view']['change'] = this.setChangeData(
				data.supplier['impression-summary']['contact-view']['count'], 
				data.compare['impression-summary']['contact-view']['count']
			);

			data.supplier['impression-summary']['contact-view-by-unique-user']['change'] = this.setChangeData(
				data.supplier['impression-summary']['contact-view-by-unique-user']['count'], 
				data.compare['impression-summary']['contact-view-by-unique-user']['count']
			);

			data.supplier['enquiry-summary']['enquiry-sent']['change'] = this.setChangeData(
				data.supplier['enquiry-summary']['enquiry-sent']['count'], 
				data.compare['enquiry-summary']['enquiry-sent']['count']
			);

			data.supplier['enquiry-summary']['enquiries-sent-by-unique-user']['change'] = this.setChangeData(
				data.supplier['enquiry-summary']['enquiries-sent-by-unique-user']['count'], 
				data.compare['enquiry-summary']['enquiries-sent-by-unique-user']['count']
			);


			this.fixOrder(
				data.supplier['search-summary']['category-searches-global'],
				data.compare['search-summary']['category-searches-global']
			);

			this.fixOrder(
				data.supplier['search-summary']['category-searches-local'],
				data.compare['search-summary']['category-searches-local']
			);

			this.fixOrder(
				data.compare['search-summary']['category-searches-global'],
				data.compare['search-summary']['category-searches-local']
			);

			this.fixOrder(
				data.supplier['search-summary']['category-searches-global'],
				data.supplier['search-summary']['category-searches-local']
			);

			count = 0;
			_.each(data.supplier['search-summary']['category-searches-global'], function(item){
				data.catSearch[count] = {};
				data.catSearch[count].current = {};
				data.catSearch[count].current.global = {};
				data.catSearch[count].current.local = {};

				data.catSearch[count].current.global.name = item.name;
				data.catSearch[count].current.global.click = item.click;
				data.catSearch[count].current.global.search = item.search;
				count++;
			}, this);

			count = 0;
			_.each(data.supplier['search-summary']['category-searches-local'], function(item){
				if(!data.catSearch[count]){
					data.catSearch[count] = {};
					data.catSearch[count].current = {};
					data.catSearch[count].current.global = {};
					data.catSearch[count].current.local = {};
				}

				data.catSearch[count].current.local.click = item.click;
				data.catSearch[count].current.local.search = item.search;
				count++;
			}, this);

			count = 0;
			_.each(data.compare['search-summary']['category-searches-global'], function(item){
				if(!data.catSearch[count]){
					data.catSearch[count] = {};
					data.catSearch[count].current = {};
					data.catSearch[count].current.global = {};
					data.catSearch[count].current.local = {};
				}
				data.catSearch[count].compare = {};
				data.catSearch[count].compare.global = {};
				data.catSearch[count].compare.local = {};

				data.catSearch[count].compare.global.click = item.click;
				data.catSearch[count].compare.global.search = item.search;

				data.catSearch[count].current.global.clickChange = this.setChangeData(
					data.catSearch[count].current.global.click, 
					item.click
				);
				data.catSearch[count].current.global.searchChange = this.setChangeData(
					data.catSearch[count].current.global.search, 
					item.search
				);

				count++;
			}, this);

			count = 0;
			_.each(data.compare['search-summary']['category-searches-local'], function(item){
				if(!data.catSearch[count]){
					data.catSearch[count] = {};
					data.catSearch[count].current = {};
					data.catSearch[count].current.global = {};
					data.catSearch[count].current.local = {};
				}
				data.catSearch[count].compare.local.click= item.click;
				data.catSearch[count].compare.local.search = item.search;

				data.catSearch[count].current.local.clickChange = this.setChangeData(
					data.catSearch[count].current.local.click, 
					item.click
				);
				data.catSearch[count].current.local.searchChange = this.setChangeData(
					data.catSearch[count].current.local.search, item.search
				);

				count++;
			}, this);

			this.fixOrder(
				data.supplier['search-summary']['brand-searches-global'],
				data.compare['search-summary']['brand-searches-global']
			);

			this.fixOrder(
				data.supplier['search-summary']['brand-searches-local'],
				data.compare['search-summary']['brand-searches-local']
			);

			this.fixOrder(
				data.supplier['search-summary']['brand-searches-local'],
				data.supplier['search-summary']['brand-searches-global']
			);

			this.fixOrder(
				data.compare['search-summary']['brand-searches-local'],
				data.compare['search-summary']['brand-searches-global']
			);

			count = 0;
			_.each(data.supplier['search-summary']['brand-searches-global'], function(item){
				data.brandSearch[count] = {};
				data.brandSearch[count].current = {};
				data.brandSearch[count].current.global = {};
				data.brandSearch[count].current.local = {};

				data.brandSearch[count].current.global.name = item.name;
				data.brandSearch[count].current.global.click = item.click;
				data.brandSearch[count].current.global.search = item.search;
				count++;
			}, this);

			count = 0;
			_.each(data.supplier['search-summary']['brand-searches-local'], function(item){
				if(!data.brandSearch[count]){
					data.brandSearch[count] = {};
					data.brandSearch[count].current = {};
					data.brandSearch[count].current.global = {};
					data.brandSearch[count].current.local = {};
				}

				data.brandSearch[count].current.local.click = item.click;
				data.brandSearch[count].current.local.search = item.search;
				count++;
			}, this);


			count = 0;
			_.each(data.compare['search-summary']['brand-searches-global'], function(item){
				if(!data.brandSearch[count]){
					data.brandSearch[count] = {};
					data.brandSearch[count].current = {};
					data.brandSearch[count].current.global = {};
					data.brandSearch[count].current.local = {};
				}
				data.brandSearch[count].compare = {};
				data.brandSearch[count].compare.global = {};
				data.brandSearch[count].compare.local = {};

				data.brandSearch[count].compare.global.click = item.click;
				data.brandSearch[count].compare.global.search = item.search;
				data.brandSearch[count].current.global.clickChange = this.setChangeData(
					data.brandSearch[count].current.global.click, 
					item.click
				);
				data.brandSearch[count].current.global.searchChange = this.setChangeData(
					data.brandSearch[count].current.global.search,
					item.search
				);

				count++;
			}, this);

			count = 0;
			_.each(data.compare['search-summary']['brand-searches-local'], function(item){
				if(!data.brandSearch[count]){
					data.brandSearch[count] = {};
					data.brandSearch[count].current = {};
					data.brandSearch[count].current.global = {};
					data.brandSearch[count].current.local = {};
				}
				data.brandSearch[count].compare.local.click = item.click;
				data.brandSearch[count].compare.local.search = item.search;

				data.brandSearch[count].current.local.clickChange = this.setChangeData(
					data.brandSearch[count].current.local.click, 
					item.click
				);
				data.brandSearch[count].current.local.searchChange = this.setChangeData(
					data.brandSearch[count].current.local.search,
					item.search
				);
				count++;
			}, this);

			return data.compare;
		},

		setChangeData: function(current, compare){
			var change = {};

			if(compare == current){
				change.amount = 0;
			}
			else if( compare > 0 && current == 0 )
			{
				change.amount = -100;
			}
			else if( compare == 0 && current > 0){
				change.amount = 100;
			}
			else {
				change.amount = ((current - compare) / compare) * 100;
			}

			if(!change.amount){
				change.amount = 0;
			}

			if(change.amount > 0){
				change.positive = 1; //positive prefix
			}
			else if(change.amount < 0){
				change.positive = 0; //negative prefix
			}

			change.amount = change.amount.toFixed(0);
			
			return change;
		},

		fixOrder: function(firstArray, secondArray){
			length = firstArray.length;

			for (var i = 0; i < length; i++) {
				
				if (!secondArray[i] || firstArray[i].name != secondArray[i].name) {
				// element added to the second array to push its other elements one position down
					secondArray.splice(i, 0, {
						"name" : firstArray[i].name,
						"click" : 0,
						"search" : 0,
						"id" : firstArray[i].id
					});
				}
			}

			if(this.round === 0){
				this.round = 1;
				this.fixOrder(secondArray, firstArray);
			}
			else {
				this.round = 0;
			}
		},

		joinSummaryArrays: function (period1var1Array,period2var1Array, period1var2Array, period2var2Array){
			var returnArray = [];
			var indexArray = [];
			$.each(period1var1Array, function(key, value) { 
				indexArray.push(value.name);
				returnArray.push({
					name:value.name,
					period1:{
						var1:value.count.toString()
					}
				});
				
				//Create "0" cells if needed for period 2, will be overwritten if values exist
				if (period2var1Array) {
				    returnArray[returnArray.length-1].period2 = {
				        var1:"0"
				    }
				}
			});
			if (period1var2Array) {
				$.each(period1var2Array, function(key, value) { 

					var pos = $.inArray(value.name, indexArray);
					if (pos!=-1)
					{
						if (returnArray[pos].period1)
							returnArray[pos].period1.var2 = value.count.toString()
						else
							returnArray[pos].period1 = {var2:value.count.toString()};
					}
					else
					{
						returnArray.push({
							name:value.name,
							period1:{
								var1:"0",
								var2:value.count.toString()
							}
						});
						indexArray.push(value.name);
					}
				});
			}
			if (period2var1Array) {
				$.each(period2var1Array, function(key, value) {
                    
					var pos = $.inArray(value.name, indexArray);
					if (pos!=-1)
					{
						returnArray[pos].period2 = {var1:value.count.toString()};
					}
					else
					{
						returnArray.push({
							name:value.name,
							period1:{
								var1:"0"
							},
							period2:{
								var1:value.count.toString()
							}
						});
						indexArray.push(value.name);
					}
				});
			}
			if (period2var2Array) {
				$.each(period2var2Array, function(key, value) {

					var pos = $.inArray(value.name, indexArray);
					if (pos!=-1)
					{
						if (returnArray[pos].period2)
							returnArray[pos].period2.var2 = value.count.toString()
						else
							returnArray[pos].period2 = {var2:value.count.toString()};
						}
					else
					{
						returnArray.push({
							name:value.name,
							period1:{
								var1:"0"
							},
							period2:{
								var1:value.count.toString()
							}
						});
						indexArray.push(value.name);
					}
				});
			}
			return returnArray;
		},

		getChartArray: function(dataArray){
			var returnArray = [],
				currentDate = Date.parse(this.dateFrom.slice(0,4)+'/'+this.dateFrom.slice(4,6)+'/'+this.dateFrom.slice(6,8)),
				toDate = Date.parse(this.dateTo.slice(0,4)+'/'+this.dateTo.slice(4,6)+'/'+this.dateTo.slice(6,8));

			_.each(dataArray, function(value){
				while (Date.parse(value.date.replace(/\-/gi,'/'))>currentDate)
				{
					currentDate += 1000*60*60*24;
					returnArray.push(0);
				}
				
				currentDate += 1000*60*60*24;
				returnArray.push(value.count);

			}, this);

			while (currentDate <= toDate)
			{
				currentDate += 1000*60*60*24;
				returnArray.push(0);
			}
			
			return returnArray;
		},

		startZingchart: function(){
			var thisView = this,
				impressions = this.getChartArray(this.supplierCollection.models[0].attributes['impression-summary'].impression.days),
				views = this.getChartArray(this.supplierCollection.models[0].attributes['impression-summary']["contact-view"].days),
				rfqs = this.getChartArray(this.supplierCollection.models[0].attributes['enquiry-summary']["enquiry-sent"].days);
			zingchart.exec("detailed-graph","destroy");
			zingchart.render({
				id : 'detailed-graph',
				width : 900,
				height : 300,
				data : {
					"graphset" : [
					{
						"background-color":"#ffffff",
						"type" : "line",
						"chart": {
						    "background-color": "#fff"
						},
						"plotarea" : {
							"margin" : "20 120 60 60"
						},
						"legend" : {
							"margin" : "auto 10 auto auto"
						},
						"scale-x" : {
							"max-items" : 8,
							"min-value" : Date.parse(thisView.dateFrom.slice(0,4)+'/'+this.dateFrom.slice(4,6)+'/'+this.dateFrom.slice(6,8)),
							"step" : 600000*6*24,
							"transform" : {
								"type" : "date",
								"all" : "%D, %d %M",
								"item":{
									"visible":false
								}
																	},
							"zooming" : 1
						},
						"guide" : {
						},
						"tooltip" : {
							"visible" : false
						},
						"scale-y" : {
							"zooming" : 0
						},
						"plot" : {
							"marker" : {
								"size" : 3
							}
						},
						"series" : [
							{
								"line-width" : 2,
								"shadow" : false,
								"text" : "Profile Views",
								"values" : impressions,
								"line-color" : "#fecb38",
								"aspect" : "spline",
								"marker": {
									"background-color": "#fecb38",
									"border-color": "#fecb38"
								},
								"tooltip-text" : "%t: %v"
							},
							{
								"line-width" : 2,
								"text" : "Contact Views",
								"shadow" : false,
								"values" : views,
								"line-color" : "#fb830f",
								"aspect" : "spline",
								"marker": {
									"background-color": "#fb830f",
									"border-color": "#fb830f"
								},
								"tooltip-text" : "%t: %v"
							},
							{
								"line-width" : 2,
								"text" : "Pages RFQs",
								"shadow" : false,
								"values" : rfqs,
								"line-color" : "#f14331",
								"aspect" : "spline",
								"marker": {
									"background-color": "#f14331",
									"border-color": "#f14331"
								},
								"tooltip-text" : "%t: %v"
							}
						]
					}
				]
				}
			});//End Zing render
		},

		exportXls: function(e){
			e.preventDefault();

			var thisView = this,
				el = $(e.target);

			if (el.hasClass('disabled')) return false;

			el.addClass('disabled');
			el.text('Exporting');

			$.ajax({
				url: '/reports/api/export-to-excel',
				type: this.ajaxType,
				data: {
					'tnid'       : thisView.supplier.tnid,
					'start'      : thisView.dateFrom,
					'end'	     : thisView.dateTo,
					'start2'     : thisView.dateFromComp,
					'end2'	     : thisView.dateToComp,
					'location'   : thisView.locations,
					'categories' : thisView.categories,
					'brands'	 : thisView.brands,
					'products'	 : thisView.products
				},
				cache: false,
				error: function(request, textStatus, errorThrown) {
			    	var response = eval('(' + request.responseText + ')');
			    	alert("ERROR " + request.status + ": " + response.error);	
					el.removeClass('disabled');
					el.text('Export');
			    },
				success: function( response ){
					el.removeClass('disabled');
					el.text('Export');
					if( typeof response.data != "undefined") {
						location.href='/reports/api/download?fileName=' + response.data;
					}
					else {
						alert("Sorry, there is a problem exporting this report. Please try again.");
					}
				}
			});
		},

		displayHelp: function(e){
			var tpl = $(e.target).attr('href'),
				thisView = this,
				html = "";

			switch(tpl) {
			    case "unactioned":
			        html = thisView.unactionedTemplate(); 
			        break;
			    case "unactionedrate":
			        html = thisView.unactionedrateTemplate(); 
			        break;
			    case "onboard":
			        html = thisView.onboardTemplate(); 
			        break;
			    case "banner":
			    	html = thisView.bannerTemplate();
			    	break;
			    case "search":
			    	html = thisView.searchTemplate();
			    	break;
			    case "profile":
			    	html = thisView.profileTemplate();
			    	break;
			    case "contact":
			    	html = thisView.contactTemplate();
			    	break;
			    case "rfq":
			    	html = thisView.rfqTemplate();
			    	break;
			    case "quotes":
			    	html = thisView.quotesTemplate();
			    	break;
			    case "po":
			    	html = thisView.poTemplate();
			    	break;
			    case "tpo":
			    	html = thisView.tpoTemplate();
			    	break;
			    case "customer":
			    	html = thisView.customerTemplate();
			    	break;
		    	case "market":
			    	html = thisView.marketTemplate();
			    	break;
			}

			$('#modalInfo .modalBody').html(html);
			this.openDialog('#modalInfo');
		},
		openViewGmvDialog: function(e){
			var fromDate = this.dateFrom.slice(6,8) + '/' + this.dateFrom.slice(4,6) + '/' + this.dateFrom.slice(0,4);
			var toDate = this.dateTo.slice(6,8) + '/' + this.dateTo.slice(4,6) + '/' + this.dateTo.slice(0,4);
			
					    //reports/supplier-conversion?parent=&id=58341&period=monthly&fromDate=20%2F11%2F2013&toDate=20%2F11%2F2014
			window.open("/reports/supplier-conversion?parent=&id=" + this.supplier.tnid + "&period=quarterly&fromDate=" + fromDate + "&toDate=" + toDate, '_blank');
		},
		openSendDialog: function(e){
			e.preventDefault();
			$('#modalContact form input[name="startDate"]').val(this.dateFrom);
			$('#modalContact form input[name="endDate"]').val(this.dateTo);
			$('#agree').bind('click', function(){
				if(!$(this).val()){
					$(this).val(1);
				}
				else {
					$(this).val('');
				}
			});
			this.openDialog("#modalContact");
		},

		validateMailForm: function(){
			$.extend($.validity.patterns, {
	            mail: /@/ 
	        });

	        $.validity.setup({ outputMode:"custom" });

	        $.validity.start();
			

			$('textarea[name="fromText"]').require('Please enter who you want to show the email is from.');
			$('textarea[name="bodyText"]').require('Please enter a message.');
			$('input.emails').match('mail','Please enter a valid email address.');
			$('input.firstEmail').require('Please enter at least one email address.');

			var result = $.validity.end();

			if(!$('input#agree').is(':checked')){
				result.valid = false;
				if($('.error.terms').length === 0){
					$('input#agree').parent().parent().parent().parent().parent().prepend("<div class='error terms'>You have to accept the terms.</div><div class='clear err'></div>");
				}
				$('input.firstEmail').focus();
			}
			else {
				$('.error.terms').remove();
				$('.err.clear').remove();
			}

			return result.valid;
		},

		sendAsEmail: function(e){
			e.preventDefault();
			if(this.validateMailForm()){
				$.ajax({
					url: '/reports/api/send-email-summary-to-customer',
					type: 'POST',
					data: $('#modalContact form.new').serialize(),
					cache: false,
				    error: function(request, textStatus, errorThrown) {
				    	response = eval('(' + request.responseText + ')');
				    	alert("ERROR " + request.status + ": " + response.error);					    	
				    },
					success: function( response ){
						$('#modalContact').overlay().close();
						$('input[name="emails[]"]').val('');	
						$('input[name="bodyText"]').val('');
						$('input[name="fromText"]').val('');						
					}
				});	
			}
		},

        openDialog: function(dialog) { 
            $(dialog).overlay({
                mask: 'black',
                left: 'center',
                fixed: false,

                onBeforeLoad: function() {
                    var windowWidth = $(window).width();
                    var modalWidth = $(dialog).width();
                    var posLeft = windowWidth/2 - modalWidth/2;

                    $(dialog).css('left', posLeft);
                }
            });

            $(dialog).overlay().load();
        },

        renderTransactions: function() {
        	$(".dataContainer").html('');
        	$('#showSummary').removeClass('selected');
			$('#showDetailed').removeClass('selected');
			$('#showCustomers').removeClass('selected');
			$('#showTransaction').addClass('selected');
			this.transactionView.getData();
        },

        renderCustomer: function()
        {
        	$(".dataContainer").html('');
			$('#showCustomers').addClass('selected');
        	$('#showSummary').removeClass('selected');
			$('#showDetailed').removeClass('selected');
			$('#showTransaction').removeClass('selected');

			this.customersView.getData();

        },

		/* 
			This function converts date from 20150101 to 01-JAN-2014, 
			this part may be removed later, if backend will except other formats too
		*/
        dateConvertToShortMonthFormat: function( dateString ) 
		{

			var ds = dateString+''; // Just to make sure it is a text
			var monthNames = new Array('JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC');
			return ds.substring(6)+'-'+monthNames[parseInt(ds.substring(4,6))-1]+'-'+ds.substring(0,4);

		}
	});

	return new sirView;
});
