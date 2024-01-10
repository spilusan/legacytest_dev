define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'libs/jquery.tools.overlay.modified',
	'libs/jquery.uniform',
	'libs/jquery.dateFormat',
	'libs/jquery.validity.min',
    'libs/jquery.validity.custom.output.sir',
    'jqueryui/datepicker',
	//'../views/selectorView',
	'text!templates/reports/sir/tpl/filters.html',
	'text!templates/reports/sir/tpl/products.html'
], function(
	$,
	_, 
	Backbone,
	Hb,
	Uniform,
	Modal,
	dateFormat,
	validity,
	validityCustom,
	datePicker,
	//selectorView,
	filtersTpl,
	productsTpl
){
	var filtersView = Backbone.View.extend({
		el: $('div.filters'),

		template: Handlebars.compile(filtersTpl),
		productsTemplate: Handlebars.compile(productsTpl),

		dateFrom: null,
		dateFromPretty: null,
		dateTo: null,
		dateToPretty: null,
		dateFromComp: null,
		dateFromCompPretty: null,
		dateToComp: null,
		dateToCompPretty: null,
		comparePeriod: false,
		compareMarket: true,
		categories: [],
		brands: [],
		locations: [],
		products: [],
		prodData: {},
		saving: false,
		locTemp: [],
		brandTemp: [],
		catTemp: [],

		initialize: function() {
			var thisView = this;
			//this.selectorView = new selectorView();
			//this.selectorView.parent = this;
		},

		getDate: function(){
			this.dateTo = new Date();
			this.dateFrom = new Date();
			this.dateFrom.setFullYear(this.dateFrom.getFullYear() - 1);
			this.dateFrom.setDate(this.dateFrom.getDate() + 1);

			this.dateToComp = new Date();
			this.dateToComp.setFullYear(this.dateToComp.getFullYear() - 1);
			this.dateFromComp = new Date();
			this.dateFromComp.setFullYear(this.dateFromComp.getFullYear() - 2);
			this.dateFromComp.setDate(this.dateFromComp.getDate() + 1);

			this.formatDates();
			this.parent.dateFrom = this.dateFromPost;
			this.parent.dateTo = this.dateToPost;
			this.parent.dateFromPretty = this.dateFromPretty;
			this.parent.dateToPretty = this.dateToPretty;

			var thisView = this;
			$(function(){
				thisView.render();
			});
		},

		formatDates: function(){
			this.dateToPost = $.format.date(this.dateTo, "yyyyMMdd");
			this.dateToPretty = $.format.date(this.dateTo, "dd MMM yyyy");
			this.dateFromPost = $.format.date(this.dateFrom, "yyyyMMdd");
			this.dateFromPretty = $.format.date(this.dateFrom, "dd MMM yyyy");
			this.dateFromInput = $.format.date(this.dateFrom, "dd/MM/yyyy");
			this.dateToInput = $.format.date(this.dateTo, "dd/MM/yyyy");

			if(this.dateToComp && this.dateFromComp){
				this.dateToCompPost = $.format.date(this.dateToComp, "yyyyMMdd");
				this.dateToCompPretty = $.format.date(this.dateToComp, "dd MMM yyyy");
				this.dateFromCompPretty = $.format.date(this.dateFromComp, "dd MMM yyyy");
				this.dateFromCompPost = $.format.date(this.dateFromComp, "yyyyMMdd");
				this.dateFromCompInput = $.format.date(this.dateFromComp, "dd/MM/yyyy");
				this.dateToCompInput = $.format.date(this.dateToComp, "dd/MM/yyyy");
			}
		},

		addSlashes: function(e){
			if(e.keyCode !== 8 && e.keyCode !== 46){
				if ($(e.target).val().length == 2){
	                $(e.target).val($(e.target).val() + "/");
	            } 
	            else if ($(e.target).val().length == 5){
	                $(e.target).val($(e.target).val() + "/");
	            }
			}
		},

		applyPretty: function(e){
			if($(e).attr('name') == 'dateTo') {
				$(e).parent().parent().parent().find('.periodSel').html(this.dateFromPretty + ' - ' + this.dateToPretty);
				$.uniform.update();
				$(e).parent().parent().find('#quick').val(0);
			}
			else if($(e).attr('name') == 'dateToCompare') {
				$(e).parent().parent().parent().find('.periodSel').html(this.dateFromCompPretty + ' - ' + this.dateToCompPretty);
				$.uniform.update();
				$(e).parent().parent().find('#quickCompare').val(0);
			}
		},

		render: function() {
			
			var data = {};
				data.dateFrom = this.dateFrom;
				data.dateFromPretty = this.dateFromPretty;
				data.dateTo = this.dateTo;
				data.dateToPretty = this.dateToPretty;
				data.dateFromComp = this.dateFromComp;
				data.dateFromCompPretty = this.dateFromCompPretty;
				data.dateToComp = this.dateToComp;
				data.dateToCompPretty = this.dateToCompPretty;

			var thisView = this;

		    $(this.el).delegate('.editMarket', 'click', function(e){
		    	e.preventDefault();
		    	if($('.selectorHolder .tabcontent.prods').hasClass('on')){
		    		thisView.initProducts();
		    	}
	    		thisView.openDialog();
		    });

			var html = this.template(data);

			$('div.filters').html(html);

			$('div.filters select').uniform();

			//TODO: MOVE TO ITEMSELECTOR, CREATE HB TEMPLATE FOR ITEMSELECTOR TABS
			$('.brandTab').bind('click', function(e){
				e.preventDefault();
				$('.selectorTab').removeClass('selected');
				$('.brandTab').addClass('selected');
				$('.tabcontent.location').hide();
				$('.tabcontent.categories').hide();
				$('.tabcontent.prods').hide();
				$('.tabcontent.prods').removeClass('on');
				$('.tabcontent.brands').show();
			});

			$('.catsTab').bind('click', function(e){
				e.preventDefault();
				$('.selectorTab').removeClass('selected');
				$('.catsTab').addClass('selected');
				$('.tabcontent.location').hide();
				$('.tabcontent.brands').hide();
				$('.tabcontent.prods').hide();
				$('.tabcontent.prods').removeClass('on');
				$('.tabcontent.categories').show();
			});

			$('.locTab').bind('click', function(e){
				e.preventDefault();
				$('.selectorTab').removeClass('selected');
				$('.locTab').addClass('selected');
				$('.tabcontent.brands').hide();
				$('.tabcontent.categories').hide();
				$('.tabcontent.prods').hide();
				$('.tabcontent.prods').removeClass('on');
				$('.tabcontent.location').show();
			});

			$('.prodTab').bind('click', function(e){
				e.preventDefault();
				$('.selectorTab').removeClass('selected');
				$('.prodTab').addClass('selected');
				var html = thisView.productsTemplate(thisView.prodData);
				$('.selectorHolder .tabcontent.prods').html('');	
				$('.selectorHolder .tabcontent.prods').html(html);				
				$('.selectorHolder input[type="checkbox"]').uniform();
				$('.itemselector.prod input.apply').bind('click', function(e){
					e.preventDefault();
					thisView.applyProducts();
					thisView.selectorView.saveState();
				});
				$('.tabcontent.brands').hide();
				$('.tabcontent.categories').hide();
				$('.tabcontent.location').hide();
				$('.tabcontent.prods').addClass('on');
				$('.tabcontent.prods').show();
			});

			$('input.date').datepicker({ 
				autoSize: false,
				dateFormat: 'dd/mm/yy'
			});

			$('form.new').delegate('.periodSel', 'click', function(e){
				thisView.togglePeriodDetails(e);
			});
			$('form.new').delegate('.periodComp', 'click', function(e){
				thisView.toggleCompareTo(e);
			});
			$('form.new').delegate('.marketComp', 'click', function(e){
				thisView.toggleChecker(e);
			});
			$('form.new').delegate('input[name="apply"]', 'click', function(e){
				e.preventDefault();
				thisView.applyClicked();
			});
			$('form.new').delegate('input.date', 'keyup', function(e){
				thisView.addSlashes(e);
			});
			$('form.new').delegate('input.date', 'change', function(e){
				thisView.addSlashes(e);
				if($(e.target).val() != ""){
				}
			});
			$('form.new').delegate('.quickSel', 'change', function(e){
				thisView.quickSelect(e);
			});
		},

		initProducts: function(){
			var thisView = this;
			var html = thisView.productsTemplate(thisView.prodData);
			$('.selectorHolder .tabcontent.prods').html('');	
			$('.selectorHolder .tabcontent.prods').html(html);		
			$('.selectorHolder input[type="checkbox"]').uniform();
			$('.itemselector.prod input.apply').bind('click', function(e){
				e.preventDefault();
				thisView.applyProducts();
				thisView.selectorView.saveState();
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

		toggleCompareTo: function(e) {
			if($('.periodSel.compare').hasClass('inactive')) {
				$('.periodSel.compare').removeClass('inactive');
			}
			else {
				$('.periodSel.compare').addClass('inactive');
				$('.periodSel.compare').parent().find('.details').removeClass('show');
			}
			this.toggleChecker(e);
		},

		toggleChecker: function(e) {
			if(!$(e.target).hasClass('checked')) {
				$(e.target).addClass('checked');
				if($(e.target).hasClass('periodComp')){
					this.comparePeriod = true;
				}
				else if($(e.target).hasClass('marketComp')){
					this.compareMarket = true;
				}
			}
			else {
				$(e.target).removeClass('checked');
				if($(e.target).hasClass('periodComp')){
					this.comparePeriod = false;
				}
				else if($(e.target).hasClass('marketComp')){
					this.compareMarket = false;
				}
			}
		},

		applyClicked: function(){
			if(this.validateDates()){
				this.applyFilters();
			}
		},

		applyFilters: function(){
			$('.error').remove();
			$('.date').removeClass('invalid');
			if($('input[name="dateFrom"]').val() != "" && $('input[name="dateTo"]').val() != ""){
				var parts = $('input[name="dateFrom"]').val().split("/");
				this.dateFrom = new Date(
										parseInt(parts[2], 10),
				                  		parseInt(parts[1], 10) - 1,
				                  		parseInt(parts[0], 10)
				                  	);
				
				parts = $('input[name="dateTo"]').val().split("/");
				this.dateTo = new Date(
										parseInt(parts[2], 10),
				                  		parseInt(parts[1], 10) - 1,
				                  		parseInt(parts[0], 10)
				                  	);
				this.formatDates();
				$('input[name="dateFrom"]').val('');
				$('input[name="dateTo"]').val('');
				this.applyPretty('input[name="dateTo"]');
			}

			if($('input[name="dateFromCompare"]').val() != "" && $('input[name="dateToCompare"]').val() != ""){
				var parts = $('input[name="dateFromCompare"]').val().split("/");
				this.dateFromComp = new Date(
										parseInt(parts[2], 10),
				                  		parseInt(parts[1], 10) - 1,
				                  		parseInt(parts[0], 10)
				                  	);

				parts = $('input[name="dateToCompare"]').val().split("/");
				this.dateToComp = new Date(
										parseInt(parts[2], 10),
				                  		parseInt(parts[1], 10) - 1,
				                  		parseInt(parts[0], 10)
				                  	);
				this.formatDates();
				$('input[name="dateFromCompare"]').val('');
				$('input[name="dateToCompare"]').val('');
				this.applyPretty('input[name="dateToCompare"]');
			}

			this.parent.dateFrom = this.dateFromPost;
			this.parent.dateTo = this.dateToPost;
			this.parent.dateFromPretty = this.dateFromPretty;
			this.parent.dateToPretty = this.dateToPretty;

			this.parent.categories = this.categories.join(',');

			this.parent.brands = this.brands.join(',');
			
			this.parent.locations = this.locations.join(',');

			this.parent.products = this.products.join(',');

			if(this.comparePeriod) {
				this.parent.comparePeriod = true;
				this.parent.dateFromComp = this.dateFromCompPost;
				this.parent.dateToComp = this.dateToCompPost;
				this.parent.dateFromCompPretty = this.dateFromCompPretty;
				this.parent.dateToCompPretty = this.dateToCompPretty;
			}
			else {
				this.parent.comparePeriod = false;
				this.parent.dateFromComp = '';
				this.parent.dateToComp = '';
			}

			if(this.compareMarket) {
				this.parent.compareMarket = true;
			}
			else {
				this.parent.compareMarket = false;
			}
			$('select[name="quickPeriod"]').val(0);
			$('select[name="quickPeriodComp"]').val(0);
			$.uniform.update();

            this.parent.renderConversion();

		},

		applyProducts: function(){
			this.products =[];
			this.prodData = {};
			var thisView = this;

			$('.itemselector form input[type="checkbox"]').each(function(){
				if($(this).attr('checked') === "checked"){
					thisView.products.push($(this).attr('name'));
					if($(this).attr('name') === "basic"){
						thisView.prodData.basic = true;
					}
					else if($(this).attr('name') === "premium"){
						thisView.prodData.premium = true;
					}
					else if($(this).attr('name') === "smart"){
						thisView.prodData.smart = true;
					}
					else if($(this).attr('name') === "expert"){
						thisView.prodData.expert = true;
					}
				}
			});

			if(this.products.length > 0){
				$('#modal .prodTab span').html(this.products.length + " selected");
			}
			else {
				$('.prodTab span').html('All services');	
			}
		},

		validateDates: function(){
			$('input[name="dateFrom"]').parent().parent().find('.error').remove();
			$.extend($.validity.patterns, {
	            date:/^(((0[1-9]|[12]\d|3[01])\/(0[13578]|1[02])\/((19|[2-9]\d)\d{2}))|((0[1-9]|[12]\d|30)\/(0[13456789]|1[012])\/((19|[2-9]\d)\d{2}))|((0[1-9]|1\d|2[0-8])\/02\/((19|[2-9]\d)\d{2}))|(29\/02\/((1[6-9]|[2-9]\d)(0[48]|[2468][048]|[13579][26])|((16|[2468][048]|[3579][26])00))))$/ 
	        });

	    	$.validity.setup({ outputMode:"custom" });

	    	$.validity.start();

    		$('input[name="dateTo"]').match('date','Please enter a valid end date');
	    	$('input[name="dateFrom"]').match('date','Please enter a valid start date');

	    	if(this.comparePeriod){
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

		    if(this.comparePeriod){
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

	    	return result.valid;
		},

		quickSelect: function(e){
			$('.error').remove();
			$('.date').removeClass('invalid');
			var to = new Date(),
				from = new Date();
			var selectedValue = $(e.target).val();
			var selected = selectedValue.split('-');
			if (selected.length == 3) {
					var currentDate = new Date();
					var currentYear =  currentDate.getFullYear();
					var currentMonth = currentDate.getMonth();
					var currentDay = currentDate.getDate();
					var from = new Date(currentYear, currentMonth-parseInt(selected[1]) + 1, currentDay + 1);
					var to = new Date(currentYear, currentMonth-parseInt(selected[0]) + 1, currentDay);
			} else if(selectedValue == 7){
				from = from.setDate(from.getDate() - 7);
			}
			else if(selectedValue == 30){
				from = from.setMonth(from.getMonth() - 1);
			}
			else if(selectedValue == 90){
				from = from.setMonth(from.getMonth() - 3); 
			}
			else if(selectedValue == 365){
				from = from.setDate(from.getDate() - 364);
			}
			else if(selectedValue == '-'){
				from = null;
				to = null;
			}
			else {
				from = this.dateFrom;
				to = this.dateTo;
			}
			if($(e.target).attr('name') == 'quickPeriod') {
				this.dateFrom = from;
				this.dateTo = to;
				this.formatDates();
				$('input[name="dateFrom"]').val(this.dateFromInput);
				$('input[name="dateTo"]').val(this.dateToInput);
				$(e.target).parent().parent().parent().find('.periodSel').html(this.dateFromPretty + ' - ' + this.dateToPretty);
			}
			else if($(e.target).attr('name') == 'quickPeriodComp') {
				this.dateFromComp = from;
				this.dateToComp = to;
				this.formatDates();
				if($(e.target).val() == '-'){
					$('input[name="dateFromCompare"]').val('');
					$('input[name="dateToCompare"]').val('');	
					$(e.target).parent().parent().parent().find('.periodSel').html('-');					
				} else {
					$('input[name="dateFromCompare"]').val(this.dateFromCompInput);
					$('input[name="dateToCompare"]').val(this.dateToCompInput);	
					$(e.target).parent().parent().parent().find('.periodSel').html(this.dateFromCompPretty + ' - ' + this.dateToCompPretty);
				}

				
			}
		},

        openDialog: function() {
        	var thisView = this;
            $("#modal").overlay({
                mask: 'black',
                left: 'center',
                fixed: false,

                onBeforeLoad: function() {
                	$('.itemselector.selectlist.location').data('oldHtml', $('.itemselector.selectlist.location').html());
                	thisView.locTemp = $.extend({}, $('.itemselector.selectlist.location').data('selected'));

                	$('.itemselector.selectlist.brands').data('oldHtml', $('.itemselector.selectlist.brands').html());
                	thisView.brandTemp = $.extend({}, $('.itemselector.selectlist.brands').data('selected'));

                	$('.itemselector.selectlist.categories').data('oldHtml', $('.itemselector.selectlist.categories').html());
                	thisView.catTemp = $.extend({}, $('.itemselector.selectlist.categories').data('selected'));

                    var windowWidth = $(window).width();
                    var modalWidth = $('#modal').width();
                    var posLeft = windowWidth/2 - modalWidth/2;
                    $('#modal').css('left', posLeft);
                },

                onLoad: function() {
                    $(window).resize(function(){
                        var windowWidth = $(window).width();
                        var modalWidth = $('#modal').width();
                        var posLeft = windowWidth/2 - modalWidth/2;

                        $('#modal').css('left', posLeft);
                    });

                },
                onClose: function(){
                	if(thisView.saving){
                		thisView.saving = false;
                	}
                	else {
                		$('.itemselector.selectlist.location').html($('.itemselector.selectlist.location').data('oldHtml'));
                		$('.itemselector.selectlist.location').data('selected', thisView.locTemp);

                		$('.itemselector.selectlist.brands').html($('.itemselector.selectlist.brands').data('oldHtml'));
                		$('.itemselector.selectlist.brands').data('selected', thisView.brandTemp);

                		$('.itemselector.selectlist.categories').html($('.itemselector.selectlist.categories').data('oldHtml'));
                		$('.itemselector.selectlist.categories').data('selected', thisView.catTemp);
                	}
                }
            });

            $('#modal').overlay().load();
        }
	});

	return filtersView;
});
