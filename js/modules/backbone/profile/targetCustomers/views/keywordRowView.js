define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/shared/hbh/general',
	'text!templates/profile/targetCustomers/tpl/keywordRow.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	HbhGen,
	keywordRowTpl
){
	var settingsKeywordView = Backbone.View.extend({
		tagName: 'div',

		template: Handlebars.compile(keywordRowTpl),
		
		initialize: function(){
			_.bindAll(this, 'render');
			this.model.view = this;
		},

	    render: function() {
	    		var thisView = this;
			var data = this.model.attributes;
			/*
			if (data.highlight === 1) {
				$(this.el).addClass('highlighed');
			}
			*/
			var html = this.template(data);

			$(this.el).html(html);
			
			$(this.el).find('i').click(function(e){
				thisView.parent.keywordStatuses = null;
				if ($(e.currentTarget).hasClass('fa-square-o')) {
					$(e.currentTarget).removeClass('fa-square-o');
					$(e.currentTarget).addClass('fa-check-square-o');
				} else {
					$(e.currentTarget).removeClass('fa-check-square-o');
					$(e.currentTarget).addClass('fa-square-o');
				}
			});

	        return this;
	    }
	});

	return settingsKeywordView;
});