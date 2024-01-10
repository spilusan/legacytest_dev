define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'libs/jquery.tools.min',
	'libs/jquery.tools.overlay.modified',
	'backbone/shared/hbh/general',
	'../hbh/rfqInbox',
	'../collections/rfqList',
	'../views/rfqRowView',
	'../views/rfqSummaryView',
	'backbone/shared/pagination/views/paginationView',
	'text!templates/trade/rfq-inbox/tpl/emptyInbox.html'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	Tools,
	Modal,
	generalHbh,
	rfqInboxHbh,
	rfqCollection,
	rfqRowView,
	rfqSummaryView,
	paginationView,
	emptyDialogTpl
){
	var rfqListView = Backbone.View.extend({
		
		el: $('table.rfqList tbody'),

		events: {
			'click a.refresh' : 'refresh'
		},

		emptyDialogTpl: Handlebars.compile(emptyDialogTpl),

		hash: require('trade/rfq-inbox/hash'),
		tnid: require('trade/rfq-inbox/tnid'),
		paginationLimit: 20,
		page: require('trade/rfq-inbox/page'),
		rid: require('trade/rfq-inbox/enquiryId'),

		initialize: function () {
			_.bindAll(this, 'render', 'renderItems', 'renderItem', 'refresh');
			this.collection = new rfqCollection();

		    this.getData();
		    
		    var that = this;

		    setInterval(function() {
		    	if(that.tableUpdating) return;
			    that.refresh();
			}, 300000);

			$('.pagesRfqList h2 .refresh').unbind().bind('click', {context: this}, function(e){
				e.preventDefault();
				e.data.context.refresh();
			});
		},

		getData: function() {
			var thisView = this;
			this.collection.fetch({
				data: $.param({ 
					id: this.tnid, 
					start: this.page, 
					total: this.paginationLimit,
					hash: this.hash
				}),
				complete: function() {
					thisView.renderItems();
				}
			});
		},

		refresh: function() {
			this.rid = "";
			this.getData();
		},

		renderItems: function(){
			this.tableUpdating = true;
			$(this.el).html('');
			_.each(this.collection.models, function(item) {
		        this.renderItem(item);
		    }, this);

			if (!window.location.origin) {
				window.location.origin = window.location.protocol+"//"+window.location.host;
			}

		    var path = $(location).attr('href').replace(window.location.origin,'');

		    if(!this.collection.models[0] && path !== "/trade/rfq"){
		    	window.location.href="/trade/rfq";
		    }
		    else if(!this.collection.models[0]) {
		    	var html = this.emptyDialogTpl();
		    	$('#modal .modalBody').html(html);
		    	this.openDialog();
		    	rfqSummaryView.render();
		    }
		    else {
		    	//pass params to pagination
				paginationView.parent = this;
				paginationView.paginationLimit = this.paginationLimit;
				paginationView.page = this.page;

		    	//render pagination view
		    	paginationView.render(this.collection.models[0].attributes.totalFound);

		    	rfqSummaryView.render();
		    	this.tableUpdating = false;
		    }
		},

		renderItem: function(item) {
		    var theRfqRowView = new rfqRowView({
		        model: item
		    });

		    var tbody = document.getElementsByTagName('tbody')[0];
		    tbody.appendChild(theRfqRowView.render(this.rid).el);
		    
		    this.rid="";
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
	    }
	});

	return new rfqListView;
});
