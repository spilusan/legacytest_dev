define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'../collections/supplierInsightDataCollection',
	'text!templates/reports/sir/tpl/analysis.html',
	'text!templates/reports/sir/tpl/poValueByBuyer.html',
	'text!templates/reports/sir/tpl/analAvarageQuote.html',
	'text!templates/reports/sir/tpl/analPriceSensitivity.html',
	'text!templates/reports/sir/tpl/analTimeToQuote.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	supplierInsightData,
	analysisMainView,
	poValueView,
	analAvarageQuoteView,
	analPriceSensitivityView,
	analTimeToQuoteView
){
	var analysisView = Backbone.View.extend({

		el: $('body'),
		analysisTemplate: Handlebars.compile(analysisMainView),
		poTemplate: Handlebars.compile(poValueView),
		analAvarageQuoteTemplate: Handlebars.compile(analAvarageQuoteView),
		analPriceSensitivityTemplate: Handlebars.compile(analPriceSensitivityView),
		analTimeToQuoteTemplate: Handlebars.compile(analTimeToQuoteView),

		events: {
		//	'click #poDetails'   : 'scrollPoDetails', 
			'click #poDetails'   : 'poDetailsClick' 
		},

		initialize: function () {
			this.poValueByBuyerCollection = new supplierInsightData();
			this.avgQuoteCollection = new supplierInsightData();
			this.qotTimePriceSensitiveCollection = new supplierInsightData();
			/* Use different collections for compare period, as the call can be asynch */
			this.poValueByBuyerCompareCollection = new supplierInsightData();
			this.avgQuoteCompareCollection = new supplierInsightData();
			this.qotTimePriceSensitiveCompareCollection = new supplierInsightData();
	   },

	   /*
	   * Entry point, to load data form backend, and render 
	   */
		getData: function( isCompare ) {

			if (isCompare) {
				var dFrom = this.parent.dateConvertToShortMonthFormat(this.parent.dateFromComp);
				var dTo = this.parent.dateConvertToShortMonthFormat(this.parent.dateToComp);
			} else {
				var dFrom = this.parent.dateConvertToShortMonthFormat(this.parent.dateFrom);
				var dTo = this.parent.dateConvertToShortMonthFormat(this.parent.dateTo);
			}

			var data = {isCompare:isCompare};
			var html = this.analysisTemplate(data);
			if (isCompare) {
				$('#sir-analysisCompare').html(html);
			} else {
				$('#sir-analysis').html(html);
			}
			
			/* The next call is for render block with spinners on */
			this.preRender(isCompare);
			this.preRenderQotTimePriceSensitive(isCompare);
			this.preRenderAnalAvarageQuote(isCompare);

			this.getPoDataData(isCompare, dFrom, dTo);
			this.getAvgQuoteData(isCompare, dFrom, dTo);
			this.getQotTimePriceSensitiveData(isCompare, dFrom, dTo);

		},

		render: function( isCompare ) {
			var thisView = this;
			var collection = (isCompare) ? this.poValueByBuyerCompareCollection : this.poValueByBuyerCollection;
			if (collection.models[0]) {
				if (isCompare) {
					var dFrom = new Date(this.dateStrToDate(this.parent.dateFrom));
					var dTo = new Date(this.dateStrToDate(this.parent.dateTo));
					var dFromComp = new Date(this.dateStrToDate(this.parent.dateFromComp));
					var dToComp = new Date(this.dateStrToDate(this.parent.dateFrom));
					var from = $.format.date(dFrom, "dd/MM/yyyy");
					var to = $.format.date(dTo, "dd/MM/yyyy");
					var fromComp = $.format.date(dFromComp, "dd/MM/yyyy");
					var toComp = $.format.date(dToComp, "dd/MM/yyyy");

				} else {
					var dFrom = new Date(this.dateStrToDate(this.parent.dateFrom));
					var dTo = new Date(this.dateStrToDate(this.parent.dateTo));
					var dFromComp = new Date(this.dateStrToDate(this.parent.dateFrom));
					var dToComp = new Date(this.dateStrToDate(this.parent.dateFrom));
					var diffDays = parseInt((dTo - dFrom) / (1000 * 60 * 60 * 24)); 
					dFromComp.setDate(dFromComp.getDate()-1-diffDays);
					dToComp.setDate(dToComp.getDate()-1);
					var from = $.format.date(dFrom, "dd/MM/yyyy");
					var to = $.format.date(dTo, "dd/MM/yyyy");
					var fromComp = $.format.date(dFromComp, "dd/MM/yyyy");
					var toComp = $.format.date(dToComp, "dd/MM/yyyy");
				}

				var data = {
					collection: collection.models[0],
					dateFrom: from,
					dateTo: to,
					comparePeriod: this.parent.comparePeriod,
					dateFromComp: fromComp,
					dateToComp: toComp,
				};

				var html = thisView.poTemplate(data);
				if (isCompare) {
					$('#poValueContentCompare').html(html);
				} else {
					$('#poValueContent').html(html);
				}

				$('#poTableScrollTable').click(this.resetPoDetailsScroll);
			}
		},


		/*
		* Render the PO data template with spinners on 
		*/
		preRender: function( isCompare ) {
			var thisView = this;

				if (isCompare) {
					var dFrom = new Date(this.dateStrToDate(this.parent.dateFrom));
					var dTo = new Date(this.dateStrToDate(this.parent.dateTo));
					var dFromComp = new Date(this.dateStrToDate(this.parent.dateFromComp));
					var dToComp = new Date(this.dateStrToDate(this.parent.dateFrom));
					var from = $.format.date(dFrom, "dd/MM/yyyy");
					var to = $.format.date(dTo, "dd/MM/yyyy");
					var fromComp = $.format.date(dFromComp, "dd/MM/yyyy");
					var toComp = $.format.date(dToComp, "dd/MM/yyyy");

				} else {
					var dFrom = new Date(this.dateStrToDate(this.parent.dateFrom));
					var dTo = new Date(this.dateStrToDate(this.parent.dateTo));
					var dFromComp = new Date(this.dateStrToDate(this.parent.dateFrom));
					var dToComp = new Date(this.dateStrToDate(this.parent.dateFrom));
					var diffDays = parseInt((dTo - dFrom) / (1000 * 60 * 60 * 24)); 
					dFromComp.setDate(dFromComp.getDate()-1-diffDays);
					dToComp.setDate(dToComp.getDate()-1);
					var from = $.format.date(dFrom, "dd/MM/yyyy");
					var to = $.format.date(dTo, "dd/MM/yyyy");
					var fromComp = $.format.date(dFromComp, "dd/MM/yyyy");
					var toComp = $.format.date(dToComp, "dd/MM/yyyy");
				}

				var data = {
					hasSpinner: true,
					collection: [],
					dateFrom: from,
					dateTo: to,
					comparePeriod: this.parent.comparePeriod,
					dateFromComp: fromComp,
					dateToComp: toComp,
				};

				var html = thisView.poTemplate(data);
				if (isCompare) {
					$('#poValueContentCompare').html(html);
				} else {
					$('#poValueContent').html(html);
				}

		},

		/*
		* Render the win/Loss data template with spinners on 
		*/
		preRenderQotTimePriceSensitive: function( isCompare )
		{
			
				var allow = (this.parent.isShipmate == 1 || this.parent.supplier.premiumListing == 1) ? 1 : 0;

				var qHtml = this.analTimeToQuoteTemplate({attributes:null, hasSpinner: true, premium: allow});
				var aHtml = this.analPriceSensitivityTemplate({attributes:null, hasSpinner: true,  premium: allow});	
				
				if (isCompare) {
					$('#timeToQuoteCompare').html(qHtml);
					$('#analPriceSensitivityCompare').html(aHtml);
				} else {
					$('#timeToQuote').html(qHtml);
					$('#analPriceSensitivity').html(aHtml);
				}

		},

		/*
		* Render the win/Loss data template with spinners on 
		*/
		preRenderAnalAvarageQuote: function( isCompare )
		{
			var allow = (this.parent.isShipmate == 1 || this.parent.supplier.premiumListing == 1) ? 1 : 0;
				var html = this.analAvarageQuoteTemplate({attributes:null, hasSpinner: true, premium: allow});
				if (isCompare) {
					$('#analAgarageQuoteTimeCompare').html(html);
				} else {
					$('#analAgarageQuoteTime').html(html);
				}
		},


		/*
		* Render the win/Loss data
		*/
		renderQotTimePriceSensitive: function( isCompare, dFrom, dTo )
		{
			var collection = (isCompare) ? this.qotTimePriceSensitiveCompareCollection : this.qotTimePriceSensitiveCollection;
			if (collection.models[0]) {
					var data = collection.models[0];
					data.isCompare = isCompare;
					var allow = (this.parent.isShipmate == 1 || this.parent.supplier.premiumListing == 1) ? 1 : 0;
					data.premium = allow;
					var qHtml = this.analTimeToQuoteTemplate(data);
					var aHtml = this.analPriceSensitivityTemplate(data);
				} else {
					var qHtml = this.analTimeToQuoteTemplate({attributes:null, premium: allow});
					var aHtml = this.analPriceSensitivityTemplate({attributes:null, premium: allow});	
				}
				if (isCompare) {
					$('#timeToQuoteCompare').html(qHtml);
					$('#analPriceSensitivityCompare').html(aHtml);
				} else {
					$('#timeToQuote').html(qHtml);
					$('#analPriceSensitivity').html(aHtml);
				}
				if (allow == 1) {
					this.getQuickestCheapestQotWonBySupplier(isCompare, dFrom, dTo );
					this.getQuickestCheapestQotWonByAllSupplier(isCompare, dFrom, dTo );
				}
		},

		renderAnalAvarageQuote: function( isCompare, dFrom, dTo )
		{
			var collection = (isCompare) ? this.avgQuoteCompareCollection : this.avgQuoteCollection;
			if (collection.models[0]) {
				var data = collection.models[0];
				data.isCompare = isCompare;
				
				var allow = (this.parent.isShipmate == 1 || this.parent.supplier.premiumListing == 1) ? 1 : 0;
				data.premium = allow;
				var html = this.analAvarageQuoteTemplate(data);
				if (isCompare) {
					$('#analAgarageQuoteTimeCompare').html(html);
				} else {
					$('#analAgarageQuoteTime').html(html);
				}

				this.calculateProgressBars(); 
				if (allow == 1) {
					this.aveQotTimeByCompetitors(isCompare, dFrom, dTo );
				}

				//hint window
				$('.quote-indicator').hover(
					function() {
						$(this).children().first().next().next().slideDown('slow');
					},
					function() {
						$('.htmlHintBox').slideUp();
					}
				);
			} 
		},

		/*
		* After win/los section is loaded, we need to adjust the progress inticator bars, It is automatically done here, we do not need to set it in the htmp template
		*/
		calculateProgressBars_Old: function()
		{
			/* After rednering, adjust the indicators */
			$('.quoteValue').each(function() {
				var quoteValue = parseInt($(this).html());
				var sliderElement = $(this).next().children().first();
				var sliderBg = sliderElement.children().first(); 
				var sliderIndicator = sliderBg.next(); 
				var fullWidth = sliderBg.width();
				var indicatorWidth = Math.round(quoteValue / 100 * (fullWidth-1)) ;
				sliderIndicator.css('width',indicatorWidth+'px');
			});

		},

		calculateProgressBars: function()
		{
			if (this.avgQuoteCollection.models[0]) {

			_.each(this.avgQuoteCollection.models[0].attributes['ave-time-periods'], function(item){
					var allCount = item['won-count']+item['lost-count'];
					if (allCount != 0) {
						var indicatorPc = Math.round(item['won-count'] / allCount * 100);
						var indicatorWidth = $('.ind_'+item['won-count']+'_'+item['lost-count']).prev().width();

						var correctedWidth =indicatorWidth * indicatorPc / 100;


						$('.ind_'+item['won-count']+'_'+item['lost-count']).css('width',correctedWidth+'px');
					}
				}, this);
			}

			if (this.avgQuoteCompareCollection.models[0]) {

			_.each(this.avgQuoteCompareCollection.models[0].attributes['ave-time-periods'], function(item){
					var allCount = item['won-count']+item['lost-count'];
					if (allCount != 0) {
						var indicatorPc = Math.round(item['won-count'] / allCount * 100);
						var indicatorWidth = $('.ind_'+item['won-count']+'_'+item['lost-count']).prev().width();

						var correctedWidth =indicatorWidth * indicatorPc / 100;


						$('.ind_'+item['won-count']+'_'+item['lost-count']).css('width',correctedWidth+'px');
					}
				}, this);
			}
		},

		/*
		*	This is the actual JSON request, 
		*/
		getPoDataData: function( isCompare, dFrom, dTo )
		{
			var thisView = this;
			var collection = (isCompare) ? this.poValueByBuyerCompareCollection : this.poValueByBuyerCollection;
			collection.reset();

				if (isCompare) {
					var dFrom = new Date(this.dateStrToDate(this.parent.dateFrom));
					var dTo = new Date(this.dateStrToDate(this.parent.dateTo));
					var dFromComp = new Date(this.dateStrToDate(this.parent.dateFromComp));
					var dToComp = new Date(this.dateStrToDate(this.parent.dateToComp));
					var from = $.format.date(dFrom, "yyyyMMdd");
					var to = $.format.date(dTo, "yyyyMMdd");
					var fromComp = $.format.date(dFromComp, "yyyyMMdd");
					var toComp = $.format.date(dToComp, "yyyyMMdd");

				} else {
					var dFrom = new Date(this.dateStrToDate(this.parent.dateFrom));
					var dTo = new Date(this.dateStrToDate(this.parent.dateTo));
					var dFromComp = new Date(this.dateStrToDate(this.parent.dateFrom));
					var dToComp = new Date(this.dateStrToDate(this.parent.dateFrom));
					var diffDays = parseInt((dTo - dFrom) / (1000 * 60 * 60 * 24)); 
					dFromComp.setDate(dFromComp.getDate()-1-diffDays);
					dToComp.setDate(dToComp.getDate()-1);
					var from = $.format.date(dFrom, "yyyyMMdd");
					var to = $.format.date(dTo, "yyyyMMdd");
					var fromComp = $.format.date(dFromComp, "yyyyMMdd");
					var toComp = $.format.date(dToComp, "yyyyMMdd");
				}
			
			var param = {
				tnid: this.parent.supplier.tnid,
				type: 'po-value-by-buyer',
				startDate1: this.parent.dateConvertToShortMonthFormat(from),
				endDate1: this.parent.dateConvertToShortMonthFormat(to),
				startDate2: this.parent.dateConvertToShortMonthFormat(fromComp),
				endDate2: this.parent.dateConvertToShortMonthFormat(toComp),
				skipTokenCheck: 1,
			};

			collection.fetch({
			type: this.ajaxType,
			data: $.param(param),
				complete: function(){
					thisView.render(isCompare, dFrom, dTo);
				},
				error: function( model, response, options ) {
					if (response.responseText) {
						var errorObj = $.parseJSON(response.responseText);
					}
				}
			});			

		},

		getAvgQuoteData: function( isCompare, dFrom, dTo )
		{

			var collection = (isCompare) ? this.avgQuoteCompareCollection : this.avgQuoteCollection;
			collection.reset();
			var thisView = this;
			
			collection.fetch({
			type: this.ajaxType,
			data: $.param({
				tnid: this.parent.supplier.tnid,
				type: 'ave-qot-time',
				startDate: dFrom,
				endDate: dTo,
				skipTokenCheck: 1,
				}),
				complete: function(){
					thisView.renderAnalAvarageQuote(isCompare, dFrom, dTo);
				},
				error: function( model, response, options ) {
					if (response.responseText) {
						var errorObj = $.parseJSON(response.responseText);
					}
				}
			});	
		},

		getQotTimePriceSensitiveData: function( isCompare, dFrom, dTo )
		{
			var collection = (isCompare) ? this.qotTimePriceSensitiveCompareCollection: this.qotTimePriceSensitiveCollection;
		 	collection.reset();
			var thisView = this;
			
			collection.fetch({
			type: this.ajaxType,
			data: $.param({
				tnid: this.parent.supplier.tnid,
				/* type: 'qot-time-price-sensitive', */
				type: 'quickest-cheapest-qot-won-all-suppliers',
				startDate: dFrom,
				endDate: dTo,
				skipTokenCheck: 1,
				}),
				complete: function(){
					thisView.renderQotTimePriceSensitive(isCompare, dFrom, dTo);
				},
				error: function( model, response, options ) {
					if (response.responseText) {
						var errorObj = $.parseJSON(response.responseText);
					}
				}
			});	
		},

		getQuickestCheapestQotWonBySupplier: function( isCompare, dFrom, dTo)
		{
			var collection = new supplierInsightData();
			
			collection.fetch({
			type: this.ajaxType,
			data: $.param({
				tnid: this.parent.supplier.tnid, 
				type: 'quickest-cheapest-qot-won-by-supplier', // it may be //quickest-cheapest-qot-won-all-suppliers
				startDate: dFrom,
				endDate: dTo,
				skipTokenCheck: 1,
				}),
				complete: function(){
					if (collection.models[0]) {
						if (isCompare) {
							$('#quickestQuoteCompare').html(Math.round(collection.models[0].attributes['quickest-qot-won-pct'],0)+"%");
							$('#cheapestQuoteCompare').html(Math.round(collection.models[0].attributes['cheapest-qot-won-pct'],0)+"%");
						} else {
							$('#quickestQuote').html(Math.round(collection.models[0].attributes['quickest-qot-won-pct'],0)+"%");
							$('#cheapestQuote').html(Math.round(collection.models[0].attributes['cheapest-qot-won-pct'],0)+"%");
						}
					}
					
				},
				error: function( model, response, options ) {
					if (response.responseText) {
						var errorObj = $.parseJSON(response.responseText);
					}
				}
			});	
		},

		getQuickestCheapestQotWonByAllSupplier: function( isCompare, dFrom, dTo)
		{
			var collection = new supplierInsightData();
			
			collection.fetch({
			type: this.ajaxType,
			data: $.param({
				tnid: this.parent.supplier.tnid, 
				type: 'how-quickest-cheapest-supplier-quotes', 
				startDate: dFrom,
				endDate: dTo,
				skipTokenCheck: 1,
				}),
				complete: function(){

					if (collection.models[0]) {
						if (isCompare) {
							 $('#quickestQuoteFreqCompare').html(Math.round(collection.models[0].attributes['quickest-qot-pct'],1)+"%");
							 $('#lowestQouteFreqCompare').html(Math.round(collection.models[0].attributes['cheapest-qot-pct'],1)+"%");
						} else {
							$('#quickestQuoteFreq').html(Math.round(collection.models[0].attributes['quickest-qot-pct'],1)+"%");
							 $('#lowestQouteFreq').html(Math.round(collection.models[0].attributes['cheapest-qot-pct'],1)+"%");
						}
					}
					
				},
				error: function( model, response, options ) {
					if (response.responseText) {
						var errorObj = $.parseJSON(response.responseText);
					}
				}
			});	
		},

		aveQotTimeByCompetitors: function( isCompare, dFrom, dTo)
		{
			var collection = new supplierInsightData();
			collection.fetch({
			type: this.ajaxType,
			data: $.param({
				tnid: this.parent.supplier.tnid, 
				type: 'ave-qot-time-by-competitors', 
				startDate: dFrom,
				endDate: dTo,
				skipTokenCheck: 1,
				}),
				complete: function(){
					if (collection.models[0]) {
						var roundedValue = collection.models[0].attributes['competitors-qot-ave'];
						if (roundedValue) {
							if (isCompare) {
								$('#otherSupplierQuotesCompare').html(roundedValue.toFixed(1).replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,")+" hrs");
							} else {
								$('#otherSupplierQuotes').html(roundedValue.toFixed(1).replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,")+" hrs");
							}
						} else {
							if (isCompare) {
								$('#otherSupplierQuotesCompare').html("0 hrs");
							} else {
								$('#otherSupplierQuotes').html("0 hrs");

							}

						}
						}
					
				},
				error: function( model, response, options ) {
					if (response.responseText) {
						var errorObj = $.parseJSON(response.responseText);
					}
				}
			});	

		},
		

		scrollPoDetails: function(e)
		{
			e.preventDefault();
			var maxScroll = $('#poTableScrollTable').height();

			var newPos = $('#scrollWrapper').scrollTop() +100;
			if (newPos > maxScroll) {
				newPos = maxScroll;
			}
			$("#scrollWrapper").animate({ scrollTop: newPos+"px" });
			//$('#scrollWrapper').scrollTop($('#scrollWrapper').scrollTop() +10 );
		},

		resetPoDetailsScroll: function()
		{
			$("#scrollWrapper").animate({ scrollTop: "0px" });
		},
		
		ukDate: function( dateString )
		{
			if (typeof(dateString) == 'string') {
				return dateString.substr(6,2)+"/"+dateString.substr(4,2)+"/"+dateString.substr(0,4);
			} else {
				return '';
			}
		},

		dateStrToDate: function( dateString )
		{
			if (typeof(dateString) == 'string') {
				return new Date(parseInt(dateString.substr(0,4)),parseInt(dateString.substr(4,2))-1,parseInt(dateString.substr(6,2)));
			} else {
				return '';
			}
		},

		poDetailsClick: function(e)
		{
			e.preventDefault();
			this.parent.renderCustomer();
		}


	});

	return analysisView;
});
