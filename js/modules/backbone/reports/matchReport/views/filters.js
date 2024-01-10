function ucwords(str) {
  //  discuss at: http://phpjs.org/functions/ucwords/
  // original by: Jonas Raoni Soares Silva (http://www.jsfromhell.com)
  // improved by: Waldo Malqui Silva (http://waldo.malqui.info)
  // improved by: Robin
  // improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
  // bugfixed by: Onno Marsman
  //    input by: James (http://www.james-bell.co.uk/)
  //   example 1: ucwords('kevin van  zonneveld');
  //   returns 1: 'Kevin Van  Zonneveld'
  //   example 2: ucwords('HELLO WORLD');
  //   returns 2: 'HELLO WORLD'
  str = String(str).toLowerCase();
  return (str + '')
    .replace(/^([a-z\u00E0-\u00FC])|\s+([a-z\u00E0-\u00FC])/g, function ($1) {
      return $1.toUpperCase();
    });
}


define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'../../../shared/hbh/general',
	'libs/jquery.uniform',
	'libs/jquery-ui-1.10.3/datepicker',
	'../collections/filters',
	'text!templates/reports/matchReport/tpl/filters.html'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	matchHbh,
	Uniform,
	Datepicker,
	filtersCollection,
	filtersTpl
){
	var filtersView = Backbone.View.extend({
		el: $('div.filterSection'),
		lastBuyerId : null,
		lastVesselId : null,
		lastPurcheserId : null,
		lastDateFrom : null,
		lastDateTo : null,
		pageReloaded: true,

		events: {
			'change select[name="branches"]'	  : 'getVesselData',
			'change select[name="vessel"]'	  : 'getPurchaserData',
			'change select[name="purchaser"]'	  : 'applyFilters',
			'change .date' : 'getVesselData',

		},
		
		buyerId: require('reports/match/buyerId'),
		vesselName: require('reports/match/vesselName'),
		fromDate: require('reports/match/fromDate'),
		toDate: require('reports/match/toDate'),

		filtersTemplate: Handlebars.compile(filtersTpl),

		initialize: function() {
			this.vesselCollection = new filtersCollection();
			this.vesselCollection.url = '/data/source/buyer-match-vessel';
			
			this.branchCollection = new filtersCollection();
			this.branchCollection.url = '/data/source/buyer-branches';
			
			this.purchaserCollection = new filtersCollection();
			this.purchaserCollection.url = '/data/source/buyer-match-purchaser';
		},

		getData: function() {
			var thisView = this;

			// first fetch branches
			// then vessel
			// then purchaser
			thisView.branchCollection.fetch({
				data: {
					'match-report': 0,
				},
				complete: function(){
					thisView.render();
					thisView.getVesselData();
				}
			});
		},

		getVesselData: function() {

			var thisView = this;
			this.lastDateFrom = $('input[name="from"]').val();
			this.lastDateTo = $('input[name="to"]').val();
			this.lastBuyerId = $('#branches').val();
			this.lastVesselId = 0; 
			this.lastPurcheserId = ''; 

			this.vesselCollection.fetch({
				data: {
					buyerId: $('#branches').val(),
					startDate: $('input[name="from"]').val(),
					endDate: $('input[name="to"]').val()
				},
				complete: function() {
					thisView.render();
					thisView.getPurchaserData();
					//thisView.renderPurchasers();
				}
			});
			
		},
		
		getPurchaserData: function() {
			var thisView = this;
			this.lastDateFrom = $('input[name="from"]').val();
			this.lastDateTo = $('input[name="to"]').val();
			this.lastBuyerId = $('#branches').val();
			this.lastVesselId = $('#vessel option:selected').index(); 
			this.lastPurcheserId = $('#purchaser').val(); 

			this.purchaserCollection.fetch({
				data: {
					buyerId: $('#branches').val(),
					startDate: $('input[name="from"]').val(),
					endDate: $('input[name="to"]').val(),
					vesselName: $('#vessel').val()
				},
				complete: function() {
					thisView.fixAmp();
					thisView.render();
					thisView.renderPurchasers();
					//thisView.render();
				}
			});
		},		
		
		render: function() {
			var data = new Object();
			data.vessel = this.vesselCollection;
			data.branch = this.branchCollection;
			
			if(this.fromDate && this.fromDate !== ""){
				data.fromDate = this.fromDate;
				data.toDate = this.toDate;
			}

			_.each(data.branch.models, function(item){
				if(this.buyerId == item.attributes.id || (!this.buyerId && item.attributes['default'])){
					item.attributes.selected = true;
				}
			}, this);
				
			_.each(data.branch.models, function(item) {
				item.buyerId = this.buyerId;
			}, this);

			_.each(data.vessel.models, function(item) {
				item.vesselName = this.vesselName;
			}, this);

			$(this.el).html('');
			
			var html = this.filtersTemplate(data);

			$(this.el).html(html);
			if (this.lastBuyerId !== null && !this.pageReloaded) {
					$('#branches').val(this.lastBuyerId);
					$('#vessel option')[this.lastVesselId].selected = true;
					$('#purchaser').val(this.lastPurcheserId);
					if (this.lastDateFrom !== null) {
						$('input[name="from"]').val(this.lastDateFrom);
						$('input[name="to"]').val(this.lastDateTo);
					}
			}
			
			$('form select').uniform();
			$('form input.date').datepicker({ 
				autoSize: false,
				dateFormat: 'dd/mm/yy'
			});

			var fromDate = $('input[name="from"]').val(),
				toDate = $('input[name="to"]').val(),
				branch = $('select[name="branches"]').val(),
				vessel = $('select[name="vessel"]').val(),
				purchaser = $('select[name="purchaser"]').val();

			this.parent.dateFrom = fromDate;
			this.parent.dateTo = toDate;
			this.parent.vessel = vessel;
			this.parent.branch = branch;
			this.parent.purchaser = purchaser;
			
	    	this.parent.getData();

	    	var thisView = this;
	    	$('input[name="show"]').unbind().bind('click', function(e){
	    		e.preventDefault();
	    		thisView.applyFilters();
	    	});
	    	
	    	$('input[name="export"]').unbind().bind('click', function(e){
	    		e.preventDefault();
	    		thisView.exportData();
	    	});
		},
		
		exportData: function(){
			var fromDate = $('input[name="from"]').val(),
			toDate = $('input[name="to"]').val(),
			branch = $('select[name="branches"]').val(),
			vessel = $('select[name="vessel"]').val(),
			purchaser = $('select[name="purchaser"]').val();

			url = '/buyer/usage/rfq-list?buyerId='+branch+'&vessel='+vessel+'&purchaser='+purchaser+'&startDate='+encodeURIComponent(fromDate)+'&endDate='+encodeURIComponent(toDate)+'&csv=1';
			location.href=url;
		},
		
		renderPurchasers: function(){
			$('#purchaser').html('<option selected="selected" value="">All purchasers</option>');
			_.each(this.purchaserCollection.models, function(item){
				var html = '<option value="' + item.attributes.CNTC_PERSON_EMAIL_ADDRESS + '">';
					if (item.attributes.CNTC_PERSON_NAME != null) {
						html += ucwords(item.attributes.CNTC_PERSON_NAME);
						html += ' -- ';
					}		
					html += String(item.attributes.CNTC_PERSON_EMAIL_ADDRESS).toLowerCase();
					html += ' (' +  item.attributes.TOTAL + ' rfq' + ((item.attributes.TOTAL>1)?'s':'') + ')';
					html += '</option>';
				$('#purchaser').append(html);
			}, this);
			$.uniform.update();
			$('.purchaserSel').show();
			this.pageReloaded = false;
		},
		

		applyFilters: function(){
			var fromDate = $('input[name="from"]').val(),
				toDate = $('input[name="to"]').val(),
				branch = $('select[name="branches"]').val(),
				vessel = $('select[name="vessel"]').val(),
				purchaser = $('select[name="purchaser"]').val();

			/*if(this.validateDates()){*/
				this.parent.dateFrom = fromDate;
				this.parent.dateTo = toDate;
				this.parent.vessel = vessel;
				this.parent.branch = branch;
				this.parent.purchaser = purchaser;
				this.parent.getData();
			//}
		},

		validateDates: function(){
			$('input[name="from"]').parent().parent().find('.error').remove();
			$.extend($.validity.patterns, {
	            date:/^(((0[1-9]|[12]\d|3[01])\/(0[13578]|1[02])\/((19|[2-9]\d)\d{2}))|((0[1-9]|[12]\d|30)\/(0[13456789]|1[012])\/((19|[2-9]\d)\d{2}))|((0[1-9]|1\d|2[0-8])\/02\/((19|[2-9]\d)\d{2}))|(29\/02\/((1[6-9]|[2-9]\d)(0[48]|[2468][048]|[13579][26])|((16|[2468][048]|[3579][26])00))))$/ 
	        });

	    	$.validity.setup({ outputMode:"custom" });

	    	$.validity.start();

    		$('input[name="to"]').require('Please enter a start date.').match('date','Please enter a valid end date');
	    	$('input[name="from"]').require('Please enter an end date.').match('date','Please enter a valid start date');

	    	var result = $.validity.end();
	    	var splitFrom = $('input[name="from"]').val().split('/');
	    	var splitTo = $('input[name="to"]').val().split('/');
	    	var formatFrom = splitFrom[1] + '/' + splitFrom[0] + '/' + splitFrom[2];
	    	var formatTo = splitTo[1] + '/' + splitTo[0] + '/' + splitTo[2];
	    	var dateFrom = new Date(formatFrom);
	    	var dateTo = new Date(formatTo);

	    	if(dateTo < dateFrom){
	    		$('input[name="dateFrom"]').parent().parent().append("<div class='error'>Start date is after end date.</div><div class='clear err'></div>");
	    		result.valid = false;
	    	}
	    	return result.valid;
		},

		fixAmp: function(){
			var text = $('#uniform-branchSelect span').text();
			text = text.replace(/&amp;/g, '&');
			$('#uniform-branchSelect span').text(text);
		}
	});

	return filtersView;
});
