	define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'libs/jquery.validity.min',
    'libs/jquery.validity.custom.output.sir',
     'jqueryui/datepicker',
	'text!templates/reports/sir/tpl/customers.html',
	'text!templates/reports/sir/tpl/customerCompetitive.html',
	'text!templates/reports/sir/tpl/customerDirect.html',
	'text!templates/reports/sir/tpl/customerCompatitiveComp.html',
	'text!templates/reports/sir/tpl/customerDirectComp.html',
	'text!templates/reports/sir/tpl/customersColumn1.html',
	'text!templates/reports/sir/tpl/customersColumn2.html',
	'text!templates/reports/sir/tpl/customersColumn3.html',
	'../collections/supplierInsightDataCollection',
	'libs/jquery.uniform',
], function(
	$, 
	_, 
	Backbone,
	Hb,
	validity,
	validityCustom,
	datePicker,
	customersTpl,
	customerCompetitiveTpl,
	customerDirectTpl,
	customerCompatitiveCompTpl,
	customerDirectCompTpl,
	customersColumn1Tpl,
	customersColumn2Tpl,
	customersColumn3Tpl,
	supplierInsightData,
	Uniform

){
	var customersView = Backbone.View.extend({

		el: $('body'),
		filterData: null,
		dateFrom: '',
		dateTo: '',
		dateFromPretty: '',
		dateToPretty: '',
		dateFromComp: '',
		dateToComp: '',
		dateFromCompPretty: '',
		dateToCompPretty: '',
		isCompare: false,
		customersTemplate: Handlebars.compile(customersTpl), 
		customerCompetitiveTemplate: Handlebars.compile(customerCompetitiveTpl), 
		customerDirectTemplate: Handlebars.compile(customerDirectTpl), 
		customerCompatitiveCompTemplate: Handlebars.compile(customerCompatitiveCompTpl), 
		customerDirectCompTemplate: Handlebars.compile(customerDirectCompTpl), 
		customersColumn1Template: Handlebars.compile(customersColumn1Tpl), 
		customersColumn2Template: Handlebars.compile(customersColumn2Tpl), 
		customersColumn3Template: Handlebars.compile(customersColumn3Tpl), 
		events: {
			'click .dataTable th' : 'reorderDataTable',
			'click .smallDataTable th' : 'reorderSmallDataTable',
			'click input.customersapply' : 'applyFilters',
		},

		initialize: function () {

			this.supplierInsightCollection = new supplierInsightData();
			this.supplierInsightCollection.compOrderDirection = true;
			this.supplierInsightCollection.dirOrderDirection = true;
			this.compareSupplierInsightCollection = new supplierInsightData();
			this.compareSupplierInsightCollection.compOrderDirection = true;
			this.compareSupplierInsightCollection.dirOrderDirection = true;

			this.supplierInsightCollection.lastLOrderField = 'company-name';
			this.compareSupplierInsightCollection.lastROrderField = 'company-name';
			this.supplierInsightCollection.lastSLOrderField = 'company-name';
			this.compareSupplierInsightCollection.lastSROrderField = 'company-name';

			/* Initalize default dates */
			var today = new Date();
			var rangeStart = new Date(today.getFullYear()-1,today.getMonth(),today.getDate() + 1);
			var rangeEnd = new Date(today.getFullYear(),today.getMonth(),today.getDate());

			this.dateFrom = $.format.date(rangeStart, "yyyyMMdd");
			this.dateTo = $.format.date(rangeEnd, "yyyyMMdd");
			this.dateFromPretty = $.format.date(rangeStart, "dd MMM yyyy");
			this.dateToPretty = $.format.date(rangeEnd, "dd MMM yyyy");

			//register helper function for comparison
			Handlebars.registerHelper('comparPercentage', function(comp1, comp2) {
				if (comp2 != 0) {
					return  Math.abs(Math.round(comp1 / comp2 * 100) -100);
				} else {
					return '';
				}
			});

			Handlebars.registerHelper('comparPercentageStyle', function(comp1, comp2) {
				if (comp2 != 0) {
					if ((Math.round(comp1 / comp2 * 100) -100) <0 ) {
						return 'arrowDown';
					} else {
						return 'arrowUp';
					}
				} else {
					return '';
				}
			});
	   },

		render: function() 
		{
			var thisView = this;
			if (!this.isCompare || (this.compareSupplierInsightCollection.models[0] && this.supplierInsightCollection.models[0])) {
				this.reorderCollections();
				this.reorderAllDataTables();
				this.alignRotatedText();

				//TODO reomove the following two lines after the header section is done
				//$(".filters").css('display','none'); /* TODO remove it, when all header filters are done */
				/* $('div.filters').html(''); */
			}
		},

		getData: function()
		{
				//TODO debug this part
				$('.dataContainerCompare').html('');
				 $('div.filters').html('');
				 $('div.filters').hide();
				 if (this.filterData === null) {
				 /* f (true) {  */
					//$('div.filters').detach(); //Remove when the main filter is separeted
					this.filterData = new Object();
					this.filterData.isCompare = this.isCompare;
					
					this.filterData.periodData = this.supplierInsightCollection.models[0];
					if (this.filterData.isCompare) {
						this.filterData.comparePeriodData = this.compareSupplierInsightCollection.models[0];
					}

					this.filterData.dateFrom = this.ukDate(this.dateFrom);
					this.filterData.dateFromPretty = this.dateFromPretty;
					this.filterData.dateTo = this.ukDate(this.dateTo);
					this.filterData.dateToPretty = this.dateToPretty;
					if (this.isCompare) {
						this.filterData.dateFromComp = this.ukDate(this.dateFromComp);
						this.filterData.dateFromCompPretty = this.dateFromCompPretty;
						this.filterData.dateToComp = this.ukDate(this.dateToComp);
						this.filterData.dateToCompPretty = this.dateToCompPretty;
					}
				} else {
					this.filterData.isCompare = this.isCompare;
				}
				if ($('.dataContainer').length !== 0){
					if ($('.dataContainer').html().length === 0)
					{
						var html = this.customersTemplate(this.filterData);

						$('.dataContainer').html(html);

						$('input.date').datepicker({ 
							autoSize: false,
							dateFormat: 'dd/mm/yy'
						});
						$('.dropDown').delegate('.checker', 'click', function(e){
							thisView.toggleChecker(e);
						});
						$('.dropDown').delegate('.quickSelCust', 'change', function(e){
							thisView.quickSelect(e);
						});
						$('.dropDown').delegate('.custPeriodSel', 'click', function(e){
							thisView.togglePeriodDetails(e);
						});
						$('select').uniform();
					}
				}
			
			this.supplierInsightCollection.reset();
			this.compareSupplierInsightCollection.reset()
			var thisView = this;
			this.supplierInsightCollection.fetch({
			type: this.ajaxType,
			data: $.param({
				tnid: this.parent.supplier.tnid,
				type: 'get-customers',
				startDate: this.dateConvertToShortMonthFormat(thisView.dateFrom),
				endDate: this.dateConvertToShortMonthFormat(thisView.dateTo),
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

			if (this.isCompare) {
				this.compareSupplierInsightCollection.fetch({
				type: this.ajaxType,
				data: $.param({
					tnid: this.parent.supplier.tnid,
					type: 'get-customers',
					startDate: this.dateConvertToShortMonthFormat(thisView.dateFromComp),
					endDate: this.dateConvertToShortMonthFormat(thisView.dateToComp),
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
			}
		},

		/* CSS cannot align the rotated cell content properly, align it with js.*/
		alignRotatedText: function()
		{
			$('.rotatedColumn').each(function(){
				var rotatedElement = $(this).children().first();
				var elementWidth = $(rotatedElement).width();
				var containerHeight = $(this).height();
				var topPos =  Math.round((containerHeight-elementWidth)/2);
				$(rotatedElement).css("top",topPos+"px")
			}); 

		},

		toggleChecker: function( e ) {
			if(!$(e.target).hasClass('checked')) {
				$(e.target).addClass('checked');
				$(e.target).children().first().val('1');
			} else {
				$(e.target).removeClass('checked');
				$(e.target).children().first().val('0');
			}
		},

		togglePeriodDetails: function(e) {
			if($(e.target).parent().find('.details').hasClass('show')){
				$(e.target).parent().find('.details').removeClass('show');
			}
			else if(!$(e.target).hasClass('inactive')) {
				$(e.target).parent().find('.details').addClass('show');
			}
		},
		
		reorderCollections: function()
		{
			/* $('#waiting').show(); */
			if (this.supplierInsightCollection.models[0]) {
                this.reorderCollection(this.supplierInsightCollection.models[0].attributes['competitive-buyer'].buyers, 'company-name', true);
                this.reorderCollection(this.supplierInsightCollection.models[0].attributes['direct-buyer'].buyers, 'company-name', true);

                if (this.filterData.isCompare) {
                    this.reorderCollection(this.compareSupplierInsightCollection.models[0].attributes['competitive-buyer'].buyers, 'company-name', true);
                    this.reorderCollection(this.compareSupplierInsightCollection.models[0].attributes['direct-buyer'].buyers, 'company-name', true);
                }
            }
			/* $('#waiting').hide(); */
		},

		reorderCollection: function(node, orderField, isAsc) {
			var nodeCount = node.length;
			var minIndex = 0;
			for (i = 0 ; i < nodeCount ; i++) {
				minIndex = i;
				for (x = i+1 ; x < nodeCount ; x++) {
					var compFrom = (node[x][orderField] === undefined) ? "" : node[x][orderField];
					var compTo = (node[minIndex][orderField] === undefined) ? "" : node[minIndex][orderField];
					if (isAsc) {		
						if (compFrom < compTo) {
							minIndex = x;
						}
					}  else {
						if (compFrom > compTo) {
							minIndex = x;
						}
					}
				}
				if (minIndex != i) { 
					var tempObj = node[i];
					node[i] = node[minIndex];
					node[minIndex] = tempObj;
				}
			}
		},

		reorderDataTable: function(e)
		{

			/* $('#waiting').show(); */

			var orderField = $(e.currentTarget).data('ord');
			var ordArray = orderField.split('_');
			
			var data = new Object();
			if (ordArray[0] == 'l') {

				if (this.supplierInsightCollection.lastLOrderField == ordArray[1]) {
					this.supplierInsightCollection.compOrderDirection = !this.supplierInsightCollection.compOrderDirection;
				} else {
					this.supplierInsightCollection.compOrderDirection = true;
				}
				this.supplierInsightCollection.lastLOrderField = ordArray[1];
				var arrowDirection = this.supplierInsightCollection.compOrderDirection;
				this.reorderCollection(this.supplierInsightCollection.models[0].attributes['competitive-buyer'].buyers,ordArray[1], arrowDirection);
				data.periodData = this.supplierInsightCollection.models[0];
				var html = this.customerCompetitiveTemplate(data);
				if (this.filterData.isCompare) {
					$('#compatitive1').html(html);
				} else {
					$('#compatitive2').html(html);
				}
			} else {
				if (this.compareSupplierInsightCollection.lastROrderField == ordArray[1]) {
					this.compareSupplierInsightCollection.compOrderDirection = !this.compareSupplierInsightCollection.compOrderDirection;
				} else {
					this.compareSupplierInsightCollection.compOrderDirection = true;
				}
				this.compareSupplierInsightCollection.lastROrderField = ordArray[1];
				var arrowDirection = this.compareSupplierInsightCollection.compOrderDirection;
				this.reorderCollection(this.compareSupplierInsightCollection.models[0].attributes['competitive-buyer'].buyers,ordArray[1], arrowDirection);
				data.comparePeriodData = this.compareSupplierInsightCollection.models[0];
				var html = this.customerCompatitiveCompTemplate(data);
				$('#compatitiveComp1').html(html);
			}

			$(e.currentTarget).parent().children().each(function(){

				if ($(this).hasClass('arrow-up')) {
					$(this).removeClass('arrow-up');
				}
				if ($(this).hasClass('arrow-down')) {
					$(this).removeClass('arrow-down');
				}
			});

			if (arrowDirection) {
				$(e.currentTarget).addClass('arrow-down');
			} else {
				$(e.currentTarget).addClass('arrow-up');
			}

			/* $('#waiting').hide(); */

		},

		reorderSmallDataTable: function(e)
		{
			/* $('#waiting').show(); */
			var orderField = $(e.currentTarget).data('ord');
			var ordArray = orderField.split('_');
			var data = new Object();
			if (ordArray[0] == 'l') {

				if (this.supplierInsightCollection.lastSLOrderField == ordArray[1]) {
					this.supplierInsightCollection.dirOrderDirection = !this.supplierInsightCollection.dirOrderDirection;
				} else {
					this.supplierInsightCollection.dirOrderDirection = true;
				}
				this.supplierInsightCollection.lastSLOrderField = ordArray[1];

				var arrowDirection = this.supplierInsightCollection.dirOrderDirection;
				this.reorderCollection(this.supplierInsightCollection.models[0].attributes['direct-buyer'].buyers,ordArray[1], arrowDirection);
				data.periodData = this.supplierInsightCollection.models[0];
				var html = this.customerDirectTemplate(data);
				if (this.filterData.isCompare) {
					$('#direct1').html(html);
				} else {
					$('#direct2').html(html);
				}
			} else {
				if (this.compareSupplierInsightCollection.lastSROrderField == ordArray[1]) {
					this.compareSupplierInsightCollection.dirOrderDirection = !this.compareSupplierInsightCollection.dirOrderDirection;
				} else {
					this.compareSupplierInsightCollection.dirpOrderDirection = true;
				}
				this.compareSupplierInsightCollection.lastSROrderField = ordArray[1];

				var arrowDirection = this.compareSupplierInsightCollection.dirOrderDirection;
				this.reorderCollection(this.compareSupplierInsightCollection.models[0].attributes['direct-buyer'].buyers,ordArray[1], arrowDirection);
				data.comparePeriodData = this.compareSupplierInsightCollection.models[0];
				var html = this.customerDirectCompTemplate(data);
				$('#directComp1').html(html);
			}

			$(e.currentTarget).parent().children().each(function(){
				if ($(this).hasClass('arrow-up')) {
					$(this).removeClass('arrow-up');
				}
				if ($(this).hasClass('arrow-down')) {
					$(this).removeClass('arrow-down');
				}
			});

			if (arrowDirection) {
				$(e.currentTarget).addClass('arrow-down');
			} else {
				$(e.currentTarget).addClass('arrow-up');
			}
			
			/* $('#waiting').hide(); */

		},

		reorderAllDataTables: function(e)
		{
			/* $('#waiting').show(); */
			var data = new Object();

			if (this.filterData.isCompare) {
				data.periodData = this.supplierInsightCollection.models[0];
				data.comparePeriodData = this.compareSupplierInsightCollection.models[0];
				data.isCompare = true;
				this.renderColumns(data);
				var html = this.customerCompetitiveTemplate(data);
				$('#compatitive1').html(html);
				var html = this.customerDirectTemplate(data);
				$('#direct1').html(html);
				var html = this.customerCompatitiveCompTemplate(data);
				$('#compatitiveComp1').html(html);
				var html = this.customerDirectCompTemplate(data);
				$('#directComp1').html(html);
			} else {
				data.periodData = this.supplierInsightCollection.models[0];
				data.isCompare = false;
				this.renderColumns(data);
				var html = this.customerCompetitiveTemplate(data);
				$('#compatitive2').html(html);
				var html = this.customerDirectTemplate(data);
				$('#direct2').html(html);
			}
			/* $('#waiting').hide(); */
		},

		applyFilters : function() {
			if(this.validateDates()){

				$('.error').remove();
				$('.date').removeClass('invalid');
				this.getData();
			}
		},

        dateConvertToShortMonthFormat: function( dateString ) 
		{

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
		},

		prettyDate: function( dateString ) {
			var parts = dateString.split("/");
			date = new Date(
				parseInt(parts[2], 10),
				parseInt(parts[1], 10) - 1,
				parseInt(parts[0], 10)
			);
			return  $.format.date(date, "dd MMM yyyy");
		},

		renderColumns: function( data ) {

				var html = this.customersColumn1Template(data);
				$('#contentColumn1').html(html);
				var html = this.customersColumn2Template(data);
				$('#contentColumn2').html(html);
				var html = this.customersColumn3Template(data);
				$('#contentColumn3').html(html);

		},

		ukDate: function( dateString )
		{
			if (typeof(dateString) == 'string') {
				return dateString.substr(6,2)+"/"+dateString.substr(4,2)+"/"+dateString.substr(0,4);
			} else {
				return '';
			}
		},

		ukAsDate: function( dateString )
		{
			if (typeof(dateString) == 'string') {
				var parts = dateString.split('/')
				return new Date(parseInt(parts[2]), parseInt(parts[1])-1, parseInt(parts[0]));
			} else {
				return null;
			}
		},

		validateDates: function(){
			this.isCompare = (!$('input[name="dateFromCompare"]').val() == '');
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

		    var splitFrom = $('input[name="dateFrom"]').val().split('/');
	    	var splitTo = $('input[name="dateTo"]').val().split('/');
	    	var formatFrom = splitFrom[1] + '/' + splitFrom[0] + '/' + splitFrom[2];
	    	var formatTo = splitTo[1] + '/' + splitTo[0] + '/' + splitTo[2];
	    	var dateFrom = new Date(formatFrom);
	    	var dateTo = new Date(formatTo);

	    	if($('input[name="dateFrom"]').val() == "" && $('select[name="quickPeriod"]').val() == 0 && $('input[name="dateTo"]').val() != "" ) {
	    		$('input[name="dateFrom"]').parent().parent().append("<div class='error'>Please enter a start date.</div><div class='clear err'></div>");
	    		result.valid = false;
	    	}
	    	if($('input[name="dateTo"]').val() == "" && $('select[name="quickPeriod"]').val() == 0 && $('input[name="dateFrom"]').val() != "" ) {
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

		    	var splitFrom = $('input[name="dateFromCompare"]').val().split('/');
		    	var splitTo = $('input[name="dateToCompare"]').val().split('/');
		    	var formatFrom = splitFrom[1] + '/' + splitFrom[0] + '/' + splitFrom[2];
		    	var formatTo = splitTo[1] + '/' + splitTo[0] + '/' + splitTo[2];
		    	var dateFrom = new Date(formatFrom);
		    	var dateTo = new Date(formatTo);

		    	if($('input[name="dateFromCompare"]').val() == "" && $('select[name="quickPeriodComp"]').val() == 0 && $('input[name="dateToCompare"]').val() != ""){
		    		$('input[name="dateFromCompare"]').parent().parent().append("<div class='error'>Please enter a start date.</div><div class='clear err'></div>");
		    		result.valid = false;
		    	}
		    	if($('input[name="dateToCompare"]').val() == "" && $('select[name="quickPeriodComp"]').val() == 0 && $('input[name="dateFromCompare"]').val() != "" ) {
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
	    	if (result.valid) {
	    			var rangeStart = this.ukAsDate($('input[name="dateFrom"]').val());
	    			var rangeEnd = this.ukAsDate($('input[name="dateTo"]').val());
					this.dateFrom = $.format.date(rangeStart, "yyyyMMdd");
					this.dateTo = $.format.date(rangeEnd, "yyyyMMdd");
					this.dateFromPretty = $.format.date(rangeStart, "dd MMM yyyy");
					this.dateToPretty = $.format.date(rangeEnd, "dd MMM yyyy");
					$('#customersPeriodSel').html(this.dateFromPretty+" - "+this.dateToPretty);
				if(this.isCompare) {
					var rangeStart = this.ukAsDate($('input[name="dateFromCompare"]').val());
	    			var rangeEnd = this.ukAsDate($('input[name="dateToCompare"]').val());
					this.dateFromComp = $.format.date(rangeStart, "yyyyMMdd");
					this.dateToComp = $.format.date(rangeEnd, "yyyyMMdd");
					this.dateFromCompPretty = $.format.date(rangeStart, "dd MMM yyyy");
					this.dateToCompPretty = $.format.date(rangeEnd, "dd MMM yyyy");
					$('#customersPeriodSelCompare').html(this.dateFromCompPretty+" - "+this.dateToCompPretty);
					
	    		}
	    	}
	    	return result.valid;
		},

		quickSelect: function(e)
		{
			var selectedValue = $(e.currentTarget).val();
			if (selectedValue == '0' || selectedValue == '-') {
				return;
			} else {

				var  today = new Date();
				switch(parseInt(selectedValue))
				{
					case 1:
						rangeStart = new Date(today.getFullYear(),today.getMonth()-12,today.getDate() + 1);
						rangeEnd = today;
						break;
					case 2:
						rangeStart = new Date(today.getFullYear(),today.getMonth()-24,today.getDate() + 1);
						rangeEnd = new Date(today.getFullYear(),today.getMonth()-12,today.getDate());
						break;
					case 3:
						rangeStart = new Date(today.getFullYear(),today.getMonth(),1);
						rangeEnd = today;
						break;
					case 4:
						rangeStart = new Date(today.getFullYear(),today.getMonth()-1,1);
						rangeEnd = new Date(today.getFullYear(),today.getMonth(),0);
						break;
					case 5:
						rangeStart = new Date(today.getFullYear(),Math.floor(today.getMonth()/3)*3,1);
						rangeEnd = today;
						break;
					case 6:
						rangeStart	= new Date(today.getFullYear(),(Math.floor(today.getMonth()/3)-1)*3,1);
						rangeEnd 	= new Date(today.getFullYear(),(Math.floor(today.getMonth()/3)-1)*3+3,0);
						break;
					case 7:
						rangeStart = new Date(today.getFullYear(),0,1);
						rangeEnd = today;
						break;
					case 8:
						rangeStart = new Date(today.getFullYear()-1,0,1);
						rangeEnd = new Date(today.getFullYear()-1,11,31);
						break;	
					default:
						rangeStart = new Date(today.getFullYear(),today.getMonth()-12,today.getDate());
						rangeEnd = today;
						break;
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
				$(e.currentTarget).parent().parent().parent().find('.custPeriodSel').html($.format.date(rangeStart, "dd MMM yyyy") + ' - ' + $.format.date(rangeEnd, "dd MMM yyyy"));
				this.isCompare = (!$('input[name="dateFromCompare"]').val() == '');
			}

		},

	});

	return customersView;
});
