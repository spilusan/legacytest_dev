define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/shared/hbh/general',
	'text!templates/shipmate/buyer-usage-dashboard/tpl/usageDashboardRow.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	HbhGen,
	usageDashboardRowTpl
){
	var usageDashboardRowView = Backbone.View.extend({
		tagName: 'tr',

		template: Handlebars.compile(usageDashboardRowTpl),

		events: {
			'click a.openDrilldown' : 'onOpenDrilldown'
		},
		
		initialize: function(){
			_.bindAll(this, 'render');
			
			this.model.view = this;
		},

	    render: function() {
			var data = this.model.attributes;
			var html = this.template(data);

			$(this.el).html(html);

	        return this;
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

	    onOpenDrilldown: function(e)
	    {
			if(e){
				e.preventDefault();
			}
			var reportType = $(e.target).attr('href');

			/* 
			* preparing  parameters for the URL
			*/
			var paramAddStr = "byo="+this.model.attributes.id;
			paramAddStr += "&name="+encodeURIComponent(this.parent.name);
			paramAddStr += "&range="+this.parent.range;
			paramAddStr += "&timezone="+this.parent.timezone;
			paramAddStr += "&excludeSM="+this.parent.excludeSM;

			switch(reportType) {
			    case '#verifiedUserAccounts':
			        window.open("/shipmate/buyer-usage-dashboard-drilldown?type=verifiedUserAccounts&"+paramAddStr, '_blank');
			        break;
			    case '#nonVerifiedUserAccounts':
			        window.open("/shipmate/buyer-usage-dashboard-drilldown?type=nonVerifiedUserAccounts&"+paramAddStr, '_blank');
			        break;
			    case '#succSignIns':
			        window.open("/shipmate/buyer-usage-dashboard-drilldown?type=succSignIns&"+paramAddStr, '_blank');
			        break;
			    case '#failedSignIns':
			        window.open("/shipmate/buyer-usage-dashboard-drilldown?type=failedSignIns&"+paramAddStr, '_blank');
			        break;
			    case '#userActivity':
			    	paramAddStr += '&reportType='+$(e.target).data('id');
			        window.open("/shipmate/buyer-usage-dashboard-drilldown?type=userActivity&"+paramAddStr, '_blank');
			        break;
				case '#searchEvents':
			        window.open("/shipmate/buyer-usage-dashboard-drilldown?type=searchEvents&"+paramAddStr, '_blank');
			        break;
				case '#spbPageImpressions':
			        window.open("/shipmate/buyer-usage-dashboard-drilldown?type=spbImpressions&"+paramAddStr, '_blank');
			        break;
				case '#contactRequests':
			        window.open("/shipmate/buyer-usage-dashboard-drilldown?type=contactRequests&"+paramAddStr, '_blank');
			        break;
				case '#pagesRfqsSent':
			        window.open("/shipmate/buyer-usage-dashboard-drilldown?type=pagesRfqsSent&"+paramAddStr, '_blank');
			        break;
				case '#activeTradingAccounts':
			        window.open("/shipmate/buyer-usage-dashboard-drilldown?type=activeTradingAccounts&"+paramAddStr, '_blank');
			        break;
				case '#rfqOrdWoImo':
			        window.open("/shipmate/buyer-usage-dashboard-drilldown?type=rfqOrdWoImo&"+paramAddStr, '_blank');
			        break;
			    default:
			        alert('Report does not exists(2): ' + reportType);
			}
	    }
	});

	return usageDashboardRowView;
});