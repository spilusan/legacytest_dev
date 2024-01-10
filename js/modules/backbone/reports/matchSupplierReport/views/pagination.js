define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'libs/jquery.uniform',
	'text!templates/reports/matchSupplierReport/tpl/pagination.html'

], function(
	$, 
	_, 
	Backbone, 
	Hb,
	Uniform,
	paginationTpl

){
	var matchSupplierPaginagteView = Backbone.View.extend({
		itemPerNavig: 5,
		callbackFunc: null,
		pageCount: 0,
		currentPage: 1,
		itemPerPage: 10,
		exportUrl: '',
		itemCount: 0,
		events: {

		},

		paginationTemplate: Handlebars.compile(paginationTpl),

		initialize: function(){

		},

		paginate: function(pagecount, currentpage, callback){

			var thisView = this;
			


			this.pageCount = pagecount;
			this.currentPage =currentpage;

			if (callback) {
				this.callbackFunc = callback;

			}
			$('#pagination').empty();
			
			var html = this.paginationTemplate({
				exporturl: thisView.exportUrl,
			});
			$('#pagination').html(html);

			$('#items-per-page').change(function(){
				thisView.itemPerPage = parseInt($(this).val());
				thisView.currentPage = 1;
				if (thisView.callbackFunc !== null ) 
				{
					thisView.callbackFunc(thisView.currentPage);
				}
			});
			$('#items-per-page').val(this.itemPerPage);
			this.render(pagecount, currentpage, callback, currentpage);

			if (pagecount <= 1) {
				$('#paginationNav').hide();
				$('select[name="items-per-page"]').hide();
				$('label[for="items-per-page"]').hide();

			} else {
				$('#paginationNav').show();
				$('select[name="items-per-page"]').show();
				$('label[for="items-per-page"]').show();
				$('select[name="items-per-page"]').uniform();
			}

			if (this.itemCount > 0) {
				$('.excelExpBtn').show();
			} else {
				$('.excelExpBtn').hide();
			}

		},

		render: function(pagecount, currentpage, callback, selectedPage) {
			var thisView = this;
			var arrowElement = null;
			var buttonElement = null;
			$('#paginationNav').empty();
			var parentElement = $('#paginationNav');
			var listFrom = Math.floor((selectedPage-1) / this.itemPerNavig) * this.itemPerNavig; 
			listFrom = (listFrom === 0) ? 1 : listFrom;

			var to = (pagecount - listFrom ) < this.itemPerNavig ? pagecount+1 : listFrom + this.itemPerNavig+1;

			buttonElement = $('<div>');
			buttonElement.addClass('pg-button');
			arrowElement  = $('<div>');
			if (listFrom == 1) {
				arrowElement.addClass('arrow-left-inactive');
				buttonElement.addClass('inactive');
			} else {
				arrowElement.addClass('arrow-left-active');
				buttonElement.data('page',listFrom -1);
				buttonElement.click(function(){
					thisView.navigButtonClick($(this));
				});
			}
			
			buttonElement.append(arrowElement);
			parentElement.append(buttonElement);


			for (var i=listFrom; i<to;i++) {
				buttonElement = $('<div>');
				buttonElement.data('page',i);
				buttonElement.html(i);
				buttonElement.addClass('pg-button');
				if (i == currentpage) {
					buttonElement.addClass('inactive');
				}
				buttonElement.click(function(){
					thisView.buttonClick($(this));
				});

				parentElement.append(buttonElement);
			}

			if (to < pagecount) {
				buttonElement = $('<div>');
				buttonElement.addClass('pg-button');
				buttonElement.addClass('white');
				buttonElement.html('...');
				parentElement.append(buttonElement);

				buttonElement = $('<div>');
				buttonElement.data('page',pagecount);
				buttonElement.addClass('pg-button');
				buttonElement.html(pagecount);
				parentElement.append(buttonElement);
				buttonElement.click(function(){
					thisView.buttonClick($(this));
				});
			}

			buttonElement = $('<div>');
			buttonElement.addClass('pg-button');
			arrowElement  = $('<div>');
			if (to < pagecount) {
				arrowElement.addClass('arrow-right-active');
				buttonElement.data('page',i);
				buttonElement.click(function(){
					thisView.navigButtonClick($(this));
				});
			} else {
				arrowElement.addClass('arrow-right-inactive');
				buttonElement.addClass('inactive');
			}
			
			buttonElement.append(arrowElement);
			parentElement.append(buttonElement);

		},

		buttonClick: function(e) 
		{
			if (this.callbackFunc !== null && !$(e).hasClass('inactive')) 
			{
				var selectedPage = $(e).data('page');
				this.callbackFunc(selectedPage);
			}
		},

		navigButtonClick: function(e) 
		{
			if (this.callbackFunc !== null && !$(e).hasClass('inactive')) 
			{
				var selectedPage = $(e).data('page');
				this.render(this.pageCount, this.currentPage, null, selectedPage);
			}
		}


	});

	return matchSupplierPaginagteView;
});