define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'../hbh/rfq',
	'backbone/shared/activity/log',
	'../views/rfqDetailsView',
	'text!templates/buyer/rfq-outbox/tpl/rfqRow.html',
	'text!templates/buyer/rfq-outbox/tpl/rfqListHeader.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	HbhRow,
	logActivity,
	rfqDetailsView,
	rfqRowTpl,
	listHeaderTpl
){
	var rfqRowView = Backbone.View.extend({
		tagName: 'tr',
		template: Handlebars.compile(rfqRowTpl),
		listHeaderTemplate: Handlebars.compile(listHeaderTpl),

		initialize: function(){
			_.bindAll(this, 'render');
			this.model.view = this;
			this.detailsView = new rfqDetailsView;
			this.detailsView.parent = this;
		},

	    render: function() {
			var data = this.model.attributes;

			if(data.status === "OPN") {
				data.stat = "open";
			}
			else if(data.status === "NEW") {
				data.stat = "open";
				data.unread = true; 
			}
			else if(data.status === "C") {
				data.stat = "closed"
			} else if(data.status === "DFT") {
				data.stat = "draft";
			}
			else if(data.status === "SUB"){
				data.stat = "open";
			}
			else {
				data.stat = "";
			}
			
			var html = this.template(data);
			$(this.el).html(html);

			$(this.el).find('input[name="expand"]').unbind().bind('click', {context: this}, function(e){
				e.preventDefault();
				var that = e.data.context;
				that.toggleDetails();
				
			});

			$(this.el).find('.showRfq').unbind().bind('click', {context: this}, function(e){
				e.preventDefault();
				var that = e.data.context;
				that.toggleDetails();
			});
			return this;
	    },

	    toggleDetails: function(){
	    	var rowCount = $('.rfqList tbody tr').length;
	    	var index = $('.rfqList tbody tr').index(this.el)+1;
	    	if(!$(this.el).hasClass('open')){
				logActivity.logActivity('rfq-on-buy-tab-expand', this.model.attributes.rfq_id);
	    		$(this.el).addClass('open');
	    		$(this.el).after(this.detailsView.getSummary(this.model.attributes.rfq_id, this.model.attributes.hash).el);
	    		var headerHtml = this.listHeaderTemplate();
	    		if(index < rowCount) {
	    			$(this.el).next().after(headerHtml);
	    		}
	    	}
	    	else {
	    		$(this.el).removeClass('open');
	    		if(index < rowCount) {
	    			$(this.el).next().next().remove();
	    		}
	    		this.detailsView.recommendedDetail.pageNo = 1;
	    		this.detailsView.close();
	    		var height = 0;
		    	if($('#content').height() < $('#sidebar').height()){
		    		height = $('#sidebar').height();
		    	}
		    	else {
		    		height = $('#content').height() + 25;
		    	}

		    	$('#body').height(height);
	    	}

	    }
	});

	return rfqRowView;
});