define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'handlebars',
 	'../hbh/supplierList',
	'libs/jquery.tools.overlay.modified',
 	'libs/jquery.uniform',
 	'../collections/suppliers',
 	'../views/supplierView',
 	'text!templates/rfq/tpl/contact.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	supplierListHbh,
	Modal,
	Uniform,
	supplierList,
	supplierView,
	contactTpl
){
	var supplierListView = Backbone.View.extend({
		
		rfqData: require("rfq/rfqData"),
		solo: require("rfq/solo"),

		contactTpl: Handlebars.compile(contactTpl),

		initialize: function() {
			_.bindAll(this, 'render', 'renderSupplier');

			var suppliers = this.rfqData.selectedSupplier;

		    this.collection = new supplierList(suppliers);
		},

		render: function() {
			_.each(this.collection.models, function(supplier) {
		        this.renderSupplier(supplier);
		    }, this);
			
		    $('input[type="checkbox"]').uniform();
			
			this.solo = 0;	    
		    
		    /*if(this.solo == 1) {
		    	if(this.rfqData.contact && this.rfqData.user){
		    		var html = this.contactTpl(this.rfqData.selectedSupplier[0]);
			    	$('#modalContact .modalBody').html(html);
			    	this.openDialog();
		    	}
		    	$('.checker').hide();
		    	$('.suppliers ul li').css('width', '800');
		    	$('.suppliers ul li label').css('width', 'auto');
		    	$('.suppliers ul li label').css('float', 'left');

		    	$('.altContact').bind('click', {context: this}, function(e){
			    	e.preventDefault();
			    	if(!e.data.context.rfqData.user){
			    		window.location.href="/user/register-login?redirectUrl=/enquiry/index/clearBasket/1/tnid/"+e.data.context.collection.models[0].attributes.tnid+"/ProfileRecId/"+e.data.context.rfqData.ProfileRecId+"/contact/1";
			    	}
			    	else {
			    		var html = e.data.context.contactTpl(e.data.context.rfqData.selectedSupplier[0]);
				    	$('#modalContact .modalBody').html(html);
				    	e.data.context.openDialog();
			    	}
			    });
		    }*/
		    
		},

		renderSupplier: function(supplier) {
		    var theSupplierView = new supplierView({
		    	 model: supplier
		    });
		    theSupplierView.solo = this.solo;;
		    $('.suppliers ul').append(theSupplierView.render().el);
		},

		openDialog: function() { 
			if(this.rfqData.ProfileRecId) {
	    		$.get('/supplier/log-contact-viewed/format/json/getprofilerecid/'+this.rfqData.ProfileRecId, function(data){});
	    		this.rfqData.ProfileRecId = false;
			}

	    	$("#modalContact").overlay({
		        mask: 'black',
		        left: 'center',
		 		fixed: false,

		        onBeforeLoad: function() {
		            var windowWidth = $(window).width();
		        	var modalWidth = $('#modalContact').width();
		        	var posLeft = windowWidth/2 - modalWidth/2;

		        	$('#modalContact').css('left', posLeft);
		        },

		        onLoad: function() {
		        	$(window).resize(function(){
		        		var windowWidth = $(window).width();
		        		var modalWidth = $('#modalContact').width();
		        		var posLeft = windowWidth/2 - modalWidth/2;

		        		$('#modalContact').css('left', posLeft);
		        	});
		        }
			});

			$('#modalContact').overlay().load();
	    }

	});

	return new supplierListView;
});