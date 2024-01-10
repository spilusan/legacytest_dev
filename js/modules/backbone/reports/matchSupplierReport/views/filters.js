define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'libs/jquery.uniform',
	'libs/jquery.dateFormat',
	'../collections/collection',
	'text!templates/reports/matchSupplierReport/tpl/matchSupplierReport.html',
	'text!templates/reports/matchSupplierReport/tpl/help/howToUse.html'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	Uniform,
	DateFormat,
	Collection,
	MatchSupplierReportTpl,
	HowToUseHelpTpl

){
	var matchSupplierFilterView = Backbone.View.extend({
		selectedBranch: null,
		selectedVessel: null,
		selectedQuality: null,
		selectedSameq: null,
		selectedDate: null,
		selectedSegment: null,
		selectedKeyword: null,
		renderLoadedList: false,
		matchSupplierReportTemplate: Handlebars.compile(MatchSupplierReportTpl),
		HowToUseHelpTemplate: Handlebars.compile(HowToUseHelpTpl),
		loadFirst: false,
		
		events: {
		},

		initialize: function(){
			var thisView = this;
			this.branchCollection = new Collection();
			this.branchCollection.url = '/data/source/buyer-branches';
			this.vesselCollection = new Collection();
			this.vesselCollection.url = '/data/source/buyer-match-vessel';
			this.segmentsCollection = new Collection();
			this.segmentsCollection.url = '/data/source/buyer-match-segments';
			this.keywordsCollecton = new Collection();
			this.keywordsCollecton.url = '/data/source/buyer-match-keywords';
			this.qualityCollection = new Collection();
			this.qualityCollection.url = '/reports/data/match-supplier-report?type=quote-quality';
			
			var activeCompany = (window.activeCompany  === undefined) ?  0 : window.activeCompany;
			var helpHtml = this.HowToUseHelpTemplate({tnid: activeCompany});
			var element = $('<div>');
			element.addClass('newHelp');
			element.html(helpHtml);
			$('body').append(element);
			$('.infoCloseBtn').click(function(){
				$('.newHelp').fadeOut(400);
			});

			/* Help with up arrow */
			element = $('<div>');
			element.addClass('savingHelp');
			$('body').append(element);

			$('.savingHelp').click(function(){
				$(this).fadeOut(400);
			});

			/* Help with down arrow */
			element = $('<div>');
			element.addClass('smallHelp');
			$('body').append(element);

			$('.smallHelp').click(function(){
				$(this).fadeOut(400);
			});

			$('body').click(function(e){
				if (!(($(e.target).hasClass('pHelp')) || ($(e.target).hasClass('sHelp')))) {
					$('.savingHelp').hide();
					$('.smallHelp').hide();
				}
			});
		},

		getData: function(){
			var thisView = this;
			thisView.renderDefaultPage();
		},

		getBranches: function()
		{
			var thisView = this;
			thisView.parent.showAjaxLoad = false;
			thisView.setControlsState(true);
			$('input.go').addClass('disabled');
			$('#waiting').hide();
			$('#branchSpinner').show();
			
			this.branchCollection.reset();
			var branchSelect = $('#branch');
			branchSelect.empty();

			this.branchCollection.fetch({
				complete: function(){
					thisView.parent.showAjaxLoad = true;
					$('input.go').removeClass('disabled');
					$('#branchSpinner').hide();
					thisView.setControlsState(false);
					var listForAll = '';
					for (var key in thisView.branchCollection.models) {
						listForAll += (listForAll === '') ? ''+thisView.branchCollection.models[key].attributes.id : ','+thisView.branchCollection.models[key].attributes.id;
					}
					
					var option = null;
					
					if (thisView.branchCollection.models.length > 1) {
						option = $('<option>');
						option.val(listForAll);
						option.text('All');
						branchSelect.append(option);
					} 
					
					var selectedId = null;
					var suppliedSelected = null;
					for (key in thisView.branchCollection.models) {
						if (thisView.branchCollection.models[key].attributes.default == 1) {
							suppliedSelected = thisView.branchCollection.models[key].attributes.id;
						}
						option = $('<option>');
						var optionName = thisView.branchCollection.models[key].attributes.name;

						if (optionName.toLowerCase().indexOf("test") === -1 && selectedId === null) {
							selectedId = thisView.branchCollection.models[key].attributes.id;
						}
						option.val(thisView.branchCollection.models[key].attributes.id);
						option.text(optionName);
						branchSelect.append(option);
					}

					if (suppliedSelected !== null) {
						selectedId = suppliedSelected;
					}

					if (thisView.selectedBranch === null) 
					{
						thisView.selectedBranch = selectedId;
					}
					branchSelect.val(thisView.selectedBranch);
					$.uniform.update(branchSelect);
					thisView.getVessels(branchSelect.val());
					thisView.getSegments();
				},
				error: function()
				{
					thisView.parent.showAjaxLoad = true;
					$('input.go').removeClass('disabled');
					$('#waiting').hide();
					$('#branchSpinner').hide();
					thisView.setControlsState(false);
					$('#innerContent').empty();
					var errorMsg = $('<span>');
					errorMsg.addClass('error');
					errorMsg.html("We're sorry, there is a problem with our backend server. Please try again later.");
					$('#innerContent').append(errorMsg);
					thisView.fixHeight();
				}
			});

		},
		
		getQualities: function () {
			var thisView = this;
			thisView.parent.showAjaxLoad = false;
			thisView.setControlsState(true);
			$('input.go').addClass('disabled');
			$('#waiting').hide();
			$('#qualitySpinner').show();
			
			this.qualityCollection.reset();
			var selectEl = $('#quality');
			selectEl.empty();

			this.qualityCollection.fetch({
				complete: function(){
					
					thisView.parent.showAjaxLoad = true;
					$('input.go').removeClass('disabled');
					$('#qualitySpinner').hide();
					thisView.setControlsState(false);
					
					var option = $('<option>');
					option.val("");
					option.text('All');
					selectEl.append(option);
					
					for (var key in thisView.qualityCollection.models) {
						option = $('<option>');
						option.val(thisView.qualityCollection.models[key].attributes.ID);
						option.text(thisView.qualityCollection.models[key].attributes.QUALITY);
						selectEl.append(option);
					}
					selectEl.val(thisView.selectedQuality);
					$.uniform.update(selectEl);
				},
				error: function()
				{
					thisView.parent.showAjaxLoad = true;
					$('input.go').removeClass('disabled');
					$('#waiting').hide();
					$('#categorySpinner').hide();
					thisView.setControlsState(false);
					$('#innerContent').empty();
					var errorMsg = $('<span>');
					errorMsg.addClass('error');
					errorMsg.html("We're sorry, there is a problem with our backend server. Please try again later.");
					$('#innerContent').append(errorMsg);
					thisView.fixHeight();
				}
			});
		},

		getVessels: function( branchId )
		{
			var thisView = this;
			thisView.parent.showAjaxLoad = false;
			thisView.setControlsState(true);
			$('input.go').addClass('disabled');
			$('#waiting').hide();
			$('#vesselSpinner').show();
			var today = new Date();
			var dTo = $.format.date(today, "dd-MM-yyyy");	
			var dateFrom = new Date(today.getFullYear(),today.getMonth()-parseInt($('#date').val()),today.getDate());
			var dFrom = $.format.date(dateFrom, "dd-MM-yyyy");	
			this.vesselCollection.reset();
			var vesselElement = $('#vessel');
			vesselElement.empty();

			var option = $('<option>');
			option.text('All');
			vesselElement.append(option);

			this.vesselCollection.fetch({
				data: {
					buyerId: branchId,
					/* S15948 Requested to remove
					startDate: dFrom,
					endDate: dTo,
					*/
				},
				complete: function() {
					thisView.parent.showAjaxLoad = true;
					$('input.go').removeClass('disabled');
					thisView.parent.showAjaxLoad = true;
					$('#vesselSpinner').hide();
					thisView.setControlsState(false);
					for (var key in thisView.vesselCollection.models) {
						var option = $('<option>');
						//option.text(thisView.vesselCollection.models[key].attributes.NAME + ' ('+ thisView.vesselCollection.models[key].attributes.RFQ_TOTAL +')');
						option.text(thisView.vesselCollection.models[key].attributes.NAME);
						option.val(thisView.vesselCollection.models[key].attributes.NAME);
						vesselElement.append(option);
					}

					$('#vessel').val(thisView.selectedVessel);
					thisView.selectedVessel = $('#vessel').val();
					$.uniform.update(vesselElement);
					if (thisView.loadFirst === false) {
							thisView.loadFirst = true;
							thisView.onShowClicked(null);
					}

				},
				error: function()
				{
					thisView.parent.showAjaxLoad = true;
					$('input.go').removeClass('disabled');
					$('#waiting').hide();
					$('#vesselSpinner').hide();
					thisView.setControlsState(false);
					$('#innerContent').empty();
					var errorMsg = $('<span>');
					errorMsg.addClass('error');
					errorMsg.html("We're sorry, there is a problem with our backend server. Please try again later.");
					$('#innerContent').append(errorMsg);
					thisView.fixHeight();
				}
			});

		},
		
		getSegments: function() {
			
			var thisView = this;
			thisView.parent.showAjaxLoad = false;
			thisView.setControlsState(true);
			$('input.go').addClass('disabled');
			$('#waiting').hide();
			$('#categorySpinner').show();
			
			this.segmentsCollection.reset();
			var selectEl = $('#category');
			selectEl.empty();

			this.segmentsCollection.fetch({
				data: {
					'bybBranchCode': $('#branch').val()
				},
				complete: function(){
					
					thisView.parent.showAjaxLoad = true;
					$('input.go').removeClass('disabled');
					$('#categorySpinner').hide();
					thisView.setControlsState(false);
					
					var option = $('<option>');
					option.val("");
					option.text('All');
					selectEl.append(option);
					
					for (var key in thisView.segmentsCollection.models) {
						if (parseInt(thisView.segmentsCollection.models[key].attributes.validSegmentCount) !== 0) {
							option = $('<option>');
							option.val(thisView.segmentsCollection.models[key].attributes.msId);
							option.text(thisView.segmentsCollection.models[key].attributes.msNotes);
							
							/*
							 * This feature is removed for request
							if (parseInt(thisView.segmentsCollection.models[key].attributes.validSegmentCount) === 0) {
								option.attr('disabled', 'disabled');
							}
							*/
							selectEl.append(option);
						}
					}
					selectEl.val(thisView.selectedSegment);
					$.uniform.update(selectEl);
					thisView.getKeywords(thisView.selectedSegment);
				},
				error: function()
				{
					thisView.parent.showAjaxLoad = true;
					$('input.go').removeClass('disabled');
					$('#waiting').hide();
					$('#categorySpinner').hide();
					thisView.setControlsState(false);
					$('#innerContent').empty();
					var errorMsg = $('<span>');
					errorMsg.addClass('error');
					errorMsg.html("We're sorry, there is a problem with our backend server. Please try again later.");
					$('#innerContent').append(errorMsg);
					thisView.fixHeight();
				}
			});
		},
		
		getKeywords: function(segmentId) {
			var thisView = this;
			thisView.parent.showAjaxLoad = false;
			thisView.setControlsState(true);
			$('input.go').addClass('disabled');
			$('#waiting').hide();
			$('#brandSpinner').show();
			
			this.segmentsCollection.reset();
			var selectEl = $('#brand');
			selectEl.empty();
			
			var option = $('<option>');
			option.val("");
			option.text('All');
			selectEl.append(option);
			
			if (segmentId) {
				this.keywordsCollecton.fetch({
					data: {
						'segmentId': segmentId,
						'bybBranchCode': $('#branch').val()
					},
					complete: function(){
						thisView.parent.showAjaxLoad = true;
						$('input.go').removeClass('disabled');
						$('#brandSpinner').hide();
						thisView.setControlsState(false);
						
						for (var key in thisView.keywordsCollecton.models) {
							var option = $('<option>');
							option.val(thisView.keywordsCollecton.models[key].attributes.mssId);
							option.text(thisView.keywordsCollecton.models[key].attributes.mssName);
							selectEl.append(option);
						}
						selectEl.val(thisView.selectedKeyword);
						$.uniform.update(selectEl);
					},
					error: function()
					{
						thisView.parent.showAjaxLoad = true;
						$('input.go').removeClass('disabled');
						$('#waiting').hide();
						$('#categorySpinner').hide();
						thisView.setControlsState(false);
						$('#innerContent').empty();
						var errorMsg = $('<span>');
						errorMsg.addClass('error');
						errorMsg.html("We're sorry, there is a problem with our backend server. Please try again later.");
						$('#innerContent').append(errorMsg);
						thisView.fixHeight();
					}
				});
			} else {
				thisView.parent.showAjaxLoad = true;
				$('input.go').removeClass('disabled');
				$('#brandSpinner').hide();
				thisView.setControlsState(false);
				$.uniform.update(selectEl);
			}
		},

		render: function() {
		},

		renderDefaultPage: function()
		{
			var thisView = this;
			$('#titleText').html('Supplier recommendations with potential savings');
			this.addInfoButton();
			var data = {
				selectedBranch: this.selectedBranch,
				selectedVessel: this.selectedVessel,
				selectedQuality: this.selectedQuality,
				selectedDate: this.selectedDate,
				selectedSameq: this.selectedSameq
			};

			var html = this.matchSupplierReportTemplate(data);
			$('#innerContent').html(html);
			$('#date').val(3);
			this.fixHeight();
			$('select').uniform();
			$('input[type="checkbox"]').uniform();

			this.getBranches();
			/*
			 * Requested to be hard coded with different variations instead
			 * If re revert back to this version, the next line can be uncommented
			this.getQualities();
			*/
			$('#quality').val(this.selectedQuality);
			
			$('.go').click(function(){
				if (!($('input.go').hasClass('disabled'))) {
					thisView.onShowClicked($(this));
				} 
			});

			if (this.renderLoadedList) {
				$('#date').val(this.selectedDate);
				this.renderLoadedList = false;
				this.parent.reRenderSupplierList();
			}
			
			$('#branch').change(function(){
				thisView.selectedBranch = $('#branch').val();
				thisView.getVessels($('#branch').val());
				thisView.getSegments();

			});
			
			$('#date').change(function(){
				thisView.selectedDate = $('#branch').val();
				thisView.getVessels($('#branch').val());
				thisView.getSegments();
			});
			
			$('#vessel').change(function(){
				thisView.selectedVessel = $('#vessel').val();
			});
			
			$('#quality').change(function(){
				thisView.selectedQuality = $('#quality').val();
			});
			
			$('#sameq').change(function(){
				thisView.selectedSameq = $('#sameq').is(':checked');
			});
			
			$('#category').change(function(){
				thisView.selectedSegment = $('#category').val();
				thisView.getKeywords($('#category').val());
			});
			
			$('#brand').change(function(){
				thisView.selectedKeyword = $('#brand').val();
			});
			
			$('select').change(function(){
				thisView.parent.supplierList.Pagination.currentPage = 1;
				thisView.fixAmp($(this));
			});
									
		},
		
		fixHeight: function()
        {
            var newHeight = ($('#content').height() > 422) ? $('#content').height()  +25 : 527;
            $('#body').height(newHeight);  
            $('#footer').show();

        },

        onShowClicked: function(e) 
        {
        	//e.preventDefault();
        	this.selectedBranch = $('#branch').val();
			this.selectedVessel = $('#vessel').val();
			this.selectedQuality = $('#quality').val();
			this.selectedSameq = $('#sameq').is(':checked');
			this.selectedDate = $('#date').val();
			this.selectedSegment = $('#category').val();
			this.selectedKeyword = $('#brand').val();
        	this.parent.getData();
        },
        
        onAlertLandingPage: function(byb)
        {
        	this.selectedBranch = byb;
			this.selectedVessel = 'All';
			this.selectedQuality = "";
			this.selectedSameq = false;
			this.selectedDate = 0;
			this.selectedSegment = "";
			this.selectedKeyword = "";
        },

		addInfoButton: function()
		{
			var thisView = this;
			var titleButton = $('<a>');
			titleButton.addClass('infoBtn');
			titleButton.html('How to use supplier recommendations');
			titleButton.click(function(){
				thisView.onInfoClick($(this));
			});
			
			$('#titleText').append(titleButton);
		},

		onInfoClick: function(e)
		{
			$('.newHelp').fadeIn(800);
		},

		setControlsState: function( state )
		{
			if (state) {
				$('#branch').attr('disabled', 'disabled');
				$('#vessel').attr('disabled', 'disabled');
				$('#date').attr('disabled', 'disabled');
				$('#category').attr('disabled', 'disabled');
				$('#brand').attr('disabled', 'disabled');
				$('#quality').attr('disabled', 'disabled');
				$('#sameq').attr('disabled', 'disabled');
				
				
			} else {
				$('#branch').removeAttr('disabled');
				$('#vessel').removeAttr('disabled');
				$('#date').removeAttr('disabled');
				$('#category').removeAttr('disabled');
				$('#brand').removeAttr('disabled');
				$('#quality').removeAttr('disabled');
				$('#sameq').removeAttr('disabled');
			}

			$.uniform.update($('#branch'));
			$.uniform.update($('#vessel'));
			$.uniform.update($('#date'));
			$.uniform.update($('#category'));
			$.uniform.update($('#brand'));
			$.uniform.update($('#quality'));
			$.uniform.update($('#sameq'));
		},
		
		fixAmp: function(element){
				var text = $(element).parent().find('span').html();
				text = text.replace(/&amp;/g, '&');
				$(element).parent().find('span').html(text);
		}

	});

	return new matchSupplierFilterView();
});
