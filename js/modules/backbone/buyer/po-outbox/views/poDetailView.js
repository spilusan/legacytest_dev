define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/shared/hbh/general',
	'backbone/shared/hbh/inbox',
	'../collections/poDetail',
	'text!templates/buyer/po-outbox/tpl/poSection.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	genHbh,
	inboxHbh,
	poDetail,
	poDetailTpl
){
	var poDetailView = Backbone.View.extend({
		tagName: 'div',
		className: 'details',
		template: Handlebars.compile(poDetailTpl),

		initialize: function(){
			_.bindAll(this, 'render');
			this.collection = new poDetail();
			this.collection.url = '/buyer/quote/details/';
			this.rfqCollection = new poDetail();
			this.rfqCollection.url = '/buyer/search/rfq-details';
		},

		getData: function(id){
			var thisView = this;
			this.collection.fetch({
				data: $.param({
					quoteRefNo: id
				}),
				complete: function() {
					thisView.getRfqData();
				}
			});
		},

		getRfqData: function(){
			var thisView = this;
			this.rfqCollection.fetch({
				data: $.param({
					rfqRefNo: thisView.collection.models[0].attributes.rfqId,
					hash: thisView.collection.models[0].attributes.hash,

				}),
				complete: function() {
					thisView.render();
				}
			});
		},

	    render: function() {
	    	var data = this.collection.models[0].attributes;
	    	data.rfq = this.rfqCollection.models[0].attributes;

	    	var discountTotal = 0;
	    	
	    	_.each(data.lineItemSections, function(sectionItem){
	    		_.each(sectionItem.lineItems, function(item){
	    			if(item.discountPercentage === null){
	    				item.discountPercentage = 0;
	    			}

	    			var discount = item.priceTotal * item.discountPercentage / 100;
	    			discountTotal += discount;

	    			if(item.changes.status === "DEC") {
	    				item.status = "Declined";
	    				item.statStyle = "dec";
	    				item.statTxtStyle = "dec";
	    			}
	    			else if(item.changes.status === "REM") {
	    				item.status = "Removed";
	    			}
	    			else if(item.changes.status === "MOD") {
	    				item.status = "Changed";
	    				item.statStyle = "chg";
	    				item.statTxtStyle = "chStat";
	    			}

	    			if(item.changes.config !== null) {
	    				item.confChg = true;
	    			}
	    			if(item.changes.quantity !== null) {
	    				item.quChg = true;
	    			}
	    			if(item.changes.unit !== null) {
	    				item.untChg = true;
	    			}
	    			if(item.changes.description !== null) {
	    				item.descChg = true;
	    			}

	    			item.priceUnit = parseFloat(item.priceUnit).toFixed(2);
	    			item.priceTotal =  parseFloat(item.priceTotal).toFixed(2);
	    			item.discountPercentage =  parseFloat(item.discountPercentage).toFixed(2);
	    		});
	    	}, this);

			data.discountedTotalCost = parseFloat(data.subTotalCost) - parseFloat(discountTotal);
			data.discountedTotalCost = data.discountedTotalCost.toFixed(2);
	    	data.discountTotal = discountTotal.toFixed(2);
	    	data.discountTotalPercentage = discountTotal / data.totalCost * 100;
	    	data.discountTotalPercentage = data.discountTotalPercentage.toFixed(2);
	    	data.totalCost = data.totalCost.toFixed(2);
	    	data.subTotalCost = data.subTotalCost.toFixed(2);

			var html = this.template(data);
			$(this.el).html(html);
			$('.section.po').append(this.el);

			$('.box.right').each(function(){
				if($(this).find("div.data").height() < $(this).next('.box.left').find('.data').height()){
					$(this).find('.data').height($(this).next('.box.left').find('.data').height());
				}
				else if($(this).find("div.data").height() > $(this).next('.box.left').find('.data').height()){
					$(this).next('.box.left').find('.data').height($(this).find('.data').height());
				}
			});

			$('.box.noBord').each(function(){
				if($(this).find('.data .right').height() < $(this).find('.data .left').height()){
					$(this).find('.data .right').height($(this).find('.data .left').height());
				}
				else if($(this).find('.data .right').height() > $(this).find('.data .left').height()){
					$(this).find('.data .left').height($(this).find('.data .right').height());
				}
			});
	    }
	});

	return poDetailView;
});