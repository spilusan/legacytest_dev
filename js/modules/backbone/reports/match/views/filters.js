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
	'text!templates/reports/match/tpl/filters.html'
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
		el: $('body'),
		lastBuyerId : null,
		lastVesselId : null,
		lastPurcheserId : null,
		lastDateFrom : null,
		lastDateTo : null,
		pageReloaded: true,
		events: {
			'change select[name="branches"]'	  : 'getVesselData',
			'change select[name="vessel"]'	  : 'getPurchaserData',
			'change .date' : 'getVesselData',
		},
		
		buyerId: require('reports/match/buyerId'),
		vesselName: require('reports/match/vesselName'),
		purchaserEmail: require('reports/match/purchaser'),
		fromDate: require('reports/match/fromDate'),
		toDate: require('reports/match/toDate'),


		filtersTemplate: Handlebars.compile(filtersTpl),

		initialize: function() {

			$('#waiting').hide();
			/*
			$('body').ajaxStart(function(){
				$('#waiting').show();
			});

			$('body').ajaxStop(function(){
				$('#waiting').hide();
			});
			*/

			this.vesselCollection = new filtersCollection();
			this.vesselCollection.url = '/data/source/buyer-match-vessel';
			
			this.branchCollection = new filtersCollection();
			this.branchCollection.url = '/data/source/buyer-branches';
			
			this.purchaserCollection = new filtersCollection();
			this.purchaserCollection.url = '/data/source/buyer-match-purchaser';
			
			this.getData();
		},

		getData: function() {
			var thisView = this;

			// first fetch branches
			// then vessel
			// then purchaser


			thisView.branchCollection.fetch({
				data: {
					'match-report': 0,
					'match': 1,
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
			var thisView = this;
			var data = new Object;
			var allBranches = [];
			var defaultBranchId = null;
			data.vessel = this.vesselCollection;
			data.branch = this.branchCollection;
			if (this.branchCollection.models) {
				for (var key in this.branchCollection.models) {
					allBranches.push(this.branchCollection.models[key].attributes.id);
				}
			}
			data.allBranches = allBranches.join();

			if(this.fromDate && this.fromDate !== ""){
				data.fromDate = this.fromDate;
				data.toDate = this.toDate;
			}
			
			_.each(data.branch.models, function(item) {
				item.buyerId = this.buyerId;
				if (parseInt(item.attributes.default) === 1) {
					defaultBranchId = item.attributes.id;
				}
			}, this);

			_.each(data.vessel.models, function(item) {
				item.vesselName = this.vesselName;
			}, this);

			$('div.filterSection').html('');
			var html = this.filtersTemplate(data);
			
		    $('div.filterSection').html(html);
		    if (!($('input[name="show"]').hasClass('disabled'))) {
		    	 $('input[name="show"]').addClass('disabled');
		    }

		    if (!this.pageReloaded) {
				$('#branches').val(this.lastBuyerId);
				$('#vessel option')[this.lastVesselId].selected = true;
				$('#purchaser').val(this.lastPurcheserId);
				if (this.lastDateFrom !== null) {
					$('input[name="from"]').val(this.lastDateFrom);
					$('input[name="to"]').val(this.lastDateTo);
				}
		    } else {
		    	/* $('#branches').val(this.buyerId); */
		    	if (defaultBranchId) {
		    		$('#branches').val(defaultBranchId);
		    	}
		    	$('#vessel').val(this.vesselName);
		    	$('#purchaser').val(this.purchaserEmail);
		    }

		    $('form select').uniform();
			$('form input.date').datepicker({ 
				autoSize: false, 
				dateFormat: "dd/mm/yy"
			});

			/*
			$('body').ajaxStart(function(){
				$('#waiting').show();
			});

			$('body').ajaxStop(function(){
				$('#waiting').hide();
			});
			*/
			$('body').ajaxStart(function(){
			    if (!($('input[name="show"]').hasClass('disabled'))) {
			    	 $('input[name="show"]').addClass('disabled');
			    }
			});

			$('body').ajaxStop(function(){
			    if ($('input[name="show"]').hasClass('disabled')) {
			    	 $('input[name="show"]').removeClass('disabled');
			    }
			});
		},
		
		renderPurchasers: function(){

			$('#purchaser').html('<option value="">All purchasers</option>');
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

			if (this.pageReloaded) {
				this.lastPurcheserId = this.purchaserEmail;
				$('#purchaser').val(this.purchaserEmail);
				this.pageReloaded = false;
			} 

			$.uniform.update();
			$('.purchaserSel').show();

			
		},

		fixAmp: function(){
			var text = $('#uniform-branchSelect span').text();
			text = text.replace(/&amp;/g, '&');
			$('#uniform-branchSelect span').text(text);
		}
	});

	return new filtersView();
});
