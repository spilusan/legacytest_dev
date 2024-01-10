define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/buyer/rfq-outbox/hbh/rfq',
	'text!templates/reports/matchReport/tpl/detailRow.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	HbhRow,
	detailsRowTpl
){
	var detailsRowView = Backbone.View.extend({
		tagName: 'tr',
		className: 'details',
		template: Handlebars.compile(detailsRowTpl),

		initialize: function(){
			_.bindAll(this, 'render');
			this.model.view = this;
		},

	    render: function() {
			var data = this.model.attributes;
			if(data.URL_QOT && data.URL_ORD){
				$('.rfqList thead tr').addClass('wide');
			}

			if(data.RFQ_DESTINATION == 'MATCH_SELECTED'){
				data.type = 'SpotSourced/AutoSourced';
				data.matchedStyle = true;
			}
			else if(data.RFQ_DESTINATION == 'BUYER_SELECTED'){
				data.type = 'Buyer selected';
			}
			
			var html = this.template(data);
			$(this.el).html(html);

			return this;
	    }
	});

	return detailsRowView;
});