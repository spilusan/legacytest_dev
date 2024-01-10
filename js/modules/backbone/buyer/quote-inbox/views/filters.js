define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'libs/jquery.uniform',
	'../views/filterItemView',
	'../collections/filters',
	'text!templates/buyer/quote-inbox/tpl/filters.html'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	Uniform,
	filterItemView,
	filtersCollection,
	filtersTpl
){
	var filtersView = Backbone.View.extend({
		el: $('div#sidebar div.filterSection'),

		events: {
			'focus input[name="keywords"]' 	  : 'onKeywordsFocus',
			'blur input[name="keywords"]' 	  : 'onKeywordsBlur',
			'click input[name="show"]'	   	  : 'onShowClicked',
			'click input[name="save"]'		  : 'onSaveClicked',
			'keydown'		  				  : 'saveFilter',
			'click input[name="confirmSave"]' : 'saveFilter',
			'change select[name="branch"]'	  : 'fixAmp'
		},

		filtersTemplate: Handlebars.compile(filtersTpl),

		keywords: null,
		period: 360,
		vessel: null,
		qotStatus: null,
		buyer: false,

		initialize: function() {
			this.collection = new filtersCollection();

			this.collection.add([
				{
					filterName: "All Quotes",
					type: "",
					selected: true
				},
				{
					filterName: "Buyer selected suppliers",
					type: "buyer"
				},
				{
					filterName: "Match selected suppliers",
					type: "match"
				}
			]);

			this.vesselCollection = new filtersCollection();
			this.vesselCollection.url = '/data/source/vessels';
			this.branchCollection = new filtersCollection();
			this.branchCollection.url = '/data/source/buyer-branches';
		},

		getData: function() {
			var thisView = this;
			this.vesselCollection.fetch({
                data: {
                    context: 'quote'
                },
				complete: function() {
					thisView.branchCollection.fetch({
                        data: {
                            context: 'quote'
                        },
						complete: function(){
							thisView.render();
						}
					});
				}
			});
		},

		render: function() {		
			var data = new Object;
			data.vessel = this.vesselCollection;
			_.each(data.vessel.models, function(item) {
		        if(item.attributes.name === this.vessel){
		        	item.attributes.selected = true;
		        }
		    }, this);

			data.branch = this.branchCollection;

			_.each(data.branch.models, function(item) {
		        if(item.attributes.id == this.buyer){
		        	item.attributes.selected = true;
		        }
		    }, this);

            /*
			if(this.keywords !== null){
				data.keywords = this.keywords;
			}

		    if(this.qotStatus === "DEC"){
		    	data.decSel = true;
		    }
		    else if(this.qotStatus === "ACC"){
		    	data.accSel = true;
		    }
		    else if(this.qotStatus === "SUB"){
		    	data.subSel = true;
		    }

		    if(this.period == 30){
		    	data.monSel = true
		    }
		    else if (this.period == 90){
		    	data.quartSel = true;
		    }
		    else if(this.period == 180){
		    	data.halfSel = true;
		    }
		    else if(this.period == 360){
		    	data.yearSel = true;
		    }
		    */

			$('div#sidebar div.filterSection').html('');
			var html = this.filtersTemplate(data);

			$('div#sidebar div.filterSection').html(html);

			this.renderItems();
			$('div#sidebar div.filterSection').find('form select').uniform();

            this.parent.period = $('#qotDateSelect').val();
            this.parent.buyer  = $('#branchSelect').val();
            //this.parent.type = $('#qotType').val();

            this.parent.getData();

            if(this.parent.setFilter){
            	$('ul.filters li').removeClass('selected');
				$('div.setFilter').addClass('selected');
            }

		},

		renderItems: function() {
			$('div#sidebar div.filterSection').find('ul.filters').html('');
			_.each(this.collection.models, function(item) {
		        this.renderItem(item);
		    }, this);
		},

		renderItem: function(item) {
			var filterItem = new filterItemView({
				model: item
			});

			filterItem.parent = this;

			$('div#sidebar div.filterSection').find('ul.filters').append(filterItem.render().el);
		},

		fixAmp: function(){
			var text = $('#uniform-branchSelect span').text();
			text = text.replace(/&amp;/g, '&');
			$('#uniform-branchSelect span').text(text);
		},

		onKeywordsFocus: function(){
			if($('input[name="keywords"]').val() == "supplier name, reference, brand, port"){
				$('input[name="keywords"]').removeClass('blur').val('');
			}
		},

		onKeywordsBlur: function(){
			if($('input[name="keywords"]').val() == "" || $('input[name="keywords"]').val() == null || !$('input[name="keywords"]').val()){
				$('input[name="keywords"]').addClass('blur').val('supplier name, reference, brand, port');
			}
		},

		onShowClicked: function(e) {
			e.preventDefault();

			if($('input[name="keywords"]').val() == "supplier name, reference, brand, port")
			{
				this.keywords = "";
			}
			else {
				this.keywords = $('input[name="keywords"]').val();	
			}
			this.period = $('select[name="qotDate"]').val();
			this.vessel = $('select[name="vessel"]').val();
			this.qotStatus = $('select[name="qotStat"]').val();
			this.buyer = $('select[name="branch"]').val();

			this.parent.keywords = this.keywords;
			this.parent.period = this.period;
			this.parent.vessel = this.vessel;
			this.parent.qotStat = this.qotStatus;
			this.parent.buyer = this.buyer;

			this.parent.getData();

			this.parent.setFilter = true;

			$('ul.filters li').removeClass('selected');
			$('div.setFilter').addClass('selected');
		},

		onSaveClicked: function(e) {
			e.preventDefault();

			$('.nameSelect').show();
			$('.nameSelect input[name="filterName"]').focus();

			$(document).mouseup(function(e)
			{
			    var container = $('.nameSelect');

			    if (!container.is(e.target) && container.has(e.target).length === 0)
			    {
			        container.hide();
			    }
			});
		},

		saveFilter: function(e) {
			if(e.which == 1 || e.which == 13 && $('input[name="filterName"]').is(":focus") == true){
				e.preventDefault();
				if($('input[name="keywords"]').val() == "supplier name, reference, brand, port")
				{
					var keywords = "";
				}
				else {
					var keywords = $('input[name="keywords"]').val();	
				}

				var period = $('select[name="rfqDate"]').val(),
					vessel = $('select[name="vessel"]').val(),
					category = $('select[name="category"]').val(),
					matchStatus = $('select[name="matchStat"]').val(),
					rfqStatus = $('select[name="rfqStat"]').val(),
					branch = $('select[name="branch"]').val(),
					filterName = $('input[name="filterName"]').val();

				if(filterName == ""){
					alert('Please enter a name.');
				}
				else {
					this.collection.add([
						{
							filterName: filterName,
							count: 13,
							category: category,
							keywords: keywords,
							vessel: vessel,
							period: period,
							matchStat: matchStatus,
							rfqStat: rfqStatus,
							buyer: branch
						}
					]);

					//this.collection.save();

					this.render();
				}
			}
		},

		onManageClicked: function(e) {
			e.preventDefault();
		}
	});

	return filtersView;
});