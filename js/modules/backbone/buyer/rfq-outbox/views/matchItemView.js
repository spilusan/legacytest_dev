define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'text!templates/buyer/rfq-outbox/tpl/matchItem.html',
	'text!templates/shared/tpl/levelDropDown.html'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	matchItemTpl,
	levelDropDownTpl
){
	var matchItemView = Backbone.View.extend({
		tagName: 'li',

		matchItemTemplate: Handlebars.compile(matchItemTpl),
		levelDropDownTemplate: Handlebars.compile(levelDropDownTpl),

		events: {
			'click input.remove' 			: 'onRemove',
			'click .levelChooser' 			: 'onLevelChooser'
		},

		render: function() {
			this.$el.addClass(this.model.attributes.level);
			var data = this.model.attributes;
			var html = this.matchItemTemplate(data);

			$(this.el).html(html);

			return this;
		},

		onLevelChooser: function() {
			$(this.el).append(this.levelDropDownTemplate());
			var that = this;
			$('.levelBox').bind('click', function(e){
				e.preventDefault();
				that.onLevelSelect(e);
			});
			$(document).mouseup(function(e)
			{
			    var container = $('.levelDropDown');

			    if (!container.is(e.target) && container.has(e.target).length === 0)
			    {
			        container.remove();
			    }
			});
		},

		onLevelSelect: function(e) {
			if($(e.target).is('div.levelBox')){
				var ele = $(e.target);
			}
			else {
				var ele = $(e.target).parent();
			}

			var level = ele.find('span.level').text(),
				html = level + ' <span class="arrow"></span>';

			ele.parent().parent().removeClass('M')
						   		 .removeClass('H')
						    	 .removeClass('L')
								 .addClass(level);
			ele.parent().parent().find('div.levelChooser').removeClass('M')
												    .removeClass('H')
												    .removeClass('L')
												    .addClass(level)
												    .html(html);
			this.model.set({level : level});
			$('.levelDropDown').hide();
		},

		onRemove: function(e){
			e.preventDefault();
			this.model.collection.remove(this.model);
			this.parent.renderItems();
		}
	});

	return matchItemView;
});