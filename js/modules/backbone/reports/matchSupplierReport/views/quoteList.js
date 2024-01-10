define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'libs/jquery.uniform',
	'../views/pagination',
	'../collections/collection',
	'text!templates/reports/matchSupplierReport/tpl/quoteList.html'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	Uniform,
	PaginationView,
	Collection,
	quoteTpl

){
	var matchSupplierQoteListView = Backbone.View.extend({
		
		events: {
			/* 'click tr': 'rfqClick', */
		},

		lastQuoteData: null,
		selectedSpbBranchCode: null,

		quoteTemplate: Handlebars.compile(quoteTpl),
		initialize: function(){
			var thisView = this;
			this.Collection = new Collection();
			this.Collection.url = '/reports/data/match-supplier-report';
			this.Pagination = new PaginationView();
			this.Pagination.parent = this;

		},

		resetPagination: function()
		{
			this.Pagination.currentPage = 1;
		},

		getData: function(spbBranchCode, quoteData){

			var thisView = this;
			this.lastQuoteData = quoteData;
			this.selectedSpbBranchCode = spbBranchCode;
			
			this.Pagination.exportUrl = '/reports/data/match-supplier-report-export?type=quote&bybBranchCode='+this.parent.parent.getFilters().selectedBranch+'&spbBranchCode='+spbBranchCode+'&vessel='+encodeURIComponent(this.parent.parent.getFilters().selectedVessel)+'&date='+this.parent.parent.getFilters().selectedDate+'&segment='+this.parent.parent.getFilters().selectedSegment+'&keyword='+this.parent.parent.getFilters().selectedKeyword+'&quality='+this.parent.parent.getFilters().selectedQuality+'&sameq='+this.parent.parent.getFilters().selectedSameq;
			this.Collection.reset();
			this.Collection.fetch({
				data: {
					type: 'quote',
					bybBranchCode: this.parent.parent.getFilters().selectedBranch,
					spbBranchCode: spbBranchCode,
					vessel:  this.parent.parent.getFilters().selectedVessel,
					segment: this.parent.parent.getFilters().selectedSegment,
					keyword: this.parent.parent.getFilters().selectedKeyword,
					date: this.parent.parent.getFilters().selectedDate,
					quality:  this.parent.parent.getFilters().selectedQuality,
					sameq:  this.parent.parent.getFilters().selectedSameq,
					page: this.Pagination.currentPage,
					itemPerPage: this.Pagination.itemPerPage,
				},
				complete: function(){
					thisView.render();
				},
				error: function()
				{
					$('#waiting').hide();
					$('#qote-data').empty();
					var errorMsg = $('<span>');
					errorMsg.addClass('error');
					errorMsg.html("We're sorry, there is a problem with our backend server. Please try again later.");
					$('#qote-data').append(errorMsg);
					thisView.fixHeight();
				}
			});
		},

		render: function() {
			var thisView = this;
			if (this.Collection.models[0]) {
				var data = this.Collection.models[0].attributes;
				data.supplierName = this.lastQuoteData.supplier.name;
				var html = this.quoteTemplate(data);
				$('#qote-data').html(html);
				this.Pagination.itemCount = data.data.length;
				this.Pagination.paginate(data.count,this.Pagination.currentPage, this.onPaginate);
				$('#quote-table tr').click(function(e){
					if (!$(e.target).hasClass('sHelp')) {
						if (!$(e.target).hasClass('comparableIcon')) {
							if (!$(e.target).hasClass('fixComparableIcon')) {
								thisView.parent.drillDown($(this)); 
							}
						}
					}
				}); 
			}
			this.fixHeight();
		},

		onPaginate: function( page )
		{
			this.paginate(this.pageCount,page, null);
			this.parent.getData(this.parent.selectedSpbBranchCode, this.parent.lastQuoteData);
		},


		fixHeight: function()
        {
            var newHeight = ($('#content').height() > 422) ? $('#content').height()  +25 : 527;
            $('#body').height(newHeight);  
            $('#footer').show();

        }

	});
	
	return new matchSupplierQoteListView();
});