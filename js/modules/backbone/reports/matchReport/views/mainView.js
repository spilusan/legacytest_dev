define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'libs/jquery.uniform',
	'../collections/rfqList',
	'../views/filters',
	'../views/rfqRowView',
	'backbone/shared/pagination/views/paginationView',
	'text!templates/reports/matchReport/tpl/rfqListHeader.html'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	Uniform,
	rfqList,
	filtersView,
	rfqRowView,
	paginationView,
	listHeaderTpl
){
	var rfqListView = Backbone.View.extend({
		
		el: $('table.rfqList'),

		listHeaderTemplate: Handlebars.compile(listHeaderTpl),

		events: {
			
		},

		page: 1,
		paginationLimit: 20,
		dateFrom: '',
		dateTo: '',
		vessel: '',
		branch: null,

		initialize: function(){
			this.collection = new rfqList();
			var thisView = this;
			$('body').ajaxStart(function(){
				$('#waiting').show();
			});

			$('body').ajaxStop(function(){
				$('#waiting').hide();
			});

			this.filtersView = new filtersView();
			this.filtersView.parent = this;
			this.filtersView.getData();
		},

		getData: function(){

			var thisView = this;
			this.collection.fetch({
				data: $.param({
					buyerId: this.branch,
					vessel: this.vessel,
					purchaser: this.purchaser,
					startDate: this.dateFrom,
					endDate: this.dateTo,
					start: this.page,
					limit: this.paginationLimit
				}),
				complete: function() {
					thisView.render();
				}
			});
		},

		render: function() {
			var html = this.listHeaderTemplate();
			$(this.el).find('thead').html(html);
			$(this.el).find('tbody').html('');
			if(this.collection.models.length == 0){
				$('table.rfqList tbody').html('<tr><td colspan="11" class="leftBorder">No RFQs found.</td></tr>');
				paginationView.parent = this;
				paginationView.paginationLimit = this.paginationLimit;
				paginationView.page = this.page;
				paginationView.render(1);
			}
			else {
				_.each(this.collection.models, function(item) {
			        this.renderItem(item);
			    }, this);
			    //pass params to pagination
				paginationView.parent = this;
				paginationView.paginationLimit = this.paginationLimit;
				paginationView.page = this.page;

		    	//render pagination view
		    	paginationView.render(this.collection.models[0].attributes.TOTAL_ROWS);
	    	}

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

		renderItem: function(item) {
		    var rfqListRow = new rfqRowView({
		        model: item
		    });
		    rfqListRow.parent = this;
		    $(this.el).find('tbody').append(rfqListRow.render().el);
		}
	});

	return new rfqListView;
});