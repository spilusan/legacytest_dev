define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'../../rfq-outbox/hbh/recommended',
	'text!templates/buyer/quote-inbox/tpl/recommendedRow.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	recHbh,
	recommendedRowTpl
){
	var recommendedRowView = Backbone.View.extend({
		tagName: 'div',
		className: 'item',
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

			$(this.el).find('input[name="sendRfq"]').bind('click', function(e){
				thisView.onSend();
			});
			return this;
	    },

	    onSend: function() {
	    	var thisView = this;
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
	    		thisView.parent.collection.reset();
	    		thisView.parent.getData();
	    		thisView.parent.parent.sendToDetail.getData();
	  		});
	    }
	});

	return recommendedRowView;
});