define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/shared/activity/log',
	'libs/jquery.uniform',
	'../views/quoteList',
	'../views/drillDown',
	'../collections/collection',
	'text!templates/reports/matchSupplierReport/tpl/quote.html'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	logActivity,
	Uniform,
	quoteList,
	drillDown,
	Collection,
	quoteTpl
){
	var matchSupplierQoteView = Backbone.View.extend({
		saving: 0,
		selectedBranchCode: null,
		mustReload: false,
		selectedOrdInternalRefNo: null,
		compareDrillDown: null,
		tmpByo: null,
		events: {

		},

		quoteTemplate: Handlebars.compile(quoteTpl),

		initialize: function(){
			var thisView = this;
			this.Collection = new Collection();
			this.Collection.url = '/reports/data/match-supplier-report';
			
			this.SpbCollection = new Collection();
			this.SpbCollection.url = '/reports/data/match-supplier-report';
			
			quoteList.parent = this;
			drillDown.parent = this;

			$('body').delegate('.comparableIcon', 'click', function(e){
				e.preventDefault();
				thisView.loadComparePopup(e);
			});

			$('body').delegate('.fixComparableIcon', 'click', function(e){
				e.preventDefault();
				$('.uncompMessagePopup').fadeIn(400);
			});
			
			$('body').delegate('.coCloseBtn', 'click', function(e){
				e.preventDefault();
				$('.comparabilityPopup').fadeOut(400);
			});

			$('body').delegate('.coCancelBtn', 'click', function(e){
				e.preventDefault();
				$('.comparabilityPopup').fadeOut(400);
			});

			$('body').delegate('.coOkBtn', 'click', function(e){
				/* Todo implement logic to store result */
				e.preventDefault();
				thisView.storeComparable();
			});
			
		},

		getData: function(spbBranchCode){
			var thisView = this;
			var $branchCode;
			var $date;
			
			if ($('#branch').length > 0) {
				$branchCode = $('#branch').val();
				$date = $('#date').val();
			} else {
				$branchCode = this.tmpByo;
				$date = 0;
				this.parent.getFilters().onAlertLandingPage($branchCode);
			}
			
			this.selectedBranchCode = spbBranchCode;
			
			this.Collection.reset();
			this.Collection.fetch({
				data: {
					type: 'supplier-branch',
					bybBranchCode: $branchCode,
					spbBranchCode: spbBranchCode,
					date: $date
				},
				complete: function(){
					thisView.preRender(spbBranchCode);
				},
				error: function()
				{
					$('#waiting').hide();
					$('#innerContent').empty();
					var errorMsg = $('<span>');
					errorMsg.addClass('error');
					errorMsg.html("We're sorry, there is a problem with our backend server. Please try again later.");
					$('#innerContent').append(errorMsg);
					thisView.fixHeight();
				}
			});
			
		},

		preRender: function(spbBranchCode)
		{
			var thisView = this;
			var data = this.Collection.models[0].attributes;
			data.saving = this.saving;

			data.date = (this.parent.getFilters().selectedDate == 0) ? '-' : this.parent.getFilters().selectedDate;
			var html = this.quoteTemplate(this.Collection.models[0].attributes);
			$('#innerContent').html(html);

			$('.profileView').click(function(){
				thisView.drillDown($(this));
			});

			this.render(spbBranchCode, data);		

		},

		getDataPageOne: function(spbBranchCode)
		{
			quoteList.resetPagination();
			this.getData(spbBranchCode);
		},
			
		render: function(spbBranchCode, data) {
			$('#titleText').html('Supplier Recommendations Report for '+this.Collection.models[0].attributes.supplier.name);
			this.adjustTradeRank();
			this.addBackButton();
			quoteList.getData(spbBranchCode, data);

		},

		renderLoaded: function(){
			var thisView = this;
			$('#titleText').html('Supplier Recommendations Report for '+this.Collection.models[0].attributes.supplier.name);
			this.addBackButton();

			var data = this.Collection.models[0].attributes;
			data.saving = this.saving;
			data.date = (this.parent.getFilters().selectedDate == 0) ? '-' : this.parent.getFilters().selectedDate;
			var html = this.quoteTemplate(this.Collection.models[0].attributes);

			$('#innerContent').html(html);

			$('.profileView').click(function(){
				thisView.drillDown($(this));
			});

			this.adjustTradeRank();
			quoteList.render();
		},

		drillDown: function(e)
		{
			var rfqId = $(e).data('rfqid');
			if (typeof(rfqId) != "undefined") {
				logActivity.logActivity('spend-benchmark-full-details', rfqId);
				drillDown.getData(rfqId);
			}
		},

		addBackButton: function()
		{
			var thisView = this;
			var titleButton = $('<a>');
			titleButton.addClass('backBtn');
			titleButton.html('Back');
			titleButton.click(function(){
				thisView.onBackClick($(this));
			});
			
			$('#titleText').append(titleButton);
		},

		onBackClick: function()
		{
			if (this.mustReload === false) {
				this.parent.getFilters().loadFirst = true;
			} else {
				this.parent.getFilters().loadFirst = false;
			}
			
			this.parent.getFilterData();
		},

		fixHeight: function()
        {
            var newHeight = ($('#content').height() > 422) ? $('#content').height()  +25 : 527;
            $('#body').height(newHeight);  
            $('#footer').show();

        },

        adjustTradeRank: function()
        {
        	var tradeRank = parseInt($('#traderank').data('rank'));
        	if (tradeRank == 0) {
        		$('#tr-no-data').show();
        		$('#td-bar').width(0);
        	} else {
	        	tradeRankStars = Math.round(tradeRank *100 / 20);
	        	var tradeWidth = 80 * (tradeRankStars/100);
	        	/* THis correction becouse of the incorrectly drawn image (space between stars is more at 19px) */
	        	if (tradeWidth > 20) {
	        		tradeWidth = tradeWidth +2;
	        	}
	        	$('#td-bar').width(tradeWidth);
	        	$('#tr-no-data').hide();
        	}
        },

        getSelectedSpbCode: function()
        {
        	return this.selectedBranchCode;
        },

		loadComparePopup: function(e) {
			var thisView = this;
			thisView.selectedOrdInternalRefNo = $(e.target).data('id');
			thisView.compareDrillDown = $(e.target).data('drilldown');

			$.ajax({
                type: "POST",
                url: "/buyer/quote/stats-exclude",
				data: { 
					quoteRefNo : thisView.selectedOrdInternalRefNo
				},
                success: function( response ){
 						thisView.renderExcludeRadioItems(response.reasons);
						if (response.statsExclude === false) {
							$('#qcOption0').attr('checked','checked');
						} else {
							if (response.reasonId !== null) {
								$('#qcOption'+response.reasonId).attr('checked','checked');
							} else {
								//TODO select default value
							}
						}
						$('input[name="cpComparable"]').uniform();
						$('.comparabilityPopup').fadeIn(400);
                },
                error: function(e) {
                	alert('Something went wrong, Please try it later');
                }
            });

		},

		storeComparable: function() {
			var thisView = this;
			var exclude = null;
			$('.comparabilityPopup').hide();

			if ($('#qcOption0').attr('checked')) {
				exclude = 0;
			} else {
				 exclude = 1;
			}

			var userSelectedItem = $('input[name="cpComparable"]:checked').val();
	    	
            $.ajax({
                type: "POST",
                url: "/buyer/quote/stats-exclude",
				data: { 
					exclude : exclude,
					quoteRefNo : thisView.selectedOrdInternalRefNo,
					reasonId : userSelectedItem
				},
                success: function(){
					$.ajax({
		                type: "GET",
		                url: "/buyer/blacklist/clear-spend-benchmark-cache",
		                success: function(){
		                	if (thisView.compareDrillDown == 1) {
		                		thisView.updateComparable(userSelectedItem, exclude, false);
		                	} else {
		                		thisView.updateComparable(userSelectedItem, exclude, true);
		                	}
		                },
		                error: function(e) {
		                	alert('Something went wrong, Please try it later');
		                	$('.comparabilityPopup').fadeOut(400);
		                }
		            });
                },
                error: function(e) {
                	alert('Something went wrong, Please try it later');
                	$('.comparabilityPopup').fadeOut(400);
                }
            });
		},

		renderExcludeRadioItems: function( data )
		{
			var holderElement = $('#coQuestions');
			holderElement.html('');

			this.addNewReasonElement(holderElement, {id:0,reason:'Comparable'});

			for (var key in data) {
				this.addNewReasonElement(holderElement, data[key]);
			}
		},

		addNewReasonElement: function(holder, dataItem)
		{
			var divElement = $('<div>');
			divElement.addClass('controlHolder');

			var radioElement = $('<input>');
			radioElement.attr('type','radio');
			radioElement.attr('id', 'qcOption'+dataItem.id);
			radioElement.attr('name', 'cpComparable');
			if (dataItem.isDefault == 1) {
				radioElement.attr('checked', 'checked');
			}
			radioElement.val(dataItem.id);

			var labelElement = $('<label>');
			labelElement.attr('for','qcOption'+dataItem.id);
			labelElement.html(dataItem.reason);
			divElement.append(radioElement);

			holder.append(divElement);
			holder.append(labelElement);

		},
		updateComparable: function(userSelectedItem, exclude, reload)
		{
			
			var thisView = this;
        	
        	for (var key in quoteList.Collection.models[0].attributes.data) {
        		var qtItem = quoteList.Collection.models[0].attributes.data[key];
        		if (thisView.selectedOrdInternalRefNo == qtItem.qotInternalRefNo) {
        			qtItem.excludeReason = userSelectedItem;
        			qtItem.correctedSaving = (exclude == 1) ? '0' : qtItem.saving;
        			if (qtItem.bestQuotedPrice > 0) {
        				qtItem.potentialSavingPercent =  Math.round(qtItem.saving / qtItem.bestQuotedPrice * 100);
        			} else {
        				qtItem.potentialSavingPercent = 0;
        			}
        		} 
        		
        	}
        	
        	this.updateComparableFromServer(reload);
        	
		},
		
		updateComparableFromServer: function(reload)
		{
			var thisView = this;

			this.SpbCollection.reset();
			this.SpbCollection.fetch({
				data: {
					type: 'supplier-aggregated',
					bybBranchCode: thisView.parent.getFilters().selectedBranch,
					vessel:  thisView.parent.getFilters().selectedVessel,
					date: thisView.parent.getFilters().selectedDate,
					segment: thisView.parent.getFilters().selectedSegment,
					keyword: thisView.parent.getFilters().selectedKeyword,
					quality: thisView.parent.getFilters().selectedQuality,
					sameq: thisView.parent.getFilters().selectedSameq,
					page: 1,
					itemPerPage: 1,
					purgeCache: false,
					spbBranchCode: thisView.selectedBranchCode
				},
				complete: function(){
					thisView.saving = thisView.SpbCollection.models[0].attributes.data[0].savingTotal;
					if (reload) {
						thisView.mustReload = true;
	                	$('#innerContent').html('');
	                	thisView.renderLoaded();
					}
				},
				error: function()
				{
					$('#waiting').hide();
					$('#result').empty();
					var errorMsg = $('<span>');
					errorMsg.addClass('error');
					errorMsg.html("We're sorry, there is a problem with our backend server. Please try again later.");
					$('#result').append(errorMsg);
					thisView.fixHeight();
				}
			});
		}

	});

	return new matchSupplierQoteView();
});