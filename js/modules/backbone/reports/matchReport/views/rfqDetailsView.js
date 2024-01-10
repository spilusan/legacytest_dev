define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'libs/jquery.uniform',
	'../collections/rfqList',
	'../views/filters',
	'../views/detailsRowView',
	'text!templates/reports/matchReport/tpl/rfqDetails.html',
	'text!templates/reports/matchReport/tpl/rfqSummary.html'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	Uniform,
	rfqList,
	filtersView,
	detailsRowView,
	detailsHeaderTpl,
	summaryTpl
){
	var rfqDetailsView = Backbone.View.extend({
		
		el: $('table.rfqList'),

		detailsHeaderTemplate: Handlebars.compile(detailsHeaderTpl),
		summaryTemplate: Handlebars.compile(summaryTpl),

		initialize: function(){
			this.collection = new rfqList();
			this.collection.url = "/buyer/usage/rfq-detail-list";
		},

		getData: function(id){

			var thisView = this;
			this.collection.fetch({
				data: $.param({
					buyerId: this.branch,
					rfqId: this.rfqId
				}),
				complete: function() {
					thisView.render();
				}
			});
		},

		render: function() {
			$('.rfqList thead tr').removeClass('wide');
			var html = this.detailsHeaderTemplate();
			$(this.el).find('thead').html(html);
			$(this.el).find('tbody').html('');
			if(this.collection.models.length == 0){
				$('table.rfqList tbody').html('<tr><td colspan="11" class="leftBorder">No RFQs found.</td></tr>');
			}
			else {
				var data = this.collection.models[0].attributes.SUMMARY;
				var html = this.summaryTemplate(data);
				$('.summarySection').html(html);

				_.each(this.collection.models, function(item) {
			        this.renderItem(item);
			    }, this);
	    	}
	    	$('input[name="back"]').show();
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

	    	var thisView = this;
	    	$('input[name="back"]').unbind().bind('click', function(e){
	    		e.preventDefault();
	    		thisView.back();
	    	});
		},

		renderItem: function(item) {
		    var rfqDetailsRow = new detailsRowView({
		        model: item
		    });
		    rfqDetailsRow.parent = this;
		    $(this.el).find('tbody').append(rfqDetailsRow.render().el);
		},

		back: function(){
			$('input[name="back"]').hide();
			$('.summarySection').hide();
			$('.filterSection').show();
			$('.pagination').show();
			$('.rfqList thead tr').removeClass('wide');
			this.parent.parent.getData();
		}
	});

	return rfqDetailsView;
});