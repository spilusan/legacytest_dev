define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'libs/jquery.tools.min',
	'libs/jquery.tools.overlay.modified',
	'../collections/blockedList',
	'../views/blockedRowView',
	'text!templates/trade/block-buyer/tpl/noBlocked.html'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	Tools,
	Modal,
	blockedCollection,
	blockedRowView,
	noBlockedTpl
){
	var blockedListView = Backbone.View.extend({
		
		el: $('table tbody'),

		template: Handlebars.compile(noBlockedTpl),

		hash: require('trade/block-buyer/hash'),
		tnid: require('trade/block-buyer/tnid'),

		initialize: function () {
			_.bindAll(this, 'render', 'renderItems', 'renderItem');
			this.collection = new blockedCollection();
		   	this.getData();
		},

		getData: function() {
			var thisView = this;
			this.collection.fetch({
				complete: function(){
					thisView.renderItems();
				}
			});
		},

		renderItems: function(){
			$(this.el).html('');
			if(this.collection.models.length === 0){
				html = this.template();
				$('table').html(html);
			}
			else {
				_.each(this.collection.models, function(item) {
			        this.renderItem(item);
			    }, this);
		   	}
		   	if($('#content table tbody tr').length === 0) {
		    	window.location.reload(true);
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
		},

		renderItem: function(item) {
		    var theBlockedRowView = new blockedRowView({
		        model: item
		    });

		    var tbody = document.getElementsByTagName("tbody")[0];
		    tbody.appendChild(theBlockedRowView.render().el);
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

	return new blockedListView;
});
