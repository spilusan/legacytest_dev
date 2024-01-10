define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/shared/hbh/general',
	'text!templates/shipmate/supplier-usage-dashboard/tpl/usageDashboardRow.html'
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
			var paramAddStr = "spb="+this.model.attributes.id;
			paramAddStr += "&name="+encodeURIComponent(this.parent.name);
			paramAddStr += "&range="+this.parent.range;
			paramAddStr += "&timezone="+this.parent.timezone;
			paramAddStr += "&excludeSM="+this.parent.excludeSM;
			paramAddStr += "&country="+this.parent.country;
			paramAddStr += "&level="+this.parent.level;

			switch('row', reportType) {
				case '#userActivity':
			    	paramAddStr += '&reportType='+$(e.target).data('id');
			        window.open("/shipmate/supplier-usage-dashboard-drilldown?type=userActivity&"+paramAddStr, '_blank');
			        break;
			    default:
			        alert('Report does not exists');
			}
	    }
	});

	return usageDashboardRowView;
});