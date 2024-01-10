define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'../views/matchItemView',
	'backbone/shared/itemselector/views/selectorView',
	'../collections/quoteDetail',
	'text!templates/buyer/quote-inbox/tpl/matchSection.html',
	'text!templates/buyer/quote-inbox/tpl/matchAddBtn.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	matchItemView,
	ItemSelector,
	matchDetail,
	matchDetailTpl,
	matchAddTpl
){
	var matchDetailView = Backbone.View.extend({
		tagName: 'div',
		className: 'details',

		events: {
			'click input[name="refresh"]' : 'onRefresh'
		},

		template: Handlebars.compile(matchDetailTpl),
		addTemplate: Handlebars.compile(matchAddTpl),

		initialize: function(){
			_.bindAll(this, 'render');
			this.catCollection = new matchDetail();
			this.prodCollection = new matchDetail();
			this.brandCollection = new matchDetail();
			this.countryCollection = new matchDetail();

			this.categorySelector = new ItemSelector('categories');
		},

		getData: function(id){
			/*this.fetchXHR = this.collection.fetch({
				data: $.param({ 

				}),
				complete: this.render();
			});*/

			this.catCollection.add([
				{
					id: 1,
					name: "Tools",
					level: "M"
				},
				{
					id: 2,
					name: "Drills",
					level: "H"
				}
			]);

			this.prodCollection.add([
				{
					id: 1,
					name: "Drill set in box",
					level: "H"
				}
			]);

			this.brandCollection.add([
				{
					id: 1,
					name: "DanishTools",
					level: "H"
				}
			]);

			this.countryCollection.add([
				{
					id: 1,
					name: "Philippines",
					level: "H"
				}
			]);

			this.render();

			return this;
		},

	    render: function() {
			var html = this.template();
			$(this.el).html(html);
			this.renderItems();
	    },

	    renderItems: function() {
			$(this.el).find('.matchSection.cat ul.cloud').html('');
			$(this.el).find('.matchSection.prod ul.cloud').html('');
			$(this.el).find('.matchSection.brand ul.cloud').html('');
			$(this.el).find('.matchSection.country ul.cloud').html('');

			_.each(this.catCollection.models, function(item) {
		        this.renderItem(item, 'cat');
		    }, this);
		    _.each(this.prodCollection.models, function(item) {
		        this.renderItem(item, 'prod');
		    }, this);
		    _.each(this.brandCollection.models, function(item) {
		        this.renderItem(item, 'brand');
		    }, this);
		    _.each(this.countryCollection.models, function(item) {
		        this.renderItem(item, 'country');
		    }, this);

		    $(this.el).find('.matchSection.cat ul.cloud').append(this.addTemplate('category'));
		    $(this.el).find('.matchSection.prod ul.cloud').append(this.addTemplate('keyword'));
		    $(this.el).find('.matchSection.brand ul.cloud').append(this.addTemplate('brand'));
		    $(this.el).find('.matchSection.country ul.cloud').append(this.addTemplate('country'));

		    var that = this;
		    $(this.el).delegate('li.add.category', 'click', function(){
		    	that.categorySelector.show();
		    });
		},

		renderItem: function(item, ele) {
			var matchItem = new matchItemView({
				model: item
			});

			matchItem.parent = this;

			var elem = ".matchSection."+ele+" ul.cloud";
			$(this.el).find(elem).append(matchItem.render().el);
		},

		onRefresh: function(e) {
			e.preventDefault;
			this.parent.recommendedDetail.getData(this.parent.collection.models[0].attributes.rfqId)
		},

	    close: function(){
	    	this.remove();
	    }
	});

	return matchDetailView;
});