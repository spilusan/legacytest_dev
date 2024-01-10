define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'../views/poDetailView',
	'text!templates/buyer/po-outbox/tpl/poView.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	poDetailView,
	poDetailsTpl
){
	var poDetailsView = Backbone.View.extend({
		tagName: 'div',
		className: 'detailContainer',
		template: Handlebars.compile(poDetailsTpl),

		initialize: function(){
			_.bindAll(this, 'render');
			this.poDetail = new poDetailView;
		},

	    render: function(data) {
	    	var thisView = this;
			var html = this.template(data);

			$(this.el).html(html);
			$('#body').prepend(this.el);

			this.poDetail.getData(data.quote.id);

			$('input[name="back"]').unbind().bind('click', function(e){
				e.preventDefault();
				thisView.close();
			});
	    },
	    
	    close: function(){
	    	this.remove();
	    	$('table.poList').show();
	    	$('div.pagination').show();
	    	$('div#sidebar').show();
	    	$('div#content').show();
	    }
	});

	return poDetailsView;
});