/**
 * reports/model
 */
define(['reports/filters', 'jqueryui-datepicker','libs/jquery-1.7.1'], function(filterModule){

	var	lastResponse,
		lastFetchedFilters,

		reportPath = '/report/data-report';

	function ReportData() {
        
		var supplierCurrentData = null,
			theirsCurrentData = null,
			supplierCompareData = null,
			theirsCompareData = null,
			brandSearchesIndex = null,
			categorySearchesIndex = null,
			filters=null,
			
			onReadyCallback=null;

		this.set = function(dataType, data)
		{
            
			switch (dataType){
				case "supplierCurrent":
					supplierCurrentData = data
					break;
				case "supplierCompare":
					supplierCompareData = data
					break;
				case "theirsCurrent":
					theirsCurrentData = data
					break;
				case "theirsCompare":
					theirsCompareData = data
					break;
			}
			if (this.isReady()) onReadyCallback(this);
		}

		this.getSupplierCurrentData = function(){
			return supplierCurrentData;
		};

		this.getSupplierCompareData = function(){
			return supplierCompareData;
		};

		this.getTheirsCurrentData = function(){
			return theirsCurrentData;
		};

		this.getTheirsCompareData = function(){
			return theirsCompareData;
		};

		this.setFilters = function(data){
			filters = data
		}

		this.getFilters = function(data){
			return filters;
		}
		
		this.getBrandSearchesIndex = function()
		{
			if (!brandSearchesIndex)
			{
				brandSearchesIndex = this.getSearchIndexArray([supplierCurrentData["search-summary"]["brand-searches-global"], (supplierCompareData)?supplierCompareData["search-summary"]["brand-searches-global"]:null,supplierCurrentData["search-summary"]["brand-searches-local"], (supplierCompareData)?supplierCompareData["search-summary"]["brand-searches-local"]:null]);
			}
			return brandSearchesIndex;

		}
		
		this.getCategorySearchesIndex = function()
		{
			if (!categorySearchesIndex)
			{
				categorySearchesIndex = this.getSearchIndexArray([supplierCurrentData["search-summary"]["category-searches-global"], (supplierCompareData)?supplierCompareData["search-summary"]["category-searches-global"]:null,supplierCurrentData["search-summary"]["category-searches-local"], (supplierCompareData)?supplierCompareData["search-summary"]["category-searches-local"]:null]);
			}
			return categorySearchesIndex;

		}

		this.onReady = function (callback)
		{
			onReadyCallback = callback;
		}
		this.isReady = function (){
			return supplierCurrentData && theirsCurrentData && ( (filters.period.length==1) || (supplierCompareData && theirsCompareData) );
		}
		this.getPeriods = function ()
		{
			return {
				period1: {from: filters.period[0].from, to: filters.period[0].to},
				period2: (filters.period.length>1)?{from: filters.period[1].from, to: filters.period[1].to}:null
			}
		}
		this.getImpressionsAsArray = function (){
			var returnArray = [];
			return this.getChartArray(supplierCurrentData["impression-summary"].impression.days);
		}
		this.getContactViewsAsArray = function (){
			return this.getChartArray(supplierCurrentData["impression-summary"]["contact-view"].days);
		}
		this.getInquiriesAsArray = function (){
			return this.getChartArray(supplierCurrentData["enquiry-summary"]["enquiry-sent"].days);
		}
        this.getUnactionedRfqCount = function (){
            return supplierCurrentData["enquiry-summary"]["unactioned-rfq"].count;
        }
		this.getChartArray = function (dataArray)
		{
			var returnArray = [];
			var currentDate = Date.parse(filters.period[0].from.slice(0,4)+'/'+filters.period[0].from.slice(4,6)+'/'+filters.period[0].from.slice(6,8));
			var toDate = Date.parse(filters.period[0].to.slice(0,4)+'/'+filters.period[0].to.slice(4,6)+'/'+filters.period[0].to.slice(6,8));
			$.each(dataArray,function(key,value){
				while (Date.parse(value.date.replace(/\-/gi,'/'))>currentDate)
				{
					currentDate += 1000*60*60*24;
					returnArray.push(0);
				}
				currentDate += 1000*60*60*24;
				returnArray.push(value.count);
			});
			while (currentDate <= toDate)
			{
				currentDate += 1000*60*60*24;
				returnArray.push(0);
			}
			return returnArray;
		}
		this.getTargetedSearchesCicksCount = function (dataSet)
		{
			var returnCount=0;
			
			$.each(dataSet["search-summary"]["brand-searches-global"],function(key,value){
				returnCount += value.click;
			});
			$.each(dataSet["search-summary"]["category-searches-global"],function(key,value){
				returnCount += value.click;
			});
			return returnCount;
		}
		this.joinSummaryArrays = function (period1var1Array,period2var1Array, period1var2Array, period2var2Array){
			var returnArray = [];
			var indexArray = [];
			$.each(period1var1Array, function(key, value) { 
				indexArray.push(value.name);
				returnArray.push({
					name:value.name,
					period1:{
						var1:value.count.toString()
					}
				});
				
				//Create "0" cells if needed for period 2, will be overwritten if values exist
				if (period2var1Array) {
				    returnArray[returnArray.length-1].period2 = {
				        var1:"0"
				    }
				}
			});
			if (period1var2Array) {
				$.each(period1var2Array, function(key, value) { 

					var pos = $.inArray(value.name, indexArray);
					if (pos!=-1)
					{
						if (returnArray[pos].period1)
							returnArray[pos].period1.var2 = value.count.toString()
						else
							returnArray[pos].period1 = {var2:value.count.toString()};
					}
					else
					{
						returnArray.push({
							name:value.name,
							period1:{
								var1:"0",
								var2:value.count.toString()
							}
						});
						indexArray.push(value.name);
					}
				});
			}
			if (period2var1Array) {
				$.each(period2var1Array, function(key, value) {
                    
					var pos = $.inArray(value.name, indexArray);
					if (pos!=-1)
					{
						returnArray[pos].period2 = {var1:value.count.toString()};
					}
					else
					{
						returnArray.push({
							name:value.name,
							period1:{
								var1:"0"
							},
							period2:{
								var1:value.count.toString()
							}
						});
						indexArray.push(value.name);
					}
				});
			}
			if (period2var2Array) {
				$.each(period2var2Array, function(key, value) {

					var pos = $.inArray(value.name, indexArray);
					if (pos!=-1)
					{
						if (returnArray[pos].period2)
							returnArray[pos].period2.var2 = value.count.toString()
						else
							returnArray[pos].period2 = {var2:value.count.toString()};
						}
					else
					{
						returnArray.push({
							name:value.name,
							period1:{
								var1:"0"
							},
							period2:{
								var1:value.count.toString()
							}
						});
						indexArray.push(value.name);
					}
				});
			}
			return returnArray;
		}
		
		this.getSearchIndexArray = function (allArrays)
		{
			var indexArray = [];
			$.each(allArrays, function(arrKey, varArray) {
				if (varArray){
					$.each(varArray, function(key, value) {
						var pos = $.inArray(value.name, indexArray);
						if (pos==-1){
							indexArray.push(value.name);
						}
					});
				}
			});
			
			return indexArray.sort();
		}
		
		this.joinSearchSummaryArrays = function (period1Array,period2Array, indexArray){

			var returnArray = [];
			$.each(indexArray, function(key, value) {
				returnArray.push({
					name:value,
					period1:{
						var1:"0",
						var2:"0"
					}
				});
			});
			$.each(period1Array, function(key, value) { 
				returnArray[$.inArray(value.name, indexArray)].period1.var1 = value.search.toString();
				returnArray[$.inArray(value.name, indexArray)].period1.var2 = value.click.toString();
			});
			
			if (period2Array) {
				for (var i=0;i<returnArray.length;i++) {
					returnArray[i].period2 = {
							var1:"0",
							var2:"0"
					};
				}
				$.each(period2Array, function(key, value) {
					returnArray[$.inArray(value.name, indexArray)].period2.var1 = value.search.toString();
					returnArray[$.inArray(value.name, indexArray)].period2.var2 = value.click.toString();
				});
			}
			return returnArray;
		}
        
        this.getTnSummaryData = function (){
            var periods = this.getPeriods(); //{from: '', to: ''},
			periods.period1 = filterModule.storageDateToShortDisplayDate(periods.period1.from) + ' - ' + filterModule.storageDateToShortDisplayDate(periods.period1.to);
			if (periods.period2 != null) {
				periods.period2 = filterModule.storageDateToShortDisplayDate(periods.period2.from) + ' - ' + filterModule.storageDateToShortDisplayDate(periods.period2.to);
			}
            
            var rfq_data = supplierCurrentData["tradenet-summary"]["RFQ"]["count"];
            var p_rfq = supplierCurrentData["enquiry-summary"]["enquiry-sent"]["count"];
            var qot_data = supplierCurrentData["tradenet-summary"]["QOT"]["count"];
            var po_data = supplierCurrentData["tradenet-summary"]["PO"]["count"];
            var povalue_data = supplierCurrentData["tradenet-summary"]["po-total-value"]["count"];
            var srfq_data = rfq_data + p_rfq;
			var returnObject = { "tnsum":[
				{
					period: periods.period1,
					rfq: rfq_data,
                    prfq: p_rfq,
                    srfq: srfq_data.toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,"),
					qot: qot_data.toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,"),
					po: po_data.toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,"),
                    povalue: Math.floor(povalue_data).toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,")
				}
			]};
        
            if (supplierCompareData){
                var rfq_data = supplierCompareData["tradenet-summary"]["RFQ"]["count"];
                var p_rfq = supplierCompareData["enquiry-summary"]["enquiry-sent"]["count"];
                var qot_data = supplierCompareData["tradenet-summary"]["QOT"]["count"];
                var po_data = supplierCompareData["tradenet-summary"]["PO"]["count"];
                var povalue_data = supplierCompareData["tradenet-summary"]["po-total-value"]["count"];
                
                returnObject.tnsum.push({
                	period: periods.period2, 
                	rfq: rfq_data, 
                	prfq: p_rfq, srfq: rfq_data + p_rfq, 
                	qot: qot_data, 
                	po: po_data, 
                	povalue: Math.floor(povalue_data).toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,")
                });
            }

            return returnObject;
        }
        
        this.getMarketData = function(){
            var returnObject = {"market":[
                    {
                        imp: theirsCurrentData.impression.total.toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,"),
                        enq: theirsCurrentData['enquiry-sent'].total.toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,"),
                        cont: theirsCurrentData['contact-view'].total.toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,"),
                        p: 1
                    }
            ]}
        
            if (supplierCompareData){
                returnObject.market.push({
                    imp: theirsCompareData.impression.total.toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,"), 
                    enq: theirsCompareData['enquiry-sent'].total.toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,"),
                    cont: theirsCompareData['contact-view'].total.toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,"),
                    p: 2
                });
            }

            return returnObject;
        }

		this.getSummaryData = function (){
			
			if(theirsCurrentData["enquiry-sent"].count < 1){
				var theirsEnqusent = theirsCurrentData["enquiry-sent"].count.toFixed(1);
			}
			else {
				var theirsEnqusent = Math.round(theirsCurrentData["enquiry-sent"].count);
			}
			
			var periods = this.getPeriods(); //{from: '', to: ''},
			periods.period1 = filterModule.storageDateToShortDisplayDate(periods.period1.from) + ' - ' + filterModule.storageDateToShortDisplayDate(periods.period1.to);
			if (periods.period2 != null) {
				periods.period2 = filterModule.storageDateToShortDisplayDate(periods.period2.from) + ' - ' + filterModule.storageDateToShortDisplayDate(periods.period2.to);
			}
        
			var returnObject = { "metrics":[
				{
					title:"Banners",
					help: "Banner adverts for your company which appear on ShipServ Pages. Every time the banner appears to a visitor then this is counted as 1 impression. The user then decides if they want to click on the banner.<br/><br/>You will have previously consented to take or buy banner adverts. The nature of the targeting will determines on which pages your banner appears would have been agreed between us when they were first set up.<br/><br/>If this is shown as 0 [zero] then this means you have not taken any banner advertising on ShipServ.<br/><br/>For Supplier Insight Report User Guides <a href='/help/sir'>click here</a>.",
					id:"bannersMetric",
					type:"horizontalMetric",
                    graphs:[
						{
							legend:"Impressions",
							values:[{ value: supplierCurrentData["banner-summary"].impression.count.toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,"), title: "Your banner impresions for " + periods.period1 }]
						}
						/*
						,{
							legend:"Clicks",
							values:[{value:supplierCurrentData["banner-summary"].click.count, title: "Your banner clicks for " + periods.period1}]
						}
						*/
					]
				},
				{
					id:"targetedSearchesMetric",
					title: "Total Search Impressions",
					help: "This is the total number of searches users made on Pages for all the brands and categories that appear in your Listing (during the specified time period). You can see which brand and categories you appear for in your Listing. You can change and add your brands and categories in the Self Service tool <a href='/help/sir'>click here</a> for more information on how to do this.<br/><br/>For Supplier Insight Report User Guides <a href='/help/sir'>click here</a>.",
					type:"horizontalMetric",
					graphs:[
						{
							legend:"Searches",
							values:[{ value: supplierCurrentData["search-summary"]["brand-and-category-searches"].count.toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,"), title: "Your Search Impressions for " + periods.period1 }]
						}
						/*
						,{
							legend:"Clicks",
							values:[{value:this.getTargetedSearchesCicksCount(supplierCurrentData), title: "Your Search Impressions for " + periods.period1}]
						}
						*/
					]
				},
				{
					id:"profileViewsMetric",
					title:"Profile Views",
					help: "The total number of times your company's profile was viewed during the selected time period.<br/><br/>For Supplier Insight Report User Guides <a href='/help/sir'>click here</a>.<br/><br/>To see the definition of the grey 'Market' graphs click on the ? to the right of Compare to Market.",
					type:"verticalMetric",
					graphs:[
						{
							legend:"You",
							values:[{ value:supplierCurrentData["impression-summary"].impression.count, fullvalue: supplierCurrentData["impression-summary"].impression.count, title: "Your profile views for " + periods.period1 }]
						},
						{
							legend:"Market (avg.)",
							values:[{ value: Math.round(theirsCurrentData.impression.count), fullvalue: theirsCurrentData.impression.count, title: "Market profile views for " + periods.period1 }]
						}
					]
				},
				{
					id:"contactViewsMetric",
					title:"Contact Views",
					help: "This is the number of times users of Pages clicked on the Contact tab on your profile and so saw your contact information, in the time period selected for your report.<br/><br/>For Supplier Insight Report User Guides <a href='/help/sir'>click here</a>.",
					type:"verticalMetric",
					graphs:[
						{
							legend:"You",
							values:[{ value:supplierCurrentData["impression-summary"]["contact-view"].count, fullvalue: supplierCurrentData["impression-summary"]["contact-view"].count, title: "Your contact views for " + periods.period1 }]
						},
						{
							legend:"Market (avg.)",
							values:[{ value: Math.round(theirsCurrentData["contact-view"].count), fullvalue: theirsCurrentData["contact-view"].count, title: "Market contact views for " + periods.period1 }]
						}
					]
				},
				{
					id:"enquiriesMetric",
					title:"Pages RFQs",
					help: "This is the total number of times you were sent a RFQ from Pages from a potential buyer (during the time period you specified).<br/><br/>Please contact your account manager if you have any questions about this number.<br/><br/>For Supplier Insight Report User Guides <a href='/help/sir'>click here</a>.",
					type:"verticalMetric",
					graphs:[
						{
							legend:"You",
							values:[{value:supplierCurrentData["enquiry-summary"]["enquiry-sent"].count, fullvalue: supplierCurrentData["enquiry-summary"]["enquiry-sent"].count, title: "Your RFQs for " + periods.period1}]
						},
						{
							legend:"Market (avg.)",
							values:[{ value: theirsEnqusent, fullvalue: theirsCurrentData["enquiry-sent"].count, title: "Market RFQs for " + periods.period1}]
						}
					]
				}
			],
            "pageOneImpressions": supplierCurrentData["search-summary"]["top-search-impression"].count.toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,"),
            "uniqueContactViews": supplierCurrentData["impression-summary"]["unique-contact-view"].count.toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,"),
            "poTotalValue": Math.floor(supplierCurrentData["tradenet-summary"]["po-total-value"].count).toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,")
        };

			if (supplierCompareData){
				returnObject.metrics[0].graphs[0].values.push({ value: supplierCompareData["banner-summary"].impression.count, title: "Your banner impresions for " + periods.period2});
				/*returnObject.metrics[0].graphs[1].values.push({ value: supplierCompareData["banner-summary"].click.count, title: "Your banner clicks for " + periods.period2});*/
				returnObject.metrics[1].graphs[0].values.push({ value: supplierCompareData["search-summary"]["brand-and-category-searches"].count, title: "Your Search Impressions for " + periods.period2});
				/*returnObject.metrics[1].graphs[1].values.push({ value: this.getTargetedSearchesCicksCount(supplierCompareData), title: "Your targeted search clicks for " + periods.period2});*/
				returnObject.metrics[2].graphs[0].values.push({ value: supplierCompareData["impression-summary"].impression.count, fullvalue: supplierCompareData["impression-summary"].impression.count, title: "Your profile views for " + periods.period2});
				returnObject.metrics[2].graphs[1].values.push({ value: Math.round(theirsCompareData.impression.count), fullvalue: theirsCompareData.impression.count, title: "Market profile views for " + periods.period2});
				returnObject.metrics[3].graphs[0].values.push({ value: supplierCompareData["impression-summary"]["contact-view"].count, fullvalue:supplierCompareData["impression-summary"]["contact-view"].count, title: "Your contact views for " + periods.period2});
				returnObject.metrics[3].graphs[1].values.push({ value: Math.round(theirsCompareData["contact-view"].count), fullvalue: theirsCompareData["contact-view"].count,title: "Market contact views for " + periods.period2});
				returnObject.metrics[4].graphs[0].values.push({ value: supplierCompareData["enquiry-summary"]["enquiry-sent"].count, fullvalue: supplierCompareData["enquiry-summary"]["enquiry-sent"].count, title: "Your enquiries for " + periods.period2});
				returnObject.metrics[4].graphs[1].values.push({ value: Math.round(theirsCompareData["enquiry-sent"].count), fullvalue: theirsCompareData["enquiry-sent"].count, title: "Market enquiries for " + periods.period2});
				
				returnObject.pageOneImpressionsCompare = supplierCompareData["search-summary"]["top-search-impression"].count.toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,");
				returnObject.uniqueContactViewsCompare = supplierCompareData["impression-summary"]["unique-contact-view"].count.toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,");
				returnObject.poTotalValueCompare = Math.floor(supplierCompareData["tradenet-summary"]["po-total-value"].count).toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,");
            }

			//set proper height or width for elements
			for (var i=0;i<returnObject.metrics.length;i++)
			{
				if (returnObject.metrics[i].type=="horizontalMetric")
				{
					for (var graph=0;graph<returnObject.metrics[i].graphs.length;graph++)
					{
						for (var value=0;value<returnObject.metrics[i].graphs[graph].values.length;value++)
						{
							returnObject.metrics[i].graphs[graph].values[value].size = (returnObject.metrics[i].graphs[graph].values[value].value.toString().length*10+22) + "px";
						}
					}
				}
				if (returnObject.metrics[i].type=="verticalMetric")
				{
					var max = 0;
					for (var graph=0;graph<returnObject.metrics[i].graphs.length;graph++)
					{
						for (var value=0;value<returnObject.metrics[i].graphs[graph].values.length;value++)
						{
							if (returnObject.metrics[i].graphs[graph].values[value].value > max) max = returnObject.metrics[i].graphs[graph].values[value].value;
						}
					}
					for (var graph=0;graph<returnObject.metrics[i].graphs.length;graph++)
					{
						for (var value=0;value<returnObject.metrics[i].graphs[graph].values.length;value++)
						{
							returnObject.metrics[i].graphs[graph].values[value].size = ((returnObject.metrics[i].graphs[graph].values[value].value > 0 )?(returnObject.metrics[i].graphs[graph].values[value].value*99/max + 1):1) + "%";
						}
					}
				}
			}

			return returnObject;
		}

		this.getImpressionBySearchKeyword = function () {

			return this.joinSummaryArrays(supplierCurrentData["impression-summary"]["impression-by-search-keywords"], (supplierCompareData)?supplierCompareData["impression-summary"]["impression-by-search-keywords"]:null);
		}
		
		this.getImpressionByUserType = function () {
			
			return this.joinSummaryArrays(supplierCurrentData["impression-summary"]["impression-by-user-type"], (supplierCompareData)?supplierCompareData["impression-summary"]["impression-by-user-type"]:null);
		}
		this.getEnquiriesBySearchKeyword = function () {
			
			return this.joinSummaryArrays(supplierCurrentData["enquiry-summary"]["enquiries-sent-by-search-keywords"], (supplierCompareData)?supplierCompareData["enquiry-summary"]["enquiries-sent-by-search-keywords"]:null);
		}
		this.getEnquiriesByUserType = function () {
			
			return this.joinSummaryArrays(supplierCurrentData["enquiry-summary"]["enquiries-sent-by-user-type"], (supplierCompareData)?supplierCompareData["enquiry-summary"]["enquiries-sent-by-user-type"]:null);
		}
		
		this.getGlobalBrandSearches = function () {
			return this.joinSearchSummaryArrays(supplierCurrentData["search-summary"]["brand-searches-global"], (supplierCompareData)?supplierCompareData["search-summary"]["brand-searches-global"]:null, this.getBrandSearchesIndex());
		}
		
		this.getLocalBrandSearches = function () {
			return this.joinSearchSummaryArrays(supplierCurrentData["search-summary"]["brand-searches-local"], (supplierCompareData)?supplierCompareData["search-summary"]["brand-searches-local"]:null, this.getBrandSearchesIndex());
		}
		this.getGlobalCategorySearches = function () {
			return this.joinSearchSummaryArrays(supplierCurrentData["search-summary"]["category-searches-global"], (supplierCompareData)?supplierCompareData["search-summary"]["category-searches-global"]:null, this.getCategorySearchesIndex());
		}
		
		this.getLocalCategorySearches = function () {
			return this.joinSearchSummaryArrays(supplierCurrentData["search-summary"]["category-searches-local"], (supplierCompareData)?supplierCompareData["search-summary"]["category-searches-local"]:null, this.getCategorySearchesIndex());
		}
		this.getProfileViewsTotalNUnique = function () {
			var returnArray = [];

			returnArray.push({
				name:"Total Number",
				period1:{
					var1: supplierCurrentData["impression-summary"].impression.count.toString(),
					var2: (theirsCurrentData)?theirsCurrentData.impression.count.toString():null
				}
			});
			returnArray.push({
				name:"By Unique Users",
				period1:{
					var1: supplierCurrentData["impression-summary"]["impression-by-unique-user"].count.toString(),
					var2: (theirsCurrentData)?theirsCurrentData["impression-by-unique-user"].count.toString():null
				}
			});	
			if (supplierCompareData)
			{
				returnArray[0].period2 = {
					var1: supplierCompareData["impression-summary"].impression.count.toString(),
					var2: (theirsCompareData)?theirsCompareData.impression.count.toString():null
				};
				returnArray[1].period2 = {
					var1: supplierCompareData["impression-summary"]["impression-by-unique-user"].count.toString(),
					var2: (theirsCompareData)?theirsCompareData["impression-by-unique-user"].count.toString():null
				};
			}
			return returnArray;
		}
		
		this.getContactViewsTU = function ()
		{
			var returnArray = [];
			returnArray.push({
				name:"Total Number",
				period1:{
					var1: supplierCurrentData["impression-summary"]["contact-view"].count.toString(),
					var2: (theirsCurrentData) ? theirsCurrentData["contact-view"].count.toString():null
				}
			});
			returnArray.push({
				name:"By Unique Users",
				period1:{
					var1: supplierCurrentData["impression-summary"]["contact-view-by-unique-user"].count.toString(),
					var2: (theirsCurrentData)?theirsCurrentData["contact-view-by-unique-user"].count.toString():null
				}
			});	
			if (supplierCompareData)
			{
				returnArray[0].period2 = {
					var1: supplierCompareData["impression-summary"]["contact-view"].count.toString(),
					var2: (theirsCompareData)?theirsCompareData["contact-view"].count.toString():null
				};
				returnArray[1].period2 = {
					var1: supplierCompareData["impression-summary"]["contact-view-by-unique-user"].count.toString(),
					var2: (theirsCompareData)?theirsCompareData["contact-view-by-unique-user"].count.toString():null
				};
			}
			return returnArray;
		}
		
		this.getEnquiresTU = function () {
			var returnArray = [];
			returnArray.push({
				name:"Total Number",
				period1:{
					var1: supplierCurrentData["enquiry-summary"]["enquiry-sent"].count.toString(),
					var2: (theirsCurrentData)?theirsCurrentData["enquiry-sent"].count.toString():null
				}
			});
			returnArray.push({
				name:"By Unique Users",
				period1:{
					var1: supplierCurrentData["enquiry-summary"]["enquiries-sent-by-unique-user"].count.toString(),
					var2: (theirsCurrentData["enquiry-sent-by-unique-user"])?theirsCurrentData["enquiry-sent-by-unique-user"].count.toString():"0"
				}
			});	
			if (supplierCompareData)
			{
				returnArray[0].period2 = {
					var1: supplierCompareData["enquiry-summary"]["enquiry-sent"].count.toString(),
					var2: (theirsCompareData)?theirsCompareData["enquiry-sent"].count.toString():null
				};
				returnArray[1].period2 = {
					var1: supplierCompareData["enquiry-summary"]["enquiries-sent-by-unique-user"].count.toString(),
					var2: (theirsCompareData["enquiry-sent-by-unique-user"])?theirsCompareData["enquiry-sent-by-unique-user"].count.toString():"0"
				};
			}
			return returnArray;
		}
	}

	/**
	 * Public module definition
	 */
	return {

		fetchStats: function(filters, callback) { 
			/*if(filters != lastFetchedFilters) {*/ //TODO - more logic needed, was storing and comparing by reference, always true after first run!
				/*lastFetchedFilters = filters;*/ //TODO clone
				lastResponse = new ReportData();
				lastResponse.setFilters(filters);
				lastResponse.onReady(callback);
				$.ajax({
					url: '/reports/api/supplier-impression',
					type: 'POST',
					data: {
						tnid:filters.tnid,
						start: filters.period[0].from,
						end: filters.period[0].to
					},
					cache: false,
					error: function(request, textStatus, errorThrown) {
						response = eval('(' + request.responseText + ')');
						alert("ERROR " + request.status + ": " + response.error);
						$('#waiting').hide();
					},
					success: function( response ){
						lastResponse.set("supplierCurrent",response.data.supplier); 
					}
				});
				$.ajax({
					url: '/reports/api/general-impression',
					type: 'POST',
					data: {
						start: filters.period[0].from,
						end: filters.period[0].to,
						categories:filters.categories.join(","),
						brands:filters.brands.join(","),
						products:filters.products.join(","),
						location:filters.location.join(",")

					},
					cache: false,
					error: function(request, textStatus, errorThrown) {
						response = eval('(' + request.responseText + ')');
						alert("ERROR " + request.status + ": " + response.error);					    	
					},
					success: function( response ){
						lastResponse.set("theirsCurrent",response.data.general);
					}
				});

				if (filters.period.length>1)
				{
					$.ajax({
						url: '/reports/api/supplier-impression',
						type: 'POST',
						data: {
							tnid:filters.tnid,
							start: filters.period[1].from,
							end: filters.period[1].to
						},
						cache: false,
						error: function(request, textStatus, errorThrown) {
							response = eval('(' + request.responseText + ')');
							alert("ERROR " + request.status + ": " + response.error);					    	
						},
						success: function( response ){
							lastResponse.set("supplierCompare",response.data.supplier); 
						}
					});
					$.ajax({
						url: '/reports/api/general-impression',
						type: 'POST',
						data: {
							start: filters.period[1].from,
							end: filters.period[1].to,
							categories:filters.categories.join(","),
							brands:filters.brands.join(","),
							products:filters.products.join(","),
							location:filters.location.join(",")

						},
						cache: false,
						error: function(request, textStatus, errorThrown) {
							response = eval('(' + request.responseText + ')');
							alert("ERROR " + request.status + ": " + response.error);					    	
						},
						success: function( response ){
							lastResponse.set("theirsCompare",response.data.general); 
						}
					});
				}
			/*}	*/ //TODO (See start of block)
		}
	}
});