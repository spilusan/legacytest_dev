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
	'libs/jquery.textfill',
	'../collections/supplierInsightDataCollection',
	'text!templates/reports/sir/tpl/conversion.html',
	'text!templates/reports/sir/tpl/conversionAwareness.html',
	'text!templates/reports/sir/tpl/conversionleadGen.html',
	'text!templates/reports/sir/tpl/conversionleadConv.html',
	'text!templates/reports/sir/tpl/conversionRedBoxes.html'
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
	quickfit,
	sirCollection,
	conversionContentHolderTpl,
	conversionAwarenessTpl,
	conversionleadGenTpl,
	conversionleadConvTpl,
	conversionRedBoxesTpl
){

	var conversionView = Backbone.View.extend({
		el: $('body'),
		conversionContentHolderTemplate: Handlebars.compile(conversionContentHolderTpl), 
		conversionAwarenessTemplate: Handlebars.compile(conversionAwarenessTpl),
		conversionleadGenTemplate: Handlebars.compile(conversionleadGenTpl),
		conversionleadConvTemplate: Handlebars.compile(conversionleadConvTpl),
		conversionRedBoxesTemplate: Handlebars.compile(conversionRedBoxesTpl),
		dateFrom: '',
		dateTo: '',
		dateFromComp: '',
		dateToComp: '',
		compareMarket: true,
		products: [],
		supplier: require('supplier/profile'),
		debug: 1,

		initialize: function() {
			if(this.debug === 1){
				this.ajaxType = "GET";
			}
			else {
				this.ajaxType = "POST";
			}

			$('input[name="agree"]').uniform();

			this.createCollections();
		},

		createCollections: function(){
			this.brandAwareCollection = new sirCollection();
			this.brandAwareCompCollection = new sirCollection();
			this.leadGenCollection = new sirCollection();
			this.leadGenCompCollection = new sirCollection();
			this.leadConvCollection = new sirCollection();
			this.leadConvCompCollection = new sirCollection();
			this.notQotCollection = new sirCollection();
			this.notQotCompCollection = new sirCollection();
			this.lostQotCollection = new sirCollection();
			this.lostQotCompCollection = new sirCollection();
		},

		resetCollections: function(){
			this.brandAwareCollection.reset();
			this.brandAwareCompCollection.reset();
			this.leadGenCollection.reset();
			this.leadGenCompCollection.reset();
			this.leadConvCollection.reset();
			this.leadConvCompCollection.reset();
			this.notQotCollection.reset();
			this.notQotCompCollection.reset();
			this.lostQotCollection.reset();
			this.lostQotCompCollection.reset();
		},

		getFilters: function(){
			this.dateFrom = this.dateConvert(this.parent.dateFrom);
			this.dateTo = this.dateConvert(this.parent.dateTo);
			this.dateFromComp = this.dateConvert(this.parent.dateFromComp);
			this.dateToComp = this.dateConvert(this.parent.dateToComp);
		},

		getData: function(){
			$.ajaxQ.abortAll();
			this.resetCollections();
			this.getFilters();

			var thisView = this;

			var pData = {
				isComparePeriod: this.parent.comparePeriod,
				isComp: false,
				period: "Period: " + this.parent.filtersView.dateFromPretty + " - " + this.parent.dateToPretty,
			};

			var html = this.conversionContentHolderTemplate(pData);
			$('.dataContainer').html(html);
			var html = this.conversionAwarenessTemplate({hasSpinner: true});
			$('#funnel-awareness').html(html);
			var html = this.conversionleadGenTemplate({hasSpinner: true});
			$('#funnel-leadGen').html(html);
			var html = this.conversionleadConvTemplate({hasSpinner: true});
			$('#funnel-leadConv').html(html);
			//var html = this.conversionRedBoxesTemplate({hasSpinner: true});
			//$('#funnel-redboxes').html(html);

			this.parent.afterConversionViewLoaded(false);
			if(this.parent.comparePeriod){
				pData.isComp = true;
				pData.period = "Period: " + this.parent.filtersView.dateFromCompPretty + " - " + this.parent.dateToCompPretty;
				var html = this.conversionContentHolderTemplate(pData);
				$('.dataContainerCompare').html(html);
				$('.dataContainerCompare').show();
				var html = this.conversionAwarenessTemplate({hasSpinner: true});
				$('#funnel-awarenessCompare').html(html);
				var html = this.conversionleadGenTemplate({hasSpinner: true});
				$('#funnel-leadGenCompare').html(html);
				var html = this.conversionleadConvTemplate({hasSpinner: true});
				$('#funnel-leadConvCompare').html(html);
				var html = this.conversionRedBoxesTemplate({hasSpinner: true});
				//$('#funnel-redboxesCompare').html(html);
				this.parent.afterConversionViewLoaded(true);
			}
			else {
				$('.dataContainerCompare').hide();
			}
			
			this.brandAwareCollection.fetch({
				type: this.ajaxType,
				data: $.param({
					type: "brand-awareness",
					tnid: this.supplier.tnid,
					startDate: this.dateFrom,
					endDate: this.dateTo,
					skipTokenCheck: 1
				}),
				complete: function( response ){
					if (response.statusText == 'OK') {
						thisView.renderBrandAwareness( false );
					}
				}
			});

			this.leadGenCollection.fetch({
				type: this.ajaxType,
				data: $.param({
					type: "lead-generation",
					tnid: this.supplier.tnid,
					startDate: this.dateFrom,
					endDate: this.dateTo,
					skipTokenCheck: 1
				}),
				complete: function( response ){
					if (response.statusText == 'OK') {
						thisView.renderLeadGeneration( false );
					}		
				}
			});

			this.leadConvCollection.fetch({
				type: this.ajaxType,
				data: $.param({
					type: "lead-conversion",
					tnid: this.supplier.tnid,
					startDate: this.dateFrom,
					endDate: this.dateTo,
					skipTokenCheck: 1
				}),
				complete: function( response ){
					if (response.statusText == 'OK') {
						thisView.renderLeadConversion( false );
					}			
				}
			});

			this.notQotCollection.fetch({
				type: this.ajaxType,
				data: $.param({
					type: "not-quoted-rfq-worth",
					tnid: this.supplier.tnid,
					startDate: this.dateFrom,
					endDate: this.dateTo,
					skipTokenCheck: 1
				}),
				complete: function( response ){
					if (response.statusText == 'OK') {
						thisView.renderNotQuotedRfqWorth( false );
					}
				}
			});

			this.lostQotCollection.fetch({
				type: this.ajaxType,
				data: $.param({
					type: "qot-lost-worth",
					tnid: this.supplier.tnid,
					startDate: this.dateFrom,
					endDate: this.dateTo,
					skipTokenCheck: 1
				}),
				complete: function( response ){
					if (response.statusText == 'OK') {
						thisView.renderQotLostWorth( false );
					}	
				}
			});

			if(this.parent.comparePeriod){
				this.brandAwareCompCollection.fetch({
					type: this.ajaxType,
					data: $.param({
						type: "brand-awareness",
						tnid: this.supplier.tnid,
						startDate: this.dateFromComp,
						endDate: this.dateToComp,
						skipTokenCheck: 1
					}),
					complete: function( response ){
						if (response.statusText == 'OK') {
							thisView.renderBrandAwareness( true );
						}
					}
				});

				this.leadGenCompCollection.fetch({
					type: this.ajaxType,
					data: $.param({
						type: "lead-generation",
						tnid: this.supplier.tnid,
						startDate: this.dateFromComp,
						endDate: this.dateToComp,
						skipTokenCheck: 1
					}),
					complete: function( response ){
						if (response.statusText == 'OK') {
							thisView.renderLeadGeneration( true );
						}		
					}
				});

				this.leadConvCompCollection.fetch({
					type: this.ajaxType,
					data: $.param({
						type: "lead-conversion",
						tnid: this.supplier.tnid,
						startDate: this.dateFromComp,
						endDate: this.dateToComp,
						skipTokenCheck: 1
					}),
					complete: function( response ){
						if (response.statusText == 'OK') {
							thisView.renderLeadConversion( true );	
						}
					}
				});

				this.notQotCompCollection.fetch({
					type: this.ajaxType,
					data: $.param({
						type: "not-quoted-rfq-worth",
						tnid: this.supplier.tnid,
						startDate: this.dateFromComp,
						endDate: this.dateToComp,
						skipTokenCheck: 1
					}),
					complete: function( response ){
						if (response.statusText == 'OK') {
							thisView.renderNotQuotedRfqWorth( true );
						}		
					}
				});

				this.lostQotCompCollection.fetch({
					type: this.ajaxType,
					data: $.param({
						type: "qot-lost-worth",
						tnid: this.supplier.tnid,
						startDate: this.dateFromComp,
						endDate: this.dateToComp,
						skipTokenCheck: 1
					}),
					complete: function( response ){
						if (response.statusText == 'OK') {
							thisView.renderQotLostWorth( true );
						}	
					}
				});
			}
		},

		renderBrandAwareness: function( isComparePeriod )
		{
			var collection = (isComparePeriod) ? this.brandAwareCompCollection.models[0].attributes : this.brandAwareCollection.models[0].attributes;
			var data = {
				brand: collection,
				isComparePeriod: false,
				compPeriod: this.parent.comparePeriod,
				period: "Period: " + this.parent.filtersView.dateFromPretty + " - " + this.parent.dateToPretty,
			};
			data.brand.onboardInf = (data.brand['onboard-influencer-ex'] == "Y") ? "50,000" : "0";
			var html = this.conversionAwarenessTemplate(data);
			var tagName = (isComparePeriod) ? '#funnel-awarenessCompare' : '#funnel-awareness';
			$(tagName).html(html);
			this.afterRender();
		},

		renderLeadGeneration: function( isComparePeriod ) {
			var collection = (isComparePeriod) ? this.leadGenCompCollection.models[0].attributes : this.leadGenCollection.models[0].attributes;
			var data = {
				leadGen: collection,
				isComparePeriod: false,
				compPeriod: this.parent.comparePeriod,
				period: "Period: " + this.parent.filtersView.dateFromPretty + " - " + this.parent.dateToPretty,
			}
			var html = this.conversionleadGenTemplate(data);
			var tagName = (isComparePeriod) ? '#funnel-leadGenCompare' : '#funnel-leadGen';
			$(tagName).html(html);
			this.afterRender();
		},

		renderLeadConversion: function( isComparePeriod ) {
			var lostCollection = (isComparePeriod) ? this.lostQotCompCollection : this.lostQotCollection;
			var convCollection = (isComparePeriod) ? this.leadConvCompCollection : this.leadConvCollection;

			if (lostCollection.models[0] && convCollection.models[0]) {
				var data = {
					conversion: convCollection.models[0].attributes,
					isComparePeriod: false,
					compPeriod: this.parent.comparePeriod,
					period: "Period: " + this.parent.filtersView.dateFromPretty + " - " + this.parent.dateToPretty
				}
				data.conversion.totalPoVal = data.conversion['direct-po-value'] + data.conversion['po-value'];
				data.conversion.qotLostRate = 100 - data.conversion['qot-rate'];

				if(data.conversion['qot-count'] > 0){
					data.conversion.winLostRate = (lostCollection.models[0].attributes.count / data.conversion['qot-count']) * 100;
				}
				else {
					data.conversion.winLostRate = 0;
				}

				if(data.conversion['qot-count'] > 0) {
					data.conversion.pendingRate = 100 - (data.conversion['win-rate'] + data.conversion.winLostRate);
					data.conversion.pendingRate = data.conversion.pendingRate.toFixed(0);
				}
				else {
					data.conversion.pendingRate = 0;
				}

				var html = this.conversionleadConvTemplate(data);
				var tagName = (isComparePeriod) ? '#funnel-leadConvCompare' : '#funnel-leadConv';
				$(tagName).html(html);
				this.setStyles(data);
				this.renderRedBoxes( isComparePeriod);	
				this.afterRender();
			}
		},

		renderNotQuotedRfqWorth: function( isComparePeriod ) {
			this.renderRedBoxes( isComparePeriod );
		},

		/*
			The lead conversation and the red boxes are depending from each other, here we make sure, all of them are loaded
		*/
		renderQotLostWorth: function( isComparePeriod ) {
			this.renderLeadConversion( isComparePeriod );
			this.renderRedBoxes( isComparePeriod );
		},

		renderRedBoxes: function( isComparePeriod )
		{
			var notQotCollection = (isComparePeriod) ? this.notQotCompCollection : this.notQotCollection;
			var lostQotCollection = (isComparePeriod) ? this.lostQotCompCollection : this.lostQotCollection;
			var leadConvCollection = (isComparePeriod) ? this.leadConvCompCollection : this.leadConvCollection;

			if (notQotCollection.models[0] && lostQotCollection.models[0] && leadConvCollection.models[0]) {
				var data = {
						conversion: leadConvCollection.models[0].attributes,
						notQot: notQotCollection.models[0].attributes,
						lostQot: lostQotCollection.models[0].attributes,
						isComparePeriod: false,
						compPeriod: this.parent.comparePeriod,
						period: "Period: " + this.parent.filtersView.dateFromPretty + " - " + this.parent.dateToPretty,
					}

				data.notQot.count = data.notQot['unactioned-rfq-worth'].count + data.notQot['declined-rfq-worth'].count;
				data.notQot.worth = data.notQot['unactioned-rfq-worth'].worth + data.notQot['declined-rfq-worth'].worth;
			    data.undefQotCount = leadConvCollection.models[0].attributes['qot-count'] - (leadConvCollection.models[0].attributes['po-count'] + data.lostQot.count); 

			    if(data.lostQot.count > 1){
			    	data.qotLostTrue = true;
			    	if(this.supplier.premiumListing !=1 && this.supplier.smartSupplier !=1 && this.supplier.expertSupplier !=1){
						$('.button.improve.win').show();
					}
			    }

			    if(data.notQot.count > 1){
			    	data.notQuotTrue = true;
			    	if(this.supplier.premiumListing != 1 && this.supplier.smartSupplier != 1 && this.supplier.expertSupplier != 1){
						$('.button.improve.qot').show();
					}
			    }

			    if(data.lostQot.count > 1 || data.notQot.count > 1){
			    	data.redBoxes = true;
			    }
			    else {
			    	data.redBoxes = false;
			    }

				var html = this.conversionRedBoxesTemplate(data);

				var tagName = (isComparePeriod) ? '#funnel-redboxesCompare' : '#funnel-redboxes';
				$(tagName).html(html);

				this.afterRender();
			}
		},

		render: function(){
			/* Deprecated, replaced by separate renders for each views */
		},

		isAllLoaded: function()
		{
			var loaded = (this.brandAwareCollection.models[0] && this.leadGenCollection.models[0] && this.leadConvCollection.models[0] && this.notQotCollection.models[0] && this.lostQotCollection.models[0]);
			if(this.parent.comparePeriod){
				return (this.brandAwareCompCollection.models[0] && this.leadGenCompCollection.models[0] && this.leadConvCompCollection.models[0] && this.notQotCompCollection.models[0] && this.lostQotCompCollection.models[0] && loaded);
			} else {
				return loaded;
			}
		},

		/*
			We need to do some changes, if all requests are sucessfully loaded
		*/
		afterRender: function()
		{
			if (this.isAllLoaded()) {
				/*
				TODO fix, this part called 3 times
				*/
				$('.toShrink').textfill();
				$('.worth.noQot').css('font-size', $('.toShrink span').css('font-size'));

				/*$('.totalDirect').hover(
					function(){
						$('.dpoTable').show();
					}, 
					function(){
						$('.dpoTable').hide();
					}
				);*/
			}
		},

		setStyles: function(data){
			this.setQotRate(data);
			this.setWinRate(data);
		},

		setQotRate: function(data){

			if(data.conversion['qot-rate'] == 0){
				$('.quote.rate .convertedBox').height(1);
				$('.quote.rate .convertedBox').addClass('low');
				$('.quote.rate .lostBox').height(99);
			}
			else {
				$('.quote.rate .convertedBox').height(data.conversion['qot-rate']);
				$('.quote.rate .lostBox').height(data.conversion.qotLostRate);
			}
			if(data.conversion['qot-rate'] < 28){
				$('.quote.rate .convertedBox').addClass('low');
			}
			if(data.conversion.qotLostRate < 28){
				$('.quote.rate .lostBox .value').addClass('low');
			}
		},

		setWinRate: function(data){
			if(data.conversion['win-rate'] == 0){
				$('.win.rate .convertedBox').height(1);
				$('.win.rate .convertedBox').addClass('low');
				$('.win.rate .lostBox').height(99);
				$('.win.rate .lostBox .value').addClass('low');
			}
			else {
				$('.win.rate .convertedBox').height(data.conversion['win-rate']);
				$('.win.rate .lostBox').height(data.conversion.winLostRate);
			}
			if(data.conversion['win-rate'] < 28){
				$('.win.rate .convertedBox').addClass('low');
			}
			if(data.conversion.winLostRate < 28){
				$('.win.rate .lostBox .value').addClass('low');
			}
		},

		dateConvert: function( dateString ) 
		{
			if(dateString){
				var monthNames = new Array('JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC');
			
				if (dateString.length === 8) {
					var parts = new Array(dateString.substr(6,2),dateString.substr(4,2),dateString.substr(0,4));
				} else {	
					var parts = dateString.split("/");
				}

				resultString = 
					parseInt(parts[0], 10) + '-' +
					monthNames[parseInt(parts[1], 10) - 1] + '-' +
					parseInt(parts[2], 10);
					return resultString;
			}
			else {
				return;
			}
		},

		showTooltip: function() {

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
        }
	});

	return conversionView;
});