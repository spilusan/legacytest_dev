define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/shared/activity/log',
	'../hbh/recommended',
	'text!templates/buyer/rfq-outbox/tpl/recommendedRow.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	logActivity,
	recHbh,
	recommendedRowTpl
){
	var recommendedRowView = Backbone.View.extend({
		tagName: 'tr',
		template: Handlebars.compile(recommendedRowTpl),

		initialize: function(){
			_.bindAll(this, 'render');
			this.model.view = this;
		},

	    render: function() {
			var data = this.model.attributes;
			if(!data.match_rank || data.match_rank === "" || data.match_rank === " "){
				data.match_rank = "-";
			}
			var html = this.template(data),
				thisView = this;

			$(this.el).html(html);

			if(this.model.attributes.wasSent){
				$(this.el).addClass('sent');
			}

			$(this.el).find('input[name="sendRfq"]').bind('click', function(e){
				thisView.onSend();
			});
			return this;
	    },

	    onSend: function() {
	    	var thisView = this;
	    	logActivity.logActivity('rfq-sent-from-buy-tab', this.model.attributes.rfq_id);
	   		var jqxhr = $.ajax({
   				url: "/buyer/search/rfq-send/",
   				data: {
   					supplierTnid: this.model.attributes.tnid,
   					rfqRefNo: this.model.attributes.rfq_id
   				}
   			})
	  		.fail(function() {
	    		alert( "error" );
	  		})
	  		.always(function(data) {
	    		thisView.parent.pageNo = 1;
	    		oldPage = $('.section.recommended .details table tbody tr').length/5;
	    		if($('.section.recommended .details table tbody tr').length > 5){
	    			thisView.parent.pageSize = $('.section.recommended .details table tbody tr').length;
	    		}
	    		thisView.parent.collection.reset();
	    		thisView.parent.getData();
	    		thisView.parent.pageSize = 5;
	    		thisView.parent.pageNo = oldPage;
	    		thisView.parent.parent.sendToDetail.getData();
	  		});
	    }
	});

	return recommendedRowView;
});