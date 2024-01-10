define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/shared/activity/log',
	'../collections/rfqSummary',
	'../views/rfqDetailView',
	'../views/matchDetailView',
	'../views/sendToDetailView',
	'../views/recommendedDetailView',
	'text!templates/buyer/rfq-outbox/tpl/rfqView.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	logActivity,
	rfqSummary,
	rfqDetailView,
	matchDetailView,
	sendToDetailView,
	recommendedDetailView,
	rfqDetailsTpl
){
	var rfqDetailsView = Backbone.View.extend({
		tagName: 'tr',
		className: 'detailSections',
		template: Handlebars.compile(rfqDetailsTpl),

		initialize: function(){
			_.bindAll(this, 'render');
			this.collection = new rfqSummary();
			this.rfqDetail = new rfqDetailView;
			this.matchDetail = new matchDetailView;
			this.sendToDetail = new sendToDetailView;
			this.recommendedDetail = new recommendedDetailView;
		},

		getSummary: function(id, hash){
			var thisView = this;
			this.rfqId = id;
			this.hash = hash;

			this.collection.fetch({
				data: $.param({
					rfqRefNo: this.rfqId
				}),
				complete: function() {
					thisView.render();
				}
			});
			return this;
		},

	    render: function() {
	    	var data = this.collection.models[0].attributes;
	    	
			var html = this.template(data);
			$(this.el).html(html);
			$($(this.el).find('.section h2 .toggle')).unbind().bind('click', {context: this}, function(e){
				e.preventDefault;
				e.data.context.toggleDetails(e.target);
			});

			this.recommendedDetail.parent = this;
			$(this.el).find('input[name="rfq"]').trigger('click');
			$(this.el).find('input[name="sendTo"]').trigger('click');
	    },

	    toggleDetails: function(el){
	    	var sectionView = "";

	    	if($(el).attr('name') === "rfq"){
	    		sectionView = this.rfqDetail; 
	    	}
	    	else if($(el).attr('name') === "match") {
	    		sectionView = this.matchDetail;
	    	}
	    	else if($(el).attr('name') === "sendTo"){
	    		sectionView = this.sendToDetail;
	    	}
	    	else {
	    		sectionView = this.recommendedDetail;
	    	}

	    	if($(el).val() === "Show"){
	    		if($(el).attr('name') === "match") {
	    			logActivity.logActivity('match-int-on-buy-tab-show', this.rfqId);
	    		}
	    		$(el).val("Hide");
	    		var elem = $(el).parents('.section');
				$(elem).append(sectionView.getData(this.rfqId, this.hash).el);
	    		sectionView.parent = this;
	    	}
	    	else {
	    		$(el).val("Show");
	    		sectionView.close();
	    		//fix height of body container due to absolute pos of content container
	    		var height = 0;
		    	if($('#content').height() < $('#sidebar').height()){
		    		height = $('#sidebar').height();
		    	}
		    	else {
		    		height = $('#content').height() + 25;
		    	}

		    	$('#body').height(height);
	    	}
	    },

	    close: function(){
	    	this.remove();
	    	this.recommendedDetail.collection.reset();
	    }
	});

	return rfqDetailsView;
});