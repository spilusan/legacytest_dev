define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/shared/hbh/general',
	'backbone/shared/hbh/inbox',
	'../collections/quoteDetail',
	'text!templates/buyer/quote-inbox/tpl/quoteSection.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	genHbh,
	inboxHbh,
	quoteDetail,
	quoteDetailTpl
){
	var quoteDetailView = Backbone.View.extend({
		tagName: 'div',
		className: 'details',
		template: Handlebars.compile(quoteDetailTpl),

		initialize: function(){
			_.bindAll(this, 'render');
			this.collection = new quoteDetail();
			this.collection.url = '/buyer/quote/details/';
			this.rfqCollection = new quoteDetail();
			this.rfqCollection.url = '/buyer/search/rfq-details';
		},

		getData: function(id, hash){
			var thisView = this;
			this.collection.fetch({
				data: $.param({
					quoteRefNo: id,
					hash: hash
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
					hash: thisView.collection.models[0].attributes.hash
				}),
				complete: function() {
					thisView.render();
				}
			});
		},

	    render: function() {
	    	var data = this.collection.models[0].attributes;
	    	data.rfq = this.rfqCollection.models[0].attributes;
	    	_.each(data.lineItemSections, function(sectionItem){
	    		sectionItem.currency = data.currency;
	    		_.each(sectionItem.lineItems, function(item){
	    			if(item.discountPercentage === null){
	    				item.discountPercentage = 0;
	    			}

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
			
			if(data.cost.discountTotalCost === null){
				data.cost.discountTotalCost = 0;
			}
	    	if(data.cost.discountPercentage === null){
	    		data.cost.discountPercentage = 0;
	    	}
	    	if(data.cost.freight === null){
	    		data.cost.freight = 0;
	    	}
	    	if(data.cost.packing === null){
	    		data.cost.packing = 0;
	    	}
	    	if(data.cost.other === null){
	    		data.cost.other = 0;
	    	}

	    	data.cost.discount = data.cost.discount.toFixed(2);
	    	data.cost.totalCost = data.cost.totalCost.toFixed(2);
	    	data.cost.subTotalCost = data.cost.subTotalCost.toFixed(2);
	    	data.cost.discountTotalCost = data.cost.discountTotalCost.toFixed(2);
	    	data.cost.discountPercentage = data.cost.discountPercentage.toFixed(2);
	    	data.cost.freight = data.cost.freight.toFixed(2);
	    	_.each(data.cost.additional, function(item){
	    		item.amount = item.amount.toFixed(2);
	    	}, this);

			var html = this.template(data);
			$(this.el).html(html);
			$('.section.quote').append(this.el);

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

	return quoteDetailView;
});