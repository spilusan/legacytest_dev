define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/shared/hbh/general',
	'backbone/shared/hbh/inbox',
	'../collections/quoteDetail',
	'text!templates/buyer/quote-inbox/tpl/sendToSection.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	genHbh,
	inboxHbh,
	sendToDetail,
	sendToDetailTpl
){
	var sendToDetailView = Backbone.View.extend({
		tagName: 'div',
		className: 'details',
		template: Handlebars.compile(sendToDetailTpl),

		initialize: function(){
			_.bindAll(this, 'render');
			this.collection = new sendToDetail();
			this.collection.url = '/buyer/search/rfq-suppliers/';
		},

		getData: function(id){
			if(id)
				this.rfqRefNo = id;

			var thisView = this;
			this.collection.fetch({
				data: $.param({
					rfqRefNo: this.rfqRefNo
				}),
				complete: function() {
					thisView.render();
				}
			});
		},

	    render: function() {
	    	var data = this.collection.models;
	    	_.each(data, function(item){
	    		if(item.attributes.rfq_status === "submitted") {
					item.attributes.stat = "Yet to quote";
				}
				else if(item.attributes.rfq_status == "declined") {
					item.attributes.stat = "Declined";
				}
				else if(item.attributes.rfq_status == "quoted") {
					item.attributes.stat = "Quoted"
				} else if(item.attributes.rfq_status == "ordered") {
					item.attributes.stat = "Ordered";
				}
				else {
					item.attributes.stat = "";
				}
	    	}, this);

			var html = this.template(data);
			$(this.el).html(html);
			$('.section.sendTo').append(this.el);
	    }
	});

	return sendToDetailView;
});