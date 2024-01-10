/**
 * reports/app module
 */
define([
	'jsrender', 
	'reports/model', 
	'reports/filters', 
	'zingchart', 
	'supplier/profile', 
	'libs/history', 
	'libs/jquery.quickfit'
], function (
	$,
	model, 
	formFilters, 
	zingchart, 
	supplierProfile, 
	history, 
	qf
) {
	"use strict";
	
	var strMarketingMsg;
	var strMarketingMsgType;
	var strLeftMarketingMsg;

    $.views.tags({
        /**
         * Incomplete, works for our purposes for now
         */
        intToWord: function( int ) {
            switch (parseInt(int)) {
                case 0: return "zero";
                case 1: return "one";
                case 2: return "two";
                case 3: return "three";
                case 4: return "four";
                case 5: return "five";
            }
        },

        lcase: function( string ) {
            return string.toLowerCase();
        },
        
        formatDate: formFilters.storageDateToShortDisplayDate
    });
	
	function setMarketingMsg(data, supplier){
		var values = data.graphs,
			premiumListing = supplierProfile.premiumListing,
			profileCompletionScore = supplierProfile.profileCompletionScore,
			impressions = supplier["banner-summary"].impression.count,
			youVal = 0,
			avgVal = 0,
			msg = "",
			msgType = "";
		
		for (var key in values)
		{
			var item = values[key];
			switch(item.legend){
			case "You":
				youVal = item.values[0].value;
			break;
			case "Market":
				avgVal = item.values[0].value;
			break;			
			}
		}
		
		//Rule 1 & 2
		if((youVal ==0 && premiumListing == "0")){
			//msg = "One of the reasons you are getting lower than average RFQs is because you only have a Basic Listing.  Upgrading to a Premium Profile is easy and it will boost you up the search rankings. We are delighted to offer you a THREE MONTHS Premium Profile at a special rate of $295. <a href='/store'>Click here</a> to sign up to this exclusive offer now.
			msg = "One of the reasons you are getting lower than average RFQs is because you only have a Basic Listing.  Upgrading to a Premium Profile is easy and it will boost you up the search rankings.  <a href='/info/pages-for-suppliers/win-new-business-with-a-premium-listing/'>Click here</a> to see the benefits of Upgrading to a Premium Profile.";
			//msg="<strong>Upgrading your listing will improve your results. Do you want to learn more?</strong><div class='zz medium green button' id='examplebutton'><a href='/info/pages-for-suppliers/win-new-business-with-a-premium-listing/'>Yes, tell me more.</a></div>";
            msgType = "warning";
		}else if ((numberWithinRange(youVal, avgVal, 5) || youVal > avgVal || (youVal < avgVal && premiumListing == "0"))  && premiumListing == "0") {
			//Rule 3
			//msg = "A Premium Profile allows you a much fuller Listing and also boosts you up the search results - which will improve your performance in these graphs. We are delighted to offer you a THREE MONTHS Premium Profile at a special rate of $295. <a href='/store'>Click here</a> to sign up to this exclusive offer now."
			//msg = "A Premium Profile allows you a much fuller listing and also boosts you up the search results which will improve your performance in these graphs.  <a href='/info/pages-for-suppliers/win-new-business-with-a-premium-listing/'>Click here</a> to see the benefits of Upgrading to a Premium Profile.";
			msg="<strong>Upgrading your listing will improve your results. Do you want to learn more?</strong><div class='zz medium green button' id='examplebutton'><a href='/info/pages-for-suppliers/win-new-business-with-a-premium-listing/'>Yes, tell me more.</a></div>";
            msgType = "learn";
		}else if (youVal == "0" && premiumListing == "1") {
			msg = "You are getting <u>valuable profile views and contact views</u> which will be resulting in offline sales.  To get more you need to ensure your listing is fully completed.  <a href='/help/premium'>Click here</a> for the Premium Profile Guide to see how.";
			msgType = "info";
		}else if((youVal > "0" && youVal < avgVal)  && premiumListing == "1") {
			msg = "You are getting <u>valuable profile views and contact views</u> which will be resulting in offline sales.  To get more you need to ensure your listing is fully completed.  <a href='/help/premium'>Click here</a> for the Premium Profile Guide to see how.";
			msgType = "info";
		}else if(numberWithinRange(youVal, avgVal, 5) && premiumListing == "1"){
			//Rule 7
			msg = "As well as <u>valuable profile and contact views</u>, you are getting an average number of RFQs.  You may well benefit from a better filled out Listing. <a href='/help/premium'>Click here</a> for the Premium Profile Guide to see how to do this.";
			msgType = "info";
		}else if (youVal > avgVal && premiumListing == "1" && profileCompletionScore <= "70") {
			//Rule 8
			msg = "As well as <u>valuable profile and contact views</u>, you are getting a higher than average number of RFQs.  There may still be opportunity to improve your performance further through a more complete Listing.  <a href='/help/premium'>Click here</a> for the Premium Profile Guide to see how to do this.";
			msgType = "info";
		} else if (youVal > avgVal && premiumListing == "1" && profileCompletionScore > "70" && impressions == "0") {
			//Rule 9
			msg = "As well as <u>valuable profile and contact views</u>, you are getting higher than average number of RFQs.  If you want to increase your brand volume further on Pages, then we recommend Banner Advertising. <a href='/info/pages-for-suppliers/banner-advertising-on-pages/'>Click here</a> for more information. ";
			msgType = "info";
		}else if(youVal > avgVal && premiumListing == "1" && profileCompletionScore > "70" && impressions > "0"){
			//Rule 10
			msg="As well as <u>valuable profile and contact views</u>, you are getting a higher than average number of RFQs.  Please contact your account manager if you have any questions or would like to boost your profile further.";
			msgType = "info";
		}	

		strMarketingMsg = msg;
		strMarketingMsgType = msgType;

		if(impressions > 0) {
			strLeftMarketingMsg = "If you'd like to discuss your banner advert performance further please contact your account manager."			
		} else {
			strLeftMarketingMsg = "The Banner Impression number is ZERO because you haven't taken a banner advert on Pages. <a href='/info/pages-for-suppliers/banner-advertising-on-pages/'>Click here</a> to see how a targeted banner will boost your performance.  There are various different options available.";
		}
	}

	function numberWithinRange (a, b, range) {
		try {
			if(a > 20  || b > 20){ 
			//Percentage (defined by range)
				if(a == 0 || b == 0){return false;}
				if(Math.abs( ( (a/b) * 100 ) - 100 ) < range){
					return true;
				}else{
					return false;
				}
			}else{
			//Exact mode
				if(a == b){return true;}
			}
		}
		catch(err)
		{
			return false;
		}
		
	}

	function resizeTablesToFit() {
		$("#totalNUnique div.tables").width(99999);
		var width = $("#totalNUnique div.tables table").length * 3;
		$("#totalNUnique div.tables table").each(function () { width += $(this).outerWidth(true); });
		$("#totalNUnique div.tables").width(width);
	}

    /*Live (generic) user event handlers*/
    $('div.collapsable h3').live('click', function () {
        $(this).closest('div.collapsable').toggleClass('collapsed');
    });
    
    $('div#viewTabs div.tab').live('click', function () {
		var tabId = $(this).attr('data-tabid');
	    $.history.load(tabId);
        
        if (tabId == 'summary') {
           $('div.summary_bottom').addClass('sum');
        }
        else {
            $('div.summary_bottom').removeClass('sum');
        }
    });
    
    $('#tnidSelector').live('change', function () {
        $('#tnid').val($(this).val());
        $('#f').submit();
    });
    
    function showWaitingMessage(message) {
        $('div#waiting').show().find('div.message').text(message);
    }
    
    function hideWaitingMessage() {
        $('div#waiting').hide();
        $('p.value').quickfit({ max: 17, min: 6, truncate: false });
    }
    
    function executeWithWaitMessage(fnc) {
        showWaitingMessage('Please wait...');
        setTimeout(function () {
            fnc.call();
            hideWaitingMessage();
        },0);
    }

	
	/**
	 * Build page from report data
	 */
	function applyFilters(filters) {
	    showWaitingMessage("Updating graphs...");
		model.fetchStats(filters, function(reportData) {

				// Render the template with the movies data and insert
				// the rendered HTML under the "movieList" element

				$.templates( "funnelViewTemplate", {
						markup: "#funnelViewTemplate",
						allowCode: true
				});
				
				$.templates( "tablesTemplate", {
						markup: "#tablesTemplate",
						allowCode: true
				});
				
				$.templates( "tableTemplate", {
						markup: "#tableTemplate",
						allowCode: true
				});
                
                $.templates( "rfqTemplate", {
						markup: "#rfqTemplate",
						allowCode: true
				});
                
                $.templates( "unactionedRfqTemplate", {
                    markup: "#unactionedRfqTemplate",
                    allowCode: true
                });
				
				setMarketingMsg(reportData.getSummaryData().metrics[4], reportData.getSupplierCurrentData());
				$("#summary").html( $.render.funnelViewTemplate({
					periods: reportData.getPeriods(),
					data: reportData.getSummaryData(),
                    dataMarket: reportData.getMarketData(),
					marketingMsg : strMarketingMsg,
					marketingMsgType : strMarketingMsgType,
					leftMarketingMsg : strLeftMarketingMsg,
                    unactionedRfqCount : reportData.getUnactionedRfqCount()
				}));
                
                $("#rfq").html( $.render.rfqTemplate({
					data: reportData.getTnSummaryData(),
                    unactionedRfqCount : reportData.getUnactionedRfqCount()
				}));

				$('div.verticalMetric div.bar').each(function () {
			        $(this).animate({height: $(this).attr('data-size')});
				});
				
				$('div.horizontalMetric div.bar').each(function () {
				    $(this).animate({width: $(this).attr('data-size')});
				});

				$("#impressionBySearchKeyword").html( $.render.tableTemplate({
					displayLeftColumn: true,
					leftColumnTitle:'Top searches that lead to profile views',
					periods: reportData.getPeriods(),
					data: reportData.getImpressionBySearchKeyword()
				}));
				
				$("#impressionByUserType").html( $.render.tableTemplate({
					displayLeftColumn: true,
					leftColumnTitle:'Type of users that view your profile',
					periods: reportData.getPeriods(),
					data: reportData.getImpressionByUserType()
				}));
				$("#enquiryBySearchKeyword").html( $.render.tableTemplate({
					displayLeftColumn: true,
					leftColumnTitle:'Top searches that lead to sending of RFQ',
					periods: reportData.getPeriods(),
					data: reportData.getEnquiriesBySearchKeyword()
				}));
				$("#enquiryByUserType").html( $.render.tableTemplate({
					displayLeftColumn: true,
					leftColumnTitle:'Type of users that send you RFQ',
					periods: reportData.getPeriods(),
					data: reportData.getEnquiriesByUserType()
				}));
				$("#brandSearches").html( $.render.tablesTemplate({
					tables:[
						{
							title: "Global",
							id: "globalBrandSearchTable",
							"cssClass": "global",
							leftColumnTitle:'Brands',
							displayLeftColumn: true,
							periods: reportData.getPeriods(),
							vars: {
								var1:"Searches",
								var2:"Clicks"
							},
							data: reportData.getGlobalBrandSearches()
						},
						{
							title: "Your Country",
							id: "localBrandSearchTable",
							"cssClass": "local",
							periods: reportData.getPeriods(),
							vars:{
								var1:"Searches",
								var2:"Clicks"
							},
							data: reportData.getLocalBrandSearches()
						}
						
					]
				}));

				$("#categorySearches").html( $.render.tablesTemplate({
					tables:[
						{
							title: "Global",
							id: "globalcCatSearchTable",
							"cssClass": "global",
							periods: reportData.getPeriods(),
							leftColumnTitle:'Categories',
							displayLeftColumn: true,
							vars:{
								var1:"Searches",
								var2:"Clicks"
							},
							data: reportData.getGlobalCategorySearches()
						},
						{
							title: "Your Country",
							id: "localCatSearchTable",
							"cssClass": "local",
							periods: reportData.getPeriods(),
							vars:{
								var1:"Searches",
								var2:"Clicks"
							},
							data: reportData.getLocalCategorySearches()
						}
						
					]
				}));
				
				$("#totalNUnique div.tables").html( $.render.tablesTemplate({
					tables:[
						{
							id: "profileViewsTable",
							title: "Profile Views",
							leftColumnTitle:'Total & Unique',
							displayLeftColumn: true,
							periods: reportData.getPeriods(),
							data: reportData.getProfileViewsTotalNUnique(),
							vars:{
								var1:"You",
								var2:"Market"
							}
						},
						{
							id: "contactViewsTable",
							title: "Contact Views",
							periods: reportData.getPeriods(),
							data: reportData.getContactViewsTU(),
							vars:{
								var1:"You",
								var2:"Market"
							}
						},
						{
							id: "enquiriesTable",
							title: "RFQs",
							periods: reportData.getPeriods(),
							data: reportData.getEnquiresTU(),
							vars:{
								var1:"You",
								var2:"Market"
							}
						}
					]
				}));
				
				resizeTablesToFit();

				if( $('#tnidSelector').length > 0 )
				{
					$('#tnidSelector').unbind('change').bind('change', function(){
						location.href = '/reports/tnid/' + $(this).val();
					});
				}
				
				$("#detailed").html( $.render.unactionedRfqTemplate({
                    unactionedRfqCount : reportData.getUnactionedRfqCount()
				}));
				
				//Using setTimeout to avoid unresponsive script warnings
				setTimeout(function () {
				    zingchart.exec("detailed-graph","destroy");
    				zingchart.render({
    					id : 'detailed-graph',
    					width : 900,
    					height : 300,
    					data : {
    						"graphset" : [
    						{
    							"background-color":"#ffffff",
    							"type" : "line",
    							"chart": {
    							    "background-color": "#fff"
    							},
    							"plotarea" : {
    								"margin" : "20 120 60 60"
    							},
    							"legend" : {
    								"margin" : "auto 10 auto auto"
    							},
    							"scale-x" : {
    								"max-items" : 8,
    								"min-value" : Date.parse(reportData.getFilters().period[0].from.slice(0,4)+'/'+reportData.getFilters().period[0].from.slice(4,6)+'/'+reportData.getFilters().period[0].from.slice(6,8)),
    								"step" : 600000*6*24,
    								"transform" : {
    									"type" : "date",
    									"all" : "%D, %d %M",
    									"item":{
    										"visible":false
    									}
    																		},
    								"zooming" : 1
    							},
    							"guide" : {
    							},
    							"tooltip" : {
    								"visible" : false
    							},
    							"scale-y" : {
    								"zooming" : 0
    							},
    							"plot" : {
    								"marker" : {
    									"size" : 3
    								}
    							},
    							"series" : [
    								{
    									"line-width" : 2,
    									"shadow" : false,
    									"text" : "Profile Views",
    									"values" : reportData.getImpressionsAsArray(),
    									"line-color" : "#fecb38",
    									"aspect" : "spline",
    									"marker": {
    										"background-color": "#fecb38",
    										"border-color": "#fecb38"
    									},
    									"tooltip-text" : "%t: %v"
    								}
    								,
    								{
    									"line-width" : 2,
    									"text" : "Contact Views",
    									"shadow" : false,
    									"values" : reportData.getContactViewsAsArray(),
    									"line-color" : "#fb830f",
    									"aspect" : "spline",
    									"marker": {
    										"background-color": "#fb830f",
    										"border-color": "#fb830f"
    									},
    									"tooltip-text" : "%t: %v"
    								}
    								,
    								{
    									"line-width" : 2,
    									"text" : "Pages RFQs",
    									"shadow" : false,
    									"values" : reportData.getInquiriesAsArray(),
    									"line-color" : "#f14331",
    									"aspect" : "spline",
    									"marker": {
    										"background-color": "#f14331",
    										"border-color": "#f14331"
    									},
    									"tooltip-text" : "%t: %v"
    								}
    							]
    						}
    					]
    					}
    				});//End Zing render
    				
                    hideWaitingMessage();
				},0);
				

		});//End model.fetchStats(...) callback

	}//End applyFilters

	function init() {
        formFilters.onApply(function(filters){
            applyFilters(filters);
        });
        
        //Apply default filters
        formFilters.onReady(function(filters){
            applyFilters(filters);
        });

        $.history.init(function (hash) {
            if(hash != "") {
                $('div.viewTabBody, .viewTabSupplemental').hide();
                $('div.viewTabBody.' + hash + ', .viewTabSupplemental.' + hash).show();
                $('#viewTabs .tab.' + hash).addClass('selected');
                $('#viewTabs .tab.' + hash).siblings().removeClass('selected');

                if(hash == "rfq" || hash == "detailed"){
                	$('.summary_bottom').removeClass('sum');
                }
        		resizeTablesToFit();
            }
        });
        
		var w;
		$('#emailReportToCustomerButton').live('click', function(){
			w = $('#forwardReportAsEmailForm')
			.ssmodal({title: 'Email this report to customer'});
		});
		
		$('#sendButton').live('click', function(){
			if ($(this).is('.disabled')) return false;
			var object = $(this);
			object.val('Sending');
			$(this).addClass('disabled');
			var error = [];
			var email = "";
			var filters = formFilters.getCurrentFilters();
			
			$("input[name='startDate']").val(filters.period[0].from);
			$("input[name='endDate']").val(filters.period[0].to);
			
			$('input[name="emails[]"]').each(function(){
				email += $(this).val();
			});
			if( email == "" ){
				error.push("\n- You need to have at least 1 email");
			}
			else
			{
				var emailPattern = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}$/;
				var i=0;
				$('input[name="emails[]"]').each(function(){
					i++;
					if( $(this).val()!="" && emailPattern.test($(this).val()) == false ){
						error.push("\n- Invalid format for Email address " + i);
					}					
				});
								
				
			}
			
			if( $('textarea[name="bodyText"]').val() == "" ){
				error.push("\n- Message");
			}
			if( $('textarea[name="fromText"]').val() == "" ){
				error.push("\n- Salutation");
			}
			
			if( $('#agree').attr('checked') == false ){
				error.push("\n- Please make sure that you tick the box");
			}
			if( error.length > 0 ){
				alert("Please check the following:" + error.join(""));
				$(this).removeClass('disabled');

			}else{
				
				$.ajax({
					url: '/reports/api/send-email-summary-to-customer',
					type: 'POST',
					data: $('form.main').serialize(),
					cache: false,
				    error: function(request, textStatus, errorThrown) {
				    	response = eval('(' + request.responseText + ')');
				    	alert("ERROR " + request.status + ": " + response.error);					    	
				    },
					success: function( response ){
						object.removeClass('disabled');
						object.val('Email the SIR now');
						w.close()							
					}
				});	
				
			}
			return false;
		});
		
		$("#enquiriesMetric h3, #enquiriesMetric .verticalMetric .you").live('click', function(){
			location.href=$('#enquiry-url').attr('href');
		});
		
		
		// bind action for top right utility buttons (export, print, etc)
		$('#exportButton').live('click', function(){
			if ($(this).is('.disabled')) return false;
			
			$(this).addClass('disabled');
			$(this).find('button').text('Exporting');
			var object = $(this);
			var filters = formFilters.getCurrentFilters();
			
			$('.exportToExcel input[name="tnid"]').val(filters.tnid);
			$('.exportToExcel input[name="start"]').val(filters.period[0].from);
			$('.exportToExcel input[name="end"]').val(filters.period[0].to);
			var url = '/reports/api/export-to-excel';
			if( filters.period[1] !== undefined )
			{
				$('.exportToExcel input[name="start2"]').val(filters.period[1].from);
				$('.exportToExcel input[name="end2"]').val(filters.period[1].to);
			}
			
			if( filters.location.length > 0 )
			{
				$('.exportToExcel input[name="location"]').val(filters.location.join(','));
			}

			if( filters.categories.length > 0 )
			{
				$('.exportToExcel input[name="categories"]').val(filters.categories.join(','));						
			}

			if( filters.brands.length > 0 )
			{
				$('.exportToExcel input[name="brands"]').val(filters.brands.join(','));						
			}

			if( filters.products.length > 0 )
			{
				$('.exportToExcel input[name="products"]').val(filters.products.join(','));						
			}

			$.ajax({
				url: url,
				type: 'POST',
				data: $('form.exportToExcel').serialize(),
				cache: false,
			    error: function(request, textStatus, errorThrown) {
			    	var response = eval('(' + request.responseText + ')');
			    	alert("ERROR " + request.status + ": " + response.error);	
					object.removeClass('disabled');
					object.attr('disabled', false);
					//object.val('Export');
					object.find('button').text('Exporting');
			    },
				success: function( response ){
					object.removeClass('disabled');
					object.attr('disabled', false);
					object.text('Export');
					if( typeof response.data != "undefined")
					location.href='/reports/api/download?fileName=' + response.data;
					else
					alert("Sorry, there is a problem exporting this report. Please try again.")
				}
			});	
			
		});	       
	} $(init);
	
	/*Public module defintion*/
	return {
	    showWaitingMessage: showWaitingMessage,
	    hideWaitingMessage: hideWaitingMessage,
	    executeWithWaitMessage: executeWithWaitMessage
	};


	/**
	 * Filters class
	 */
	function Filters() {



	}

});