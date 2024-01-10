define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/shared/hbh/general',
	'libs/cookie',
	'libs/jquery.tools.overlay.modified',
	'../views/filtersView',
	'../views/transactionView',
	'../views/analysisView',
	'../views/calculatorView',
	'../views/customersView',
	'../views/conversionView',
	'../views/membershipView',
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
	'text!templates/reports/sir/tpl/poDirect.html',
	'text!templates/reports/sir/tpl/poComp.html',
	'text!templates/reports/sir/tpl/tpo.html',
	'text!templates/reports/sir/tpl/customer.html',
	'text!templates/reports/sir/tpl/market.html',
	'text!templates/reports/sir/tpl/membershipHelp.html',
	'text!templates/reports/sir/tpl/help/winloss-timetoquote.html',
	'text!templates/reports/sir/tpl/help/winloss-pricesens.html',
	'text!templates/reports/sir/tpl/help/reduce-ttquote.html',
	'text!templates/reports/sir/tpl/help/get-more-rfqs.html',
	'text!templates/reports/sir/tpl/help/improve-quote-rate.html',
	'text!templates/reports/sir/tpl/help/improve-win-rate.html',
	'text!templates/reports/sir/tpl/help/upgrade.html',
	'text!templates/reports/sir/tpl/help/basic-membership.html',
	'text!templates/reports/sir/tpl/help/customers-no-buyers.html',
	'text!templates/reports/sir/tpl/noQuot.html',
	'text!templates/reports/sir/tpl/lostQuot.html',
	'text!templates/reports/sir/tpl/pendingQuot.html',
	'text!templates/reports/sir/tpl/ajaxError.html',
	'text!templates/reports/sir/tpl/progressBar.html',
	'text!templates/reports/sir/tpl/avgQuotTime.html'
], function(
	$,
	_, 
	Backbone,
	Hb,
	generalHbh,
	cookie,
	Modal,
	filters,
	transaction,
	analysisView,
	calculatorView,
	customers,
	conversionView,
	membershipView,
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
	poDirectTpl,
	poCompTpl,
	tpoTpl,
	customerTpl,
	marketTpl,
	membershipHelpTpl,
	winlossTimeToQoteHelpTpl,
	winlossPricesensHelpTpl,
	reduceTtquoteTpl,
	getMoreRfqsTpl,
	improveQuoteRateTpl,
	improveWinRateTpl,
	upgradeTpl,
	basicMembershipTpl,
	customersNoBuyersTpl,
	noQuotTpl,
	lostQuotTpl,
	pendingQuotTpl,
	ajaxErrorTpl,
	progressBarTpl,
	avgQuotTimeTpl
){
	var sirView = Backbone.View.extend({
		el: $('body'),
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
		poDirectTemplate: Handlebars.compile(poDirectTpl),
		poCompTemplate: Handlebars.compile(poCompTpl),
		tpoTemplate: Handlebars.compile(tpoTpl),
		customerTemplate: Handlebars.compile(customerTpl),
		marketTemplate: Handlebars.compile(marketTpl),
		membershipHelpTemplate: Handlebars.compile(membershipHelpTpl),
		winlossTimeToQoteHelpTemplate: Handlebars.compile(winlossTimeToQoteHelpTpl),
		winlossPricesensHelpTemplate: Handlebars.compile(winlossPricesensHelpTpl),
		reduceTtquoteTemplate: Handlebars.compile(reduceTtquoteTpl),
		getMoreRfqsTemplate: Handlebars.compile(getMoreRfqsTpl),
		improveQuoteRateTemplate: Handlebars.compile(improveQuoteRateTpl),
		improveWinRateTemplate: Handlebars.compile(improveWinRateTpl),
		upgradeTemplate: Handlebars.compile(upgradeTpl),
		basicMembershipTemplate: Handlebars.compile(basicMembershipTpl),
		customersNoBuyersTemplate: Handlebars.compile(customersNoBuyersTpl),
		noQuotTemplate: Handlebars.compile(noQuotTpl),
		lostQuotTemplate: Handlebars.compile(lostQuotTpl),
		pendingQuotTemplate: Handlebars.compile(pendingQuotTpl),
		ajaxErrorTemplate: Handlebars.compile(ajaxErrorTpl),
		progressBarTemplate: Handlebars.compile(progressBarTpl),
		avgQuotTimeTemplate: Handlebars.compile(avgQuotTimeTpl),
		
		supplier: require('supplier/profile'),
		tabToView: require('supplier/tabToView'),
		isShipmate: require('user/shipmate'),
		
		comparePeriod: false,
		dateFrom: '',
		dateTo: '',
		dateFromComp: '',
		dateToComp: '',
		initialize: function() {
			var thisView = this;
			window.onerror =  this.onError;

			$('body').ajaxStop(function(){
				$('#waiting').hide();
				$('#progressBarHolder').hide();
			});

			var html = this.progressBarTemplate();
			$('#content').append(html);

			/*
			 * To be able to cancel all AJAX calles
			 * DEV-1323 calls must be aborted after switching to new navigation. The changes were implemented with DEV-1323 Please see commits there
			 */
			$.ajaxQ = (function(){
				var id = 0, Q = {}, progressbarMax = 0, progressbarPercent = 0, progressBarPointer = 0;
                $('#progressValue').width(5);

				$(document).ajaxSend(function(e, jqx, settings){
					if (/\/reports\/supplier-insight-data.*/i.test(settings.url)) {
						jqx._id = ++id;
						Q[jqx._id] = jqx;
						progressbarMax++;
						progressBarPointer++;
						renderProgressBar();
					}
				});
				
				$(document).ajaxComplete(function(e, jqx, settings){
					if (/\/reports\/supplier-insight-data.*/i.test(settings.url)) {
						progressBarPointer--;

						if (jqx.statusText === "abort") {
							resetProgressBar();
						}

						delete Q[jqx._id];
						renderProgressBar();
					}
				});

				return {
					abortAll: function(){
				    	$.each(Q, function(i, jqx){
				        	jqx.abort();
				        });
				        
				        return true;
				    }
				};

                function renderProgressBar() {

                    if (progressBarPointer > 0) {
                        $('#progressBarHolder').show();

                        var perc = 1 - (progressBarPointer  / progressbarMax);

                        if (progressbarPercent < perc) {
                            progressbarPercent = perc;
                        }

                        $('#progressLabel').html(parseInt(progressbarPercent * 100) +'%');

                        var newWidth = parseInt(585 * progressbarPercent + 5);

                        $('#progressValue').animate({
                                width: newWidth+"px"
                            },
                            250,
                            function(){
                                if ($('#progressValue').width() >= 589) {
                                    $('#progressBarHolder').hide();
                                }
                            }
                        );
                    } else {
                        resetProgressBar();
					}
                }

                function resetProgressBar() {
                    progressbarMax = 0;
                    progressbarPercent = 0;
                    progressBarPointer = 0;
                    $('#progressBarHolder').hide();
                    $('#progressValue').width(5);
				}

			})();

			$('#waiting').hide();

			/* Cache all ajax errors */
			$(document).ajaxError(function (e, xhr, options) {
  				// do your stuff
  				if (xhr.readyState == 4  && xhr.status !== 200 && xhr.statusText !== 'abort') {
  					$.ajaxQ.abortAll();
  					/* $('#waiting').hide(); */
  					if (xhr.responseText) {
						var errorObj = $.parseJSON(xhr.responseText);
						if (errorObj.error) {
							thisView.renderAjaxErrorMessage(errorObj.error);
							$.active = 0;
							throw new Error('Ajax error');
						}
					}
					$.active = 0;
					thisView.renderAjaxErrorMessage("We're sorry, there is a problem with our backend server. Please try again later.");
					throw new Error('Ajax error');
  				}
  				
			});

			this.membershipView = new membershipView();
			this.membershipView.parent = this;
			this.membershipView.getData();

			this.filtersView = new filters();
			this.filtersView.parent = this;
			this.filtersView.getDate();

			this.analysisView = new analysisView();
			this.analysisView.parent = this;

			this.calculatorView =  new calculatorView();
			this.calculatorView.parent = this;
			/* this.calculatorView.getData();  */

			this.transactionView = new transaction();
			this.transactionView.parent = this;

			this.customersView = new customers();
			this.customersView.parent = this;

			this.conversionView = new conversionView();
			this.conversionView.parent = this;
			$(document).ready(function(){
				thisView.render();
			});
			
		},

		render: function(){		

			var thisView = this;
			
			$('body').delegate('a.cHelp', 'click', function(e){
				e.preventDefault();
				thisView.displayHelp(e);
			});

			$('body').delegate('a.tHelp', 'click', function(e){
				e.preventDefault();
				thisView.displayHelp(e);
			});
			
			$('body').delegate('span.tHelp', 'click', function(e){
				e.preventDefault();
				thisView.displayThelp(e);
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

			switch (this.tabToView)
			{
				case 'billable':
					this.renderTransactions();
					return;
				case 'customers':
					this.renderCustomer();
					return;
				case 'conversion':
					this.renderConversion();
					return;
				default:
					break;
			}

			window.addEventListener("hashchange", function (e) { // also add one for `onclick`
				thisView.loadHashPage();
			});

			this.loadHashPage();

		},

		isSmartSir: function() {
			var location = window.location.href;
			return (location.indexOf('/reports/smart-sir') !== -1);
		},

		smartSirUnselectAllTabs: function () {
            $('#showConversion').removeClass('selected');
            $('#showTransaction').removeClass('selected');
            $('#showDetailed').removeClass('selected');
            $('#showCustomers').removeClass('selected');
        },

		loadHashPage: function() {

			var isSmartSir = this.isSmartSir();
			if (isSmartSir) {
                this.smartSirUnselectAllTabs();
            }

			var hash = window.location.hash.substr(1);
			switch (hash) {
				case 'showConversion':
                    if (isSmartSir) {
                        $('#showConversion').addClass('selected');
                    }
					this.renderConversion();
					break;
				case 'showTransaction':
                    if (isSmartSir) {
                        $('#showTransaction').addClass('selected');
                    }
                    this.renderTransactions();
					break;
				case 'showCustomers':
                    if (isSmartSir) {
                        $('#showCustomers').addClass('selected');
                    }
					this.renderCustomer();
					break;
				default:
                    if (isSmartSir) {
                        $('#showConversion').addClass('selected');
                    }
                    this.renderConversion();
					break;
			}
			
		    setTimeout(function () {
		    		$(window).scrollTop(0);
		    }, 100);
		    
			$( "a[href^='/reports/']" ).each(function(){
				if (location.href.indexOf($(this).attr('href')) === -1) {
                    $(this).removeClass('current').parent().removeClass('current');
				} else {
                    $(this).addClass('current').parent().addClass('current');
				}
			});

            this.getCaption();
		},

		getCaption: function()
		{
            var captionText = $('a.menu-label.current').html();
			var title = 'Supplier Insight Report | ' + captionText + ' | ' + this.supplier.tnid + ' | ' + this.supplier.name;
			$('h1.styled span').html('<strong>'+title+'</strong>');
			$('h1.styled span').attr('title',title);
		},

		renderConversion: function() 
		{

            $.ajaxQ.abortAll();
			$('#unactionedBox').html('');

			if ($('div.filters').html().length === 0) {
				$('.dataContainer').html('');
				$('div.filters').show();
				this.filtersView.getDate();
			}

            this.conversionView.getData();
        },

		//TODO remove this callback, put it back to renderConversion, and separate the HTML view, to have the div tag to render
		afterConversionViewLoaded: function( isCompare )
		{
			this.analysisView.getData(isCompare); 
			if (!isCompare) {
				this.calculatorView.getData();
			}
			
		},

		renderTransactions: function() {
			$.ajaxQ.abortAll();
            $('#unactionedBox').html('');
			this.getCaption($('#showTransaction').html());
        	$(".dataContainer").html('');
        	$('#showConversion').removeClass('selected');
			$('#showDetailed').removeClass('selected');
			$('#showCustomers').removeClass('selected');
			$('#showTransaction').addClass('selected');
			this.transactionView.getDataAndResetToDefaults();
        },

        renderCustomer: function()
        {
            $.ajaxQ.abortAll();
            $('#unactionedBox').html('');
        	this.getCaption($('#showCustomers').html());
        	$(".dataContainer").html('');
			this.customersView.getData();
        },

		displayHelp: function(e){
			e.preventDefault();

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
			    case "poDirect":
			    	html = thisView.poDirectTemplate();
			    	break;
			    case "poComp":
			    	html = thisView.poCompTemplate();
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
			    case "winloss-timetoquote":
			    	html = thisView.winlossTimeToQoteHelpTemplate();
			    	break;
			    case "winloss-price-sens":
			    	html = thisView.winlossPricesensHelpTemplate();
			    	break;
				case "reduce-ttquote":
			        html = thisView.reduceTtquoteTemplate(); 
			        break;
			    case "get-more-rfqs":
			        html = thisView.getMoreRfqsTemplate(); 
			        break;
			    case "improve-quote-rate":
			        html = thisView.improveQuoteRateTemplate(); 
			        break;
			    case "improve-win-rate":
			        html = thisView.improveWinRateTemplate(); 
			        break;
			    case "upgrade":
			        html = thisView.upgradeTemplate(); 
			        break;
			    case "customers-no-buyers":
			        html = thisView.customersNoBuyersTemplate(); 
			        break;
			    case "noQuot":
			        html = thisView.noQuotTemplate(); 
			        break;
			    case "lostQuot":
			        html = thisView.lostQuotTemplate(); 
			        break;
			    case "pendingQuot":
			        html = thisView.pendingQuotTemplate(); 
			        break;
			    case "avgQuotTime":
			        html = thisView.avgQuotTimeTemplate(); 
			        break;
			    case "basicMembership":
			    	html = thisView.membershipHelpTemplate();
			    	break;
			}

			$('#modalInfo .modalBody').html(html);
			this.openDialog('#modalInfo');
		},

		displayThelp: function(e)
		{
			e.preventDefault();
			var tpl = $(e.target).data('template'),
			thisView = this;
            var html = "";
			switch(tpl) {
			    case "membership":
			        html = thisView.membershipHelpTemplate();
			        break;
			    case "basic-membership":
			        html = thisView.basicMembershipTemplate();
			        break;
			    default:
			    	break;
			}

			$('#modalInfo .modalBody').html(html);
			this.openDialog('#modalInfo');
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

        renderAjaxErrorMessage: function( errorMessage )
        {
        	var html = this.ajaxErrorTemplate({message:errorMessage}); 
        	
        	$(".dataContainer").html(html);
        	
        	/* $("#body").html(html); */
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

		},

		onError: function(errorMsg, url, lineNumber)
		{
			//var errorMessage = '<div class="ajaxError">'+errorMsg+" line("+lineNumber+")  in "+url+'</div>'
			var errorM = "We're sorry, there is a problem. Please try again later.";
			var errorMessage = '<div class="ajaxError">'+errorM+'</div>';
        	$(".dataContainer").html(errorMessage);
        	/* $('#waiting').hide(); */
		}

	});

	return new sirView();
});
