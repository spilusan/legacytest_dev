define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'../views/quoteDetailView',
	'../views/sendToDetailView',
	'../views/recommendedDetailView',
	'text!templates/buyer/quote-inbox/tpl/quoteView.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	quoteDetailView,
	sendToDetailView,
	recommendedDetailView,
	quoteDetailsTpl
){
	var quoteDetailsView = Backbone.View.extend({
		tagName: 'div',
		className: 'detailContainer',
		template: Handlebars.compile(quoteDetailsTpl),

		initialize: function(){
			_.bindAll(this, 'render');
			this.quoteDetail = new quoteDetailView;
			this.sendToDetail = new sendToDetailView;
			this.recommendedDetail = new recommendedDetailView;
		},

	    render: function(data) {
	    	var thisView = this;
			var html = this.template(data);

			$(this.el).html(html);
			$('#body').prepend(this.el);

			this.quoteDetail.getData(data.quote.id);
			this.sendToDetail.getData(data.quote.originalRfqId);

			this.recommendedDetail.parent = this;
			this.recommendedDetail.getData(data.quote.originalRfqId);

			$('input.toggle').unbind().bind('click', function(e){
				e.preventDefault();
				thisView.toggleDisplay(e.target);
			});

			$('input[name="back"]').unbind().bind('click', function(e){
				e.preventDefault();
				thisView.close();
			});
	    },

	    toggleDisplay: function(elem){
	    	if($(elem).parent('h2').next('.details').css('display') === "none"){
	    		$(elem).val('Hide section');
	    		$(elem).parent('h2').next('.details').show();
	    	}
	    	else {
				$(elem).val('Show section');
	    		$(elem).parent('h2').next('.details').hide();
	    	}
	    },

	    close: function(){
	    	$('.details').css('display', 'block');
	    	this.remove();
	    	$('#body').addClass('liquid');
	    	$('table.quoteList').show();
	    	$('div.pagination').show();
	    	$('div#sidebar').show();
	    	$('div#content').show();
	    	
	    	var height = 0;
	    	if($('#content').height() < $('#sidebar').height()){
	    		height = $('#sidebar').height();
	    	}
	    	else {
	    		height = $('#content').height() + 25;
	    	}

	    	$('#body').height(height);
	    }
	});

	return quoteDetailsView;
});