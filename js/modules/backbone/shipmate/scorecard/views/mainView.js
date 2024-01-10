define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
], function(
	$, 
	_, 
	Backbone, 
	Hb
){
	var mainView = Backbone.View.extend({
		
		events: {
			/* 'click ' : 'onDisableRightClick', */
		},
		md5sum: require('user/md5sum'),
		userCode: require('user/userCode'),
		socecardDomain: require('user/scorecardDomain'),
		scorecardAdvanced: require('user/scorecardAdvanced'),

		gURLETLSplrGMV: null,
		gURLETLStatsBySplrPHP: null,
		gURLETLStatsByByr: null,
		gURLETLByrSplrLst: null,
		gURLETLTxngVssl: null,
		gURLETLEstBybRmpUp: null,
		gURLETLAvePOValue: null,
		gURLS4545: null,
		monthDays: ['31', '28', '31', '30', '31', '30', '31', '31', '30', '31', '30', '31'],
		monthNames: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
		multiplier: 12, // Defaults to 1 month
		fromDay: -1,
		fromMonth: -1,
		fromYear: -1,
		toDay: -1,
		toMonth: -1,
		toYear: -1,
		toMaxDay:  -1,
		fromMonthName: '',
		toMonthName: '',

		initialize: function ()
		{
			var thisView = this;

			this.gURLETLSplrGMV = this.socecardDomain+"/ShipServ/xsql/";
			this.gURLETLStatsBySplrPHP = this.socecardDomain+"/ShipServ/xsql/";
			this.gURLETLStatsByByr = this.socecardDomain+"/ShipServ/xsql/";
			this.gURLETLByrSplrLst = this.socecardDomain+"/ShipServ/xsql/ETL_Buyer_Suppliers_List.xsql";
			this.gURLETLTxngVssl = this.socecardDomain+"/ShipServ/xsql/";
			this.gURLETLEstBybRmpUp = this.socecardDomain+"/ShipServ/xsql/ETL_Est_Buyer_RampUp.xsql";
			this.gURLETLAvePOValue = this.socecardDomain+"/ShipServ/xsql/ETL_AveragedPO_Value_NoMax.xsql";
			this.gURLS4545 =  this.socecardDomain+"/ShipServ/xsql/Telesales_SupplierPurchase_Value.xsql";

			String.prototype.trim = function() {
			 return this.replace(/^\s+|\s+$/g,"");
			};
			/* Dispable right click */
			$(document).ready(function(){
				/*
                // -- 34 Paul, 128 Silvia, 361 Kim
                if ( this.userCode != 8000023 && this.userCode != 8003769 && this.userCode != 8000024 && this.userCode != 441) {
                */
                if (thisView.scorecardAdvanced != true) {
                    document.getElementById( 'div_etlbyrsplrlst' ).style.display = 'none';
                    document.getElementById( 'div_etlbyrsplrlst' ).style.visibility = 'hidden';
					document.getElementById( 'section_supppurvalue' ).style.display = 'none';
                    document.getElementById( 'section_supppurvalue' ).style.visibility = 'hidden';
                }

				if (document.layers)
				{
					document.captureEvents(Event.MOUSEDOWN);
				}

				document.onmousedown=this.onDisableRightClick;
				
				/* Adding Click events */
				$("#body").click(function(e){
					thisView.onDisableRightClick(e);
				});

				$(".fnOpenReportWin").click(function(e){
					thisView.fnOpenReportWin(e);
				});

				$(".runETLStatsBySplrPHP").click(function(e){
					thisView.runETLStatsBySplrPHP(e);
				});

				$(".runETLSplrGMV").click(function(e){
					thisView.runETLSplrGMV(e);
				});

				$(".runETLStatsByByr").click(function(e){
					thisView.runETLStatsByByr(e);
				});

				$(".runETLByrSplrLst").click(function(e){
					thisView.runETLByrSplrLst(e);
				});

				$(".runETLTxngVssl").click(function(e){
					thisView.runETLTxngVssl(e);
				});

				$(".runETLEstBybRmpUp").click(function(e){
					thisView.runETLEstBybRmpUp(e);
				});

				$(".runETLAvePOValue").click(function(e){
					thisView.runETLAvePOValue(e);
				});

				$(".runS4545").click(function(e){
					thisView.runS4545(e);
				});
				
				/* Chakge form elements */

				$(".clickDateRange").change(function(e){
					thisView.clickDateRange(e);
				});

				$(".clickToDay").change(function(e){
					thisView.clickToDay(e);
				});

				$(".clickToMonth").change(function(e){
					thisView.clickToMonth(e);
				});

				$(".clickToYear").change(function(e){
					thisView.clickToYear(e);
				});

				thisView.render();
			});

		},

		render: function()
		{
			this.fixHeight();
			this.initDates();
		},

		onDisableRightClick: function(i)
		{

			var thisView = this;
			var message="Sorry, for security reasons your right mouse button has been disabled.";

			if (document.all)
			{

				if (event.button == 2)
				{
					alert(message);
					return false;
				}
			}

			if (document.layers)
			{
				if (i.which == 3)
				{
					alert(message);
					return false;
				}
			}
		},

		fixHeight: function()
		{

			var nHeight = $('#content').height()+25;

			if (nHeight > 0) {
			$('#body').height(nHeight);
	    		/* if ($(".benchTab").find('li:first').hasClass('selected') == true) { */
	    			if (true) {
	    			var newWidth = $(window).width()-260;
	    			if (newWidth<980)  {
							newWidth=980;
	    			}
					$('#content').css('width' , newWidth+'px');
	    		} else {
					$('#content').css('width' , 'auto');
	    		}
    		}
		},

		fnOpenReportWin: function(e)
		{
			var thisView = this;
			e.preventDefault();
		    var reporturl, newWin1;
		    var reportid = $(e.currentTarget).data('id');
		    var userCode = null;

			// form one

			myBars='directories=no,location=no,menubar=no,status=no,titlebar=yes,toolbar=yes,scrollbars=yes,resizable=yes';

			if (reportid=='prevday')
			{
				reporturl=this.socecardDomain+"/ShipServ/xsql/ScoreCardPD.xsql?";
			}

			if (reportid=='pdbwb')
			{
				reporturl=this.socecardDomain+"/ShipServ/xsql/ScoreCardPDBuyerwiseBreakup.xsql?";
			}
			if (reportid=='pdswb')
			{
				reporturl=this.socecardDomain+"/ShipServ/xsql/ScoreCardPDSupplierwiseBreakup.xsql?";
			}
			
			if (reportid=='newcbuyer')
			{
				reporturl=this.socecardDomain+"/ShipServ/xsql/BCP.xsql?max=30&skip=0&id='"+ document.controlp.newcbuyer.value+"'&";
			}

			if (reportid=='cbuyer')
			{
				reporturl=this.socecardDomain+"/ShipServ/xsql/BuyControlPanel.xsql?id='"+    document.controlp.cbuyer.value+"'&";
			}
			
			if (reportid=='csupplier')
			{
				reporturl=this.socecardDomain+"/ShipServ/xsql/SupControlPanel.xsql?id='"+    document.controlp.csupplier.value+"'&";
			}

			if (reportid=='pages_stats')
			{
	            reporturl=this.socecardDomain+"/ShipServ/xsql/ScoreCardPages.xsql?dt='"+document.pages_stats.AD17.value+"-"+document.pages_stats.AD18.value+"-"+document.pages_stats.AD19.value+"'&";
			}

			if (reportid=='list_verif')
			{
				reporturl=this.socecardDomain+"/ShipServ/xsql/RecentlyVerified.xsql?start_dt='"+document.frmverified.AD20.value+"-"+document.frmverified.AD21.value+"-"+document.frmverified.AD22.value+"'&end_dt='"+document.frmverified.AD23.value+"-"+document.frmverified.AD24.value+"-"+document.frmverified.AD25.value+"'&";
			}
			
			if (reportid=='inactive')
			{
				reporturl=this.socecardDomain+"/ShipServ/xsql/InactiveUsers.xsql?";
			}

			if (reportid=='wrongimo')
			{
				reporturl=this.socecardDomain+"/ShipServ/xsql/wrongimo.xsql?";
			}

			if (reportid=='buyer')
			{
				reporturl=this.socecardDomain+"/ShipServ/xsql/TransDetail.xsql?id='"+    document.penbuytxn.buyer.value+"'&";
			}

			if (reportid=='invoice' || reportid=='invoice2' || reportid=='invoice2a' || reportid=='invoice3' || reportid=='invoice4')
			{
			    //Access Restricted to Accounts
			    //These reports looks like deprecated, and they are removed from the view
			    userCode = this.userCode;
			    /*
				// 5-har, 24-kim, 44=colin, 34=paul, 78=shona
			    if(userCode != 5 && userCode != 8000024 && userCode != 44 && userCode != 8000023 && userCode != 78) {
				*/
			    if (this.scorecardAdvanced != true) {	
			    	alert("you do not have permission to access invoice details.");
			    	return;
			    }
			}

			if (reportid=='invoice')
			{
				reporturl=this.socecardDomain+"/ShipServ/xsql/Invoices/BuyerList.xsql?mth='"+    document.buyinv.AD2.value+"-"+document.buyinv.AD3.value+"'&";
			}
	
			if (reportid=='invoice2')
			{
				reporturl=this.socecardDomain+"/ShipServ/xsql/Invoices/BuyInvoice.xsql?usr='"+    document.buyinv.acnum.value+"'&mth='"+document.buyinv.AD4.value+document.buyinv.AD5.value+"'&";
			}
		
			if (reportid=='invoice2a')
			{
				reporturl=this.socecardDomain+"/ShipServ/xsql/Invoices/BuyInvoiceKemi.xsql?usr='"+    document.buyinv.acnum2a.value+"'&mth='"+document.buyinv.AD42a.value+document.buyinv.AD52a.value+"'&";
			}
			
			if (reportid=='invoice3')
			{
				reporturl=this.socecardDomain+"/ShipServ/xsql/Invoices/SupplierList.xsql?mth='"+document.buyinv.AD6.value+"-"+document.buyinv.AD7.value+"'&";
			}

			if (reportid=='invoice4')
			{
				reporturl=this.socecardDomain+"/ShipServ/xsql/Invoices/SupInvoice.xsql?usr="+    document.buyinv.supacnum.value+"&mth='"+document.buyinv.AD8.value+document.buyinv.AD9.value+"'&";
			}
			//End Form5

			if(reportid == 'con_stats_by_supp')
			{
				reporturl=this.socecardDomain+"/ShipServ/xsql/con_stats_by_supplier.xsql?dt='"+    document.constatsbysupp.month.value+"-"+document.constatsbysupp.year.value+"'&";
			}

			if(reportid == 'AttachmentListByBuyer')
			{
				reporturl=this.socecardDomain+"/ShipServ/xsql/FileAttachment.xsql?start_dt='"+document.attlist.AD11.value+"-"+document.attlist.AD12.value+"-"+document.attlist.AD13.value+"'&end_dt='"+document.attlist.AD14.value+"-"+document.attlist.AD15.value+"-"+document.attlist.AD16.value+"'&buyercode='"+document.attlist.buyerTNID.value+"'&";
			}

			if (reportid=='supppurvalue')
			{

				//Access Restricted to Accounts
				userCode = this.userCode;
				/*
				// 5-har, 24-kim, 44=colin, 34=paul, 78=shona, 289=bo, 57=mikael, 138=mia, 466=mslinger
				if( userCode != 8000024  && userCode != 8000023 && userCode != 78 && userCode != 57 && userCode != 8081319 && userCode != 138 && userCode != 8218778 && userCode != 8003769 && userCode != 8000024 && userCode != 8061221 && userCode != 441) {
				*/
				if (this.scorecardAdvanced != true) {
				    alert("you do not have permission to access supplier value details.");
				    return;
				}

				if((document.supppurvalue.optsuppname.value==="") && (document.supppurvalue.suppname.value===""))
				{
				    alert("please enter or select supplier");
				    return;
				}
				if((document.supppurvalue.suppname.value===""))
				{
				    reporturl=this.socecardDomain+"/ShipServ/xsql/SuppPurchaseValue.xsql?start_dt='"+document.supppurvalue.AE1.value+"-" +document.supppurvalue.AE2.value+"-"+document.supppurvalue.AE3.value+"'&end_dt='"+document.supppurvalue.AE4.value+"-"+document.supppurvalue.AE5.value+"-"+document.supppurvalue.AE6.value+"'&suppid="+document.supppurvalue.optsuppname.value+"&";
				}
				else
				{
					// uncommented robin 03nov06 when adding ablility to enter TNID directly
				    reporturl=this.socecardDomain+"/ShipServ/xsql/SuppPurchaseValue.xsql?start_dt='"+document.supppurvalue.AE1.value+"-" +document.supppurvalue.AE2.value+"-"+document.supppurvalue.AE3.value+"'&end_dt='"+document.supppurvalue.AE4.value+"-"+document.supppurvalue.AE5.value+"-"+document.supppurvalue.AE6.value+"'&suppid="+document.supppurvalue.suppname.value+"&";
					//document.supppurvalue.suppname.value="";
					//return;
				}

			}

			reporturl = reporturl + 'uid=' + thisView.userCode + "&cd='" + thisView.md5sum + "'";

			/* newWin1=open(reporturl, '',myBars); */
			newWin1 = this.openRedirect(reporturl, myBars);
		},

		runETLStatsBySplrPHP: function(e)
		{
			e.preventDefault();

            	var ind = $(e.currentTarget).data('ind');
            	var form = document.etlstatsbysplrphp;

                if (!this.areDatesValidated(form.fromday.value,
                    form.frommonth.value,
                    form.fromyear.value,
                    form.today.value,
                    form.tomonth.value,
                    form.toyear.value))
                {
                    return false;
                }

                var url = "";
                var spbname = "";
                var spbtnid = "";

                // Setting which one is being clicked
                if (ind == 1)
                {
                    if (this.trim(form.spbtnid.value) === "" ||
                        this.trim(form.spbtnid.value) === "All")
                    {
                        form.spbtnid.value = "All";
                        form.spbname.value = "All";
                        // url = gURLETLStatsBySplr + "ETL_Stats_By_Supplier_All.xsql";
                        url = this.socecardDomain+'/phpreports/etlstatsbysplr/index.php';
                    }
                    else
                    {
                        // Check if supplier TNID is numeric
                        if (isNaN(form.spbtnid.value)       ||
                            (form.spbtnid.value).length < 3 ||
                            parseFloat(form.spbtnid.value) < 200)
                        {
                            alert("Please provide a valid supplier TNID (at least > 199)");
                            return false;
                        }

                        form.spbname.value = "All";
                        url = this.gURLETLStatsBySplrPHP + "ETL_Stats_By_Supplier_TNID.xsql";
                    }
                }

                if (ind == 2)
                {
                    if (this.trim(form.spbname.value) === "" ||
                        this.trim(form.spbname.value) === "All")
                    {
                        form.spbtnid.value = "All";
                        form.spbname.value = "All";
                        // url = gURLETLStatsBySplr + "ETL_Stats_By_Supplier_All.xsql";
                    }
                    else
                    {
                        form.spbtnid.value = "All";
                        // url = gURLETLStatsBySplr + "ETL_Stats_By_Supplier_Name.xsql";
                    }

                    url = this.socecardDomain+'/phpreports/etlstatsbysplr/index.php';
                }

                spbname = form.spbname.value;
                spbtnid = form.spbtnid.value;
              
                // Preparing for report run
                var toDate = form.today.value + "-" +
                             form.tomonth.value + "-" +
                             form.toyear.value;

                var fromDate = form.fromday.value + "-" +
                               form.frommonth.value + "-" +
                               form.fromyear.value;

                var urlETLStatsBySplr = url +
                                        "?startdt='" + fromDate + "'" +
                                        "&enddt='" + toDate + "'" +
                                        "&spbtype='" + form.spbtype.value + "'" +
                                        "&bcbyb='" + form.bcbyb.value + "'" +
                                        "&spbtnid='" + spbtnid + "'" +
                                        "&spbname='" + spbname + "'" +
                                        "&uid=" + this.userCode + 
                                        "&cd='" + this.md5sum + "'";

                // Run!
                var options = "directories=no,location=no,menubar=no,status=no,titlebar=yes,toolbar=yes,scrollbars=yes,resizable=yes";

                /* var newWin = open(urlETLStatsBySplr, "", options); */
                var newWin = this.openRedirect(urlETLStatsBySplr, options);
                
                return true;
            },

			areDatesValidated: function(fromday, frommonth, fromyear, today, tomonth, toyear)
            {
                var toLimitDays = 31;
                var fromLimitDays = 31;
                var isInvalidToDate = false;
                var isInvalidFromDate = false;

                // Check empty validations
                if (fromday === "" || frommonth === "" || fromyear === "")
                {
                    isInvalidFromDate = true;
                }

                if (today === "" || tomonth === "" || toyear === "")
                {
                    isInvalidToDate = true;
                }

                // Get the limit days per month
                if (frommonth == "FEB")
                {
                    fromLimitDays = this.getLeapDays(fromyear);
                }

                if (frommonth == "APR" ||
                    frommonth == "JUN" ||
                    frommonth == "SEP" ||
                    frommonth == "NOV")
                {
                    fromLimitDays = 30;
                }

                if (tomonth == "FEB")
                {
                    toLimitDays = this.getLeapDays(toyear);
                }

                if (tomonth == "APR" ||
                    tomonth == "JUN" ||
                    tomonth == "SEP" ||
                    tomonth == "NOV")
                {
                    toLimitDays = 30;
                }

                // Check the inputted month-day pairs
                if (fromday > fromLimitDays)
                {
                    isInvalidFromDate = true;
                }

                if (today > toLimitDays)
                {
                    isInvalidToDate = true;
                }

                if (isInvalidFromDate)
                {
                    alert("Please provide a valid From Date input");
                    return false;
                }

                if (isInvalidToDate)
                {
                    alert("Please provide a valid To Date input");
                    return false;
                }

                // Compare the dates
                var toDate = tomonth + " " + today + ", " + toyear;
                var fromDate = frommonth + " " + fromday + ", " + fromyear;

                if (Date.parse(fromDate) > Date.parse(toDate))
                {
                    alert("Please provide a valid From Date that is less than or equal to the To Date");
                    return false;
                }

                return true;
            },

            trim: function(str)
            {
                return str.replace(/^\s+|\s+$/g,"");
            },

            // Get the February leap/non-leap days
            getLeapDays: function(year)
            {
                if (new Date(year, 2-1, 29).getDate() == 29)
                {
                    return 29;
                }
                else
                {
                    return 28;
                }
            },

			runETLSplrGMV: function(e)
            {
				e.preventDefault();

            	var ind = $(e.currentTarget).data('ind');
            	var form = document.etlsplrgmv;

                if (!this.areDatesValidated(form.fromday.value,
                    form.frommonth.value,
                    form.fromyear.value,
                    form.today.value,
                    form.tomonth.value,
                    form.toyear.value))
                {
                    return false;
                }

                if (!this.isRptDateValidated(form.rptday.value,
                    form.rptmonth.value,
                    form.rptyear.value,
                    form.today.value,
                    form.tomonth.value,
                    form.toyear.value))
                {
                    return false;
                }

                var url = "";

                // Setting which one is being clicked
                if (ind == 1)
                {
                    // Check if supplier TNID is numeric
                    if (form.spbtnid.value.trim() === "" ||
                        isNaN(form.spbtnid.value)       ||
                        (form.spbtnid.value).length < 3 ||
                        parseFloat(form.spbtnid.value) < 200)
                    {
                        alert("Please provide a valid supplier TNID (at least > 199)");
                        return false;
                    }

                    url = this.gURLETLSplrGMV + "ETL_Stats_By_Supplier_GMV_TNID.xsql";
                }

                var spbtnid = form.spbtnid.value;

                // Preparing for report run
                var toDate = form.today.value + "-" +
                             form.tomonth.value + "-" +
                             form.toyear.value;

                var rptDate = form.rptday.value + "-" +
                              form.rptmonth.value + "-" +
                              form.rptyear.value;

                var fromDate = form.fromday.value + "-" +
                               form.frommonth.value + "-" +
                               form.fromyear.value;

                // Check if within two (2) years
                var daysDiff = Math.round(
                    (new Date(form.today.value + ' ' + form.tomonth.value + ' ' + form.toyear.value) -
                     new Date(form.fromday.value + ' ' + form.frommonth.value + ' ' + form.fromyear.value)) / 86400000
                );

                if (daysDiff < 0) {
                    alert("Please provide a valid date range");
                    return false;
                }

                if (daysDiff > 730) {
                    alert("Please limit the date range within two (2) years");
                    return false;
                }

                if ((parseInt(form.toyear.value, 10) - parseInt(form.fromyear.value, 10)) > 1) {
                    alert("Please limit the date range within two (2) years");
                    return false;
                }

                var urlETLSplrGMV =  url +
                                     "?dtrng='" + form.dtrng.value + "'" +
                                     "&startdt='" + fromDate + "'" +
                                     "&enddt='" + toDate + "'" +
                                     "&rptdt='" + rptDate + "'" +
                                     "&spbtype='" + form.spbtype.value + "'" +
                                     "&spbtnid='" + spbtnid + "'" +
                                     "&uid=" + this.userCode + 
                                     "&cd='" + this.md5sum + "'";

                // Run!
                var options = "directories=no,location=no,menubar=no,status=no,titlebar=yes,toolbar=yes,scrollbars=yes,resizable=yes";

                /*var newWin = open(urlETLSplrGMV, "", );*/
                var newWin = this.openRedirect(urlETLSplrGMV, options);
                return true;
            },

			isRptDateValidated: function(rptday, rptmonth, rptyear, today, tomonth, toyear)
            {
                var rptLimitDays = 31;
                var isInvalidRptDate = false;

                // Check empty validations
                if (rptday === "" || rptmonth === "" || rptyear === "")
                {
                    isInvalidRptDate = true;
                }

                // Get the limit days per month
                if (rptmonth == "FEB")
                {
                    rptLimitDays = getLeapDays(rptyear);
                }

                if (rptmonth == "APR" ||
                    rptmonth == "JUN" ||
                    rptmonth == "SEP" ||
                    rptmonth == "NOV")
                {
                    rptLimitDays = 30;
                }

                // Check the inputted month-day pair
                if (rptday > rptLimitDays)
                {
                    isInvalidRptDate = true;
                }

                if (isInvalidRptDate)
                {
                    alert("Please provide a valid Report As-Of Date input");
                    return false;
                }

                // Compare the dates
                var toDate = tomonth + " " + today + ", " + toyear;
                var rptDate = rptmonth + " " + rptday + ", " + rptyear;

                if (Date.parse(rptDate) < Date.parse(toDate))
                {
                    alert("Please provide a valid Report As-Of Date that is greater than or equal to the To Date");
                    return false;
                }

                return true;
            },

			// Run the ETL-based Stats By Buyer report
            runETLStatsByByr: function(e)
            {

            	e.preventDefault();

            	var ind = $(e.currentTarget).data('ind');
            	var form = document.etlstatsbybyr;

                if (!this.areDatesValidated(form.fromday.value,
                    form.frommonth.value,
                    form.fromyear.value,
                    form.today.value,
                    form.tomonth.value,
                    form.toyear.value))
                {
                    return false;
                }

                var url = "";
                var bybname = "";
                var bybtnid = "";

                // Setting which one is being clicked
                if (ind === 0)
                {
                    if (this.trim(form.bybtnid.value) === "" ||
                        this.trim(form.bybtnid.value) === "All")
                    {
                        form.bybtnid.value = "All";
                        url = this.gURLETLStatsByByr + "ETL_Stats_By_Buyer_All.xsql";
                    }
                    else
                    {
                        // Check if buyer TNID is numeric
                        if (isNaN(form.bybtnid.value)       ||
                            (form.bybtnid.value).length < 3 ||
                            parseFloat(form.bybtnid.value) < 100)
                        {
                            alert("Please provide a valid buyer TNID (at least > 99)");
                            return false;
                        }

                        url = this.gURLETLStatsByByr + "ETL_Stats_By_Buyer_TNID_WO_SubBuyer.xsql";
                    }

                    bybname = "";
                    bybtnid = form.bybtnid.value;
                }

                if (ind == 1)
                {
                    // Check if buyer TNID is numeric
                    if (isNaN(form.bybtnid.value)       ||
                        (form.bybtnid.value).length < 3 ||
                        parseFloat(form.bybtnid.value) < 100)
                    {
                        alert("Please provide a valid buyer TNID (at least > 99)");
                        return false;
                    }

                    bybname = "";
                    bybtnid = form.bybtnid.value;
                    url = this.gURLETLStatsByByr + "ETL_Stats_By_Buyer_TNID.xsql";
                }

                if (ind == 2)
                {
                    if (this.trim(form.bybname.value) === "" ||
                        this.trim(form.bybname.value) === "All")
                    {
                        form.bybname.value = "All";
                        url = this.gURLETLStatsByByr + "ETL_Stats_By_Buyer_All.xsql";
                    }
                    else
                    {
                        url = this.gURLETLStatsByByr + "ETL_Stats_By_Buyer_Name.xsql";
                    }

                    bybtnid = "";
                    bybname = form.bybname.value;
                }

                // Check POM buyer filter
                if (isNaN(form.pombyb.value)           ||
                    this.trim(form.pombyb.value) === ""      ||
                    parseInt(form.pombyb.value,10) > 1 ||
                    parseInt(form.pombyb.value,10) < 0)
                {
                    form.pombyb.value = 1;
                }

                // Preparing for report run
                var toDate = form.today.value + "-" +
                             form.tomonth.value + "-" +
                             form.toyear.value;

                var fromDate = form.fromday.value + "-" +
                               form.frommonth.value + "-" +
                               form.fromyear.value;

                var urlETLStatsByByr = url +
                                       "?startdt='" + fromDate + "'" +
                                       "&enddt='" + toDate + "'" +
                                       "&bybctype='" + form.bybctype.value + "'" +
                                       "&bybtnid='" + bybtnid + "'" +
                                       "&bybname='" + bybname + "'" +
                                       "&pombyb='" + form.pombyb.value + "'" +
                                       "&bcbyb='" + form.bcbyb.value + "'" +
                                       "&uid=" + this.userCode + 
                                       "&cd='" + this.md5sum + "'";

                // Run!
                var options = "directories=no,location=no,menubar=no,status=no,titlebar=yes,toolbar=yes,scrollbars=yes,resizable=yes";

                /*var newWin = open(urlETLStatsByByr, "", options); */
                var newWin = this.openRedirect(urlETLStatsByByr, options);
                return true;
            },

			// Run the ETL-based Stats By Buyer report
            runETLByrSplrLst: function(e)
            {

				e.preventDefault();
            	var form = document.etlbyrsplrlst;

                if (!this.areDatesValidated(form.fromday.value,
                    form.frommonth.value,
                    form.fromyear.value,
                    form.today.value,
                    form.tomonth.value,
                    form.toyear.value))
                {
                    return false;
                }

                // Check minimum USD value
                if (isNaN(form.minusdval.value)       ||
                    this.trim(form.minusdval.value) === ""  ||
                    parseFloat(form.minusdval.value) < 0)
                {
                    alert("Please provide a valid minimum USD value (at least 0)");
                    return false;
                }

                if (isNaN(form.bybtnid.value)       ||
                    this.trim(form.bybtnid.value) === ""  ||
                    (form.bybtnid.value).length < 3 ||
                    parseFloat(form.bybtnid.value) < 100)
                {
                    alert("Please provide a valid buyer TNID (at least > 99)");
                    return false;
                }

                // Preparing for report run
                var toDate = form.today.value + "-" +
                             form.tomonth.value + "-" +
                             form.toyear.value;

                var fromDate = form.fromday.value + "-" +
                               form.frommonth.value + "-" +
                               form.fromyear.value;

                var urlETLByrSplrLst = this.gURLETLByrSplrLst +
                                       "?startdt='" + fromDate + "'" +
                                       "&enddt='" + toDate + "'" +
                                       "&minusdval='" + form.minusdval.value + "'" +
                                       "&bybtnid='" + form.bybtnid.value + "'" +
                                       "&uid=" + this.userCode + 
                                       "&cd='" + this.md5sum + "'";

                // Run!
                var options = "directories=no,location=no,menubar=no,status=no,titlebar=yes,toolbar=yes,scrollbars=yes,resizable=yes";

                /* var newWin = open(urlETLByrSplrLst, "", options); */
                var newWin = this.openRedirect(urlETLByrSplrLst, options);
                return true;
            },

			// Run the ETL-based Transacting Vessels report
            runETLTxngVssl: function(e)
            {
				e.preventDefault();

            	var ind = $(e.currentTarget).data('ind');
            	var form = document.etltxngvssl;
                
                if (!this.areDatesValidated(form.fromday.value,
                    form.frommonth.value,
                    form.fromyear.value,
                    form.today.value,
                    form.tomonth.value,
                    form.toyear.value))
                {
                    return false;
                }

                var url = "";
                var bybname = "";
                var bybtnid = "";

                // Setting which one is being clicked
                if (ind == 1)
                {
                    if (this.trim(form.bybtnid.value) === "" ||
                        this.trim(form.bybtnid.value) === "All")
                    {
                        form.bybtnid.value = "All";
                        url = this.gURLETLTxngVssl + "ETL_Transacting_Vessels_All.xsql";
                    }
                    else
                    {
                        // Check if buyer TNID is numeric
                        if (isNaN(form.bybtnid.value)       ||
                            (form.bybtnid.value).length < 3 ||
                            parseFloat(form.bybtnid.value) < 100)
                        {
                            alert("Please provide a valid buyer TNID (at least > 99)");
                            return false;
                        }

                        url = this.gURLETLTxngVssl + "ETL_Transacting_Vessels_TNID.xsql";
                    }

                    bybname = "";
                    bybtnid = form.bybtnid.value;
                }

                if (ind == 2)
                {
                    if (this.trim(form.bybname.value) === "" ||
                        this.trim(form.bybname.value) === "All")
                    {
                        form.bybname.value = "All";
                        url = this.gURLETLTxngVssl + "ETL_Transacting_Vessels_All.xsql";
                    }
                    else
                    {
                        url = this.gURLETLTxngVssl + "ETL_Transacting_Vessels_Name.xsql";
                    }

                    bybtnid = "";
                    bybname = form.bybname.value;
                }

                // Check POM buyer filter
                if (isNaN(form.pombyb.value)           ||
                    this.trim(form.pombyb.value) === ""      ||
                    parseInt(form.pombyb.value,10) > 1 ||
                    parseInt(form.pombyb.value,10) < 0)
                {
                    form.pombyb.value = 1;
                }

                // Preparing for report run
                var toDate = form.today.value + "-" +
                             form.tomonth.value + "-" +
                             form.toyear.value;

                var fromDate = form.fromday.value + "-" +
                               form.frommonth.value + "-" +
                               form.fromyear.value;

                var urlETLTxngVssl = url +
                                     "?startdt='" + fromDate + "'" +
                                     "&enddt='" + toDate + "'" +
                                     "&rgntype='" + form.rgntype.value + "'" +
                                     "&bybctype='" + form.bybctype.value + "'" +
                                     "&inctst='" + form.inctst.value + "'" +
                                     "&bybtnid='" + bybtnid + "'" +
                                     "&bybname='" + bybname + "'" +
                                     "&pombyb='" + form.pombyb.value + "'" +
                                     "&bcbyb='" + form.bcbyb.value + "'" +
                                     "&uid=" + this.userCode + 
                                     "&cd='" + this.md5sum + "'";

                // Run!
                var options = "directories=no,location=no,menubar=no,status=no,titlebar=yes,toolbar=yes,scrollbars=yes,resizable=yes";

                /* var newWin = open(urlETLTxngVssl, "", options); */
                var newWin = this.openRedirect(urlETLTxngVssl, options);
                return true;
            },

			// Run the ETL-based Estimated Buyer Ramp-Up report
            runETLEstBybRmpUp: function (e)
            {
                // Check POM buyer filter

                var form = document.etlestbybrmpup;

                if (isNaN(form.pombyb.value)           ||
                    this.trim(form.pombyb.value) === ""      ||
                    parseInt(form.pombyb.value,10) > 1 ||
                    parseInt(form.pombyb.value,10) < 0)
                {
                    form.pombyb.value = 1;
                }

                // Preparing for report run
                var toDate = form.today.value + "-" +
                             this.toMonthName + "-" +
                             // form.tomonth.value + "-" +
                             form.toyear.value;

                var fromDate = form.fromday.value + "-" +
                               this.fromMonthName + "-" +
                               // form.frommonth.value + "-" +
                               form.fromyear.value;

                var urlETLEstBybRmpUp = this.gURLETLEstBybRmpUp +
                                        "?startdt='" + fromDate.toUpperCase() + "'" +
                                        "&enddt='" + toDate.toUpperCase() + "'" +
                                        "&rgntype='" + form.rgntype.value + "'" +
                                        "&bybctype='" + form.bybctype.value + "'" +
                                        "&multiplier='" + form.multiplier.value + "'" +
                                        "&pombyb='" + form.pombyb.value + "'" +
                                     	"&uid=" + this.userCode + 
                                     	"&cd='" + this.md5sum + "'";

                // Run!
                var options = "directories=no,location=no,menubar=no,status=no,titlebar=yes,toolbar=yes,scrollbars=yes,resizable=yes";
                
                /* var newWin = open(urlETLEstBybRmpUp, "", options); */
                var newWin = this.openRedirect(urlETLEstBybRmpUp, "", options);
                return true;
            },


            resetFromRange: function(form)
            {
                form.today.value = 'Day';
                form.tomonth.value = 'Month';
                form.toyear.value = 'Year';
                form.submit.disabled = true;
                this.fromDay = -1;
                this.fromMonth = -1;
                this.fromYear = -1;
                this.fromMonthName = '';
            },

            computeFromRange: function(form)
            {
                if (this.toDay > -1 && this.toMonth > -1 && this.toYear > -1) {

                    this.toMaxDay = this.monthDays[this.toMonth];
                    if (this.toMonth === 1) {
                        this.toMaxDay = this.getLeapDays(this.toYear);
                    }

                    // Compute the day
                    if (this.toDay >= this.toMaxDay) {
                        this.toDay = this.toMaxDay;
                        this.fromDay = 1;
                    } else {
                        this.fromDay = this.toDay + 1;
                    }

                    // Compute the month
                    // Check if date range is just 1 month
                    if (this.multiplier === 12) {
                        this.fromMonth = this.toMonth;
                    } else {
                        // This is in 3 months
                        this.fromMonth = this.toMonth - 2;

                    }

                    if (this.fromDay > 1) { this.fromMonth -= 1; }
                    if (this.fromMonth < 0) { this.fromMonth += 12; }

                    this.fromYear = this.toYear;

                    if (this.fromMonth > this.toMonth) { this.fromYear -= 1; }
                    if (this.fromDay >= this.monthDays[this.fromMonth]) {
                        this.fromDay = this.monthDays[this.fromMonth];
                    }

                    // Check for correct day when February
                    if (this.fromMonth === 1 && this.fromDay > this.getLeapDays(this.fromYear)) {
                        this.fromDay = this.getLeapDays(this.fromYear);
                    }

                    this.toMonthName = this.monthNames[this.toMonth];
                    this.fromMonthName = this.monthNames[this.fromMonth];
                    form.fromday.value = this.fromDay;
                    form.fromyear.value = this.fromYear;
                    form.frommonth.value = this.fromMonthName;

                    form.today.selectedIndex = this.toDay;
                    form.submit.disabled = false;
                }
            },

            clickDateRange: function(e)
            {
            	e.preventDefault();
            	var form = document.etlestbybrmpup;
                this.multiplier = parseInt(form.multiplier.value, 10);
                this.computeFromRange(form);
            },

            clickToDay: function(e)
            {
				e.preventDefault();
            	var form = document.etlestbybrmpup;
                this.toDay = parseInt(form.today.value, 10);
                if (this.toDay > 0) {
                    this.computeFromRange(form);
                } else {
                    this.toDay = -1;
                    this.resetFromRange(form);
                }
            },

            clickToMonth: function(e)
            {
            	e.preventDefault();
            	var form = document.etlestbybrmpup;

                this.toMonth = parseInt(form.tomonth.value, 10);
                if (this.toMonth > 0) {
                    this.toMonth -= 1;
                    this.toMonthName = this.monthNames[this.toMonth];
                    this.computeFromRange(form);
                } else {
                    this.toMonth = -1;
                    this.resetFromRange(form);
                }
            },

            clickToYear: function(e)
            {
				e.preventDefault();
            	var form = document.etlestbybrmpup;

                this.toYear = parseInt(form.toyear.value, 10);
                if (this.toYear > 0) {
                    this.computeFromRange(form);
                } else {
                    this.toYear = -1;
                    this.resetFromRange(form);
                }
            },

            // Run the ETL-based Average PO Value report
            runETLAvePOValue: function(e)
            {

            	e.preventDefault();
            	var form = document.etlavepovalue;

                if (!this.areDatesValidated(form.fromday.value,
                    form.frommonth.value,
                    form.fromyear.value,
                    form.today.value,
                    form.tomonth.value,
                    form.toyear.value))
                {
                    return false;
                }

                // Check minimum USD value
                if (isNaN(form.minusdval.value)       ||
                    this.trim(form.minusdval.value) === ""  ||
                    parseFloat(form.minusdval.value) < 0)
                {
                    alert("Please provide a valid minimum USD value");
                    return false;
                }

                // Check maximum USD value
                if (isNaN(form.maxusdval.value)       ||
                    this.trim(form.maxusdval.value) === ""  ||
                    parseFloat(form.maxusdval.value) < parseFloat(form.minusdval.value))
                {
                    alert("Please provide a valid maximum USD value");
                    return false;
                }

                // Check POM buyer filter
                if (isNaN(form.pombyb.value)           ||
                    this.trim(form.pombyb.value) === ""      ||
                    parseInt(form.pombyb.value,10) > 1 ||
                    parseInt(form.pombyb.value,10) < 0)
                {
                    form.pombyb.value = 1;
                }

                // Preparing for report run
                var toDate = form.today.value + "-" +
                             form.tomonth.value + "-" +
                             form.toyear.value;

                var fromDate = form.fromday.value + "-" +
                               form.frommonth.value + "-" +
                               form.fromyear.value;

                var urlETLAvePOValue = this.gURLETLAvePOValue +
                                       "?startdt='" + fromDate + "'" +
                                       "&enddt='" + toDate + "'" +
                                       "&bybname='" + form.bybname.value + "'" +
                                       "&minusdval='" + form.minusdval.value + "'" +
                                       "&maxusdval='" + form.maxusdval.value + "'" +
                                       "&pombyb='" + form.pombyb.value + "'" +
                                       "&bcbyb='" + form.bcbyb.value + "'" +
                                       "&uid=" + this.userCode + 
                                       "&cd='" + this.md5sum + "'";

                // Run!
                var options = "directories=no,location=no,menubar=no,status=no,titlebar=yes,toolbar=yes,scrollbars=yes,resizable=yes";

                /* var newWin = open(urlETLAvePOValue, "", options); */
                var newWin = this.openRedirect(urlETLAvePOValue, options);
                return true;
            },

			// Run the Telesales Supplier Purchase Value report
            runS4545: function(e)
            {

            	e.preventDefault();
            	var form = document.telesupppurvalue;

                if (!this.areDatesValidated(form.fromday.value,
                                       form.frommonth.value,
                                       form.fromyear.value,
                                       form.today.value,
                                       form.tomonth.value,
                                       form.toyear.value))
                {
                    return false;
                }

                // Check supplier TNID validations
                if (isNaN(form.supptnid.value)       ||
                    this.trim(form.supptnid.value) === ""  ||
                    (form.supptnid.value).length < 3 ||
                    form.supptnid.value < 200)
                {
                    alert("Please provide a valid Supplier TNID");
                    return false;
                }

                // Preparing for report run
                var toDate = form.today.value + "-" +
                             form.tomonth.value + "-" +
                             form.toyear.value;

                var fromDate = form.fromday.value + "-" +
                               form.frommonth.value + "-" +
                               form.fromyear.value;

                var urlS4545 = this.gURLS4545 +
                               "?start_dt='" + fromDate + "'" +
                               "&end_dt='" + toDate + "'" +
                               "&suppid=" + form.supptnid.value +
							   "&uid=" + this.userCode + 
							   "&cd='" + this.md5sum + "'";

                // Run!
                var options = "directories=no,location=no,menubar=no,status=no,titlebar=yes,toolbar=yes,scrollbars=yes,resizable=yes";

                /* var newWin = open(urlS4545, "", options); */
                var newWin = this.openRedirect(urlS4545, options);
                return true;
            },

            initDates: function()
            {
            	var mydate=new Date();
				var mydate1=new Date(mydate.valueOf()-(30*24*60*60*1000));
				var mydate2=new Date(mydate.valueOf()-(7*24*60*60*1000));

				/* S881 - lorenz dec 13,07 */

				var mydates881=new Date();
				mydates881.setDate(mydates881.getDate()-1);
				var years881=mydates881.getYear();
				if (years881 < 1000)
				{
					years881+=1900;
				}
				var days881=mydates881.getDay();
				var months881=mydates881.getMonth();
				var dayms881=mydates881.getDate();
				if (dayms881<10)
				{
					dayms881="0"+dayms881;
				}
				

				/* end S881 - lorenz dec 13,07 */

				var year=mydate.getYear();
				if (year < 1000)
				{
					year+=1900;
				}

				var day=mydate.getDay();
				var month=mydate.getMonth();
				var daym=mydate.getDate();
				if (daym<10) {
					daym="0"+daym;
				}
				

				var year1=mydate1.getYear();
				if (year1 < 1000)
				{
					year1+=1900;
				}
				
				var day1=mydate1.getDay();
				var month1=mydate1.getMonth();
				var daym1=mydate1.getDate();
				if (daym1<10)
				{
					daym1="0"+daym1;
				}
				

				var year2=mydate2.getYear();
				if (year2 < 1000)
				{
					year2+=1900;
				}
				
				var day2=mydate2.getDay();
				var month2=mydate2.getMonth();
				var daym2=mydate2.getDate();
				if (daym2<10)
				{
					daym2="0"+daym2;
				}
				

				var dayarray = new Array("Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday");
				var montharray = new Array("JAN","FEB","MAR","APR","MAY","JUN","JUL","AUG","SEP","OCT","NOV","DEC");

				document.pages_stats.AD17.value = daym;
				document.pages_stats.AD18.value = montharray[month];
				document.pages_stats.AD19.value = year;

				document.frmverified.AD20.value = daym2;
				document.frmverified.AD21.value = montharray[month2];
				document.frmverified.AD22.value = year2;

				document.frmverified.AD23.value = daym;
				document.frmverified.AD24.value = montharray[month];
				document.frmverified.AD25.value = year;

				document.supppurvalue.AE1.value = daym1;
				document.supppurvalue.AE2.value = montharray[month1];
				document.supppurvalue.AE3.value = year1;

				document.supppurvalue.AE4.value = daym;
				document.supppurvalue.AE5.value = montharray[month];
				document.supppurvalue.AE6.value = year;

				//rpb - buyer_ramp-up report
				var mday;
				var mos;

				if(daym == 1)
				{
				  if(montharray[month] == 'JAN')
				  {
				     mday = 31;
				     mos = 'DEC';
				  }
				  else if(montharray[month] == 'FEB')
				  {
				     mday = 31;
				     mos = 'JAN';
				  }
				  else if(montharray[month] == 'MAR')
				  {
				     if(year % 4 === 0)
				     {
				        mday = 29;
				        mos ='FEB';
				     }
				     else
				     {
				       mday = 28;
				       mos = 'FEB';
				     }
				  }
				  else if(montharray[month] == 'APR')
				  {
				     mday = 31;
				     mos = 'MAR';
				  }
				  else if(montharray[month] == 'MAY')
				  {
				     mday =30;
				     mos = 'APR';
				  }
				  else if(montharray[month] == 'JUN')
				  {
				     mday =31;
				     mos ='MAY';
				  }
				  else if(montharray[month] == 'JUL')
				  {
				     mday =30;
				     mos = 'JUN';
				  }
				  else if(montharray[month] == 'AUG')
				  {
				     mday =31;
				     mos = 'JUL';
				  }
				  else if(montharray[month] == 'SEP')
				  {
				     mday = 31;
				     mos = 'AUG';
				  }
				  else if(montharray[month] == 'OCT')
				  {
				     mday = 30;
				     mos = 'SEP';
				  }
				  else if(montharray[month] == 'NOV')
				  {
				     mday = 31;
				     mos = 'OCT';
				  }
				  else
				  {
				    mday = 30;
				    mos = 'NOV';
				  }
				}
				else
				{
				    mos = montharray[month];
				    mday = daym - 1;

				}

				var months = new Array(13);
				months[0]  = "JAN";
				months[1]  = "FEB";
				months[2]  = "MAR";
				months[3]  = "APR";
				months[4]  = "MAY";
				months[5]  = "JUN";
				months[6]  = "JUL";
				months[7]  = "AUG";
				months[8]  = "SEP";
				months[9]  = "OCT";
				months[10] = "NOV";
				months[11] = "DEC";
				var now         = new Date();
				var monthnumber = now.getMonth();
				var monthname   = months[monthnumber];
				var monthday    = now.getDay();
				var syear       = now.getYear()-1;
				var eyear       = now.getYear();
				if(year < 2000) { year = year + 1900; }
				var aPmonth = monthname;
				var aPstartyr = syear;
				var aPendyr = eyear;
            },

            openRedirect: function(url, options)
            {
            	var newUrl = '/shipmate/scorecard-redirect?service=' + encodeURIComponent(url);
            	return open(newUrl, "", options);
            }
	});

	return new mainView();
});
