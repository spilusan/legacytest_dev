define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/shared/hbh/general',
	'libs/waypoints/waypoints',
	'../collections/reportCollection',
	'../collections/menuCollection',
	'../views/filtersView',
	'../views/tableRowView',
	'backbone/shared/pagination/views/paginationView',
	'text!templates/reports/webreporter/tpl/allPoHead.html',
	'text!templates/reports/webreporter/tpl/allPoFoot.html',
	'text!templates/reports/webreporter/tpl/allQotHead.html',
	'text!templates/reports/webreporter/tpl/allQotFoot.html',
	'text!templates/reports/webreporter/tpl/allReqHead.html',
	'text!templates/reports/webreporter/tpl/allReqFoot.html',
	'text!templates/reports/webreporter/tpl/allRfqHead.html',
	'text!templates/reports/webreporter/tpl/poSupplierHead.html',
	'text!templates/reports/webreporter/tpl/poSupplierFoot.html',
	'text!templates/reports/webreporter/tpl/poVesselHead.html',
	'text!templates/reports/webreporter/tpl/poVesselFoot.html',
	'text!templates/reports/webreporter/tpl/txnSupplierHead.html',
	'text!templates/reports/webreporter/tpl/txnSupplierFoot.html',
	'text!templates/reports/webreporter/tpl/txnVesselHead.html',
	'text!templates/reports/webreporter/tpl/txnVesselFoot.html',
	'text!templates/reports/webreporter/tpl/spbSumHead.html',
	'text!templates/reports/webreporter/tpl/spbSumFoot.html',
	'text!templates/reports/webreporter/tpl/spbSumPendHead.html',
	'text!templates/reports/webreporter/tpl/spbSumPendFoot.html',
	'text!templates/reports/webreporter/tpl/spbRfqHead.html',
	'text!templates/reports/webreporter/tpl/spbRfqFoot.html',
	'text!templates/reports/webreporter/tpl/spbQotHead.html',
	'text!templates/reports/webreporter/tpl/spbQotFoot.html',
	'text!templates/reports/webreporter/tpl/spbQotPendHead.html',
	'text!templates/reports/webreporter/tpl/spbQotPendFoot.html',
	'text!templates/reports/webreporter/tpl/spbOrdHead.html',
	'text!templates/reports/webreporter/tpl/spbOrdFoot.html',
	'text!templates/reports/webreporter/tpl/spbFullHead.html',
	'text!templates/reports/webreporter/tpl/spbFullFoot.html',
	'text!templates/reports/webreporter/tpl/spbChart.html',
	'text!templates/reports/webreporter/tpl/menuOptions.html'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	generalHbh,
	waypoints,
	reportCollection,
	menuCollection,
	filters,
	tableRowView,
	paginationView,
	allPoHeadTpl,
	allPoFootTpl,
	allQotHeadTpl,
	allQotFootTpl,
	allReqHeadTpl,
	allReqFootTpl,
	allRfqHeadTpl,
	poSupplierHeadTpl,
	poSupplierFootTpl,
	poVesselHeadTpl,
	poVesselFootTpl,
	txnSupplierHeadTpl,
	txnSupplierFootTpl,
	txnVesselHeadTpl,
	txnVesselFootTpl,
	spbSumHeadTpl,
	spbSumFootTpl,
	spbSumPendHeadTpl,
	spbSumPendFootTpl,
	spbRfqHeadTpl,
	spbRfqFootTpl,
	spbQotHeadTpl,
	spbQotFootTpl,
	spbQotPendHeadTpl,
	spbQotPendFootTpl,
	spbOrdHeadTpl,
	spbOrdFootTpl,
	spbFullHeadTpl,
	spbFullFootTpl,
	spbChartTpl,
	menuOptionsTpl
){
	var webreporterView = Backbone.View.extend({
		el: $('body'),

		formData: {
			isRefresh: 1,
			isDrill: 0,
			rptType: "STD",
			prevSpbTnid: 0,
			prevSpbName: "All suppliers",
			prevVesselId: 0,
			prevVesselName: "All vessels",
			rfqIsDec: 0,
			ordIsAcc: 0,
			ordIsDec: 0,
			ordIsPoc: 0,
			rptIsAsc: 1
		},
		returnTo: 'main',
		page: 1,
		csv: 0,
		spbReport: 'summary',
		runClicked: false,
		spbChartLoaded: false,
		lastFormContent: null,

		allPoHeadTemplate: Handlebars.compile(allPoHeadTpl),
		allPoFootTemplate: Handlebars.compile(allPoFootTpl),
		allQotHeadTemplate: Handlebars.compile(allQotHeadTpl),
		allQotFootTemplate: Handlebars.compile(allQotFootTpl),
		allReqHeadTemplate: Handlebars.compile(allReqHeadTpl),
		allReqFootTemplate: Handlebars.compile(allReqFootTpl),
		allRfqHeadTemplate: Handlebars.compile(allRfqHeadTpl),
		poSupplierHeadTemplate: Handlebars.compile(poSupplierHeadTpl),
		poSupplierFootTemplate: Handlebars.compile(poSupplierFootTpl),
		poVesselHeadTemplate: Handlebars.compile(poVesselHeadTpl),
		poVesselFootTemplate: Handlebars.compile(poVesselFootTpl),
		txnSupplierHeadTemplate: Handlebars.compile(txnSupplierHeadTpl),
		txnSupplierFootTemplate: Handlebars.compile(txnSupplierFootTpl),
		txnVesselHeadTemplate: Handlebars.compile(txnVesselHeadTpl),
		txnVesselFootTemplate: Handlebars.compile(txnVesselFootTpl),
		spbSumHeadTemplate: Handlebars.compile(spbSumHeadTpl),
		spbSumFootTemplate: Handlebars.compile(spbSumFootTpl),
		spbSumPendHeadTemplate: Handlebars.compile(spbSumPendHeadTpl),
		spbSumPendFootTemplate: Handlebars.compile(spbSumPendFootTpl),
		spbRfqHeadTemplate: Handlebars.compile(spbRfqHeadTpl),
		spbRfqFootTemplate: Handlebars.compile(spbRfqFootTpl),
		spbQotHeadTemplate: Handlebars.compile(spbQotHeadTpl),
		spbQotFootTemplate: Handlebars.compile(spbQotFootTpl),
		spbQotPendHeadTemplate: Handlebars.compile(spbQotPendHeadTpl),
		spbQotPendFootTemplate: Handlebars.compile(spbQotPendFootTpl),
		spbOrdHeadTemplate: Handlebars.compile(spbOrdHeadTpl),
		spbOrdFootTemplate: Handlebars.compile(spbOrdFootTpl),
		spbFullHeadTemplate: Handlebars.compile(spbFullHeadTpl),
		spbFullFootTemplate: Handlebars.compile(spbFullFootTpl),
		spbChartTemplate: Handlebars.compile(spbChartTpl),
		menuOptionsTemplate: Handlebars.compile(menuOptionsTpl),

		events: {
			'click h1 .return' : 'returnToPrevious',
			'click table.data.head tr th a' : 'setOrder',
			'click .spbBtns a' : 'spbTabClicked',
			'click .fitReport' : 'toggleFitTable'
 		},

		initialize: function() {
			this.filtersView = new filters();
			this.filtersView.parent = this;

			this.reportCollection = new reportCollection();
			//this.reportCollection.url = "/services/webreporter/";
			this.reportCollection.url = "/webreporter/report/";

			//Initalise menu collection
			this.menuCollection = new menuCollection();
			this.menuCollection.url = "/user/get-menu-options-to-display";
			

			$('body').ajaxStart(function(){
				$('#waiting').show();
			});

			$('body').ajaxStop(function(){
				$('#waiting').hide();
			});

			//this.loadMenus();
			this.waypointsSticky();
		},

		waypointsSticky: function(){
			var defaults, wrap;

		    defaults = {
		    	wrapper: '<div class="sticky-wrapper" />',
		    	stuckClass: 'stuck',
		    	direction: 'down right'
		    };

		    wrap = function($elements, options) {
		    	var $parent;

		    	$elements.wrap(options.wrapper);
		    	$parent = $elements.parent();
		    	return $parent.data('isWaypointStickyWrapper', true);
		    };
		    
		    $.waypoints('extendFn', 'sticky', function(opt) {
		    	var $wrap, options, originalHandler;

		    	options = $.extend({}, $.fn.waypoint.defaults, defaults, opt);
		    	$wrap = wrap(this, options);
		    	originalHandler = options.handler;
		    	options.handler = function(direction) {
		        
		        	var $sticky, shouldBeStuck;

		        	$sticky = $(this).children(':first');
		        	shouldBeStuck = options.direction.indexOf(direction) !== -1;
		        	$sticky.toggleClass(options.stuckClass, shouldBeStuck);
		        	$wrap.height(shouldBeStuck ? $sticky.outerHeight() : '');
		        	if (originalHandler != null) {
		          		return originalHandler.call(this, direction);
		        	}
		    	};
		    	
		    	options.offset = 120; 
		    	$wrap.waypoint(options);
		    	return this.data('stuckClass', options.stuckClass);
		    });
		    
		    return $.waypoints('extendFn', 'unsticky', function() {
		    	var $parent;

		    	$parent = this.parent();
		    	if (!$parent.data('isWaypointStickyWrapper')) {
		    		return this;
		    	}
		    	$parent.waypoint('destroy');
		    	this.unwrap();
		    	return this.removeClass(this.data('stuckClass'));
		    });
		},

		getCsvData:function(){
			var thisView = this;
			if($('select#companyName').val().substring(0,3) === "ALL"){
				this.formData.iscnsldt = 1;
				var split = $('select#companyName').val().split('-');
				this.formData.bybTnid = split[1];
			}
			else {
				this.formData.iscnsldt = 0;
			}
			var rptTitle,
				rptDtRange;

			switch(this.formData.rptCode) {
			    case "GET-ALL-ORD":
			        rptTitle = "All POs";
			        break;
			    case "GET-ALL-RFQ":
			        rptTitle = "All RFQs";
			        break;
			    case "GET-ORD-SUPPLIERS":
			        rptTitle = "POs by Supplier";
			        break;
			    case "GET-ORD-VESSELS":
			        rptTitle = "POs by Vessel";
			        break;
			    case "GET-SPB-ANALYSIS":
			        rptTitle = "Supplier Analysis";
			        break;
			    case "GET-TXN-SUPPLIERS":
			        rptTitle = "Transactions by Supplier";
			        break;
			    case "GET-TXN-VESSELS":
			        rptTitle = "Transactions by Vessel";
			        break;
			    case "GET-ALL-ORD-MMS":
			    	rptTitle = "All POs searchable";
			}
			switch($('#dateRange').val()){
				case "CSTMDTS":
					rptDtRange = "CSTMDTS";
					break;
				case "1":
					rptDtRange = "PRV01MO";
					break;
				case "3":
					rptDtRange = "PRV03MO";
					break;
				case "6":
					rptDtRange = "PRV06MO";
				case "12":
					rptDtRange = "PRV12MO";
				case "1Y":
					rptDtRange = "PRVYEAR";
				case "1W":
					rptDtRange = "PRVWEEK";
			}
			$.ajax({
                url      : thisView.reportCollection.url,
                type     : 'POST',
                data     : {
                	_params: JSON.stringify({
						"_session_id" : this.formData.session,
						"_app_code" : "WebReporter",
						"_rpt_code" : this.formData.rptCode,
						"_rpt_type" : "STD",
						"_from_date" : this.formData.fromDate,
						"_to_date" : this.formData.toDate,
						"_rpt_title" : rptTitle,
						"_rpt_header" : "header",
						"_sort_field" : this.formData.rptOrd,
						"_sort_is_asc" : this.formData.rptIsAsc,
						"_rows_per_page" : this.formData.rows,
						"_action" : "CSV",
						"_prev_page" : 0,
						"_curr_page" : this.page, 
						"_vessel_id" : this.formData.vesselId,
						"_vessel_name" : this.formData.vesselName,
						"_rpt_is_rfrsh" : this.formData.isRefresh,
						"_rpt_is_cnsldt" : this.formData.iscnsldt,
						"_rpt_is_inctst" : 0,
						"_byb_tnid" : this.formData.bybTnid,
						"_byb_name" : this.formData.bybName,
						"_spb_tnid" : this.formData.spbTnid,
						"_spb_name" : this.formData.spbName,
						"_user_name" : this.formData.username,
						"_ord_ref_no" : "NA",
						"_ord_int_no" : 0,
						"_rate_code" : this.formData.currency,
						"_cntc_code" : this.formData.bybCntc,
						"_cntc_name" : this.formData.bybCntcName,
						"_rfq_is_dec" : this.formData.rfqIsDec,
						"_ord_is_acc" : this.formData.ordIsAcc,
						"_ord_is_dec" : this.formData.ordIsDec,
						"_ord_is_poc" : this.formData.ordIsPoc,
						"_rfq_cutoff_days" : this.formData.rfqCutoff,
						"_qot_cutoff_days" : this.formData.qotCutoff,
						"_ord_cutoff_days" : this.formData.ordCutoff,
						"_ord_prchsr_code" : "NA",
						"_rpt_as_of_date" : this.formData.rptAsOfDate,
						"_num_fmt" : this.formData.numFormat,
						//"_date_range" : rptDtRange,
						"_date_range" : this.formData.dateRange,
						"_spb_anlyss_ctrl" : "SUMM",
						"_rtn_title" : "Return to main page",
						"_ord_srch"  : this.formData.rptSrch
					})
                },
                tries    : 0,
                retries  : 1,
                timeout  : 1200000,
                dataType : 'json',

                error : function(request, status, error) {

                    if (status === 'timeout') {

                        this.tries++;
                        if (this.tries <= this.retries) { $.ajax(this); return; }

                    } else {
                        if (status === 'error') { alert('Error: ' + error); }
                        if (this.tries > 1) { alert('Oops! There seems to be a connection timeout, please try again later.'); }
                    }
                },

                success : function(json) {
                	
                    if (typeof json === 'undefined' || typeof json.link === 'undefined' || typeof json.status === 'undefined') {
                        alert('No data has been found for the requested report parameters.');
                        return false;
                    }

                    if (json.status !== 'ok') {
                        alert(json.status);
                        return false;
                    }

                    window.location.assign(json.link);
                    return true;
                }
            });
		},

		getData: function(refresh){
			
			var formContent = $("form.filtersForm").serialize();

			if (this.lastFormContent != formContent) 
			{
				//Report form content changed, we have to reload the chart
				this.lastFormContent = formContent;
				this.spbChartLoaded = false;
			}
			
			if(!refresh){
				var refresh = 0;
			}
			else {
				refresh = 1;
			}

			this.formData.isRefresh = refresh;


			if(this.formData.isRefresh == 1 && (this.formData.rptCode == "GET-ALL-ORD" || this.formData.rptCode == "GET-ALL-QOT" || this.formData.rptCode == "GET-ALL-RFQ" || this.formData.rptCode == "GET-ALL-REQ")){
	    		this.formData.rptIsAsc = 0;
	    	}
	    	else if(this.formData.isRefresh == 1) {
	    		this.formData.rptIsAsc = 1;
	    	}

			var thisView = this;
			if($('select#companyName').val().substring(0,3) === "ALL") {
				this.formData.iscnsldt = 1;
				var split = $('select#companyName').val().split('-');
				this.formData.bybTnid = split[1];
			}
			else {
				this.formData.iscnsldt = 0;
			}
			this.reportCollection.reset();
			this.reportCollection.fetch({
				type: 'POST',
				data: $.param({
					_params: JSON.stringify({
						"_session_id" : this.formData.session,
						"_app_code" : "WebReporter",
						"_rpt_code" : this.formData.rptCode,
						"_rpt_type" : "STD",
						"_from_date" : this.formData.fromDate,
						"_to_date" : this.formData.toDate,
						"_rpt_title" : "title",
						"_rpt_header" : "header",
						"_sort_field" : this.formData.rptOrd,
						"_sort_is_asc" : this.formData.rptIsAsc,
						"_rows_per_page" : this.formData.rows,
						"_action" : "RPT",
						"_prev_page" : 0,
						"_curr_page" : this.page, 
						"_vessel_id" : this.formData.vesselId,
						"_vessel_name" : this.formData.vesselName,
						"_rpt_is_rfrsh" : this.formData.isRefresh,
						"_rpt_is_cnsldt" : this.formData.iscnsldt,
						"_rpt_is_inctst" : 0,
						"_byb_tnid" : this.formData.bybTnid,
						"_byb_name" : this.formData.bybName,
						"_spb_tnid" : this.formData.spbTnid,
						"_spb_name" : this.formData.spbName,
						"_user_name" : this.formData.username,
						"_ord_ref_no" : "NA",
						"_ord_int_no" : 0,
						"_rate_code" : this.formData.currency,
						"_cntc_code" : this.formData.bybCntc,
						"_cntc_name" : this.formData.bybCntcName,
						"_rfq_is_dec" : this.formData.rfqIsDec,
						"_ord_is_acc" : this.formData.ordIsAcc,
						"_ord_is_dec" : this.formData.ordIsDec,
						"_ord_is_poc" : this.formData.ordIsPoc,
						"_rfq_cutoff_days" : this.formData.rfqCutoff,
						"_qot_cutoff_days" : this.formData.qotCutoff,
						"_ord_cutoff_days" : this.formData.ordCutoff,
						"_ord_prchsr_code" : "NA",
						"_rpt_as_of_date" : this.formData.rptAsOfDate,
						"_num_fmt" : this.formData.numFormat,
						"_date_range" : this.formData.dateRange,
						"_spb_anlyss_ctrl" : "SUMM",
						"_rtn_title" : "Return to main page",
						"_ord_srch"  : this.formData.rptSrch
					})
				}),
				complete: function(){
					if(thisView.reportCollection.models.length > 0){
						if(thisView.formData.isRefresh === 1){
							thisView.totals = thisView.reportCollection.models[0].attributes.totals;
							thisView.rowCount = thisView.reportCollection.models[0].attributes.rowCount;
						}
						thisView.render();
					}
					else {
						alert('No data has been found for the requested report parameters.');
						thisView.returnToPrevious();
					}
				}
			});
		},

		setOrder: function(e){
			e.preventDefault();
			this.formData.rptOrd = $(e.target).attr('href');
			
			/*$(e.target).addClass('ord');*/
			
			if($(e.target).hasClass('asc')){
				this.formData.rptIsAsc = 0;
			}
			else {
				this.formData.rptIsAsc = 1;
			}
			this.getData();
		},

		setTemplate: function(){
			switch(this.formData.rptCode) {
			    case "GET-ALL-ORD":
			   	  this.totals.iscnsldt = this.formData.iscnsldt;
			        var html = {
			        	headHtml : this.allPoHeadTemplate(this.formData),
			        	footHtml : this.allPoFootTemplate(this.totals)
			        };

			        if(this.returnTo == 'posup'){
			        	$('h1.header span').text('Standard Reports - POs by Supplier - All POs');
			        }
			        else if(this.returnTo == 'poves'){
			        	$('h1.header span').text('Standard Reports - POs by Vessel - All POs');
			        }
			        else if(this.returnTo == 'txnSup' && this.formData.ordIsAcc == 0 && this.formData.ordIsDec == 0 && this.formData.ordIsPoc == 0) {
			        	$('h1.header span').text('Standard Reports - Transactions by Supplier - All POs');
			        }
			        else if(this.returnTo == 'txnSup' && this.formData.ordIsAcc == 1) {
			        	$('h1.header span').text('Standard Reports - Transactions by Supplier - Acc. POs');
			        }
			        else if(this.returnTo == 'txnSup' && this.formData.ordIsDec == 1) {
			        	$('h1.header span').text('Standard Reports - Transactions by Supplier - Dec. POs');
			        }
			        else if(this.returnTo == 'txnSup' && this.formData.ordIsPoc == 1) {
			        	$('h1.header span').text('Standard Reports - Transactions by Supplier - POCs');
			        }
			        else if(this.returnTo == 'txnVes' && this.formData.ordIsAcc == 0 && this.formData.ordIsDec == 0 && this.formData.ordIsPoc == 0) {
			        	$('h1.header span').text('Standard Reports - Transactions by Vessel - All POs');
			        }
			        else if(this.returnTo == 'txnVes' && this.formData.ordIsAcc == 1) {
			        	$('h1.header span').text('Standard Reports - Transactions by Vessel - Acc. POs');
			        }
			        else if(this.returnTo == 'txnVes' && this.formData.ordIsDec == 1) {
			        	$('h1.header span').text('Standard Reports - Transactions by Vessel - Dec. POs');
			        }
			        else if(this.returnTo == 'txnVes' && this.formData.ordIsPoc == 1) {
			        	$('h1.header span').text('Standard Reports - Transactions by Vessel - POCs');
			        }
			        else {
			        	$('h1.header span').text('Standard Reports - All POs');
			        }

			        return html;
			        break;
			    case "GET-ALL-ORD-MMS":

			    	this.totals.iscnsldt = this.formData.iscnsldt;
			        var html = {
			        	headHtml : this.allPoHeadTemplate(this.formData),
			        	footHtml : this.allPoFootTemplate(this.totals)
			        };
			        $('h1.header span').text('Standard Reports - All POs Searchable');
			        
			        return html;
			        break;
			    case "GET-ALL-RFQ":
			    	var html = {
			    		headHtml : this.allRfqHeadTemplate(this.formData),
			    		footHtml : ""
			    	};

			    	if(this.returnTo == 'txnSup' && this.formData.rfqIsDec == 0){
			    		$('h1.header span').text('Standard Reports - Transactions by Supplier - All RFQs');
			    	}
			    	else if(this.returnTo == 'txnSup' && this.formData.rfqIsDec == 1){
			    		$('h1.header span').text('Standard Reports - Transactions by Supplier - Dec. RFQs');
			    	}
			    	else if(this.returnTo == 'txnVes' && this.formData.rfqIsDec == 0){
			    		$('h1.header span').text('Standard Reports - Transactions by Vessel - All RFQs');
			    	}
			    	else if(this.returnTo == 'txnVes' && this.formData.rfqIsDec == 1){
			    		$('h1.header span').text('Standard Reports - Transactions by Vessel - Dec. RFQs');
			    	}
			    	else {
			    		$('h1.header span').text('Standard Reports - All RFQs');
			    	}

			    	return html;
			    	break;
			    case "GET-ORD-SUPPLIERS":
			    	var html = {
			    		headHtml : this.poSupplierHeadTemplate(this.formData),
			    		footHtml : this.poSupplierFootTemplate(this.totals)
			    	};

			    	$('h1.header span').text('Standard Reports - POs by Supplier');

			    	return html;
			    	break;
			    case "GET-ORD-VESSELS":
			    	var html = {
			    		headHtml : this.poVesselHeadTemplate(this.formData),
			    		footHtml : this.poVesselFootTemplate(this.totals)
			    	};

			    	$('h1.header span').text('Standard Reports - POs by Vessel');

			    	return html;
			    	break;
			    case "GET-SPB-ANALYSIS":
					var chartData = {
				    	q1d: this.totals.qotlt1dpcttotal,
				    	q1d4d: this.totals.qot1d4dpcttotal,
				    	q4d: this.totals.qotgt4dpcttotal,
				    };
			    	var html = {
			    		sumHeadHtml : this.spbSumHeadTemplate(this.formData),
			    		sumFootHtml : this.spbSumFootTemplate(this.totals),
			    		sumPendHeadHtml : this.spbSumPendHeadTemplate(this.formData),
			    		sumPendFootHtml : this.spbSumPendFootTemplate(this.totals),
			    		rfqHeadHtml : this.spbRfqHeadTemplate(this.formData),
			    		rfqFootHtml : this.spbRfqFootTemplate(this.totals),
			    		qotHeadHtml : this.spbQotHeadTemplate(this.formData),
			    		qotFootHtml : this.spbQotFootTemplate(this.totals),
			    		qotPendHeadHtml : this.spbQotPendHeadTemplate(this.formData),
			    		qotPendFootHtml : this.spbQotPendFootTemplate(this.totals),
			    		ordHeadHtml : this.spbOrdHeadTemplate(this.formData),
			    		ordFootHtml : this.spbOrdFootTemplate(this.totals),
			    		fullHeadHtml : this.spbFullHeadTemplate(this.formData),
			    		fullFootHtml : this.spbFullFootTemplate(this.totals),
			    		spbChartHtml : this.spbChartTemplate(chartData)
			    	};

			    	$('h1.header span').text('Standard Reports - Supplier Analysis');

			    	return html;
			    	break;
			    case "GET-TXN-SUPPLIERS":
			    	var html = {
			    		headHtml : this.txnSupplierHeadTemplate(this.formData),
			    		footHtml : this.txnSupplierFootTemplate(this.totals)
			    	};

			    	$('h1.header span').text('Standard Reports - Transactions by Supplier');

			    	return html;
			    	break;
			    case "GET-TXN-VESSELS":
			    	var html = {
			    		headHtml : this.txnVesselHeadTemplate(this.formData),
			    		footHtml : this.txnVesselFootTemplate(this.totals)
			    	};

			    	$('h1.header span').text('Standard Reports - Transactions by Vessels');

			    	return html;
			    	break;
			    case "GET-ALL-QOT":
			    	this.totals.iscnsldt = this.formData.iscnsldt;
			    	var html = {
			    		headHtml : this.allQotHeadTemplate(this.formData),
			    		footHtml : this.allQotFootTemplate(this.totals)
			    	};

			    	if(this.returnTo == 'txnSup'){
			        	$('h1.header span').text('Standard Reports - Transactions by Supplier - All QOTs');
			        }
			        else if(this.returnTo == 'txnVes'){
			        	$('h1.header span').text('Standard Reports - Transactions by Vessel - All QOTs');
			        }
			        else {
			        	$('h1.header span').text('Standard Reports - All QOTs');
			        }

			    	return html;
			    	break;
			    case "GET-ALL-REQ":
			   		this.totals.iscnsldt = this.formData.iscnsldt;
			    	var html = {
			    		headHtml : this.allReqHeadTemplate(this.formData),
			    		footHtml : this.allReqFootTemplate(this.totals)
			    	};

			        if(this.returnTo == 'txnVes'){
			        	$('h1.header span').text('Standard Reports - Transactions by Vessel - All REQs');
			        }
			        else {
			        	$('h1.header span').text('Standard Reports - All REQs');
			        }

			    	return html;
			    	break;
			}
		},

		render: function(){
			$('.toStick').removeClass('stuck');

			var html = this.setTemplate();

			if(this.formData.isDrill == 0 && this.formData.rptCode != "GET-SPB-ANALYSIS") {
				this.spbChartLoaded = false;
				$('.reportData table.data.head thead tr').html(html.headHtml);
				$('.reportData table.data.foot thead tr').html(html.footHtml);
				$('.reportData table.data tbody').html('');
		    	$('h1.header .return').show();
		    	$('.subReportData').hide();
		    	$('.reportData').show();
		    	$('.spbBtns').hide();
			}
			else if(this.formData.rptCode != "GET-SPB-ANALYSIS") {
				this.spbChartLoaded = false;
				$('.subReportData table.data.head thead tr').html(html.headHtml);
				$('.subReportData table.data.foot thead tr').html(html.footHtml);
				$('.subReportData table.data tbody').html('');
				$('.subReportData').show();
				$('.reportData').hide();
				$('.spbBtns').hide();
			}
			else {
				$('.spbReportData.rptData table.data tbody').html('');
				$('#spbChart').hide();
				/* $('#spbChart').html(''); */
				switch (this.spbReport){
					case "summary":
						$('.spbReportData.rptData table.data.head thead tr').html(html.sumHeadHtml);
						$('.spbReportData.rptData table.data.foot thead tr').html(html.sumFootHtml);
						break;
					case "sumPendQot":
						$('.spbReportData.rptData table.data.head thead tr').html(html.sumPendHeadHtml);
						$('.spbReportData.rptData table.data.foot thead tr').html(html.sumPendFootHtml);
						break;
					case "rfqKpi":
						$('.spbReportData.rptData table.data.head thead tr').html(html.rfqHeadHtml);
						$('.spbReportData.rptData table.data.foot thead tr').html(html.rfqFootHtml);
						$('#spbChart').show();
						if (this.spbChartLoaded == false) {
							this.spbChartLoaded = true;
							$('#spbChart').html(html.spbChartHtml);
							this.renderChart();
						}
						break;
					case "qotKpi":
						$('.spbReportData.rptData table.data.head thead tr').html(html.qotHeadHtml);
						$('.spbReportData.rptData table.data.foot thead tr').html(html.qotFootHtml);
						break;
					case "qotKpiPendQot":
						$('.spbReportData.rptData table.data.head thead tr').html(html.qotPendHeadHtml);
						$('.spbReportData.rptData table.data.foot thead tr').html(html.qotPendFootHtml);
						break;
					case "ordKpi":
						$('.spbReportData.rptData table.data.head thead tr').html(html.ordHeadHtml);
						$('.spbReportData.rptData table.data.foot thead tr').html(html.ordFootHtml);
						break;
					case "full":
						$('.spbReportData.rptData table.data.head thead tr').html(html.fullHeadHtml);
						$('.spbReportData.rptData table.data.foot thead tr').html(html.fullFootHtml);
						$('#spbChart').show();
						if (this.spbChartLoaded == false) {
							this.spbChartLoaded = true;
							$('#spbChart').html(html.spbChartHtml);
						}
						this.renderChart();
						break;

				}
				$('h1.header .return').show();
				$('.subReportData').hide();
				$('.reportData').hide();
				$('.spbBtns').show();
				$('.spbReportData').show();

			}

			$('table.data.head thead tr th a').removeClass('ord');
			$('table.data.head thead tr th a').removeClass('asc');
			$('table.data.head thead tr th a').removeClass('desc');

			$('table.data.head thead tr th a[href="' + this.formData.rptOrd + '"]').addClass('ord');
			if(this.formData.rptIsAsc == 1){
				$('table.data.head thead tr th a[href="' + this.formData.rptOrd + '"]').addClass('asc');
			}
			else {
				$('table.data.head thead tr th a[href="' + this.formData.rptOrd + '"]').addClass('desc');
			}

			if(this.reportCollection.models.length == 0){
				paginationView.parent = this;
				paginationView.paginationLimit = this.paginationLimit;
				paginationView.page = this.page;
				paginationView.render(1);
			}
			else {
				_.each(this.reportCollection.models, function(item) {
			        this.renderItem(item);
			    }, this);

				if(this.formData.spbTnid == 0) {
					$('select#supplier').html('<option value="0" selected="selected">All suppliers</option>');
				}
				else {
					$('select#supplier').html('<option value="0">All suppliers</option>');
				}
				
			    _.each(this.reportCollection.models[0].attributes.suppliers, function(item){
			    	var optHtml = '<option value="'+ item.spbtnid + '"';
		    		if(item.spbtnid == this.formData.spbTnid){
		    			optHtml += ' selected="selected"';
		    		}
		    		optHtml += '>'+ item.spbname + '</option>';
			    	$('select#supplier').append(optHtml);
			    }, this);
			    
			    if(this.formData.vesselId == 0) {
					$('select#vessel').html('<option value="0" selected="selected">All vessels</option>');
				}
				else {
					$('select#vessel').html('<option value="0">All Vessels</option>');
				}

			    _.each(this.reportCollection.models[0].attributes.vessels, function(item){
			    	var optHtml = '<option value="'+ item.vesid + '"';
		    		if(item.vesid == this.formData.vesselId){
		    			optHtml += ' selected="selected"';
		    		}
		    		optHtml += '>' + item.vesimo + ' - ' + item.vesname + '</option>';
			    	$('select#vessel').append(optHtml);
			    }, this);

			    if(this.formData.bybCntc == "NA") {
					$('select#contact').html('<option value="0" selected="selected">All contacts</option>');
				}
				else {
					$('select#contact').html('<option value="0">All Contacts</option>');
				}

			    _.each(this.reportCollection.models[0].attributes.contacts, function(item){
			    	var optHtml = '<option value="'+ item.cntccode + '"';
		    		if(item.cntccode == this.formData.bybCntc){
		    			optHtml += ' selected="selected"';
		    		}
		    		optHtml += '>' + item.cntcname + '</option>';
			    	$('select#contact').append(optHtml);
			    }, this);

			    $.uniform.update();
			    
			    //pass params to pagination
				paginationView.parent = this;
				paginationView.paginationLimit = this.formData.rows;
				paginationView.page = this.page;
				paginationView.render(this.rowCount);
	    	}

	    	$('table.data').show();
	    	$('.pagination').show();

	    	var html = "Showing ";
	    	html += (this.formData.rows * (this.page - 1)) + 1;
	    	html += " - ";
    		if(this.reportCollection.models.length < this.formData.rows){
    			html = this.rowCount;
    		}
    		else {
    			html += this.formData.rows * this.page;
	    	}

	    	var rowCount = this.rowCount;
	    	var type = typeof rowCount;
			if(type !== 'number'){
				rowCount = parseFloat(rowCount);
			}

			rowCount = rowCount.toFixed(0);
			rowCount = rowCount.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
	    	html += ' out of ';
	    	html += rowCount;
	    	html += ' records';
    		
    		if(this.formData.rptCode == "GET-SPB-ANALYSIS"){
				$('.spbReportData .recordInfo').html(html);
				var elem = ".spbReportData .toStick";

				if($('.spbReportData .sticky-wrapper').length < 1)
	    		{
	    			$('.spbReportData .toStick').waypoint('sticky');
	    		}
    		}
    		else if(this.formData.isDrill == 1){
    			$('.subReportData .recordInfo').html(html);
    			var elem = ".subReportData .toStick";

    			if($('.subReportData .sticky-wrapper').length < 1)
	    		{
	    			$('.subReportData .toStick').waypoint('sticky');
	    		}
    		}
    		else {
    			$('.reportData .recordInfo').html(html);
    			var elem = ".reportData .toStick";

    			if($('.reportData .sticky-wrapper').length < 1){
		    		$('.reportData .toStick').waypoint('sticky');
		    	}
    		}

   			$(elem).width($(elem).parent().parent().find('table.data.body').width());
	    	
			$(window).scroll(function() {
			    var currPos = $(document).scrollLeft();
			    $('.toStick').css('left', 14 - currPos);
			});

	    	var thisView = this;
            thisView.fixHeight();

            $(window).resize(function(){

	    		thisView.fixHeight();

	    		if(thisView.formData.rptCode == "GET-SPB-ANALYSIS"){
	    			var elem = ".spbReportData .toStick";
	    		}
	    		else if(thisView.formData.isDrill == 1) {
	    			var elem = ".subReportData .toStick";
	    		}
	    		else {
	    			var elem = ".reportData .toStick";
	    		}
	    		if(!$(elem).hasClass('fit')){
	    			if($(elem).width() >= 916) {
		    			$(elem).width($(window).width() - 276);
		    			$(elem).parent().parent().find('table.data.body').width($(window).width() - 276);
		    			$(elem).parent().parent().find('table.data.foot').width($(window).width() - 276);
		    		}
		    		else {
		    			$(elem).width(916);
		    			$(elem).parent().parent().find('table.data.body').width(916);
		    			$(elem).parent().parent().find('table.data.foot').width(916);
		    		}
	    		}
	    	});
		},

		renderItem: function(item) {
		    var tableRow = new tableRowView({
		        model: item
		    });

		    tableRow.parent = this;

		    if(this.formData.isDrill == 0 && this.formData.rptCode != "GET-SPB-ANALYSIS") {
		    	$('.reportData table.data tbody').append(tableRow.render().el);
		    }
		    else if(this.formData.rptCode != "GET-SPB-ANALYSIS"){
		    	$('.subReportData table.data tbody').append(tableRow.render().el);
		    }
		    else {
		    	$('.spbReportData.rptData table.data tbody').append(tableRow.render().el);
		    }
		},

		returnToPrevious: function(e){

			if ($('#spbChart').length != 0) {
				this.spbChartLoaded = false;
				$('#spbChart').html('');
			}
			
			$('.toStick').removeClass('stuck');
			if(e){
				e.preventDefault();	
			}
			
			if(this.formData.isDrill == 1){
				this.getPrevData();
			}

			this.formData.rptType = "STD";
			switch(this.returnTo){
				case "main":
					this.returnToMain();
					break;
				case "posup":
					this.returnToPoSupplier();
					break;
				case "poves":
					this.returnToPoVessel();
					break;
				case "txnSup":
					this.returnToTxnSupplier();
					break;
				case "txnVes":
					this.returnToTxnVessel();
					break;
			}

			$.uniform.update();

			//fix height of body container due to absolute pos of content container
	    	var height = 0;
	    	if($('#content').height() < $('#sidebar').height()){
	    		height = $('#sidebar').height();
	    	}
	    	else {
	    		height = $('#content').height() + 25;
	    	}

	    	$('#body').height(height);

	    	$(window).resize(function(){
	    		$('#body').height(height);
	    	});
		},

		returnToMain: function() {
			this.runClicked = false;
			$('.changedMsg').hide();
			$('.spbReportData').removeClass('fixed');
			$('#header').removeClass('fixed');
			$('.divider').removeClass('fixed');
			$('.reportData').hide();
			$('.spbReportData').hide();
			$('#uniform-reportType').show();
			$('label[for="reportType"]').show();
			$('#infoBox').show();
			$('#rptSubFilters').hide();
			$('h1.header .return').hide();
			$('.pagination').hide();
			
			$('form.new .filters').removeClass('sub');
			$('form.new .actions').removeClass('sub');
			
			$('h1.header span').text('Standard Reports');
			
			$('select#supplier option').removeAttr('selected');
			$('select#supplier option[value="0"]').attr('selected', 'selected');
	    	$('select#vessel option').removeAttr('selected');
	    	$('select#vessel option[value="0"]').attr('selected', 'selected');
	    	$('select#contact option').removeAttr('selected');
	    	$('select#contact option[value="NA"]').attr('selected', 'selected');
		},

		returnToPoSupplier: function(){

			$('h1 .return').text('Return to main page');

			$('.subReportData').hide();
			$('.reportData').show();

			$('select#reportType option').removeAttr('selected');
	    	$('select#reportType option[value="GET-ORD-SUPPLIERS:SPBNAME"]').attr('selected', 'selected');
	    	
	    	$('select#supplier').removeAttr('disabled');
	    	$('select#vessel').removeAttr('disabled');
	    	$('select#contact').removeAttr('disabled');
	    	
	    	this.formData.rptCode = "GET-ORD-SUPPLIERS";
	    	this.formData.rptOrd = "SPBNAME";
	    	
	    	this.formData.spbTnid = this.prevSpbTnid;
	    	this.formData.spbName = this.prevSpbName;
	    	$('select#supplier option').removeAttr('selected');
	    	$('select#supplier option[value="' + this.formData.spbTnid + '"]').attr('selected', 'selected');

	    	$('h1.header span').text('Standard Reports - POs by Supplier');

	    	this.returnTo = "main";
		},

		returnToPoVessel: function(){
			$('h1 .return').text('Return to main page');

			$('.subReportData').hide();
			$('.reportData').show();

			$('select#reportType option').removeAttr('selected');
	    	$('select#reportType option[value="GET-ORD-VESSELS:VESNAME"]').attr('selected', 'selected');
	    	
	    	$('select#supplier').removeAttr('disabled');
	    	$('select#vessel').removeAttr('disabled');
	    	$('select#contact').removeAttr('disabled');
	    	
	    	this.formData.rptCode = "GET-ORD-VESSELS";
	    	this.formData.rptOrd = "VESNAME";
	    	
	    	this.formData.vesselId = this.prevVesselId;
	    	this.formData.vesselName = this.prevVesselName;

	    	$('select#vessel option').removeAttr('selected');
	    	$('select#vessel option[value="' + this.formData.vesselId + '"]').attr('selected', 'selected');

	    	$('h1.header span').text('Standard Reports - POs by Vessel');

	    	this.returnTo = "main";
		},

		returnToTxnSupplier: function(){
			$('h1 .return').text('Return to main page');

			$('.subReportData').hide();
			$('.reportData').show();

			$('select#reportType option').removeAttr('selected');
	    	$('select#reportType option[value="GET-TXN-SUPPLIERS:SPBNAME"]').attr('selected', 'selected');
	    	
	    	$('select#supplier').removeAttr('disabled');
	    	$('select#vessel').removeAttr('disabled');
	    	$('select#contact').removeAttr('disabled');
	    	
	    	this.formData.rptCode = "GET-TXN-SUPPLIERS";
	    	this.formData.rptOrd = "SPBNAME";
	    	
	    	this.formData.spbTnid = this.prevSpbTnid;
	    	this.formData.spbName = this.prevSpbName;
	    	$('select#supplier option').removeAttr('selected');
	    	$('select#supplier option[value="' + this.formData.spbTnid + '"]').attr('selected', 'selected');

	    	$('h1.header span').text('Standard Reports - Transactions by Supplier');

	    	this.formData.rfqIsDec = 0;
	    	this.formData.ordIsAcc = 0;
	    	this.formData.ordIsDec = 0;
	    	this.formData.ordIsPoc = 0;

	    	$('select#reportType option[value="GET-ALL-QOT:QOTSUBDT"]').remove();

	    	this.returnTo = "main";
		},

		returnToTxnVessel: function(){
			$('h1 .return').text('Return to main page');

			$('.subReportData').hide();
			$('.reportData').show();

			$('select#reportType option').removeAttr('selected');
	    	$('select#reportType option[value="GET-TXN-VESSELS:VESNAME"]').attr('selected', 'selected');
	    	
	    	$('select#supplier').removeAttr('disabled');
	    	$('select#vessel').removeAttr('disabled');
	    	$('select#contact').removeAttr('disabled');
	    	
	    	this.formData.rptCode = "GET-TXN-VESSELS";
	    	this.formData.rptOrd = "VESNAME";
	    	
	    	this.formData.vesselId = this.prevVesselId;
	    	this.formData.vesselName = this.prevVesselName;
	    	$('select#vessel option').removeAttr('selected');
	    	$('select#vessel option[value="' + this.formData.vesselId + '"]').attr('selected', 'selected');

	    	$('h1.header span').text('Standard Reports - Transactions by Vessel');

	    	this.formData.rfqIsDec = 0;
	    	this.formData.ordIsAcc = 0;
	    	this.formData.ordIsDec = 0;
	    	this.formData.ordIsPoc = 0;

	    	$('select#reportType option[value="GET-ALL-QOT:QOTSUBDT"]').remove();
	    	$('select#reportType option[value="GET-ALL-REQ:REQSUBDT"]').remove();

	    	this.returnTo = "main";
		},

		getPrevData: function(){
			this.rowCount = this.prevRows;
			this.page = this.prevPage;
			this.formData.rows = this.prevRowLimit;
			this.formData.currency = this.prevCurr;
			this.formData.numFormat = this.prevNumFormat;
			this.formData.fromDate = this.prevPostFromDate;
			this.formData.toDate = this.prevPostToDate;
			this.formData.dateRange = this.prevDateRange;

			$('select#rows option').removeAttr('selected');
			$('select#rows option[value="' + this.formData.rows + '"]').attr('selected', 'selected');

			$('select#currency option').removeAttr('selected');
			$('select#currency option[value="' + this.formData.currency + '"]').attr('selected', 'selected');

			$('select#dateRange option').removeAttr('selected');
			$('select#dateRange option[value="' + this.formData.dateRange + '"]').attr('selected', 'selected');

			$('input[name="fromDate"]').val(this.prevFromDate);
			$('input[name="toDate"]').val(this.prevToDate);

			this.formData.isDrill = 0;

			paginationView.paginationLimit = this.formData.rows;
			paginationView.page = this.page;
			paginationView.render(this.rowCount);

			$('.toStick').removeClass('stuck');
		},

		spbTabClicked: function(e){
			e.preventDefault();
			$('.spbBtns a').removeClass('selected');
			$(e.target).addClass('selected');
			this.spbReport = $(e.target).attr('href');
			if($(e.target).attr('href') == "full"){
				$('.spbReportData').addClass('fixed');
				$('#header').addClass('fixed');
				$('.divider').addClass('fixed');
			}
			else {
				$('.spbReportData').removeClass('fixed');
				$('#header').removeClass('fixed');
				$('.divider').removeClass('fixed');
			}
			this.render();
		},

		toggleFitTable: function(e){
			e.preventDefault();
			var el = '.' + $(e.target).attr('href');

			if($(el).find('table').hasClass('fit')){
				$(el).find('.toStick').removeClass('fit');
				$(el).find('table.body').removeClass('fit');
				$(el).find('table.head').removeAttr('style');
				$(el).find('table.foot').removeAttr('style');
				$(el).find('table.head').show();
				$(el).find('table.foot').show();
				$(e.target).text('Expand table to fit data');
			}
			else {
				$(el).find('.toStick').addClass('fit');
				$(el).find('table.body').addClass('fit');
				$(el).find('table.head').width($(el).find('table.body').width());
				$(el).find('table.foot').width($(el).find('table.body').width());
				$(e.target).text('Fit table to screen');
			}
		},

		renderChart: function()
		{
			var barWidth = 450;
			var chartData = {
				    	rfqResponseRate: Math.round(this.totals.rfqtotalresppcttotal),
				    	q1dpc: Math.round(barWidth * this.totals.qotlt1dpcttotal / 100),
				    	q1d4dpc: Math.round(barWidth * this.totals.qot1d4dpcttotal / 100),
				    	q4dpc: Math.round(barWidth * this.totals.qotgt4dpcttotal / 100),
				    };

			setTimeout(function(){
				$('.radial-progress').attr('data-progress', chartData.rfqResponseRate);
				$('#pg1').css('width' , chartData.q1dpc+'px');
				$('#pg2').css('width' , chartData.q1d4dpc+'px');
				$('#pg3').css('width' , chartData.q4dpc+'px');
			}, 500);
                
		},

		loadMenus: function()
		{
			var thisView = this;
			var tabName = 'analyse';
			var url = window.location.href;
			var urlParts = url.split('?');
			if (urlParts.length > 1)
			{
				var params = urlParts[1].split('&');
				for (var key in params) {
					if (params[key] == "tab=shipmate") {
						tabName = 'shipmate';
						$('#analyseTab').removeClass('selected');
						$('#shipTab').addClass('selected');	
					}
				}
			}

			this.menuCollection.reset();

			this.menuCollection.fetch({
				type: 'POST',
				data: {
					'type': tabName
				},
				complete: function(){
					if(thisView.menuCollection.models.length > 0){
						if (thisView.menuCollection.models[0].attributes.loginStatus == true)
						{
							thisView.renderMenuOptions();
						} else {
							$('#sidebar').show();
						}
					} else {
						$('#sidebar').show();
					}

				},
				error: function() {
					$('#sidebar').show();
				}

			});
		},

		renderMenuOptions: function()
		{

			if (this.menuCollection.models[0].attributes.shipmate == true)
			{
				$('.shipTab').show();

			}

			var html = this.menuOptionsTemplate(this.menuCollection.models[0].attributes);
			$('#sidebar').html(html);
			$('#sidebar').show();
		},

        fixHeight: function () {

            //fix height of body container due to absolute pos of content container
            var height = 0;
            if($('#content').height() < $('#sidebar').height()){
                height = $('#sidebar').height();
            }
            else {
                height = $('#content').height() + 25;
            }

            $('#body').height(height);

        }

	});

	return new webreporterView();
});
