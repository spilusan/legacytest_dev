define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/shared/hbh/general',
	'backbone/shared/pagination/views/paginationView',
	'../collections/collection',
	'text!templates/shipmate/erroneous-transactions/tpl/content.html'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	generalHbh,
	paginationView,
	Collection,
	contentTpl
){
	var mainView = Backbone.View.extend({
		
		el: $('body'),

		contentTemplate: Handlebars.compile(contentTpl),

		events: {
			'click #closeEmailPev' : 'closeEmailPreview',
			'click #pgNext' : 'onNextPage',
			'click #pgPrev' : 'onPrevPage',
			
		},

		initialize: function () {
			paginationView.page = 1;
			paginationView.parent = this;
			paginationView.paginationLimit = 20;
			this.errCollection = new Collection();
			this.errCollection.url = '/shipmate/erroneous-transactions-report';
			this.getData();
			$( window ).resize(function(){
				$('#emailPreview').height($(window).height()-40);
				$('#emailWrapper').height($(window).height()-100);
			});
		},

		getData: function()
		{
				var thisView = this;
				this.errCollection.reset();
				this.errCollection.fetch({
				type: this.ajaxType,
				data: {
					'type': 'transaction-list',
					'page': paginationView.page,
					'itemPerPage': paginationView.paginationLimit,
				},
				complete: function(){
					thisView.render();
				},
				error: function( model, response, options ) {
					if (response.responseText) {
						var errorObj = $.parseJSON(response.responseText);
					}
				}
			});	

		},

		render: function() {

			var thisView = this;

			var data = {
				data: this.errCollection.models[0].attributes.data,
			}

			var html = this.contentTemplate(data);
			$('#erroneous-transactions').html(html);
			$('.sendSuppAlert').click(function(e){
				e.preventDefault();
				thisView.resendNotification('resend-supplier',$(this));
			}); 

			$('.sendBuyerAlert').click(function(e){
				e.preventDefault();
				thisView.resendNotification('resend-buyer',$(this));
			}); 

			$('.markAsCorrect').click(function(e){
				e.preventDefault();
				thisView.confirmDocument($(this));
			}); 

			$('.showAlerts').click(function(e){
				e.preventDefault();
				thisView.showAlerts($(this));
			}); 
	
				    //pass params to pagination

				//render pagination view
		    	paginationView.render(this.errCollection.models[0].attributes.itemCount);

			this.fixHeight();

		},
		fixHeight: function() {

			var nHeight = $('#content').height();

			if (nHeight > 0) {
			$('#body').height(nHeight);
	    		/* if ($(".benchTab").find('li:first').hasClass('selected') == true) { */
	    			if (true) {
	    			var newWidth = $(window).width()-260;
	    			if (newWidth<980)  {
							newWidth=980
	    			}
					$('#content').css('width' , newWidth+'px');
	    		} else {
					$('#content').css('width' , 'auto');
	    		}
    		}
		},

		resendNotification: function( notificationType, sender )
		{
			var etnOrdInternalRefNo = parseInt($(sender).data('id'));
			var spinnerElement = $(sender).next();
			var resentLabel = $(spinnerElement).next();
			$(spinnerElement).show();
			$(sender).hide();
			$.ajax({
					type: 'POST',
					url: '/shipmate/erroneous-transactions-report',
					data: {
						'type' : notificationType,
						'ordInternalRefNo': etnOrdInternalRefNo,
					},
					cache: false,
					success: function(result){
						$(spinnerElement).hide();
						$(sender).show();
						$(resentLabel).show();
					},
					error: function( error ) {
						$(spinnerElement).hide();
						$(sender).show();
						$(resentLabel).show();
					}
				});

		},


		confirmDocument: function( sender )
		{
			var etnOrdInternalRefNo = parseInt($(sender).data('id'));
			var spinnerElement = $(sender).next();
			var contentTd = $(sender).parent().prev().prev();
			$(spinnerElement).show();
			$(sender).hide();
			$.ajax({
					type: 'POST',
					url: '/shipmate/erroneous-transactions-report',
					data: {
						'type' : 'confirm-as-correct',
						'ordInternalRefNo': etnOrdInternalRefNo,
					},
					cache: false,
					success: function(result){
						$(spinnerElement).hide();
						$(sender).show();
						$(contentTd).html('Correct as issued');
					},
					error: function( error ) {
						$(spinnerElement).hide();
						$(sender).show();
					}
				});

		},

		showAlerts: function( sender )
		{
			$('#emailWrapper').html('');

			var etnOrdInternalRefNo = parseInt($(sender).data('id'));
			var alerts = $(sender).data('alerts');
			if (alerts.indexOf("S") >= 0) this.fetchAlert(etnOrdInternalRefNo, 'email-supplier', sender);
			if (alerts.indexOf("B") >= 0) this.fetchAlert(etnOrdInternalRefNo, 'email-buyer', sender);
			if (alerts.indexOf("G") >= 0) this.fetchAlert(etnOrdInternalRefNo, 'email-gsd', sender);
			if (alerts.indexOf("2") >= 0) this.fetchAlert(etnOrdInternalRefNo, 'email-second-reminder', sender);

		},

		fetchAlert: function(etnOrdInternalRefNo, alertType, sender)
		{
			var thisView = this;

			var spinnerElement = $(sender).next();
			$(spinnerElement).show();
			$(sender).hide();
			$.ajax({
					type: 'POST',
					url: '/shipmate/erroneous-transactions-report',
					data: {
						'type' : alertType,
						'ordInternalRefNo': etnOrdInternalRefNo,
					},
					cache: false,
					success: function(result){
						if (result.html) {
							thisView.showDocument(result.html, alertType);
							$('#emailWrapper').scrollTop(0);
						}
						$(spinnerElement).hide();
						$(sender).show();
					},
					error: function( error ) {
						$(spinnerElement).hide();
						$(sender).show();
					}
				});
		},

		showDocument: function( html, alertType )
		{
			$('#emailPreview').height($(window).height()-40);
			$('#emailWrapper').height($(window).height()-100);
			$('#emailPreview').fadeIn(600);
			var newBlock = $('<div>');
			newBlock.html(html);
			var separator = $('<div>');

			switch (alertType)
			{
				case 'email-supplier':
					separator.html('<hr>Email to supplier<hr>');
					break;

				case 'email-buyer':
					separator.html('<hr>Email to Buyer<hr>');
					break;

				case 'email-gsd':
					separator.html('<hr>Email to GSD<hr>');
					break;

				case 'email-second-reminder':
					separator.html('<hr>Reminder to Supplier<hr>');
					break;
			}

			$('#emailWrapper').append(separator);
			$('#emailWrapper').append(newBlock);
		},

		closeEmailPreview: function(e)
		{
			$('#emailPreview').fadeOut(600);
		}

	});

	return new mainView;
});
