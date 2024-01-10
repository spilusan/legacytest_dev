define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'libs/jquery.uniform',
	'../views/filterItemView',
	'../collections/filters',
	'text!templates/buyer/rfq-outbox/tpl/filters.html'
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
			'change select[name="branch"]'	  : 'onBranchChange' //'fixAmp'
		},

		filtersTemplate: Handlebars.compile(filtersTpl),

		buyer: false,

		initialize: function() {
			this.collection = new filtersCollection();

			this.collection.add([
				{
					filterName: "All RFQs",
					category: "",
					keywords: "",
					vessel: "",
					period: 360,
					matchStat: "",
					rfqStat: "",
					selected: true
				},
				{
					filterName: "Open RFQs",
					category: "",
					keywords: "",
					vessel: "",
					period: 360,
					matchStat: "",
					rfqStat: "OPN"
				}/*,
				{
					filterName: "RFQs with savings",
					count: 32,
					category: "",
					keywords: "",
					vessel: "",
					period: 360,
					matchStat: "savings",
					rfqStat: ""
				}*/
			]);

			this.vesselCollection = new filtersCollection();
			this.vesselCollection.url = '/data/source/vessels';
			this.branchCollection = new filtersCollection();
			this.branchCollection.url = '/data/source/buyer-branches-buy';
		},

		getData: function() {
			var thisView = this;

            // changed by Yuriy Akopov on 2014-10-31, DE5291
			this.branchCollection.fetch({
                data: {
                    context: 'rfq'
                },
				complete: function() {
                    // render view for the first time to init buyer branch dropdown and get access to its values
                    thisView.render();
                    buyerBranchId = $('#branchSelect').val();
                    //console.log('Initialising vessels list for branch ' + buyerBranchId);

					thisView.vesselCollection.fetch({
                        data: {
                            context: 'rfq-outbox',
                            buyerBranchId: $('#branchSelect').val()
                        },

						complete: function(){
                            // render for the second time to initialise vessels list
							thisView.render();
						}
					});
				}
			});
		},

		render: function() {
			var data = {};
			data.vessel = this.vesselCollection;
			data.branch = this.branchCollection;

			_.each(data.branch.models, function(item){
				if(this.buyer == item.attributes.id || (!this.buyer && item.attributes['default'])){
					item.attributes.selected = true;
				}
			}, this);

			$('div#sidebar div.filterSection').html('');
			var html = this.filtersTemplate(data);

			$('div#sidebar div.filterSection').html(html);

			this.renderItems();
			$('div#sidebar div.filterSection').find('form select').uniform();

            this.parent.period = $('#rfqDateSelect').val();
            this.parent.buyer  = $('#branchSelect').val();
            this.parent.matchStat = $('#matchStatus').val();

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
			if($('input[name="keywords"]').val() == "reference, subject, description"){
				$('input[name="keywords"]').removeClass('blur').val('');
			}
		},

		onKeywordsBlur: function(){
			if($('input[name="keywords"]').val() === "" || $('input[name="keywords"]').val() === null || !$('input[name="keywords"]').val()){
				$('input[name="keywords"]').addClass('blur').val('reference, subject, description');
			}
		},

        // added by Yuriy Akopov on 2014-10-31, DE5291 to re-read vessels list when a new §h is selected
        onBranchChange: function() {
            this.fixAmp();

            thisView = this;
            buyerBranchId = $('#branchSelect').val();

            this.parent.smallSpinner = 1;

            // re-read vessels list for the new
            this.vesselCollection.fetch({
                data: {
                    context: 'rfq-outbox',
                    buyerBranchId: buyerBranchId
                },
                complete: function() {
                    // update view with the new vessels collection
                    $('select[name="vessel"]').html('');

                    var html = '<option value="">All vessels</option>';
                    _.each(thisView.vesselCollection.models, function(item){
                        html += '<option value="';
                        html += item.attributes.name;
                        html += '">' + item.attributes.name + '</option>';
                    }, thisView);

                    //console.log('Switched to buyer branch '+ buyerBranchId);
                    $('select[name="vessel"]').html(html);
                }
            });

        },

		onShowClicked: function(e) {
			e.preventDefault();
			var keywords;

			if($('input[name="keywords"]').val() == "reference, subject, description")
			{
				keywords = "";
			}
			else {
				keywords = $('input[name="keywords"]').val();	
			}
			var period = $('select[name="rfqDate"]').val(),
				vessel = $('select[name="vessel"]').val(),
				category = $('select[name="category"]').val(),
				matchStatus = $('select[name="matchStat"]').val(),
				rfqStatus = $('select[name="rfqStat"]').val();
				branch = $('select[name="branch"]').val();

			this.parent.keywords = keywords;
			this.parent.period = period;
			this.parent.vessel = vessel;
			this.parent.matchStat = matchStatus;
			this.parent.rfqStat = rfqStatus;
			this.parent.category = category;
			this.parent.buyer = branch;

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
			var keywords;

			if(e.which == 1 || e.which == 13 && $('input[name="filterName"]').is(":focus") === true){
				e.preventDefault();
				if($('input[name="keywords"]').val() == "reference, subject, description")
				{
					keywords = "";
				}
				else {
					keywords = $('input[name="keywords"]').val();	
				}

				var period = $('select[name="rfqDate"]').val(),
					vessel = $('select[name="vessel"]').val(),
					category = $('select[name="category"]').val(),
					matchStatus = $('select[name="matchStat"]').val(),
					rfqStatus = $('select[name="rfqStat"]').val(),
					branch = $('select[name="branch"]').val(),
					filterName = $('input[name="filterName"]').val();

				if(filterName === ""){
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