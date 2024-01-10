define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'libs/jquery.uniform',
	'../collections/collection',
	'text!templates/reports/matchSupplierReport/tpl/drillDown.html'

], function(
	$, 
	_, 
	Backbone, 
	Hb,
	Uniform,
	Collection,
	drillTpl

){
	var matchSupplierDrillView = Backbone.View.extend({
		
		events: {

		},

		drillTemplate: Handlebars.compile(drillTpl),

		initialize: function(){
			this.Collection = new Collection();
			this.Collection.url = '/buyer/usage/rfq-detail-list';

			

			$('body').delegate('.ddComparableIcon', 'click', function(e){
				e.preventDefault();
				$('.uncompMessagePopup').fadeIn(400);
			});
			
			$('body').delegate('.ddCoCloseBtn', 'click', function(e){
				e.preventDefault();
				$('.uncompMessagePopup').fadeOut(400);
			});

		},

		getData: function(rfqId){

			var thisView = this;
			this.Collection.reset();
			this.Collection.fetch({
				data: {
					buyerId: thisView.parent.parent.getFilters().selectedBranch,
					rfqId: rfqId,
				},
				complete: function(){
					thisView.render();
				},
				error: function()
				{
					$('#waiting').hide();
					$('#result').empty();
					var errorMsg = $('<span>');
					errorMsg.addClass('error');
					errorMsg.html("We're sorry, there is a problem with our backend server. Please try again later.");
					$('#result').append(errorMsg);
					thisView.parent.fixHeight();
				}
			});

			//quoteList.getData(); //move to call after ajax

		},

		render: function() {
			var thisView = this;
			$('#titleText').html('Supplier Recommendations - RFQ Drilldown');
			this.addBackButton();
			var cheapestValue = this.Collection.models[0].attributes.SUMMARY.CHEAPEST_BUYER_QOT_VALUE;
			for (var key in this.Collection.models ) {
				var totalPrice = this.Collection.models[key].attributes.QOT_TOTAL_PRICE_IN_USD;
				var saving = cheapestValue - totalPrice;
				this.Collection.models[key].attributes.A_SAVING = (saving > 0 && totalPrice > 0) ? saving : null;
			}

			var data = {
				spbBranchCode: thisView.parent.getSelectedSpbCode(),
				data: this.Collection.models,
				summary: this.Collection.models[0].attributes.SUMMARY,
			};


			var html = this.drillTemplate(data);
			$('#innerContent').html(html);
			this.parent.fixHeight();

		},

		addBackButton: function()
		{
			var thisView = this;
			var titleButton = $('<a>');
			titleButton.addClass('backBtn');
			titleButton.html('Back');
			titleButton.click(function(){
				thisView.onBackClick($(this));
			});
			
			$('#titleText').append(titleButton);

		},

		onBackClick: function()
		{
			$('.savingHelp').hide();
			this.parent.renderLoaded();
		}


	});

	return new matchSupplierDrillView();
});