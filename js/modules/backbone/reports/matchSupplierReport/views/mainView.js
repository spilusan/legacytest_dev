define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/shared/hbh/general',
	'libs/jquery.uniform',
	'../views/filters',
	'../views/supplierList',
	'../views/quote',
	'text!templates/reports/matchSupplierReport/tpl/help/missingHelp.html',
	'text!templates/reports/matchSupplierReport/tpl/help/potentialSaving.html',
	'text!templates/reports/matchSupplierReport/tpl/help/howCalculate.html',
	'text!templates/reports/matchSupplierReport/tpl/help/custom.html',
	'text!templates/reports/matchSupplierReport/tpl/remove-supplier-popup.html',
	'text!templates/reports/matchSupplierReport/tpl/comparability-popup.html',
	'text!templates/reports/matchSupplierReport/tpl/uncomp-message-popup.html'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	GeneralHbh,
	Uniform,
	filters,
	supplierList,
	quote,
	missingHelpTpl,
	potentialSavingTpl,
	howCalculateTpl,
	customTpl,
	removeSupplierPopupTpl,
	comparabilityPopupTpl,
	uncompMessagePopupTpl
){
	var matchSupplierReportView = Backbone.View.extend({

		showAjaxLoad: true,
		supplierToBlacklist: null,
		purgeCache: false,
		reasons: null,
		removeSupplierPopupTemplate: Handlebars.compile(removeSupplierPopupTpl),
		comparabilityPopupTemplate: Handlebars.compile(comparabilityPopupTpl),
		uncompMessagePopupTemplate: Handlebars.compile(uncompMessagePopupTpl),
		
		events: {
	
		},
		
		missingHelp: Handlebars.compile(missingHelpTpl),
		potentialSavingHelp: Handlebars.compile(potentialSavingTpl),
		howCalculateHelp: Handlebars.compile(howCalculateTpl),
		customTemplate : Handlebars.compile(customTpl),
		
		initialize: function(){
			var thisView = this;
			this.supplierList = supplierList;

			this.getCompareItems();
			this.fixWidth();

			$(window).resize(function(){
				thisView.fixWidth();
			});

			$('body').delegate('a.pHelp', 'click', function(e){
				e.preventDefault();
				thisView.displayHelp(e);
			});

			$('body').delegate('.sHelp', 'click', function(e){
				e.preventDefault();
				thisView.displaySmallHelp(e);
			});
			
			$('body').delegate('.rsCloseBtn', 'click', function(e){
				e.preventDefault();
				$('.removeSupplier').fadeOut(400);
			});

			$('body').delegate('.rsCancelBtn', 'click', function(e){
				e.preventDefault();
				$('.removeSupplier').fadeOut(400);
			});

			$('body').delegate('.rsOkBtn', 'click', function(e){
				e.preventDefault();
				thisView.addSupplierToBlacklist(e);
			});

			$('body').ajaxStart(function(){
				if (thisView.showAjaxLoad === true) {
					$('.savingHelp').hide();
					$('#waiting').show();
				}
			});

			$('body').ajaxStop(function(){
				$('#waiting').hide();
			});

			$(document).ready(function(){
				var html = '';
				var removeSupplierPopup = $('<div>');
				html = thisView.removeSupplierPopupTemplate();
				$(removeSupplierPopup).html(html);
				$('body').append(removeSupplierPopup);
				$('input[name="blacklistAction"]').uniform();

				var compPopup = $('<div>');
				html = thisView.comparabilityPopupTemplate();
				$(compPopup).html(html);
				$('body').append(compPopup);
				$('input[name="cpComparable"]').uniform();

				var unCompPopup = $('<div>');
				html = thisView.uncompMessagePopupTemplate();
				$(unCompPopup).html(html);
				$('body').append(unCompPopup);

			});
			supplierList.parent = this;
			quote.parent = this;
			filters.parent = this;
			
			
			var location = document.location.href.split('#');
			var loadMainReport = true;
			
			if (location.length > 1) {
				var params = location[1].split('&');
				var param = null;
				var spb = null;
				var byo = null;
				var saving = null;
				
				if (params.length === 3) {
					for (var key in params) {
						param = params[key].split('=');
						
						switch (param[0]) {
							case 'spb':
								spb = parseInt(param[1]);
								break;
							case 'byo':
								byo = parseInt(param[1]);
								break;
							case 'saving':
								saving = parseInt(param[1]);
								break;
							default:
								break;
						}
						
					}
				}
				
				if (byo !== null && spb !== null && saving !== null) {
					quote.tmpByo = byo;
					this.renderQuote(spb, saving);
					loadMainReport = false;
				}

			}
			
			if (loadMainReport) {
				filters.getData();
			}
			
		},

		getData: function(){
			supplierList.getData();
		},

		render: function() {

		},

		renderQuote: function( spbBranchCode, saving )
		{
		   quote.saving = saving;
	       quote.getDataPageOne(spbBranchCode);
		},

		getFilterData: function()
		{
			filters.renderLoadedList = true;
			filters.getData();
			
		},

		reRenderSupplierList: function()
		{
			supplierList.renderList();
		},

		getFilters: function()
		{
			return filters;
		},

		displayHelp: function(e){
			e.preventDefault();

			var tpl = $(e.target).attr('href'),
				thisView = this,
				html = "";

			switch(tpl) {
			    case "#potentialSaving":
			        html = thisView.potentialSavingHelp(); 
			        break;
			    case "#howCalculateHelp":
			        html = thisView.howCalculateHelp(); 
			        break;
			  	default:
					html = thisView.missingHelp(); 
			  		break;
			}

			this.openDialog(e.target, html);
		},

		displaySmallHelp: function(e){
			e.preventDefault();
			var helpId = null;
			var tpl = $(e.target).data('help'),
				thisView = this,
				html = "";

			switch(tpl) {
			  
			    case "uncomparable":
			    	helpId = $(e.target).data('helpid');
			    	if (helpId == 'default') {
			    		html = thisView.customTemplate({
			    			customText: 'This quote is already set to uncomparable as it is not for 100% of the requested items.'
			    		}); 
			    	} else {
						var textToDisplay = 'Help text is missing';
				    	for (var key in thisView.reasons) {
				    		if (thisView.reasons[key].id == helpId) {
				    			textToDisplay = thisView.reasons[key].description;
				    		}
				    	}
				        html = thisView.customTemplate({customText: textToDisplay}); 
			    	}

			        break;
			  	default:
					html = thisView.missingHelp(); 
			  		break;
			}

			this.openSmallHelpDialog(e.target, html);
		},
		

		openDialog: function( element, html )
		{
			$('.savingHelp').html(html);
			var position = $(element).offset();
			$('.savingHelp').css('left', (position.left-50)+"px");
			$('.savingHelp').css('top', (position.top+10) +"px");
			$('.savingHelp').fadeIn(800);
		},

		openSmallHelpDialog: function( element, html )
		{
			$('.smallHelp').html(html);
			var sHeight = $('.smallHelp').height();
			var position = $(element).offset();
			$('.smallHelp').css('left', (position.left-50)+"px");
			$('.smallHelp').css('top', (position.top-40-sHeight) +"px");
			$('.smallHelp').fadeIn(800);
		},

		fixWidth: function()
		{
		
			var element = $('#content');
			var newWidth = $(window).width() - $(element).offset().left - 25;
			newWidth = (newWidth < 1005) ? 1005 : newWidth;
			element.width(newWidth);
			
			var newHeight = ($('#content').height() > 422) ? $('#content').height()  +5 : 427;
            $('#body').height(newHeight);  
            $('#footer').show();
		},

		addSupplierToBlacklist: function(e)
		{
			var thisView = this;
			var blacklistType = '';

			var reasonText = $('textarea[name="blacklistReasonText"]').val();
			/* Trim this way, for EI8 compatibility */
			reasonText = reasonText.replace(/^\s+|\s+$/gm,'');
			if (reasonText.length === 0) {
				/* Validate input box */
				$( "#validationWarning" ).fadeIn( 300 ).delay( 1200 ).fadeOut( 400 );
			} else {
				$('.removeSupplier').hide();
				if ($("#blacklistOtion1").attr('checked')) {
					blacklistType = 'blacksb';
				} else {
					blacklistType = 'blacklist';
				}

				/* TODO add reason, store it somewhere */
	            $.ajax({
	                type: "POST",
	                url: "/buyer/blacklist/add",
	                data: {
	                	supplierId: thisView.supplierToBlacklist,
	                	type: blacklistType
	                },
	                success: function(){
	                	/* Store reason text */
						$.ajax({
			                type: "POST",
			                url: "/buyer/blacklist/store-reason",
			                data: {
			                	supplierId: thisView.supplierToBlacklist,
			                	type: blacklistType,
			                	reason: reasonText
			                },
			                success: function(){


								$.ajax({
					                type: "GET",
					                url: "/buyer/blacklist/clear-spend-benchmark-cache",
					                success: function(){
										$('textarea[name="blacklistReasonText"]').val('');
										
										thisView.purgeCache = false;
										/* thisView.getData(); */
										thisView.removeItemFromCollection(thisView.supplierToBlacklist);
										supplierList.renderList();
					                },
					                error: function(e) {
					                	alert('Something went wrong, Please try it later');
					                	$('.comparabilityPopup').fadeOut(400);
					                }
					            });


			                },
			                error: function(e) {
			                	alert('Something went wrong, Please try it later');
			                	$('.removeSupplier').fadeOut(400);
			                }
			            });
	                },
	                error: function(e) {
	                	alert('Something went wrong, Please try it later');
	                	$('.removeSupplier').fadeOut(400);
	                }
	            });
			}
		},
		
		getCompareItems: function()
		{
			var thisView = this;
			$.ajax({
                type: "POST",
                url: "/buyer/quote/stats-exclude",

                success: function( response ){
                	thisView.reasons = response.reasons;
                },
                error: function(e) {
                	alert('Something went wrong, Please try it later');
                }
            });
		},

		removeItemFromCollection: function( branchCode )
		{
			supplierList.removeItemFromCollection(branchCode);
		}

	});

	return new matchSupplierReportView();
});