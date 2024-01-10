define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'text!templates/buyer/rfq-outbox/tpl/filterItem.html'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	filterItemTpl
){
	var filterItemView = Backbone.View.extend({
		tagName: 'li',

		filterItemTemplate: Handlebars.compile(filterItemTpl),

		events: {
			'click' : 'onClicked'
		},

		render: function() {
			var data = this.model.attributes;
			var html = this.filterItemTemplate(data);

			if(this.model.attributes.selected){
				$(this.el).addClass('selected');
			}

			$(this.el).html(html);

			return this;
		},

		onClicked: function(e) {
			e.preventDefault();
			var data = this.model.attributes;
			
			_.each(this.parent.collection.models, function(item){
				item.attributes.selected = false;
			});

			data.selected = true;

			var mainView = this.parent.parent;

			mainView.qotType = data.type;

			this.parent.render();

			mainView.getData();
		}
	});

	return filterItemView;
});