define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'../hbh/rfqInbox',
	'backbone/shared/hbh/general',
	'../collections/rfqData',
	'text!templates/trade/rfq-inbox/tpl/rfqPrint.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	rfqInboxHbh,
	generalHbh,
	rfqData,
	rfqPrintTpl
){
	var viewRfq = Backbone.View.extend({

		rfqTemplate: Handlebars.compile(rfqPrintTpl),

		hash: require('trade/rfq-inbox/hash'),
		tnid: require('trade/rfq-inbox/tnid'),
		pinId: require('trade/rfq-inbox/pinId'),
		printMode: require('trade/rfq-inbox/printMode'),

		el: $('.rfqDisplay'),

		initialize: function(){
			_.bindAll(this, 'render');
			this.collection = new rfqData();
			this.render();
		},

	    render: function() {
   			this.getRfqData();
  			/*if( this.printMode == '1' ){
	    		window.print();
	    	}*/
	        return this;
	    },

	    getRfqData: function() {
	    	var thisView = this;
	    	this.collection.reset();
			this.collection.fetch({ 
				data: $.param({ 
					id: this.pinId,
					tnid: this.tnid,
					hash: this.hash
				}),
				complete: function(){
					thisView.renderRfq();
				}
			});
	    },

	    renderRfq: function(){	    	
	    	$('.rfqDisplay .rfqFrame').remove();

	    	if(this.collection.models[0]){
	    		var data = this.collection.models[0].attributes;

	    		//fix empty port name
	    		if (data.rfqDeliveryPortName && data.rfqDeliveryPortName === " "){
	    			data.rfqDeliveryPortName = null;
	    		}

	    		//fix empty delivery address
	    		if (data.rfqAddress === "\r\n, , , , , \r\ncontact: , tel: , fax: , email: ") {
	    			data.rfqAddress = null;
	    		}
	    		
	    		var html = this.rfqTemplate(data);
		    	$(this.el).append(html);

		    	this.removeEmptyBlocks(data);
	    	}
	    },

	    removeEmptyBlocks: function(data) {
	    	// equalizing height of contact data boxes
	    	if(
	    		$('.dataBox.col.left .dataFrame.contact').height() > $('.dataBox.col.right .dataFrame.contact').height()
	    	){
	    		$('.dataBox.col.right .dataFrame.contact').css('height', $('.dataBox.col.left .dataFrame.contact').height());
	    	}
	    	else if(
	    		$('.dataBox.col.left .dataFrame.contact').height() < $('.dataBox.col.right .dataFrame.contact').height()
	    	) {
	    		$('.dataBox.col.left .dataFrame.contact').css('height', $('.dataBox.col.right .dataFrame.contact').height());
	    	}

	    	//check if billing details exist and remove box if not
	    	if(
	    		!data.rfqBillingAddress1 && 
	    		!data.rfqBillingAddress2 && 
	    		!data.rfqBillingCity && 
	    		!data.rfqBillingPostalZipCode &&
	    		!data.rfqBillingStateProvince &&
	    		!data.rfqBillingCountry
	    	) {
	    		$('.dataBox.bill').remove();
	    		$('.dataBox.deliver').removeClass('col');
	    		$('.dataBox.deliver').removeClass('right');
	    		var billing = true;
	    	}

	    	//check if delivery details exist and remove box if not
	    	if(
	    		!data.rfqAddress &&
	    		!data.rfqDateTime &&
	    		!data.rfqTermsOfDelivery &&
	    		!data.rfqTransportationMode
	    	) {
	    		$('.dataBox.deliver').remove();
	    		$('.dataBox.bill').removeClass('col');
	    		var deliver = true;
	    	}

	    	// if billing and delivery details are present equalize height of boxes
	    	if(!billing && !deliver) {
	    		if(
	    			$('.dataBox.col.deliver .dataFrame').height() > $('.dataBox.col.bill .dataFrame').height()
	    		) {
		    		$('.dataBox.col.bill .dataFrame').css('height', $('.dataBox.col.deliver .dataFrame').height());
		    	}
		    	else if(
		    		$('.dataBox.col.deliver .dataFrame').height() < $('.dataBox.col.bill .dataFrame').height()
		    	){
		    		$('.dataBox.col.deliver .dataFrame').css('height', $('.dataBox.col.bill .dataFrame').height());
		    	}
	    	}

	    	//check if other buyer details exist and remove box if not
	    	if(
	    		!data.rfqPackagingInstructions &&
	    		!data.rfqComments
	    	) {
	    		$('.dataBox.otherDets').remove();
	    	}
	    	//check if has attachment and remove box if not
	    	if(data.enquiry.pinHasAttachment === "0") {
	    		$('.dataBox.attachments').remove();
	    	}

	    	//check if line items are present and remove box if not
	    	if(
	    		$('.dataBox.lineItems table.section').length <= 1 && 
	    		$('.dataBox.lineItems table.section tbody tr.item').length <= 1 &&
	    		(
	    			!data.rfqLineItems ||
	    			!data.rfqLineItems[1].lineItems[0].rflIdType &&
	    			!data.rfqLineItems[1].lineItems[0].rflIdCode &&
	    			!data.rfqLineItems[1].lineItems[0].rflProductDesc &&
	    			!data.rfqLineItems[1].lineItems[0].rflComments
	    		)
	    	){
	    		$('.dataBox.lineItems').remove();
	    	}
	    }
	});

	return new viewRfq;
});