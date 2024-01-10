define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/shared/hbh/general',
	'libs/jquery.tools.overlay.modified',
	'text!templates/shipmate/prime-suppliers/tpl/automatchRow.html',
	'text!templates/shipmate/prime-suppliers/tpl/automatchSelectedRow.html',
	'text!templates/shipmate/prime-suppliers/tpl/linkedSuppliersRow.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	HbhGen,
	Modal,
	automatchRowTpl,
	automatchSelectedRowTpl,
	linkedSuppliersRowTpl
){
	var automatchRowView = Backbone.View.extend({
		tagName: 'tr',

		template: Handlebars.compile(automatchRowTpl),
		templateSelected: Handlebars.compile(automatchSelectedRowTpl),
		templateSuppliers: Handlebars.compile(linkedSuppliersRowTpl),

		hostname: require('shipmate/targetSegments/hostname'),
		url: "",

		events: {
			'click img.move.add'   : 'addKeywordset',
			'click img.move.desel' : 'removeKeywordset',
			'click img.toggleSet'  : 'toggleSet',
			'click img.download'   : 'downloadSet',
			'click img.upload'	   : 'uploadSet',
			'click img.linked'     : 'showSuppliersLinked'
		},
		
		initialize: function(){
			var protocol = (window.location.protocol === "https:") ? 'https://' : 'http://';
			_.bindAll(this, 'render');
			
			this.baseUrl = this.hostname.split(".");
			this.url = this.baseUrl[1];

			if(this.url == "myshipserv"){
				this.url = protocol + "match" + "." + this.baseUrl[1] + "." + this.baseUrl[2] + "/";
			}
			// @todo: replace with the URL supplier to Require.js from PHP backend in the view
			else if (/^(ukdev\d)$/.test(this.baseUrl[0])) {
				this.url = protocol + "ukdev" + "." + this.baseUrl[1] + "." + this.baseUrl[2] + "/match-app/";
			} else {
				this.url = protocol + this.baseUrl[0] + "." + this.baseUrl[1] + "." + this.baseUrl[2] + "/match-app/";
			}
		},

	    render: function(type) {
	    	this.type = type;

	    	this.model.view = this;

			var data = this.model.attributes;

			if(type == "available"){
				var html = this.template(data);
			}
			else {
				var html = this.templateSelected(data);
			}

			$(this.el).html(html);

	        return this;
	    },

	    addKeywordset: function(e){
	    	e.preventDefault();

	    	var thisView = this;
			var postUrl = this.url + "supplier/" + this.parent.parent.model.attributes.id + "/keyword-set/" + this.model.attributes.id;
			if (confirm('Are you sure you would like to link this supplier to this keyword set.')) {
				$.ajax({
					type: "POST",
					url: postUrl,
					data: JSON.stringify({
						"enabled" : thisView.model.attributes.enabled,
						"threshold" : thisView.model.attributes.threshold
					}),
					success: function(){
						thisView.parent.selectedCollection.add(thisView.model);
						thisView.parent.availableCollection.remove(thisView.model);
						thisView.parent.search();

						var postUrl = thisView.url + "supplier/" + thisView.parent.parent.model.attributes.id;

						$.ajax({
							type: "PUT",
							url: postUrl,
							data: JSON.stringify({
								"id" : thisView.parent.parent.model.attributes.id,
								"autoMatch" : {
									"enabled" : true
								}
							}),
							success: function(){
								thisView.parent.selectedCollection.add(thisView.model);
								thisView.parent.availableCollection.remove(thisView.model);
								thisView.parent.search();
							}
						});
					}
				});
			}
	    },

	    removeKeywordset: function(e){
	    	e.preventDefault();

	    	var thisView = this;
			var postUrl = this.url + "supplier/" + this.parent.parent.model.attributes.id + "/keyword-set/" + this.model.attributes.id;
			if (confirm('Are you sure you would like to unlink this supplier from this keyword set.')) { 
				$.ajax({
					type: "DELETE",
					url: postUrl,
					success: function(){
						thisView.parent.availableCollection.add(thisView.model);
						thisView.parent.selectedCollection.remove(thisView.model);
						thisView.parent.search();
					}
				});
			}
			else {
				return;
			}
	    },

	    toggleSet: function(e){
	    	var thisView = this;
			var postUrl = this.url + "supplier/keyword-set/" + this.model.attributes.id;

			if($(e.target).hasClass('enabled')){
				if (confirm('You will disable this keyword set globally.')) { 
					var enabled = false;
				}
				else {
					return;
				}				
			}
			else {
				if (confirm('You will enable this keyword set globally.')) {
					var enabled = true;
				}
				else {
					return;
				}
			}

			this.model.set({
				enabled: enabled
			});

			this.model.save({}, {url: postUrl});

			this.render(this.type);
	    },

	    downloadSet: function(){
	    	var thisView = this;
			var getUrl = this.url + "supplier/keyword-set/" + this.model.attributes.id + '/csv/';
			var win = window.open(getUrl, "_blank");
	    },

	    uploadSet: function(){
	    	$('label[for="tnids"]').hide();
			$('input[name="tnids"]').hide();
			$('label[for="type"]').hide();
			$('select[name="type"]').hide();
			$('#modal .modalBody h1').html('Upload CSV for keyword set:');
	    	$('form[name="newSet"] input[name="name"]').val(this.model.attributes.name);
	    	$('form[name="newSet"] input[name="name"]').attr('disabled', 'disabled');
	    	$('input[name="upload"]').removeClass('create');
	    	$('input[name="upload"]').addClass('update');
	    	this.parent.setId = this.model.attributes.id;
	    	this.parent.openDialog();
	    },

	    showSuppliersLinked: function(){
	    	if(this.model.attributes.suppliers.list.length > 0){
	    		$('#modalX .modalBody .modalContent table tbody').html();
	    		var html = this.templateSuppliers(this.model.attributes.suppliers);
	    		$('#modalX .modalBody .modalContent table tbody').html(html);
	    		this.openDialog();
	    	}
	    	else {
	    		alert('This keyword set is not linked to any supplier');
	    	}
	    },

	    openDialog: function() {
	    	$("#modalX").overlay({
		        mask: 'black',
		        left: 'center',
		        fixed: 'true',
		 
		        onBeforeLoad: function() {
		            var windowWidth = $(window).width();
		        	var modalWidth = $('#modalX').width();
		        	var posLeft = windowWidth/2 - modalWidth/2;

		        	$('#modalX').css('left', posLeft);
		        },

		        onLoad: function() {
		        	$(window).resize(function(){
		        		var windowWidth = $(window).width();
		        		var modalWidth = $('#modalX').width();
		        		var posLeft = windowWidth/2 - modalWidth/2;

		        		$('#modalX').css('left', posLeft);
		        	});
		        }
			});

			$('#modalX').overlay().load();
	    }
	});

	return automatchRowView;
});