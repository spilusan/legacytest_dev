define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/shared/hbh/general',
	'text!templates/buyer/rfq-outbox/tpl/sendToRow.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	generalHbh,
	sendToRowTpl
){
	var sendToRowView = Backbone.View.extend({
		tagName: 'tr',
		template: Handlebars.compile(sendToRowTpl),

		events: {
			'click a.excludeStat' : 'excludeClicked',
			'click a.sendRemind'  : 'remindClicked'
		},

		initialize: function(){
			_.bindAll(this, 'render');
			this.model.view = this;
		},

		render: function() {
			var data = this.model.attributes;
			if(!data.rank || data.rank === "" || data.rank === " "){
				data.rank = "-";
			}

			if(data.rfq_status === "submitted") {
				data.stat = "Yet to quote";
			}
			else if(data.rfq_status == "declined") {
				data.stat = "Declined";
			}
			else if(data.rfq_status == "quoted") {
				data.stat = "Quoted"
			} else if(data.rfq_status == "ordered") {
				data.stat = "Ordered";
			}
			else {
				data.stat = "";
			}

			if (!data.from_match) {
				data.matched = "Buyer selected";
			}
			else {
				data.matched = "SpotSourced/AutoSourced";
			}
			if(data.quote_cheapest){
				$(this.el).addClass('cheapest');
			}
			html = this.template(data);
			$(this.el).html(html);
			return this;
	    },

	    remindClicked: function(e){
	    	e.preventDefault();
	    	var thisView = this;
	    	$.ajax({
				type: "POST",
				url: "/buyer/match/remind",
				data: { 
					rfqRefNo : thisView.model.attributes.rfq_id,
					supplierTnid : thisView.model.attributes.tnid
				}
			})
			.done(function( msg ) {
				alert("A reminder has been sent to this Supplier.");
			});
	    },

	    excludeClicked: function(e){
	    	e.preventDefault();
	    	var exclude,
	    		thisView = this;
	    	if(this.model.attributes.quote_exclude){
	    		var exclude = 0;
	    	}
	    	else {
	    		var exclude = 1;
	    	}
	    	
	    	$.ajax({
				type: "POST",
				url: "/buyer/quote/stats-exclude",
				data: { 
					exclude : exclude,
					quoteRefNo : thisView.model.attributes.quote_id
				}
			})
			.done(function( msg ) {
				if(exclude == 1){
					thisView.model.attributes.quote_exclude = true;
				}
				else {
					thisView.model.attributes.quote_exclude = false;
				}
				
				thisView.render();
			});
	    }
	});

	return sendToRowView;
});