define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'../collections/rfqList',
	'../views/rfqRowView',
	'../views/filters',
	'backbone/shared/pagination/views/paginationView'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	rfqList,
	rfqRowView,
	filters,
	paginationView
){
	var rfqListView = Backbone.View.extend({
		
		el: $('table.rfqList'),

		events: {
			'click th.setorder' : 'onSetOrder'
		},

		page: 1,
		paginationLimit: 20,
		sort: "date_sent",
		sortOrder: "desc",
		period: null,
		keywords: null,
		vessel: null,
		matchStat: null,
		rfqStat: null,
		category: null,
		buyer: require('buyer/rfq-outbox/branchId'),
		smallSpinner: 0,
		setFilter: require('buyer/rfq-outbox/setFilter'),

		initialize: function(){
			this.collection = new rfqList();
			this.collection.url = "/buyer/search/rfq-list/";
 			var thisView = this;
			$('body').ajaxStart(function(){
				if(thisView.smallSpinner === 0){
					$('#waiting').show();
				}
				else {
					$('.smallSpin').show();
					$('input[name="show"]').hide();
				}
			});

			$('body').ajaxStop(function(){
				$('#waiting').hide();
				$('.smallSpin').hide();
				thisView.smallSpinner = 0;
				$('input[name="show"]').show();
			});

			this.filtersView = new filters();
			this.filtersView.parent = this;
			this.filtersView.buyer = this.buyer;
			this.filtersView.getData();
		},

		getData: function() {
			var thisView = this;
			this.collection.fetch({
				data: $.param({
					orderBy: this.sort,
					orderDir: this.sortOrder,
					pageNo: this.page,
					pageSize: this.paginationLimit,
					filters: {
						days: this.period,
						vessel: this.vessel,
						status: this.rfqStat,
						keywords: this.keywords,
						buyer: this.buyer,
                        matchStat: this.matchStat
					}
				}),
				complete: function() {
					thisView.render();
				}
			});
		},

		render: function() {
			var thisView = this;
			$(this.el).find('tbody').html('');
			if(this.collection.models.length === 0){
				$('h1.header .sum').text(0);
				$('table.rfqList tbody').html('<tr><td colspan="9" class="leftBorder">No RFQs found.</td></tr>');
				paginationView.parent = this;
				paginationView.paginationLimit = this.paginationLimit;
				paginationView.page = this.page;
				paginationView.render(1);
			}
			else {
				$('h1.header .sum').text(this.collection.models[0].attributes.total);
				_.each(this.collection.models, function(item) {
			        this.renderItem(item);
			    }, this);
			    //pass params to pagination
				paginationView.parent = this;
				paginationView.paginationLimit = this.paginationLimit;
				paginationView.page = this.page;

		    	//render pagination view
		    	paginationView.render(this.collection.models[0].attributes.total);
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
	    		thisView.fixWidht();
	    	});

	    	/* var thisView = this; */
	    	$('#sidebar ul#nav li a').unbind().bind('click', function(e){
	    		e.preventDefault();
	    		var href = $(e.target).attr('href');
	    		if(thisView.buyer){
	    			href += "?branchId=" + thisView.buyer;
	    		}

	    		if(thisView.setFilter){
	    			href += "&setFilter=true";
	    		}

	    		window.location.href = href;
	    	});
		},

		renderItem: function(item) {
		    var rfqListRow = new rfqRowView({
		        model: item
		    });
		    rfqListRow.parent = this;
		    $(this.el).append(rfqListRow.render().el);
		},

		onSetOrder: function(e) {
			e.preventDefault();
			var el;

			if(!$(e.target).hasClass('setorder')){
				el = $(e.target).parent();
			}
			else {
				el = $(e.target);
			}

			this.sort = $(el).attr('title');

			if($(el).hasClass('ordered')) {
				if($('.ordered').hasClass('desc')){
					$('.ordered').removeClass('desc');
					$('.ordered').addClass('asc');
					this.sortOrder = "asc";
				}
				else {
					$('.ordered').removeClass('asc');
					$('.ordered').addClass('desc');
					this.sortOrder = "desc";
				}
			}
			else {
				$('th.setorder').removeClass('ordered');
				$('th.setorder').removeClass('asc');
				$('th.setorder').removeClass('desc');

				$(el).addClass('ordered');
				$(el).addClass('desc');
				this.sortOrder = "desc";
			}

			this.getData();
		},

		fixWidht: function(){
			/* TODO add sub table width calculation */
 		 	var newW = $(window).width()-900;
    		$('.section.match').width(newW);
    		$('.section.sendTo').width(newW);
    		$('.section.recommended').width(newW);
		}
	});

	return new rfqListView();
});