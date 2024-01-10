define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'libs/jquery.dateFormat',
	'libs/jquery.tools.min',
	'libs/jquery.tools.overlay.modified',
	'../collections/summary',
	'text!templates/trade/rfq-inbox/tpl/summary.html'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	dateFormat,
	Tools,
	Modal,
	summaryCollection,
	rfqSummaryTpl
){
	var rfqSummaryView = Backbone.View.extend({
		
		el: $('#content'),

		hash: require('trade/rfq-inbox/hash'),
		tnid: require('trade/rfq-inbox/tnid'),
		shipMate: require('trade/rfq-inbox/shipMate'),

		template: Handlebars.compile(rfqSummaryTpl),

		initialize: function () {
			this.summaryCollection = new summaryCollection();
		},

		render: function() {
			var thisView = this;
			this.summaryCollection.fetch({ 
				data: $.param({ id: this.tnid, hash: this.hash}),
				complete: function(){
					thisView.renderSummary();
				}
			});

			$('a[rel].ovl').overlay({
		        mask: 'black',
		        left: 'center',
		        fixed: 'true',
		 
		        onBeforeLoad: function() {
		 
		            var wrap = this.getOverlay().find('.modalBody');
		 
		            wrap.load(this.getTrigger().attr('href'));

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
		},

		renderSummary: function(){
			if(this.summaryCollection.models[0]) {
				var data = this.summaryCollection.models[0];
				var ndata = data.attributes.notification;
				data = data.attributes.stats;

				var html = this.template(data);
				var today = new Date();
				var todate = $.format.date(today, "dd MMM yyyy");
				var fromdate = today.setDate(today.getDate()-366);
				fromdate = $.format.date(fromdate, "dd MMM yyyy");

				$('div.summary').remove();
				$('.listInfo').after(html);
				$('.listInfo .sum').html(data.sent);
				$('.listInfo .from').html(fromdate);
				$('.listInfo .to').html(todate);
				$('.listInfo p .email').html(ndata.email);

				if(!this.shipMate){
					$('.listInfo p a.change').attr('href', ndata.urlToChange);
					$('.listInfo p a.change').attr('target', '_blank');
					$('.listInfo p a.change').removeClass('ovl');
					$('.listInfo p a.change').removeAttr('rel');
					$('.listInfo p a.change').unbind();
				}
			}
			//fix height of body container due to absolute pos of content container
	    	var height = 0;
	    	if($('#content').height() < $('#sidebar').height()){
	    		height = $('#sidebar').height();
	    	}
	    	else {
	    		height = $('#content').height() + 25;
	    	}

	    	$('#body').height(height);

	    	$(window).resize(function(){
	    		$('#body').height(height);
	    	});	
		}
	});

	return new rfqSummaryView;
});
