define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'libs/jquery.tools.min',
	'libs/jquery.tools.overlay.modified',
	'backbone/shared/hbh/general',
	'text!templates/trade/block-buyer/tpl/blockedRow.html',
	'text!templates/trade/block-buyer/tpl/noBlocked.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	Tools,
	Modal,
	HbhGen,
	blockedRowTpl,
	noBlockedTpl
){
	var blockedRowView = Backbone.View.extend({
		tagName: 'tr',

		template: Handlebars.compile(blockedRowTpl),
		noBlockedTemplate: Handlebars.compile(noBlockedTpl),

		hash: require('trade/block-buyer/hash'),
		tnid: require('trade/block-buyer/tnid'),

		events: {
			'click input.unblock' : 'unblock'
		},
		
		initialize: function(){
			_.bindAll(this, 'render', 'unblock');
			this.model.view = this;
		},

	    render: function() {
			var data = this.model;
			var html = this.template(data);

			$(this.el).html(html);

	        return this;
	    },

	    unblock: function(e) {
	    	e.preventDefault();
	    	$.ajax({
	            url: '/enquiry/blocked-sender/tnid/'+this.tnid +'/uid/'+this.model.attributes.PBL_PSU_ID+'/a/d',
	            type: 'GET',
	            cache: false,
	            error: function(request, textStatus, errorThrown) {
	                response = eval('(' + request.responseText + ')');
	                if( response.error != "User must be logged in"){
	                    alert("ERROR " + request.status + ": " + response.error);
	                }
	            }
	        });
	    	this.remove();
	    	if($('table tbody tr').length === 0){
	    		html = this.noBlockedTemplate();
	    		$('table').html(html);
	    	}
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

	return blockedRowView;
});