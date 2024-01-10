define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'libs/jquery.tools.overlay.modified',
	'../views/rfqOrdWoImoRow',
	'../collections/collection',
	'text!templates/shipmate/buyer-usage-dashboard/tpl/rfqOrdWoImoRowHead.html'
], function(
	$,
	_, 
	Backbone,
	Hb,
	Modal,
	rfqOrdWoImoRowView,
	collection,
	usageDashboardHeadTpl
){
	var usageDashboardView = Backbone.View.extend({
		el: $('body'),
		buyer: require('buyer/org'),
		params: require('buyer/params'),
		headTemplate: Handlebars.compile(usageDashboardHeadTpl),

		events: {
			'click input[name="export"]' : 'exportReport'
		},

		initialize: function(){
			this.collection = new collection();
			this.collection.url = "/reports/data/appusage/drilldown/rfq-ord-wo-imo-row/"+this.buyer.byoOrgCode;

		},

		getCollection: function(){
			var thisView = this;
			
			this.collection.fetch({
				type: "GET",
				complete: function(){
					thisView.render();
				}
			});
		},

		render: function(){			
			var html = this.headTemplate();
			$('table thead').html(html);

			this.renderItems();
		},

		renderItems: function(){
			$('table tbody').html('');
			_.each(this.collection.models, function(item){
				this.renderItem(item);
			}, this);

			var wWidth = $(window).width() -250;
			if ($('table').width() > wWidth) {
				wWidth = $('table').width();
			}
			
			$('#header').width(wWidth + 250);
			$('.divider').width(wWidth + 250);
			$('body').width(wWidth + 250);
			$('#body').width(wWidth + 235);
			$('#content').width(wWidth);

		},

		renderItem: function(item) {
			var rfqOrdWoImoRowViewObj = new rfqOrdWoImoRowView({
				model: item
			});

			rfqOrdWoImoRowViewObj.parent = this;

			$('table tbody').append(rfqOrdWoImoRowViewObj.render().el);
		},

        exportReport: function(e){
			if(e){
				e.preventDefault();
			}
        	var thisView = this;
        	e.preventDefault();
        	this.setCookie('showSpinner', 'true', 1);
        	$('#waiting').show();
        	var SpnnerTimer = setInterval(function(){
        		if (thisView.getCookie('showSpinner') === '') {
        			clearInterval(SpnnerTimer);
        			$('#waiting').hide();
        		}
        	}, 1000);
        	location.href = "/reports/export/appusage/drilldown/rfq-ord-wo-imo-row/"+this.buyer.byoOrgCode;
        },

        setCookie: function(cname, cvalue, exdays){
		    var d = new Date();
		    d.setTime(d.getTime() + (exdays*24*60*60*1000));
		    var expires = "expires="+ d.toUTCString();
		    document.cookie = cname + "=" + cvalue + "; path=/; " + expires;
		},

		getCookie: function(cname){
		    var name = cname + "=";
		    var ca = document.cookie.split(';');
		    for(var i = 0; i <ca.length; i++) {
		        var c = ca[i];
		        while (c.charAt(0)==' ') {
		            c = c.substring(1);
		        }
		        if (c.indexOf(name) === 0) {
		            return c.substring(name.length,c.length);
		        }
		    }
		    return "";
		}
	});

	return usageDashboardView;
});
