define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'../views/quoteDetailsView',
	'text!templates/buyer/quote-inbox/tpl/quoteRow.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	quoteDetailsView,
	quoteRowTpl
){
	var quoteRowView = Backbone.View.extend({
		tagName: 'tr',
		template: Handlebars.compile(quoteRowTpl),

		initialize: function(){
			_.bindAll(this, 'render');
			this.model.view = this;
			this.detailsView = new quoteDetailsView;
		},

	    render: function() {
			var data = this.model.attributes;

			if(data.quote.status === "ACC") {
				data.stat = "accepted";
			}
			else if(data.quote.status === "DEC") {
				data.stat = "declined";
			}
			else if(data.quote.status === "SUB") {
				data.stat = "open"
			}
			else {
				data.stat = "";
			}

			var html = this.template(data);

			$(this.el).html(html);

			$(this.el).removeClass('unread');

			if(data.read == null) {
				$(this.el).addClass('unread');
			}

			$(this.el).find('input[name="show"]').unbind().bind('click', {context: this}, function(e){
				e.preventDefault();
				var that = e.data.context;
				that.showDetails();
			});

			$(this.el).find('a').unbind().bind('click', {context: this}, function(e){
				e.preventDefault();
				var that = e.data.context;
				that.showDetails();
			});

			return this;
	    },

	     showDetails: function(){
	     	$('#body').removeClass('liquid');
	     	$('#body').height('auto');
	    	$('table.quoteList').hide();
	    	$('div.pagination').hide();
	    	$('div#sidebar').hide();
	    	$('div#content').hide();
	    	this.detailsView.render(this.model.attributes);
	    }
	});

	return quoteRowView;
});