	define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'libs/jquery.validity.min',
    'libs/jquery.validity.custom.output.sir',
     'jqueryui/datepicker',
	'libs/waypoints/waypoints-sticky',
	'../collections/supplierInsightDataCollection',
	'../../gmv/collections/gmvList',
	'../../gmv/views/newGmvRowView',
	'text!templates/reports/sir/tpl/transaction.html',
	'text!templates/reports/sir/tpl/transactionMessage.html',
	'text!templates/reports/sir/tpl/transactionSummary.html',
	'text!templates/reports/sir/tpl/unactionedBox.html'

], function(
	$, 
	_, 
	Backbone,
	Hb,
	validity,
	validityCustom,
	datePicker,
	Sticky,
	supplierInsightData,
	gmvList,
	gmvRowView,
	transactionTpl,
	transactionMessageTpl,
	transactionSummaryTpl,
	unactionedBoxTpl
){
	var transactionView = Backbone.View.extend({

		el: $('body'),
		transactionTemplate: Handlebars.compile(transactionTpl), 
		transactionSummaryTemplate: Handlebars.compile(transactionSummaryTpl),
		transactionMessageTemplate: Handlebars.compile(transactionMessageTpl),
		unactionedBoxTemplate: Handlebars.compile(unactionedBoxTpl),
		dateFrom: '',
		dateTo: '',
		dateFromPretty: '',
		dateToPretty: '',
		dateFromComp: '',
		dateToComp: '',
		dateFromCompPretty: '',
		dateToCompPretty: '',
		isCompare: false,
		tnid: null,

		events: {
			'click a.expandAll'   : 'toggleAll',
			'click a.collapseAll' : 'toggleAll',
			'click a.expandSel'   : 'toggleSel',
			'click a.collapseSel'   : 'toggleSel',
			'click input.transactionsApply' : 'applyFilters',
			'click .transactionComp' : 'toggleComp',
			'click .billPeriodSel' : 'togglePeriodDetails',
		},

		initialize: function () {

			this.pagesStatsCollection = new supplierInsightData();
			this.tradingStatsCollection = new supplierInsightData();
			this.comparePagesStatsCollection = new supplierInsightData();
			this.compareTradingStatsCollection = new supplierInsightData();

			this.collection = new supplierInsightData();
			/* Initalize default dates */

			this.supplierCollection = new supplierInsightData();
			this.supplierCollection.url = "/reports/api/supplier";

			this.initalizeDates();

			//Helper for month selector
			Handlebars.registerHelper('monthSelectorOptions', function(options) {
				var optionTag = '';
				var currentDate = new Date();
				var currentYear =  currentDate.getFullYear();
				var currentMonth = currentDate.getMonth();
				var month = new Array('January','February','March','April','May','June','July','August','September','October','November','December');
				optionTag += '<option value="1-13-now">1 to 12 months back from now</option>';
				optionTag += '<option value="13-25-now">13-24 months back from now</option>';
				for (y = currentYear ; y>currentYear-2;y--)
				{
					while (currentMonth >= 0) {
						optionTag += '<option value="'+(currentMonth+1)+'-'+y+'">'+y+' '+month[currentMonth]+'</option>';
						currentMonth--;
					}
					currentMonth = 11;
				}
			return optionTag;
			});
	   },

	   initalizeDates: function()
	   {
	   		this.isCompare = false;
	   		var today = new Date();
			var rangeStart = new Date(today.getFullYear(),today.getMonth()-1,1);
			var rangeEnd = new Date(today.getFullYear(),today.getMonth(),0);
			this.dateFrom = $.format.date(rangeStart, "yyyyMMdd");
			this.dateTo = $.format.date(rangeEnd, "yyyyMMdd");
			this.dateFromPretty = $.format.date(rangeStart, "dd MMM yyyy");
			this.dateToPretty = $.format.date(rangeEnd, "dd MMM yyyy");
			this.supplierCollection.reset();
			this.pagesStatsCollection.reset();
			this.tradingStatsCollection.reset();
			this.comparePagesStatsCollection.reset();
			this.compareTradingStatsCollection.reset();
	   },

	   /**
	   * Get  all data for the header
	   */
	   getDataRange: function()
	   {
	   		/* We have to reset all collections, except gmv at once, to be able to check if all are loaded */

			this.pagesStatsCollection.reset();
			this.tradingStatsCollection.reset();
			this.comparePagesStatsCollection.reset();
			this.compareTradingStatsCollection.reset();
			/* Load all json, for the period */

			this.getCollection('pages-stats',  this.pagesStatsCollection, this.dateFrom, this.dateTo);
			this.getCollection('trading-stats',  this.tradingStatsCollection, this.dateFrom, this.dateTo);

			/* Load jsons for compare period, only if compare checkbox is ticked */
			if (this.isCompare) {
				this.getCollection('pages-stats',  this.comparePagesStatsCollection, this.dateFromComp, this.dateToComp);
				this.getCollection('trading-stats',  this.compareTradingStatsCollection, this.dateFromComp, this.dateToComp);
			}
	   },

	   /**
	   * General function for backend call, where the calls are similar
	   */
	   getCollection: function( collectionType, collection, dateFrom, dateTo )
	   {
			
			var thisView = this;

			collection.fetch({
			type: this.ajaxType,
			data: $.param({
				tnid: this.parent.supplier.tnid,
				type: collectionType,
				startDate: this.parent.dateConvertToShortMonthFormat(dateFrom),
				endDate: this.parent.dateConvertToShortMonthFormat(dateTo),
				skipTokenCheck: 1,
				}),
				complete: function(){
					thisView.render();
				},
				error: function( model, response, options ) {
					if (response.responseText) {
						var errorObj = $.parseJSON(response.responseText);
					}
				}
			});	
	   },

	   /**
	   * If all collections are fetced, creata an object for the view, and load
	   */
	   render: function()
	   {

		var loaded = false;
		var thisView = this;
		var templateData = {};
	   	if (this.isCompare) {
	   		if (this.pagesStatsCollection.models[0]  && this.tradingStatsCollection.models[0]  && this.comparePagesStatsCollection.models[0]  && this.compareTradingStatsCollection.models[0] ) {
					templateData.comparePeriod = this.isCompare;
					templateData.dateFromPretty = this.dateFromPretty;
					templateData.dateToPretty = this.dateToPretty;
					templateData.dateFromCompPretty = this.dateFromCompPretty;
					templateData.dateToCompPretty = this.dateToCompPretty;
					templateData.dateFrom = this.ukDate(this.dateFrom);
					templateData.dateTo = this.ukDate(this.dateTo);
					templateData.dateFromComp = this.ukDate(this.dateFromComp);
					templateData.dateToComp = this.ukDate(this.dateToComp);

					/* templateData.gmvChange = this.getChange(1,1); //Removed line, */
					templateData.gmvChange = this.getChange(this.tradingStatsCollection.models[0].attributes['po-total-value']['count'],this.compareTradingStatsCollection.models[0].attributes['po-total-value']['count']);
					templateData.customersChange = this.getChange(this.tradingStatsCollection.models[0].attributes['customer-count'],this.compareTradingStatsCollection.models[0].attributes['customer-count']);
					templateData.poChange = this.getChange(this.tradingStatsCollection.models[0].attributes['PO']['count'],this.compareTradingStatsCollection.models[0].attributes['PO']['count']);
					templateData.quoteChange = this.getChange(this.tradingStatsCollection.models[0].attributes['QOT']['count'],this.compareTradingStatsCollection.models[0].attributes['QOT']['count']);
					templateData.rfqChange = this.getChange(this.tradingStatsCollection.models[0].attributes['RFQ']['count'],this.compareTradingStatsCollection.models[0].attributes['RFQ']['count']);
					templateData.targetImpression = this.getChange(this.pagesStatsCollection.models[0].attributes['targeted-impressions']['count'], this.comparePagesStatsCollection.models[0].attributes['targeted-impressions']['count']); 
					templateData.unactionedChange = this.getChange(this.pagesStatsCollection.models[0].attributes['unactioned-rfq']['count'], this.comparePagesStatsCollection.models[0].attributes['unactioned-rfq']['count']);
					templateData.contactViewChange = this.getChange(this.pagesStatsCollection.models[0].attributes['unique-contact-view']['count'], this.comparePagesStatsCollection.models[0].attributes['unique-contact-view']['count']);

					/* Values for the view */
					templateData.pagesStatsCollection = this.pagesStatsCollection.models[0].attributes;
					templateData.tradingStatsCollection = this.tradingStatsCollection.models[0].attributes;
					templateData.comparePagesStatsCollection = this.comparePagesStatsCollection.models[0].attributes;
					templateData.compareTradingStatsCollection = this.compareTradingStatsCollection.models[0].attributes;

					loaded = true;
		   		}
	   	} else {
  			if (this.pagesStatsCollection.models[0]  && this.tradingStatsCollection.models[0] ) {

					templateData.comparePeriod = this.isCompare;
					templateData.dateFromPretty = this.dateFromPretty;
					templateData.dateToPretty = this.dateToPretty;
					templateData.dateFrom = this.ukDate(this.dateFrom);
					templateData.dateTo = this.ukDate(this.dateTo);

					templateData.pagesStatsCollection = this.pagesStatsCollection.models[0].attributes;
					templateData.tradingStatsCollection = this.tradingStatsCollection.models[0].attributes;

					loaded = true;

	   			}   		
	   		}
	   		if (loaded) {
	   			var html = thisView.transactionSummaryTemplate(templateData);
				$('#transactionSummary').html(html);
				$('div.filterContent select').uniform();
				$('input.date').datepicker({ 
					autoSize: false,
					dateFormat: 'dd/mm/yy'
				});
				$('body').delegate('.quickSelTw', 'change', function(e){
					thisView.quickSelect(e);
				});
			}
	   },

	   /*
	   * Generate a % value for the period changes, avoiding zero divison, and return the class for the html (Arrow color, direction)
	   * @return object
	   */
		getChange: function(value1, value2)
        {
        	//calculage canges, handle zero division
        	var result = {};
        	result.arrowClass = '';
        	result.fontColor = 'green';
        	result.value = '';

        	var val1 = parseFloat(value1);
        	var val2 = parseFloat(value2);

        	if (val2 > val1) {
	        	if (val2 !== 0) {
	        		result.value = -Math.round(100-(val1/val2*100));
	        	} 
        	} else {
        		if (val1 !== 0) {
	        		result.value = Math.round(100-(val2/val1*100));
	        	} 
        	}

        	if (result.value !== '') {
        		if (result.value>0) {
        			result.arrowClass = 'arrow-green-up';
        			result.fontColor = 'green';
        		} else {
					result.arrowClass = 'arrow-down-red';
					result.fontColor = 'red';
        		}
        		result.value = Math.abs(result.value) + "%";
        	}


        	return result;

        },

        /**
        * Get the GMV data list from the backend
        */
		getGmvData : function(dateFrom, dateTo, tnid) {

			var thisView = this;
			var dFrom = dateFrom.substr(0,4)+'-'+dateFrom.substr(4,2)+'-'+dateFrom.substr(6,2);
			var dTo = dateTo.substr(0,4)+'-'+dateTo.substr(4,2)+'-'+dateTo.substr(6,2);

			if (dFrom == this.dateFrom && dTo == this.dateTo && tnid == this.tnid ) {
				thisView.sortData();
			} else {
				this.tnid = tnid;
				this.collection.reset();
				this.collection.fetch({
					data: $.param({
						type: 'get-gmv-breakdown',
						startDate: this.parent.dateConvertToShortMonthFormat(this.dateFrom),
						endDate: this.parent.dateConvertToShortMonthFormat(this.dateTo),
						tnid: this.tnid,
						skipTokenCheck: 1
					}),
					success: function() {
						thisView.sortData();
					},
					error: function(collection, response, options){
						if (response.statusText !== 'abort') {
                            try {
                                data = JSON.parse(response.responseText);
                            }
                            catch (err) {
                                //alert(err.message);
                            }
                        }
					}
				});
			}
		}, 

		/**
		* Render the GMV details list
		*/
		renderGmv: function()
		{
			if(this.collection.models.length > 0 ){
				
				$('.noItems').hide();
				$('.gmvData').html('');

				_.each(this.collection.models[0].attributes['po-total-value'].parentBuyers, function(item){
					this.renderItem(item);
				}, this);


		        var datefrom = this.dateFrom;
				var dateto = this.dateTo;

				var dFromForCsv = datefrom.substr(0,4)+'-'+datefrom.substr(4,2)+'-'+datefrom.substr(6,2);
				var dToForCsv = dateto.substr(0,4)+'-'+dateto.substr(4,2)+'-'+dateto.substr(6,2);
				tnid = this.tnid;
		        
				//$('a.view.csv').attr('href',"/reports/new-gmv-data/?type=csv&datefrom=" + datefrom + "&dateto=" + dateto + "&tnid=" + tnid);
				$('a.view.csv').attr('href',"/reports/gmv-data/?type=csv&logmeinplease=true&datefrom=" + dFromForCsv + "&dateto=" + dToForCsv + "&tnid=" + tnid + '&asFile=1');
				var total = this.collection.models[0].attributes['po-total-value']['total-adjusted-gmv'].toFixed(2);
		        /*var total = this.collection.models[0]..totalTrans['total-adjusted-gmv'].toFixed(2); */
		        total = total.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
		        $('.totalTrans').html(total);
		        $('.actions').show();
		        
			} else {
				$('.gmvData').html('');
				$('.noItems').show();
				$('.totalTrans').html('0');
				$('.actions').show();
			}
			
			$('.actions').waypoint('sticky', {offset: 60});
		},


		getDataAndResetToDefaults: function()
		{
			this.initalizeDates();
			this.getData();
		},

		/**
		* This is the entry point, to load result form backend, and render it to the screen
		*/
		getData: function() {
			var html = '';

			$('div.filters').html('');
			$('div.filters').hide();
			if (this.parent.supplier.premiumListing == 1 || this.parent.isShipmate == 1) {
				$('.dataContainerCompare').html('');
				html = this.transactionTemplate();
				$('.dataContainer').html(html);
				this.getGmvData( this.dateFrom, this.dateTo, this.parent.supplier.tnid);
				this.getDataRange();
				this.getSupplierData();
			} else {
				$('.dataContainerCompare').html('');
				html = this.transactionMessageTemplate();
				$('.dataContainer').html(html);
                $('#progressBarHolder').hide();
			}
		},

		getSupplierData: function()
		{

			var thisView = this;
			var html = '';
			this.supplierCollection.reset();
			this.supplierCollection.fetch({
				type: this.ajaxType,
				data: $.param({
					tnid: this.parent.supplier.tnid,
					start: this.dateFrom,
					end: this.dateTo,
				}),
				complete: function(){
									
				if (thisView.supplierCollection.models.length > 0 && thisView.supplierCollection.models[0].attributes['enquiry-summary']['unactioned-rfq'].count > 0) {
					html = thisView.unactionedBoxTemplate({
					unactionedRfqCount: thisView.supplierCollection.models[0].attributes['enquiry-summary']['unactioned-rfq'].count,
					unactionedRfqRate: thisView.supplierCollection.models[0].attributes['enquiry-summary']['unactioned-rfq'].rate.toFixed(1),
				});
				} else {
					html = '';
				} 
				$('#unactionedBox').html(html);
				}
			});
		},

		/**
		* Render 1 GMV item
		*/
		renderItem: function(item) {
		    var gmvListRow = new gmvRowView({
		        model: item
		    });
		    $('.gmvData').append(gmvListRow.render().el);
		    $('.gmvData').append('<div class="firstChildContainer"></div>');
		},

		/**
		* Sort the list of PO-s POC-s by refnr+Doctype
		*/
		sortData: function() {
			var origID = null;
			if (this.collection.models[0]) {
				if (this.collection.models[0].attributes['po-total-value']) {
			 _.each(this.collection.models[0].attributes['po-total-value'].parentBuyers, function(item){

				item.totalSum = 0;
				_.each(item.CHILDREN, function(child){
					child.childSum = 0;
					child.totalChildSum = 0;
					child.currencies = "";
					child.sortedData = {};
					_.each(child.DATA, function(dataItem){

						item.totalSum = item.totalSum + dataItem['adjusted-cost'];
						child.childSum = child.childSum + dataItem['adjusted-cost'];
						child.totalChildSum = child.totalChildSum + dataItem['total-cost-usd'];

						if(child.currencies !== ""){
							if(child.currencies.search(dataItem['currency']) === -1){
								child.currencies += ", " + dataItem['currency'];
							}
						}
						else {
							child.currencies += dataItem['currency'];
						}


						if(!origID){
								origID = dataItem['internal-ref-no'];

							if(!child.sortedData[origID]){
								child.sortedData[origID] = [];
							}

							child.sortedData[origID].push(dataItem);
						}
						else {
							if(dataItem['internal-ref-no'] == origID){
								child.sortedData[origID].push(dataItem);
							}
							else {								
								origID = dataItem['internal-ref-no'];

								if(!child.sortedData[origID]){
									child.sortedData[origID] = [];
								}

								child.sortedData[origID].push(dataItem);
							}
						}

					
						child.sortedData[origID].sort(function(a, b){
						    if(a['doc-type'] < b['doc-type']) return -1;
						    if(a['doc-type'] > b['doc-type']) return 1;
						    return 0;
						});
	

					}, child);
				});
			}, this);

			this.renderGmv();
			}
			}
		},

		/**
		* Toggle all GMV data
		*/
		toggleAll: function(e){
			e.preventDefault();
			var state = false;
			if($(e.target).hasClass('expandAll')){
				state = false;
			}
			else {
				state = true;
			}
			$('table.group').each(function() {
                if($(this).find('tbody').hasClass('ui-state-active') === state){
                    $(this).trigger('click');
                }
            });
            $('table.parent').each(function() {
                if($(this).find('tbody').hasClass('ui-state-active') === state){
                    $(this).trigger('click');
                }
            });
            $('table.child').each(function() {
                if($(this).find('tbody').hasClass('ui-state-active') === state){
                    $(this).trigger('click');
                }
            });
		},

		
		/**
		* Toggle selected GMV data
		*/
		toggleSel: function(e){
			var state = false;
			e.preventDefault();
			if($(e.target).hasClass('expandSel')){
				state = false;
			}
			else {
				state = true;
			}
            $('table.gmv').each(function() {
                var el = $(this).find('input.groupExpand');
                if($(el).prop('checked')){
                    if($(this).find('tbody').hasClass('ui-state-active') === state){
                        $(this).trigger('click');
                    }
                }
            });
		},

		togglePeriodDetails: function(e) {
			if($(e.target).parent().find('.details').hasClass('show')){
				$(e.target).parent().find('.details').removeClass('show');
			}
			else if(!$(e.target).hasClass('inactive')) {
				$(e.target).parent().find('.details').addClass('show');
			}
		},

		quickSelect: function(e)
		{
			var rangeStart = null;
			var rangeEnd = null;

			var selectedValue = $(e.currentTarget).val();
			if (selectedValue == '0' || selectedValue == '-') {
				return;
			} else {
				var selected = selectedValue.split('-');
				if (selected.length == 3) {
					var currentDate = new Date();
					var currentYear =  currentDate.getFullYear();
					var currentMonth = currentDate.getMonth();
					var currentDay = currentDate.getDate();
					rangeStart = new Date(currentYear, currentMonth-parseInt(selected[1]) + 1, currentDay + 1);
					rangeEnd = new Date(currentYear, currentMonth-parseInt(selected[0]) + 1, currentDay);
				} else {
					rangeStart= new Date(parseInt(selected[1]), parseInt(selected[0])-1, 1);
					rangeEnd = new Date(parseInt(selected[1]), parseInt(selected[0]), 0);
				}
				if($(e.target).attr('name') == 'quickPeriod') {
					$('input[name="dateFrom"]').val($.format.date(rangeStart, "dd/MM/yyyy"));
					$('input[name="dateTo"]').val($.format.date(rangeEnd, "dd/MM/yyyy"));
					this.dateFrom = $.format.date(rangeStart, "yyyyMMdd");
					this.dateTo = $.format.date(rangeEnd, "yyyyMMdd");
					this.dateFromPretty = $.format.date(rangeStart, "dd MMM yyyy");
					this.dateToPretty = $.format.date(rangeEnd, "dd MMM yyyy");
				} else if($(e.target).attr('name') == 'quickPeriodComp') {
					$('input[name="dateFromCompare"]').val($.format.date(rangeStart, "dd/MM/yyyy"));
					$('input[name="dateToCompare"]').val($.format.date(rangeEnd, "dd/MM/yyyy"));
					this.dateFromComp = $.format.date(rangeStart, "yyyyMMdd");
					this.dateToComp = $.format.date(rangeEnd, "yyyyMMdd");
					this.dateFromCompPretty = $.format.date(rangeStart, "dd MMM yyyy");
					this.dateToCompPretty = $.format.date(rangeEnd, "dd MMM yyyy");
				}
				$(e.currentTarget).parent().parent().parent().find('.billPeriodSel').html($.format.date(rangeStart, "dd MMM yyyy") + ' - ' + $.format.date(rangeEnd, "dd MMM yyyy"));
			}

		},

		applyFilters: function(e)
		{
			//TODO validate the input, then apply to the boxes, then call getdata
			$('div.filters').html('');
			$('div.filters').hide();
			if(this.validateDates()){
				$('.error').remove();
				$('.date').removeClass('invalid');
				var rangeStart = this.ukStringToDate($('input[name="dateFrom"]').val());
				var rangeEnd = this.ukStringToDate($('input[name="dateTo"]').val());
				this.dateFrom = $.format.date(rangeStart, "yyyyMMdd");
				this.dateTo = $.format.date(rangeEnd, "yyyyMMdd");
				this.dateFromPretty = $.format.date(rangeStart, "dd MMM yyyy");
				this.dateToPretty = $.format.date(rangeEnd, "dd MMM yyyy");


				$('#periodSel').html($.format.date(rangeStart, "dd MMM yyyy") + ' - ' + $.format.date(rangeEnd, "dd MMM yyyy"));
				if (this.isCompare) {
					var compRangeStart = this.ukStringToDate($('input[name="dateFromCompare"]').val());
					var compRangeEnd = this.ukStringToDate($('input[name="dateToCompare"]').val());
					this.dateFromComp = $.format.date(compRangeStart, "yyyyMMdd");
					this.dateToComp = $.format.date(compRangeEnd, "yyyyMMdd");
					this.dateFromCompPretty = $.format.date(compRangeStart, "dd MMM yyyy");
					this.dateToCompPretty = $.format.date(compRangeEnd, "dd MMM yyyy");
					$('#periodSelCompare').html($.format.date(compRangeStart, "dd MMM yyyy") + ' - ' + $.format.date(compRangeEnd, "dd MMM yyyy"));
				}
				//$('.details').addClass('hide');
				this.getData();
			}  else {
				$('.details').addClass('show');
			}
		},

		toggleComp: function(e) {
			this.compEnableDisable($(e.currentTarget).hasClass('checked'), $(e.currentTarget));
		},

		compEnableDisable: function( disabled, target )
		{
			if (disabled) {
				target.removeClass('checked');
				$('.billPeriodSel.compare').addClass('inactive');
				$('input[name="dateFromCompare"]').val('');
				$('input[name="dateToCompare"]').val('');
				$('select[name="quickPeriodComp"]').val(0);

				$('input[name="dateFromCompare"]').attr("disabled", true);
				$('input[name="dateToCompare"]').attr("disabled", true);
				$('select[name="quickPeriodComp"]').attr("disabled", true);
				$.uniform.update('select[name="quickPeriodComp"]');
				$('input[name="dateFromCompare"]').removeClass('invalid');
				$('input[name="dateToCompare"]').removeClass('invalid');
				target.parent().parent().find('.error').remove();
				target.parent().parent().find('.err').remove();
				this.isCompare =false;
			} else {
				target.addClass('checked');
				$('.billPeriodSel.compare').removeClass('inactive');
				$('input[name="dateFromCompare"]').attr("disabled", false);
				$('input[name="dateToCompare"]').attr("disabled", false);
				$('select[name="quickPeriodComp"]').attr("disabled", false);
				$.uniform.update('select[name="quickPeriodComp"]');
				this.isCompare =true;
			}
		},

		ukDate: function( dateString )
		{
			if (typeof(dateString) == 'string') {
				return dateString.substr(6,2)+"/"+dateString.substr(4,2)+"/"+dateString.substr(0,4);
			} else {
				return '';
			}
		},

		ukStringToDate: function( dateStr )
		{
			var parts = dateStr.split('/');
			return  new Date(parseInt(parts[2]),parseInt(parts[1]) - 1,parseInt(parts[0]));
		},

		validateDates: function(){

			var splitFrom = null;
			var splitTo = null;
			var formatFrom = null;
	    	var formatTo = null;
			var dateFrom = null;
		    var dateTo = null;

			$('input[name="dateFrom"]').parent().parent().find('.error').remove();
			$.extend($.validity.patterns, {
	            date:/^(((0[1-9]|[12]\d|3[01])\/(0[13578]|1[02])\/((19|[2-9]\d)\d{2}))|((0[1-9]|[12]\d|30)\/(0[13456789]|1[012])\/((19|[2-9]\d)\d{2}))|((0[1-9]|1\d|2[0-8])\/02\/((19|[2-9]\d)\d{2}))|(29\/02\/((1[6-9]|[2-9]\d)(0[48]|[2468][048]|[13579][26])|((16|[2468][048]|[3579][26])00))))$/ 
	        });

	    	$.validity.setup({ outputMode:"custom" });

	    	$.validity.start();
    	
    		$('input[name="dateTo"]').match('date','Please enter a valid end date');
	    	$('input[name="dateFrom"]').match('date','Please enter a valid start date');
	    	$('input[name="dateTo"]').require('Please enter a valid end date');
	    	$('input[name="dateFrom"]').require('Please enter a valid start date');

	    	if(this.isCompare){
	    		
	    		$('input[name="dateToCompare"]').require('Please enter a valid end date');
		    	$('input[name="dateFromCompare"]').require('Please enter a valid start date');
	    		$('input[name="dateToCompare"]').match('date','Please enter a valid end date');
		    	$('input[name="dateFromCompare"]').match('date','Please enter a valid start date');
		    }

		    var result = $.validity.end();

		    splitFrom = $('input[name="dateFrom"]').val().split('/');
	    	splitTo = $('input[name="dateTo"]').val().split('/');
	    	formatFrom = splitFrom[1] + '/' + splitFrom[0] + '/' + splitFrom[2];
	    	formatTo = splitTo[1] + '/' + splitTo[0] + '/' + splitTo[2];
	    	dateFrom = new Date(formatFrom);
	    	dateTo = new Date(formatTo);

	    	if($('input[name="dateFrom"]').val() === "" && $('select[name="quickPeriod"]').val() == 0 && $('input[name="dateTo"]').val() !== "" ) {
	    		$('input[name="dateFrom"]').parent().parent().append("<div class='error'>Please enter a start date.</div><div class='clear err'></div>");
	    		result.valid = false;
	    	}
	    	if($('input[name="dateTo"]').val() === "" && $('select[name="quickPeriod"]').val() == 0 && $('input[name="dateFrom"]').val() !== "" ) {
				$('input[name="dateTo"]').parent().parent().append("<div class='error'>Please enter an end date.</div><div class='clear err'></div>");
	    		result.valid = false;
	    	}
	    	if(dateTo < dateFrom){
	    		$('input[name="dateFrom"]').parent().parent().append("<div class='error'>Start date is after end date.</div><div class='clear err'></div>");
	    		result.valid = false;
	    	}

		    if(this.isCompare){
		    	var firstDateFrom = dateFrom;
		    	var firstDateTo = dateTo;

		    	splitFrom = $('input[name="dateFromCompare"]').val().split('/');
		    	splitTo = $('input[name="dateToCompare"]').val().split('/');
		    	formatFrom = splitFrom[1] + '/' + splitFrom[0] + '/' + splitFrom[2];
		    	formatTo = splitTo[1] + '/' + splitTo[0] + '/' + splitTo[2];
		    	dateFrom = new Date(formatFrom);
		    	dateTo = new Date(formatTo);

		    	if($('input[name="dateFromCompare"]').val() === "" && $('select[name="quickPeriodComp"]').val() == 0 && $('input[name="dateToCompare"]').val() !== ""){
		    		$('input[name="dateFromCompare"]').parent().parent().append("<div class='error'>Please enter a start date.</div><div class='clear err'></div>");
		    		result.valid = false;
		    	}
		    	if($('input[name="dateToCompare"]').val() === "" && $('select[name="quickPeriodComp"]').val() == 0 && $('input[name="dateFromCompare"]').val() !== "" ) {
					$('input[name="dateToCompare"]').parent().parent().append("<div class='error'>Please enter an end date.</div><div class='clear err'></div>");
		    		result.valid = false;
		    	}
		    	if(dateTo < dateFrom){
		    		$('input[name="dateFromCompare"]').parent().parent().append("<div class='error'>Start date is after end date.</div><div class='clear err'></div>");
		    		result.valid = false;
		    	}
		    	if(dateFrom > firstDateFrom) {
		    		$('input[name="dateFromCompare"]').parent().parent().append("<div class='error'>Please enter earlier period here.</div><div class='clear err'></div>");
		    		result.valid = false;
		    	}
	    	}

	    	return result.valid;
		}

	});

	return transactionView;
});
