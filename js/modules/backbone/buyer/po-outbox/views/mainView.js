define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'../collections/poList',
	'../views/poRowView',
	'../views/filters',
	'backbone/shared/pagination/views/paginationView'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	poList,
	poRowView,
	filters,
	paginationView
){
	var poListView = Backbone.View.extend({
		
		el: $('table.poList'),

		events: {
			'click th.setorder' : 'onSetOrder'
		},

		page: 1,
		paginationLimit: 20,
		sort: "date",
		sortOrder: "desc",
		period: "365",
		keywords: null,
		vessel: null,
		matchStat: null,
		qotStat: null,
		qotType: null,
		buyer: null,

		initialize: function(){
			this.collection = new poList();
			this.collection.url = "/buyer/quote/list/";

			$('body').ajaxStart(function(){
				$('#waiting').show();
			});

			$('body').ajaxStop(function(){
				$('#waiting').hide();
			});

			this.getData();

			this.filtersView = new filters();
			this.filtersView.parent = this;
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
				$('table.poList tbody').html('<tr><td colspan="7" class="last leftBorder">No Quotes found.</td></tr>');
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
		    	paginationView.render(this.collection.models[0].attributes.page.total);
	    	}
		},

		renderItem: function(item) {
		    var poListRow = new poRowView({
		        model: item
		    });
		    $(this.el).append(poListRow.render().el);
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

	return new poListView;
});