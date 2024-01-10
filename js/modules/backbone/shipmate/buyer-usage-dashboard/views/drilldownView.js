define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/shared/hbh/general',
	'../views/verifiedUserAccountView',
	'../views/activeTradingAccountsView',
	'../views/nonVerifiedUserAccountView',
	'../views/succSignInsView',
	'../views/failedSignInsView',
	'../views/userActivityView',
	'../views/searchEventsyView',
	'../views/searchImpressionsyView',
	'../views/contactRequestsView',
	'../views/pagesRfqsView',
	'../views/rfqOrdWoImoView'
], function(
	$,
	_, 
	Backbone,
	Hb,
	generalHbh,
	verifiedUserAccount,
	activeTradingAccount,
	nonVerifiedUserAccount,
	succSignIns,
	failedSignIns,
	userActivity,
	searchEvents,
	searchImpressions,
	contactRequests,
	pagesRfqs,
	rfqOrdWoImo
){
	var drilldownView = Backbone.View.extend({
		el: $('body'),
		params: require('buyer/params'),
		
		initialize: function() {
			$(document).ajaxStart(function(){
				$('#waiting').show();
			});

			$(document).ajaxStop(function(){
				$('#waiting').hide();
			});
			
			/*
			* Add new reports here
			*/
			switch(this.params.type) {
			    case 'verifiedUserAccounts':
			        this.reportView = new verifiedUserAccount();
			        break;
			    case 'nonVerifiedUserAccounts':
			        this.reportView = new nonVerifiedUserAccount();
			        break;
			    case 'succSignIns':
			        this.reportView = new succSignIns();
			        break;
			    case 'failedSignIns':
			        this.reportView = new failedSignIns();
			        break;
				case 'userActivity':
			        this.reportView = new userActivity();
			        break;
				case 'searchEvents':
			        this.reportView = new searchEvents();
			        break;
				case 'spbImpressions':
			        this.reportView = new searchImpressions();
			        break;
				case 'contactRequests':
			        this.reportView = new contactRequests();
			        break;
				case 'pagesRfqsSent':
			        this.reportView = new pagesRfqs();
			        break;
				case 'activeTradingAccounts':
			        this.reportView = new activeTradingAccount();
			        break;
				case 'rfqOrdWoImo':
			        this.reportView = new rfqOrdWoImo();
			        break;
			    default:
			        alert('Report does not exists(1): ' + this.params.type);
			        return;
			}

			this.reportView.parent = this;
			this.reportView.getCollection();

			this.render();
		},

		render: function(){		
			this.reportView.render();
		},

        renderAjaxErrorMessage: function( errorMessage )
        {
        	var html = this.ajaxErrorTemplate({message:errorMessage}); 
        	
        	$(".innerContent").html(html);
        },

		onError: function(errorMsg, url, lineNumber)
		{
			//var errorMessage = '<div class="ajaxError">'+errorMsg+" line("+lineNumber+")  in "+url+'</div>'
			var errorM = "We're sorry, there is a problem. Please try again later.";
			var errorMessage = '<div class="ajaxError">'+errorM+'</div>';
        	$(".dataContainer").html(errorMessage);
        	$('#waiting').hide();
		}
	});

	return new drilldownView();
});
