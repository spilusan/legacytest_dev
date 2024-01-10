define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/shared/hbh/general',
	'libs/jquery.tools.overlay.modified',
	'../views/automatchView',
	'text!templates/shipmate/prime-suppliers/tpl/segmentSuppliersRow.html',
	'text!templates/shipmate/prime-suppliers/tpl/segmentSuppliersOverallRow.html',
	'text!templates/shipmate/prime-suppliers/tpl/segmentCandidateSuppliersRow.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	HbhGen,
	Modal,
	automatchView,
	segmentSuppliersRowTpl,
	segmentSuppliersOverallRowTpl,
	segmentCandidateSuppliersRowTpl
){
	var supplierRowView = Backbone.View.extend({
		tagName: 'tr',

		template: Handlebars.compile(segmentSuppliersRowTpl),
		templateOverall: Handlebars.compile(segmentSuppliersOverallRowTpl),
		candidateTemplate: Handlebars.compile(segmentCandidateSuppliersRowTpl),

		hostname: require('shipmate/targetSegments/hostname'),
		url: "",

		events: {
			'click a.moveUp'   : 'promoteSupplier',
			'click a.moveDown' : 'demoteSupplier',
			'click a.text'	   : 'showKeywords'
		},
		
		initialize: function(){
			var protocol = (window.location.protocol === "https:") ? 'https://' : 'http://';
			_.bindAll(this, 'render');

			this.automatchView = new automatchView();
			this.automatchView.parent = this;
			
			this.baseUrl = this.hostname.split(".");
			this.url = this.baseUrl[1];

			if(this.url == "myshipserv"){
				// @todo: this should be read from application.ini by backend (Yuriy)
				this.url = protocol + "match" + "." + this.baseUrl[1] + "." + this.baseUrl[2] + "/";
			}
			else if (/^(ukdev\d)$/.test(this.baseUrl[0])) {
				this.url = protocol + "ukdev" + "." + this.baseUrl[1] + "." + this.baseUrl[2] + "/match-app/";
			} else {
				this.url = protocol + this.baseUrl[0] + "." + this.baseUrl[1] + "." + this.baseUrl[2] + "/match-app/";
			}

			this.model.view = this;
		},

	    render: function(type, overall) {
			var data = this.model.attributes;

			/*
			if(data.gmv.total.gmv == 0){
				if(data.gmv.trend.gmv == 0) {
					data.gmv.trend.rate = 0;
				}
				else {
					data.gmv.trend.rate = 100;
				}
			}
			else {
				data.gmv.trend.rate = data.gmv.trend.gmv / data.gmv.total.gmv * 100 - 100;
				data.gmv.trend.rate = data.gmv.trend.rate.toFixed(0);
			}

			if(data.gmv.trend.rate > 0){
				data.gmv.trend.positive = 1;
			}
			else if(data.gmv.trend.rate < 0){
				data.gmv.trend.positive = 2;
			}
			else {
				data.gmv.trend.positive = 0;
			}
			*/

			if(type == "current"){
				if(overall == false){
					var html = this.template(data);
					$(this.el).html(html);
				}
				else {
					var html = this.templateOverall(data);
					$(this.el).html(html);
				}
			}
			else {
				var html = this.candidateTemplate(data);
				$(this.el).html(html);
			}

	        return this;
	    },

	    promoteSupplier: function(e){
	    	e.preventDefault();

	    	var thisView = this;
			var postUrl = this.url + "supplier/segment/" + this.parent.parent.model.attributes.id + "/member/" + this.model.attributes.id;
			
			$.post( postUrl, function( data ) {
				$.uniform.restore('#tabMatch');
				$.uniform.restore('#tabOverall');
				$.uniform.restore('#tabMatchOv');
				$.uniform.restore('#tabOverallOv');

				thisView.parent.getData();
				thisView.model.collection.remove(thisView.model);
			});
	    },

	    demoteSupplier: function(e){
	    	e.preventDefault();
	    	var thisView = this;
	    	var postUrl = this.url + "supplier/segment/" + this.parent.parent.model.attributes.id + "/member/" + this.model.attributes.id;

	    	var p = $.ajax({
			    url: postUrl,
			    type: 'DELETE'
			});
			
			p.done(function(data, textStatus, jqXHR) {
				if(jqXHR.status !== 204) {
			    	handleError(jqXHR.status);
			    	return;
			  	}

			  	$.uniform.restore('#tabMatch');
				$.uniform.restore('#tabOverall');
				$.uniform.restore('#tabMatchOv');
				$.uniform.restore('#tabOverallOv');
				
			  	thisView.parent.getCandidateCollection();
				// Normal processing here
			});
	    },

	    showKeywords: function(){
	    	this.automatchView.tnid = this.model.attributes.id;
	    	this.automatchView.supplierName = this.model.attributes.name;
	    	this.automatchView.getData();
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

	return supplierRowView;
});