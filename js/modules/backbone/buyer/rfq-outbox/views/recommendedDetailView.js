define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/shared/activity/log',
	'../collections/rfqDetail',
	'../views/recommendedRowView',
	'text!templates/buyer/rfq-outbox/tpl/recommendedSection.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	logActivity,
	recommendedDetail,
	recommendedRow,
	recommendedDetailTpl
){
	var recommendedDetailView = Backbone.View.extend({
		tagName: 'div',
		className: 'details',
		template: Handlebars.compile(recommendedDetailTpl),
		pageSize: 5,
		pageNo: 1,
		custom: false,
		terms: null,

		events: {
			'click input[name="more"]'		: 'getMore',
			'click input[name="sendToAll"]' : 'sendToAll'
		},

		initialize: function(){
			_.bindAll(this, 'render');
			this.collection = new recommendedDetail();
			this.collection.url = '/buyer/search/results/';
		},

		getData: function(id){
			this.parent.parent.parent.smallSpinner = 1;
			$('#waiting').hide();
			$('.section.recommended .smallSpin').css('display', 'inline-block');
			
			if(id)
				this.rfqRefNo = id;

			if(this.custom) {
				thisView = this;
				this.terms = {};
				this.terms.categories = [];
				thisView.terms.brands = [];
				thisView.terms.locations = [];
				thisView.terms.tags = [];

				_.each(this.parent.matchDetail.catCollection.models, function(item){
					thisView.terms.categories.push({
						id: item.attributes.id,
						level: item.attributes.level
					});
				});

				_.each(this.parent.matchDetail.brandCollection.models, function(item){
					thisView.terms.brands.push({
						id: item.attributes.id,
						level: item.attributes.level
					});
				});

				_.each(this.parent.matchDetail.locationCollection.models, function(item){
					thisView.terms.locations.push({
						id: item.attributes.id,
						level: item.attributes.level
					});
				});

				_.each(this.parent.matchDetail.tagCollection.models, function(item){
					thisView.terms.tags.push({
						tag: item.attributes.output_name,
						level: item.attributes.level
					});
				});

				terms = JSON.stringify(this.terms);

			}
			else {
				terms = "";
			}

			var thisView = this;
			this.collection.fetch({
				type: 'POST',
				remove: false,
				add: true,
				data: $.param({ 
					rfqRefNo: this.rfqRefNo,
					pageSize: this.pageSize,
					pageNo: this.pageNo,
					terms: terms
				}),
				complete: function(){
					thisView.render();
				}
			});
			
			return this;
		},

	    render: function() {
	    	this.parent.parent.parent.smallSpinner = 0;
	    	$('.section.recommended .smallSpin').hide();
	    	this.elem = '.section.recommended .details table tbody#rec' + this.rfqRefNo;
	    	var data = new Object();
	    	data.id = this.rfqRefNo;
			var html = this.template(data);

			$(this.el).html(html);

			this.renderItems();

			if (this.collection.length === 0) {
				$('input[name="more"]').hide();
				$('input[name="sendToAll"]').hide();
				$(this.elem).html('<tr><td colspan="5" style="text-align: center;font-weight: bold;">No recommended Suppliers found.</td></tr>');
			} else if (this.collection.length >= this.collection.models[0].attributes.total) {
	    		$('input[name="more"]').remove();
	    		this.delegateEvents();
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
	    },

	    reRender: function() {
	    	this.custom = true;
	    	this.pageNo = 1;
	    	this.collection.reset();
	    	$(this.elem).html('');
	    	this.getData();
	    	$('body').animate({
		        scrollTop: $('.section.sendTo').offset().top
		    }, 1000);
		    //fix height of body container due to absolute pos of content container
	    	var height = 0;
	    	if($('#content').height() < $('#sidebar').height()){
	    		height = $('#sidebar').height();
	    	}
	    	else {
	    		height = $('#content').height() + 25;
	    	}

	    	$('#body').height(height);
	    },

	    renderItems: function() {
	    	_.each(this.collection.models, function(item) {
		        this.renderItem(item);
		    }, this);
	    },

		renderItem: function(item) {
		    var recommendedListRow = new recommendedRow({
		        model: item
		    });

		    recommendedListRow.parent = this;

		    $(this.elem).append(recommendedListRow.render().el);
		},

	    close: function() {
	    	this.remove();
	    	this.pageNo = 1;
	    	this.collection.reset();
	    	//fix height of body container due to absolute pos of content container
	    	var height = 0;
	    	if($('#content').height() < $('#sidebar').height()){
	    		height = $('#sidebar').height();
	    	}
	    	else {
	    		height = $('#content').height() + 25;
	    	}

	    	$('#body').height(height);
	    },

	    getMore: function() {
	    	this.pageNo ++;
	    	this.getData();
	    },

	    sendToAll: function() {
	    	var sendData = "rfqRefNo=" + this.collection.models[0].attributes.rfq_id;
	    	logActivity.logActivity('rfq-sent-from-buy-tab', 'All ('+this.collection.models[0].attributes.rfq_id+')');
	    	_.each(this.collection.models, function(item){
	    		if(!item.attributes.wasSent){
	    			sendData += "&supplierTnid[]=" + item.attributes.tnid;
	    			item.attributes.wasSent = true;
	    		}
	    	}, this);

	    	var thisView = this;

	    	var jqxhr = $.ajax({
   				url: "/buyer/search/rfq-send/",
   				data: sendData
   			})
	  		.fail(function() {
	    		alert( "error" );
	  		})
	  		.always(function(data) {
	  			thisView.pageNo = 1;
	  			thisView.collection.reset();
	    		thisView.getData();
	    		thisView.parent.sendToDetail.getData();
	  		});
	    }
	});

	return recommendedDetailView;
});