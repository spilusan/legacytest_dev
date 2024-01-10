define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'libs/jquery.shipserv-tooltip',
	'libs/jquery.tools.overlay.modified',
	'/js/lib/jquery.shipserv.sticky.js',
	'../views/usageDashboardRowView',
	'../collections/collection',
	'text!templates/shipmate/buyer-usage-dashboard/tpl/usageDashboardHead.html',
	'text!templates/shipmate/buyer-usage-dashboard/tpl/usageDashboardHeadFake.html'
], function(
	$,
	_, 
	Backbone,
	Hb,
	shTooltip,
	Modal,
	Sticky,
	usageDashboardRowView,
	collection,
	usageDashboardHeadTpl,
	usageDashboardHeadFakeTpl
){
	var usageDashboardView = Backbone.View.extend({
		el: $('body'),

		headTemplate: Handlebars.compile(usageDashboardHeadTpl),
		headFakeTemplate: Handlebars.compile(usageDashboardHeadFakeTpl),

		name: "",
		range: 30,
		timezone: "GMT",
		excludeSM: false,
		sortBy: "name",
		sortOrder: "asc",

		events: {
			'click input[name="run"]'    : 'applyFilters',
			'click a.sorting'		     : 'sort',
			'submit form'			     : 'applyFilters',
			'click input[name="export"]' : 'exportReport'
		},

		initialize: function(){
			this.collection = new collection();
			this.collection.url = "/reports/data/appusage";

			this.timezoneCollection = new collection();
			this.timezoneCollection.url= "/reports/data/timezones";

			this.renderFilterDropdowns();
			$(document).ready(function(){
				$('#usageData').shipservSticky();
			});
		},

		getCollection: function(){
			var thisView = this;
			
			this.collection.fetch({
				data: $.param({
					name: this.name,
					range: this.range,
					timezone: this.timezone,
					excludeSM: this.excludeSM,
					sortBy: this.sortBy,
					sortOrder: this.sortOrder
				}),
				type: "GET",
				complete: function(){
					thisView.render();
				}
			});
		},

		render: function(){	
			$('table thead').empty();

			var html = this.headTemplate();
			$('table thead').html(html);
			
			this.applySorting();
			this.renderItems();
			
            $('.taHelp').shTooltip({
                displayType : 'top'
            });
		},

		renderItems: function(){
			$('table tbody').html('');
			_.each(this.collection.models, function(item){
				this.renderItem(item);
			}, this);

			this.tableSticky();

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
			var usageDashboardRow = new usageDashboardRowView({
				model: item
			});

			usageDashboardRow.parent = this;

			$('table tbody').append(usageDashboardRow.render().el);
		},

		applyFilters: function(e){
			if(e){
				e.preventDefault();
			}
			this.updateFilterValues();
			this.getCollection();
		},

		sort: function(e){
			e.preventDefault();
			this.sortBy = $(e.target).attr('href');
			
			$('table thead tr th').removeClass('sort');

			if($(e.target).parent().hasClass('asc')){
				$('table thead tr th').removeClass('asc');

				this.sortOrder = "desc";
			}
			else {
				$('table thead tr th').removeClass('desc');

				this.sortOrder = "asc";
			}
			this.applyFilters();
		},

		applySorting: function(){
			var thisView = this;

			$('table thead tr th').each(function(){
				if($(this).find('a').attr('href') === thisView.sortBy){
					$(this).addClass('sort');
					$(this).addClass(thisView.sortOrder);
				}
			});
		},

		openDialog: function(){
            $("#modal").overlay({
                mask: 'black',
                left: 'center',
                fixed: true,

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

        exportReport: function(e){
			if(e){
				e.preventDefault();
			}
			this.updateFilterValues();
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
        	location.href = "/reports/export/appusage?name="+encodeURIComponent(this.name)+"&range="+this.range+"&timezone="+this.timezone+"&excludeSM="+this.excludeSM+"&sortBy="+this.sortBy+"&sortOrder="+this.sortOrder;
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
		},

		tableSticky: function() {
			/* This one does not stick the table head anymore, as a JQuery plugin is written for this, but displays warning message if there is no data */
			var table = $('#usageData');
			$(table).show();
			var tds = $(table).find('tbody').find('tr').first().find('td');
			if (tds.length === 0) {
				$(table).hide();
				$('#warning').show();
			}
		},
		renderFilterDropdowns: function() {
			
			var thisView = this;
			
			var timeZoneSelect = $('select[name="timezone"]');
	
			timeZoneSelect.empty();
			var timeZoneOption = null;
	
			thisView.timezoneCollection.fetch({
				type: "GET",
				complete: function(){
					//Rener Timezones
					timeZoneOption = $('<option>');
					timeZoneOption.val('');
					timeZoneOption.html('All');
					timeZoneSelect.append(timeZoneOption);
					_.each(thisView.timezoneCollection.models, function(item){
						timeZoneOption = $('<option>');
						timeZoneOption.val(item.attributes.id);
						timeZoneOption.html(item.attributes.name);
						timeZoneSelect.append(timeZoneOption);
					}, this);
					thisView.updateFilterValues();
					thisView.getCollection();
				}
			});
		},

		updateFilterValues: function()
		{
			this.name = $('input[name="tnidName"]').val();
			this.range = $('select[name="date"]').val();
			this.timezone = $('select[name="timezone"]').val();
			if($('input[name="xcludeShipmate"]').attr('checked')){
				this.excludeSM = true;
			}
			else {
				this.excludeSM = false;
			}
		}

	});

	return usageDashboardView;
});
