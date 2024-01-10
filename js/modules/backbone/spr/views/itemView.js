define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'text!templates/spr/tpl/itemView.html'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	itemViewTpl
){
	var itemView = Backbone.View.extend({
		
		tagName: 'li',

		itemViewTemplate: Handlebars.compile(itemTpl),


		initialize: function () {
			_.bindAll(this, 'render');
			this.model.view = this;
		},

		render: function() {
			var data = this.model.attributes;
			var html = this.itemViewTemplate(data);

			$(this.el).html(html);

			$(this.el).unbind().bind('click', {context: this}, function(e){
				e.preventDefault();
				that = e.data.context;
				that.foo();
			});

			return this;
		},

		foo: function(){
			//what to do if item is clicked
		}
	});

	return itemView;
});
