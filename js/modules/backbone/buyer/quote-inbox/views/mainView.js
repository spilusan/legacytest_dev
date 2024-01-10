define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'../collections/quoteList',
	'../views/quoteRowView',
	'../views/filters',
	'backbone/shared/pagination/views/paginationView'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	quoteList,
	quoteRowView,
	filters,
	paginationView
){
	var quoteListView = Backbone.View.extend({
		
		el: $('table.quoteList'),

		events: {
			'click th.setorder' : 'onSetOrder'
		},

		page: 1,
		paginationLimit: 20,
		sort: "date",
		sortOrder: "desc",
		period: null,
		keywords: null,
		vessel: null,
		matchStat: null,
		qotStat: null,
		qotType: null,
		buyer: require('buyer/rfq-outbox/branchId'),
		setFilter: require('buyer/rfq-outbox/setFilter'),

		initialize: function(){
			this.collection = new quoteList();
			this.collection.url = "/buyer/quote/list/";

			$('body').ajaxStart(function(){
				$('#waiting').show();
			});

			$('body').ajaxStop(function(){
				$('#waiting').hide();
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
						status: this.qotStat,
						keywords: this.keywords,
						type: this.qotType,
						buyer: this.buyer
					}
				}),
				complete: function() {
					thisView.render();
				}
			});
		},

		render: function() {
			$(this.el).find('tbody').html('');
			
			if(this.collection.models.length == 0){
				$('h1.header .sum').text(0);
				$('table.quoteList tbody').html('<tr><td colspan="7" class="last leftBorder">No Quotes found.</td></tr>');
				paginationView.parent = this;
				paginationView.paginationLimit = this.paginationLimit;
				paginationView.page = this.page;
				paginationView.render(1);
			}
			else {
				$('span.sum').html(this.collection.models[0].attributes.page.quoteCount);
				_.each(this.collection.models, function(item) {
			        this.renderItem(item);
			    }, this);
			    //pass params to pagination
				paginationView.parent = this;
				paginationView.paginationLimit = this.paginationLimit;
				paginationView.page = this.page;

		    	//render pagination view
		    	paginationView.render(this.collection.models[0].attributes.page.quoteCount);
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

	    	var thisView = this;
	    	$('#sidebar ul#nav li a').bind('click', function(e){
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
		    var quoteListRow = new quoteRowView({
		        model: item
		    });
		    $(this.el).append(quoteListRow.render().el);
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
		}
	});

	return new quoteListView;
});