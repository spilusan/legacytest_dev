define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'libs/jquery.tools.min',
	'libs/jquery.tools.overlay.modified',
	'backbone/shared/hbh/general',
	'../hbh/rfqInbox',
	'../collections/rfqData',
	'../views/rfqSummaryView',
	'text!templates/trade/rfq-inbox/tpl/rfqRow.html',
	'text!templates/trade/rfq-inbox/tpl/rfqPrint.html',
	'text!templates/trade/rfq-inbox/tpl/confirmation.html',
	'text!templates/trade/rfq-inbox/tpl/blockConf.html',
	'text!templates/trade/rfq-inbox/tpl/smartReply.html',
	'text!templates/trade/rfq-inbox/tpl/expertReply.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	Tools,
	Modal,
	HbhGen,
	HbhInbox,
	rfqData,
	rfqSummaryView,
	rfqRowTpl,
	rfqPrintTpl,
	confDiaTpl,
	blockConfTpl,
	smartReplyTpl,
	expertReplyTpl
){
	var rfqRowView = Backbone.View.extend({
		tagName: 'tr',

		template: Handlebars.compile(rfqRowTpl),
		rfqTemplate: Handlebars.compile(rfqPrintTpl),
		confirmDialogTpl: Handlebars.compile(confDiaTpl),
		blockConfTpl: Handlebars.compile(blockConfTpl),
		smartReplyTpl: Handlebars.compile(smartReplyTpl),
		expertReplyTpl: Handlebars.compile(expertReplyTpl),

		hash: require('trade/rfq-inbox/hash'),
		tnid: require('trade/rfq-inbox/tnid'),
		uid: require('trade/rfq-inbox/userId'),
		isMemberOfCompany: require('trade/rfq-inbox/isPartOfCompany'),
		
		initialize: function(){
			_.bindAll(this, 'render');
			this.collection = new rfqData();
			this.model.view = this;
		},

	    render: function(rid) {
			var data = this.model.attributes;
			var html = this.template(data);

			$(this.el).html(html);

			$(this.el).unbind().bind('click', {context: this}, function(e) {
   				e.data.context.getRfqData(e.data.context.model.attributes.pinId);
  			});

  			if(data.pinStatus === "Not clicked") 
  			{
  				$(this.el).addClass('unread');
  			}
  			else if(data.pinStatus === "Details viewed")
  			{
  				$(this.el).removeClass('unread');
  				$(this.el).addClass('read');
  			}
  			else if(data.pinStatus === "Not interested")
  			{
  				$(this.el).removeClass('read');
  				$(this.el).addClass('declined');
  			}
  			else if(data.pinStatus === "Replied")
  			{
  				$(this.el).removeClass('read');
  				$(this.el).addClass('quoted');
  			}

	        if(rid && rid!=="") {
	        	this.getRfqData(rid);
	        	rid = "";
	        }

	        return this;
	    },

	    reRender: function() {
			this.render();
  			rfqSummaryView.render();
	    },

	    getRfqData: function(id) {
	    	var thisView = this;
	    	this.collection.reset();
			this.collection.fetch({ 
				data: $.param({ 
					id: id,
					tnid: this.tnid,
					hash: this.hash
				}),
				complete: function(){
					thisView.setRead();
					thisView.pinId = "";
					thisView.renderRfq();
				}
			});
	    },

	    renderRfq: function(){
	    	if(history.pushState){
	    		var page = "?page=" + $('.pagination ul li.current').text();
	    		history.pushState("", "", page);
	    	}
	    	$('.pagesRfqList').hide();
	    	$('.pagination').hide();
	    	$('.rfqDisplay .rfqFrame').remove();

	    	if(this.collection.models[0]){
	    		data = this.collection.models[0].attributes;
				
				//fix empty port name
	    		if (data.rfqDeliveryPortName === " "){
	    			data.rfqDeliveryPortName = null;
	    		}

	    		//fix empty delivery address
	    		if (data.rfqAddress === "\r\n, , , , , \r\ncontact: , tel: , fax: , email: ") {
	    			data.rfqAddress = null;
	    		}

	    		//Append template for rfq and show
	    		var html = this.rfqTemplate(data);
		    	$('.rfqDisplay').append(html);
		    	$('.rfqDisplay').show();
		    	$('.buttons').show();	    	

		    	//removing empty blocks
		    	this.removeEmptyBlocks(data);

		    	// removing the reply and decline button if RFQ is more than 180 days
		    	if( data.enquiry.dateDiffFromToday >= 180 || this.isMemberOfCompany == 'false'){
		    		$('.reply').hide();
		    		$('.decline').hide();
		    	// show the reply/decline button otherwise
		    	}else{
		    		$('.reply').show();
		    		$('.decline').show();
		    	}
		    	
		    	//hide block button if not member of company
		    	if( this.isMemberOfCompany == 'false' ){
		    		$('.block').hide();
		    	}else{
		    		$('.block').show();
		    	}

		    	if(
		    		data.rfqSupplier.integratedOnTradeNet === 'Y' &&
					(
						data.rfqAction.product === "SMART_SUPPLIER" || 
						data.rfqAction.product === "EXPERT_SUPPLIER"
					)
				){
		    		$('input.reply').hide();
		    		$('input.decline').hide();
		    		$('.smartInfo').show();
				}
		    	
		    	//Bind buttons
		    	$('input.reply').unbind().bind('click', {context: this, dataContext: data}, function(e){
	  				e.preventDefault();
	  				e.data.context.quote();
	  			});

		    	$('input.decline').unbind().bind('click', {context: this}, function(e) {
		    		e.preventDefault();
	   				e.data.context.decline();
	  			});

	  			$('input.block').unbind().bind('click', {context: this}, function(e) {
		    		e.preventDefault();
	   				e.data.context.block();
	  			});

	  			$('input.print').unbind().bind('click', {context: this}, function(e) {
		    		e.preventDefault();
	   				e.data.context.printRfq(data.enquiry.pinId, e.data.context.tnid, data.enquiry.pinHashKey);
	  			});

	  			$('input.back').unbind().bind('click', {context: this}, function(e) {
		    		e.preventDefault();
	   				e.data.context.back();
	  			});
	    	}
	    	$('#body').removeClass('liquid');
	  		$('#content').addClass('rfqView');	
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
	    },

	    setRead: function() {	    	
	    	//$('.rfqList tr').removeClass('open');
	    	//$(this.el).addClass('open');
	    	if(this.model.attributes.pinStatus === "Not clicked") {
	    		// do not mark the status of RFQ to READ for NON-Member
	    		if( this.isMemberOfCompany == 'true' ){
		    		this.model.set({
			    		pinStatus: "Details viewed"
			    	});

			    	var that = this;
			    	this.model.save({},{
			    		success: function(model, response){
			    			setTimeout(function() {
								that.reRender();
							}, 500);
			    		}
			    	});
	    		}
	    		
	    	}
	    },

	    block: function() {	   
	    	
	    	var data = this.collection.models[0].attributes;
	    	var url = "";
		        url += "/tnid/" + this.tnid;
		        url += "/uid/" + data.enquiry.pinUsrUserCode;
		        url += "/a/Y";
		    var that = this;
	        $.ajax({
	            url: '/enquiry/blocked-sender' + url,
	            type: 'GET',
	            cache: false,
	            error: function(request, textStatus, errorThrown) {
	                response = eval('(' + request.responseText + ')');
	                if( response.error != "User must be logged in"){
	                    alert("ERROR " + request.status + ": " + response.error);
	                }
	            },
	            success: function( response ){
	                var html = that.blockConfTpl();

		    		$('#modal .modalBody').html(html);

		    		that.openDialog();
	            }
	        });    
	    },

	    decline: function() {
	    	var data = this.collection.models[0].attributes;
	    	
	    	if(
	    		data.rfqSupplier.integratedOnTradeNet === 'Y' &&
				(
					data.rfqAction.product === "SMART_SUPPLIER" || 
					data.rfqAction.product === "EXPERT_SUPPLIER"
				)
			){
				this.checkSmartExpert(data);
			}
			else if( data.rfqSupplier.integratedOnTradeNet === 'Y' && data.rfqAction.product === "START_SUPPLIER") {
				window.open(data.rfqAction.urlForStartSupplier);
			}
			else {
		    	data.action = "decline";
		    	data.Action = "Decline";
		    	data.Button = "Decline";

		    	if(data.enquiry.pirDeclinedDate !== null) {
		    		data.button = "decline";
		    		data.actionPast = "declined";
		    		data.dec = true;

		    		var html = this.confirmDialogTpl(data);

		    		$('#modal .modalBody').html(html);

		    		this.openDialog();

		    		$('#modal .modalBody .modalContent .close').unbind().bind('click', function(){
		    			$('#modal').overlay().close();
		    		});

		    		$('#modal .modalBody .modalContent .decline').unbind().bind('click', {context: this}, function(e){
		    			e.data.context.model.set({
				    		pinStatus: "Not interested"
				    	});

				    	//e.data.context.reRender();
		    			//$('#modal').overlay().close();
		    			//$(e.data.context.el).click();
		    			var href="/enquiry/reject/?supplierBranchId=";
		    				href += e.data.context.tnid;
		    				href += "&declineSource=VIEW&enquiryId=";
		    				href += e.data.context.model.attributes.pinId;
		    				href += "&hash=";
		    				href += e.data.context.collection.models[0].attributes.enquiry.pinHashKey;
		    				href += "&page=";
		    				href += $('.pagination ul li.current').text();
		    			e.data.context.model.save({}, {
				    		success: function() {
				    			window.location=href;
				    		}
				    	});
		    		});
		    	}
		    	else if(data.enquiry.pirRepliedDate !== null) {
		    		data.button = "reply";
		    		data.actionPast = "replied to";
		    		data.rep = true;

		    		var html = this.confirmDialogTpl(data);

		    		$('#modal .modalBody').html(html);

		    		this.openDialog();

		    		$('#modal .modalBody .modalContent .close').unbind().bind('click', function(){
		    			$('#modal').overlay().close();
		    		});

		    		$('#modal .modalBody .modalContent .decline').unbind().bind('click', {context: this}, function(e){
		    			e.data.context.model.set({
				    		pinStatus: "Not interested"
				    	});
				    	
				    	//e.data.context.reRender();
		    			//$('#modal').overlay().close();
		    			//$(e.data.context.el).click();
		    			var href="/enquiry/reject/?supplierBranchId=";
		    				href += e.data.context.tnid;
		    				href += "&declineSource=VIEW&enquiryId=";
		    				href += e.data.context.model.attributes.pinId;
		    				href += "&hash=";
		    				href += e.data.context.collection.models[0].attributes.enquiry.pinHashKey;
		    				href += "&page=";
		    				href += $('.pagination ul li.current').text();
		    			e.data.context.model.save({}, {
				    		success: function() {
				    			window.location=href;
				    		}
				    	});
		    		});
		    	}
		    	else {
			    	this.model.set({
			    		pinStatus: "Not interested"
			    	});
			    	
			    	//this.reRender();
			    	//$(this.el).click();
		    		var href="/enquiry/reject/?supplierBranchId=";
		    			href += this.tnid;
		    			href += "&declineSource=VIEW&enquiryId=";
		    			href += this.model.attributes.pinId;
		    			href += "&hash=";
		    			href += this.collection.models[0].attributes.enquiry.pinHashKey;
		    			href += "&page=";
	    				href += $('.pagination ul li.current').text();
	    			this.model.save({}, {
	    				success: function(){
	    					window.location=href;
	    				}
	    			});
		    	}
		    }
	    },

	    quote: function() {
	    	var data = this.collection.models[0].attributes;
	    	if(
	    		data.rfqSupplier.integratedOnTradeNet === 'Y' &&
				(
					data.rfqAction.product === "SMART_SUPPLIER" || 
					data.rfqAction.product === "EXPERT_SUPPLIER"
				)
			){
				this.checkSmartExpert(data);
			}
			else if(data.rfqSupplier.integratedOnTradeNet === 'Y' && data.rfqAction.product === "START_SUPPLIER") {
				window.open(data.rfqAction.urlForStartSupplier);
			}
			else {
				data.action = "reply to";
		    	data.Action = "Reply to";
		    	data.Button = "Reply";

		    	if(data.enquiry.pirDeclinedDate !== null) {
		    		data.button = "decline";
		    		data.actionPast = "declined";
		    		data.dec = true;

		    		var html = this.confirmDialogTpl(data);

		    		$('#modal .modalBody').html(html);

		    		this.openDialog();

		    		$('#modal .modalBody .modalContent .close').unbind().bind('click', function(){
		    			$('#modal').overlay().close();
		    		});

		    		$('#modal .modalBody .modalContent .reply').unbind().bind('click', {context: this}, function(e){
		    			e.data.context.model.set({
				    		pinStatus: "Replied"
				    	});
				    	
				    	//$(e.data.context.el).click();
				    	//e.data.context.reRender();
		    			$('#modal').overlay().close();
		    			var href="/enquiry/post-respond-survey/enquiryId/";
		    				href += e.data.context.model.attributes.pinId;
		    				href += "/supplierBranchId/";
		    				href += e.data.context.tnid;
		    				href += "/hash/";
		    				href += e.data.context.collection.models[0].attributes.enquiry.pinHashKey;
		    				href += "/page/";
		    				href += $('.pagination ul li.current').text();
		    				href += "/";
		    			e.data.context.model.save({}, {
				    		success: function() {
				    			var myWindow = window.open("mailto:" + data.enquiry.pinEmail + "?subject=RE: " + data.rfqSubject);
		    					if(myWindow){
			    					myWindow.close();
			    				}
		    					myWindow.close();
				    			window.location=href;
				    		}
				    	});
		    		});
		    	}
		    	else if(data.enquiry.pirRepliedDate !== null) {
		    		data.button = "reply";
		    		data.actionPast = "replied to";
		    		data.rep = true;

		    		var html = this.confirmDialogTpl(data);

		    		$('#modal .modalBody').html(html);

		    		this.openDialog();

		    		$('#modal .modalBody .modalContent .close').unbind().bind('click', function(){
		    			$('#modal').overlay().close();
		    		});

		    		$('#modal .modalBody .modalContent .reply').unbind().bind('click', {context: this}, function(e){
		    			e.data.context.model.set({
				    		pinStatus: "Replied"
				    	});
				    	
				    	//$(e.data.context.el).click();
				    	//e.data.context.reRender();
		    			$('#modal').overlay().close();
		    			

		    			var href="/enquiry/post-respond-survey/enquiryId/";
		    				href += e.data.context.model.attributes.pinId;
		    				href += "/supplierBranchId/";
		    				href += e.data.context.tnid;
		    				href += "/hash/";
		    				href += e.data.context.collection.models[0].attributes.enquiry.pinHashKey;
		    				href += "/page/";
		    				href += $('.pagination ul li.current').text();
		    				href += "/";
		    			e.data.context.model.save({}, {
				    		success: function() {
				    			var myWindow = window.open("mailto:" + data.enquiry.pinEmail + "?subject=RE: " + data.rfqSubject);
		    					if(myWindow){
		    						myWindow.close();
		    					}
				    			window.location=href;
				    		}
				    	});
		    		});
		    	}
		    	else {
					this.model.set({
			    		pinStatus: "Replied"
			    	});
			    	
			    	//$(this.el).click();
			    	//this.reRender();
				
					var href="/enquiry/post-respond-survey/enquiryId/";
	    				href += this.model.attributes.pinId;
	    				href += "/supplierBranchId/";
	    				href += this.tnid;
	    				href += "/hash/";
	    				href += this.collection.models[0].attributes.enquiry.pinHashKey;
	    				href += "/page/";
	    				href += $('.pagination ul li.current').text();
	    				href += "/";
	    			this.model.save({}, {
	    				success: function(){
	    					var myWindow = window.open("mailto:" + data.enquiry.pinEmail + "?subject=RE: " + data.rfqSubject);
		    				if(myWindow){
		    					myWindow.close();
		    				}
	    					window.location=href;
	    				}
	    			});
	    			
	    		}
			}
	    },

	    printRfq: function(id, tnid, hash) {
	    	url = "/trade/view-rfq?print=1&id="+id+"&tnid="+tnid+"&hash="+hash;
	    	window.open(url);
	    },

	    checkSmartExpert: function(data) {
	    	if(data.rfqAction.product === "SMART_SUPPLIER"){
				var html = this.smartReplyTpl();
			}
			else if(data.rfqAction.product === "EXPERT_SUPPLIER"){
				var html = this.expertReplyTpl();
			}
			$('#modal .modalBody').html(html);
			this.openDialog();
	    },

	    openDialog: function() {
	    	$("#modal").overlay({
		        mask: 'black',
		        left: 'center',
		        fixed: 'true',
		 
		        onBeforeLoad: function() {
		            var windowWidth = $(window).width();
		        	var modalWidth = $('#modal').width();
		        	var posLeft = windowWidth/2 - modalWidth/2;

		        	$('#modal').css('left', posLeft);
		        },

		        onLoad: function() {
		        	$(window).resize(function(){
		        		var windowWidth = $(window).width();
		        		var modalWidth = $('#modal').width();
		        		var posLeft = windowWidth/2 - modalWidth/2;

		        		$('#modal').css('left', posLeft);
		        	});
		        }
			});

			$('#modal').overlay().load();
	    },

	    back: function(){
			$('.pagesRfqList').show();
	    	$('.pagination').show();
	    	$('.rfqDisplay').hide();
	    	$('.buttons').hide();
	    	$('#body').addClass('liquid');
	    	$('#content').removeClass('rfqView');
		}
	});

	return rfqRowView;
});