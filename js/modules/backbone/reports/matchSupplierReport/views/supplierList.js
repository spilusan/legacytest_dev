define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/shared/activity/log',
	'libs/jquery.uniform',
	'../views/pagination',
	'../collections/collection',
	'text!templates/reports/matchSupplierReport/tpl/matchSupplierList.html'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	logActivity,
	Uniform,
	PaginationView,
	Collection,
	MatchSupplierListTpl,
	removeSupplierPopupTpl
){
	var matchSupplierSupplierView = Backbone.View.extend({
		
		matchSupplierListTemplate: Handlebars.compile(MatchSupplierListTpl),
		events: {
			//'click .viewQote': 'onViewQuote',
			//'click .blacklist': 'blacklistClick',
		},

		initialize: function(){
			
			thisView = this;

			this.Collection = new Collection();
			this.Pagination = new PaginationView();
			this.Collection.url = '/reports/data/match-supplier-report';
			this.Pagination.parent = this;
			
			$('body').delegate('.blacklist', 'click', function(e){
				e.preventDefault();
				thisView.blacklistClick(e);
			});

			/* IE11 Workaround */
			$('body').delegate('input[name="blacklistAction"]', 'click', function(e){
				$('textarea[name="blacklistReasonText"]').focus();
			});
		


		},

		getData: function(){

			var thisView = this;
		
			$('.savingHelp').hide();
			$('#waiting').show();
			var purgeCache = (thisView.parent.purgeCache) ? 'purge' : false;
			thisView.parent.purgeCache = false;

			/* Set pagination Export URL */
			this.Pagination.exportUrl = '/reports/data/match-supplier-report-export?type=supplier&bybBranchCode='+$('#branch').val()+'&vessel='+encodeURIComponent($('#vessel').val())+'&date='+$('#date').val() + '&segment=' +  $('#category').val() + '&keyword=' + $('#brand').val() + '&quality=' + $('#quality').val()+ '&sameq=' + $('#sameq').is(':checked');
			this.Collection.reset();
			this.Collection.fetch({
				data: {
					type: 'supplier',
					bybBranchCode: $('#branch').val(),
					vessel:  $('#vessel').val(),
					date: $('#date').val(),
					segment: $('#category').val(),
					keyword: $('#brand').val(),
					quality: $('#quality').val(),
					sameq: $('#sameq').is(':checked'),
					page: this.Pagination.currentPage,
					itemPerPage: this.Pagination.itemPerPage,
					purgeCache: purgeCache
				},
				complete: function(){
					thisView.renderList();
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
			
		},

		render: function() {

		},

		renderList: function()
		{
			if (this.Collection.models.length > 0) {
				var thisView = this;
				
				var data = this.Collection.models[0].attributes;
				var html = this.matchSupplierListTemplate(data);
				$('#result').html(html);
	
				this.Pagination.itemCount = data.data.length;
				this.Pagination.paginate(data.count,this.Pagination.currentPage, this.onPaginate);
				$('.viewQote').click(function(){
					if (!($('input.go').hasClass('disabled'))) {
						thisView.onViewQuote($(this));
					}
				});
	
				this.fixHeight();
			} 
		},

		onPaginate: function( page )
		{
			this.paginate(this.pageCount,page, null);
			this.parent.getData();
		},
		

		fixHeight: function()
        {
            var newHeight = ($('#content').height() > 422) ? $('#content').height()  +25 : 527;
            $('#body').height(newHeight);  
            $('#footer').show();

        },

        onViewQuote: function(e)
        {
        	//e.preventDefalult();
        	var rootElement = ($(e).hasClass('root')) ? $(e).parent() : $(e).parent().parent();
        	var saving = $(rootElement).data('saving');
        	var spbBranchCode = parseInt($(rootElement).data('id'));
			logActivity.logActivity('spend-benchmark-view-quotes', spbBranchCode);
        	this.parent.renderQuote(spbBranchCode, saving);
        },

        blacklistClick: function(e) {
        	this.parent.supplierToBlacklist = $(e.target).data('id');
        	/* Set initial state of radio buttons, and edit box*/
        	$("#blacklistOtion2").attr('checked', false);
        	$("#blacklistOtion1").attr('checked', 'checked');
        	$('textarea[name="blacklistReasonText"]').html('');
        	$.uniform.update('input[name="blacklistAction"]');

        	$('.removeSupplier').fadeIn(400);
        },

        removeItemFromCollection:function( branchCode )
        {
        	var data = this.Collection.models[0].attributes.data;
        	for (var key in data) {
        		if (data[key].spbBranchCode == branchCode) {
        			 data[key].spbBranchCode = 'deleted'; 
        		}
        	}
         }

	});

	return new matchSupplierSupplierView();
});