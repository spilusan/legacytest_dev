define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'../collections/quoteDetail',
	'../views/recommendedRowView'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	recommendedDetail,
	recommendedRow
){
	var recommendedDetailView = Backbone.View.extend({
		tagName: 'div',
		className: 'details',
		pageSize: 5,
		pageNo: 1,
		custom: false,
		terms: null,

		events: {
			//'click input[name="more"]'		: 'getMore',
			//'click input[name="sendToAll"]' : 'sendToAll'
		},

		initialize: function(){
			_.bindAll(this, 'render');
			this.collection = new recommendedDetail();
			this.collection.url = '/buyer/search/results/';
		},

		getData: function(id){
			if(id)
				this.rfqRefNo = id;

			terms = "";

			this.fetchXHR = this.collection.fetch({
				add: true,
				data: $.param({ 
					rfqRefNo: this.rfqRefNo,
					pageSize: this.pageSize,
					pageNo: this.pageNo,
					terms: terms
				}),
				complete: this.render
			});
			
			return this;
		},

	    render: function() {
	    	this.elem = '.section.recommended';
	    	$('.section.recommended .details').html('');
	    	var data = new Object();
	    	data.id = this.rfqRefNo;

			this.renderItems();

			if(this.collection.length === 0){			
				$(this.elem).append('No recommended Suppliers found.');
				return;
			}

			$(this.elem).append(this.el);

	    	this.delegateEvents();
	    },

	    renderItems: function() {
	    	_.each(this.collection.models, function(item) {
		        this.renderItem(item);
		    }, this);
	    },

		renderItem: function(item) {
		    var recommendedListRow = new recommendedRow({
		        model: item
		    });

		    recommendedListRow.parent = this;

		    $(this.el).append(recommendedListRow.render().el);
		}
	});

	return recommendedDetailView;
});