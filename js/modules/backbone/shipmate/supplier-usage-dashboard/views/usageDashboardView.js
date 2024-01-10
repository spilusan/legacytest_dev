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
	'text!templates/shipmate/supplier-usage-dashboard/tpl/usageDashboardHead.html',
	'text!templates/shipmate/supplier-usage-dashboard/tpl/usageDashboardHeadFake.html'
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
			'click input[name="export"]' : 'exportReport',
			'keydown input.number'		 : 'onNumberInputKeyDown'
		},

		initialize: function(){
			
			this.collection = new collection();
			this.collection.url = "/reports/data/supplier-appusage";

			this.timezoneCollection = new collection();
			this.timezoneCollection.url= "/reports/data/timezones";

			this.countriesCollection = new collection();
			this.countriesCollection.url  = "/reports/data/countries";

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
					country: this.country,
					level: this.level,
					gmvFrom: this.gmvFrom,
					gmvTo: this.gmvTo,
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
			if (this.collection.models.length === 499) {
				$('#more500').show();
			} else {
				$('#more500').hide();
			}

			_.each(this.collection.models, function(item){
				this.renderItem(item);
			}, this);

			
			this.tableSticky();

			var wWidth = $(window).width() -250;
			if ($('table').width() > wWidth) {
				wWidth = $('table').width();
			}
			
			$('#header').width(wWidth + 245);
			$('.divider').width(wWidth + 245);
			
			$('body').width(wWidth + 255);
			$('#body').width(wWidth + 240);
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

        	var paramAddStr = "name="+encodeURIComponent(this.name);
			paramAddStr += "&range="+this.range;
			paramAddStr += "&timezone="+this.timezone;
			paramAddStr += "&excludeSM="+this.excludeSM;
			paramAddStr += "&country="+this.country;
			paramAddStr += "&level="+this.level;
			paramAddStr += "&gmvFrom="+this.gmvFrom;
			paramAddStr += "&gmvTo="+this.gmvTo;
			paramAddStr += "&sortBy="+this.sortBy;
			paramAddStr += "&sortOrder="+this.sortOrder;

        	location.href = "/reports/export/supplier-appusage?"+paramAddStr;
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

		updateFilterValues: function() {
			this.name = $('input[name="tnidName"]').val();
			this.range = $('select[name="date"]').val();
			this.timezone = $('select[name="timezone"]').val();
			this.country = $('select[name="country"]').val();
			this.level = $('select[name="level"]').val();
			this.gmvFrom = $('input[name="gmvFrom"]').val();
			this.gmvTo = $('input[name="gmvTo"]').val();

			if($('input[name="xcludeShipmate"]').attr('checked')){
				this.excludeSM = true;
			}
			else {
				this.excludeSM = false;
			}
		},

		tableSticky: function() {
			/* This one does not stick the table head anymore, as a JQuery plugin is written for this, but displays warning message if there is no data */
			var table = $('#usageData');
			$(table).show();
			var tds = $(table).find('tbody').find('tr').first().find('td');
			if (tds.length === 0) {
				$(table).hide();
				$('#warning').show();
			} else {
				$('#warning').hide();
			}	
		}, 

		renderFilterDropdowns: function() {
			
			var thisView = this;
			
			var timeZoneSelect = $('select[name="timezone"]');
			var countriesSelect = $('select[name="country"]');

			timeZoneSelect.empty();
			countriesSelect.empty();
			var timeZoneOption = null;
			var countriesOption = null;

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
					
					/* Automacically render report on first load disabled
					 * thisView.getCollection();
					 */
				}
			});

			thisView.countriesCollection.fetch({
				type: "GET",
				complete: function(){
					//Rener countries
					countriesOption = $('<option>');
					countriesOption.val('');
					countriesOption.html('All');
					countriesSelect.append(countriesOption);
					_.each(thisView.countriesCollection.models, function(item){
						countriesOption = $('<option>');
						countriesOption.val(item.attributes.CntCountryCode);
						countriesOption.html(item.attributes.CntName);
						countriesSelect.append(countriesOption);
					}, this);
				}
			});
		},
		
		onNumberInputKeyDown: function(e) {
			return ($(e.currentTarget).val().length < 12 && ((e.keyCode > 47 && e.keyCode < 58) || (e.keyCode > 36 && e.keyCode < 41) || (e.keyCode === 13 || e.keyCode === 8 || e.keyCode === 46))); 
		}
	});

	return usageDashboardView;
});
