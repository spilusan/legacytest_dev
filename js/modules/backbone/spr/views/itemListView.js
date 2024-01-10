define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'libs/jquery.tools.min',
	'libs/jquery.tools.overlay.modified',
	'Hbh/trade/mainViewHbh',
	'../collections/itemList',
	'../views/itemView',
	'text!templates/spr/tpl/itemList.html'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	Tools,
	Modal,
	mainViewHbh,
	itemListCol,
	itemView,
	itemListTpl
){
	var itemListView = Backbone.View.extend({
		
		el: $('body ul'),

		events: {
			'click a' : 'render'
		},

		itemListTemplate: Handlebars.compile(itemListTpl),


		initialize: function () {
			
			this.collection = new itemList();

			this.fetchXHR = this.collection.fetch({
				data: $.param({ 

				}),
				complete: function() {
					
				}
			});
		},

		render: function() {
			$(this.el).html();

			_.each(this.collection.models, function(item) {
		        this.renderItem(item);
		    }, this);
		},

		renderItem: function(item) {
			var ItemView = new itemView({
		        model: item
		    });

		    $(this.el).append(ItemView.render().el);
		}
	});

	return new itemListView();
});
