define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/buyer/rfq-outbox/hbh/rfq',
	'../views/rfqDetailsView',
	'text!templates/reports/matchReport/tpl/rfqRow.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	HbhRow,
	rfqDetailsView,
	rfqRowTpl
){
	var rfqRowView = Backbone.View.extend({
		tagName: 'tr',
		template: Handlebars.compile(rfqRowTpl),

		initialize: function(){
			_.bindAll(this, 'render');
			this.model.view = this;
		},

	    render: function() {
			var data = this.model.attributes;
			
			if(data.ORD_STATUS == 'ord_by_match'){
				data.poAward = 'Sourcing Engine';
				data.matchedStyle = true;
			}
			else if(data.ORD_STATUS == 'ord_by_buyer'){
				data.poAward = 'Buyer supplier';
			}
			
			var html = this.template(data);
			$(this.el).html(html);

			var thisView = this;
			$(this.el).find('.showDet').unbind().bind('click', function(e){
				e.preventDefault();
				thisView.showDetails();
			});
			return this;
	    },

	    showDetails: function(){
	    	this.rfqDetailsView = new rfqDetailsView();
	    	this.rfqDetailsView.parent = this;
	    	this.rfqDetailsView.branch = this.parent.branch;
	    	this.rfqDetailsView.rfqId = this.model.attributes.RFQ_INTERNAL_REF_NO;
	    	this.rfqDetailsView.getData();
	    	$('.filterSection').hide();
	    	$('.pagination').hide();
	    	$('.summarySection').show();

	    }
	});

	return rfqRowView;
});