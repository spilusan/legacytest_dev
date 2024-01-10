define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/shared/hbh/general',
	'libs/jquery.tools.overlay.modified',
	'libs/jquery.autocomplete',
	'jqueryui/datepicker',
	'../views/suppliersView',
	'text!templates/shipmate/prime-suppliers/tpl/targetSegmentRow.html',
	'text!templates/shipmate/prime-suppliers/tpl/editSegmentModal.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	HbhGen,
	Modal,
	Autocomplete,
	datepicker,
	suppliersView,
	targetSegmentRowTpl,
	editSegmentTpl
){
	var targetSegmentRowView = Backbone.View.extend({
		tagName: 'tr',

		template: Handlebars.compile(targetSegmentRowTpl),
		editSegmentTemplate: Handlebars.compile(editSegmentTpl),
		categories: [],
		brands: [],
		xhrReqs: 0,
		hostname: require('shipmate/targetSegments/hostname'),
		url: "",

		events: {
			'click a.remove' : 'deleteSegment',
			'click a.editS'   : 'editSegment',
			'click a.open'	 : 'showSegment'
		},
		
		initialize: function(){
			var protocol = (window.location.protocol === "https:") ? 'https://' : 'http://';
			_.bindAll(this, 'render');
			this.model.view = this;
			this.suppliersView = new suppliersView();

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

	    render: function() {
			var data = this.model.attributes;
			var html = this.template(data);

			$(this.el).html(html);

	        return this;
	    },

	    deleteSegment: function(e){
	    	e.preventDefault();
	    	var segmentId = this.model.attributes.id;
	    	var conf = confirm('Are you sure you want to delete this segment?');
	    	if(conf == true){
	    		this.model.destroy();
		    	this.parent.search();
	    	}
	    },

	    editSegment: function(){
	    	this.parent.editSegment = true;
	    	this.parent.segmentId = this.model.attributes.id;
	    	thisView = this;

	    	$('input[name="startDate"]').datepicker({
	    		dateFormat: 'dd.mm.yy'
	    	});

	    	//transform date to correct format
	    	var date = this.model.attributes.date.start;
	    	var newDate = date.split(" ");
	    	newDate = newDate[0].split("-");
	    	date = newDate[2] + "." + newDate[1] + "." + newDate[0];

	    	$('input[name="segmentName"]').val(this.model.attributes.name);
	    	$('input[name="startDate"]').val(date);
	    	
	    	if(this.model.attributes.comments){
	    		$('textarea[name="comments"]').val(this.model.attributes.comments)
	    	}

	    	_.each(this.model.attributes.categories, function(item){
	    		this.parent.categories = [];
	    		//this.categories.push(item.id);

				var html = '<li><a class="removeCategoryItem" href="' + item.id + '"></a><span>' + item.name + '</span><div class="clear"></div></li>';

				$('.selectedItems.categs').append(html);
	    	}, this);

	    	_.each(this.model.attributes.brands, function(item){
	    		this.parent.brands = [];

				var html = '<li><a class="removeBrandItem" href="' + item.id + '"></a><span>' + item.name + '</span><div class="clear"></div></li>';

				$('.selectedItems.brands').append(html);
	    	}, this);

	    	_.each(this.model.attributes.locations, function(item){
	    		this.parent.locations = [];

				var html = '<li><a class="removeLocationItem" href="' + item.id + '"></a><span>' + item.name + '</span><div class="clear"></div></li>';

				$('.selectedItems.countries').append(html);
	    	}, this);

	    	var thisView = this,
				sUrl = this.url + "inbox/autocomplete-categories";
			
			$('input[name="catComplete"]').autocomplete({
                serviceUrl: sUrl,
                width:370,
                maxHeight: 160,
                zIndex: 9999,
                minChars: 2,
                dataType: 'json',
                appendTo: 'div.selectlist',
                triggerSelectOnValidInput: false,
                transformResult: function(response) {
                	thisView.lCount = response.totalCount;

			        return {
			            suggestions: $.map(response.items, function(dataItem) {
			                return { value: dataItem.name, data: dataItem.id, count: thisView.lCount };
			            })
			        };
			    },
                onStart: function(){
                	$('#waiting').hide();
                },
                onFinish: function(){
                },
                onSelect: function(response) {
                    var data = {};
                    data.id = response.data;
                    data.name = response.value;

                    thisView.parent.addCategory(data);
                }
            });

			var bUrl = this.url + "inbox/autocomplete-brands";
			
			$('input[name="brandComplete"]').autocomplete({
                serviceUrl: bUrl,
                width:370,
                maxHeight: 160,
                zIndex: 9999,
                minChars: 2,
                dataType: 'json',
                appendTo: 'div.selectlistB',
                triggerSelectOnValidInput: false,
               transformResult: function(response) {
                	thisView.bCount = response.totalCount;

			        return {
			            suggestions: $.map(response.items, function(dataItem) {
			                return { value: dataItem.name, data: dataItem.id, count: thisView.bCount };
			            })
			        };
			    },
                onStart: function(){
                	$('#waiting').hide();
                },
                onFinish: function(){
                },
                onSelect: function(response) {
                    var data = {};
                    data.id = response.data;
                    data.name = response.value;

                    thisView.parent.addBrand(data);
                }
            });

			var cUrl = this.url + "supplier/location";

			$('input[name="countryComplete"]').autocomplete({
                serviceUrl: cUrl,
                width:370,
                maxHeight: 160,
                zIndex: 9999,
                minChars: 2,
                dataType: 'json',
                appendTo: 'div.selectlistC',
                triggerSelectOnValidInput: false,
                transformResult: function(response) {
                	thisView.cCount = response.response.length;
			        return {
			            suggestions: $.map(response.response, function(dataItem) {
			                return { value: dataItem.name, data: dataItem.id, count: thisView.cCount };
			            })
			        };
			    },
                onStart: function(){
                	$('#waiting').hide();
                },
                onFinish: function(){
                },
                onSelect: function(response) {
                    var data = {};
                    data.id = response.data;
                    data.name = response.value;

                    thisView.parent.addLocation(data);
                }
            });

	    	$('#segments').hide();
	    	$('#segmentEditor').show();

	    	$('input[name="ok"]').unbind().bind('click', function(e){
	    		e.preventDefault();
	    		thisView.saveModel();
	    	});
	    },

	    showSegment: function(){
	    	$('#waiting').show();
	    	this.suppliersView.parent = this;
	    	this.suppliersView.getCandidateCollection();
	    },

	    saveModel: function(){
	    	var thisView = this;

	    	this.model.set('name', $('input[name="segmentName"]').val());
	    	this.model.attributes.date.start = $('input[name="startDate"]').val();
	    	this.model.set('comments', $('input[name="comments"]').val());

	    	this.model.save();

	    	var catsLength = thisView.parent.categories.length;
			var brandsLength = thisView.parent.brands.length;
			var locLength= thisView.parent.locations.length;

			if(thisView.parent.categories == 0){
				thisView.xhrReqs++;
				if(thisView.xhrReqs == catsLength + brandsLength + locLength + 3){
	        		$('#segmentEditor').hide();
	        		$('#segments').show();
	        		thisView.parent.categories = [];
	        		thisView.parent.brands = [];
	        		thisView.parent.locations = [];
	        		$('.selectedItems').html('');
	        		thisView.parent.getCollection();
	        	}
			}
			else {
				thisView.xhrReqs++;
				_.each(thisView.parent.categories, function(item){
					var url = this.url + "supplier/segment/" + thisView.model.attributes.id + "/category/" + item;
					$.ajax({
			            type:   'POST',
			            url:    url
			        }).done(function(response) {
			        	thisView.xhrReqs++;
			        	if(thisView.xhrReqs == catsLength + brandsLength + locLength + 3){
			        		$('#segmentEditor').hide();
			        		$('#segments').show();
			        		thisView.parent.categories = [];
			        		thisView.parent.brands = [];
			        		thisView.parent.locations = [];
			        		$('.selectedItems').html('');
			        		thisView.parent.getCollection();
			        	}
			        }).fail(function() {
			            alert("Failed to edit segment");
			        });
				}, this);
			}

			if(thisView.parent.brands == 0){
				thisView.xhrReqs++;
				if(thisView.xhrReqs == catsLength + brandsLength + locLength + 3){
	        		$('#segmentEditor').hide();
	        		$('#segments').show();
	        		thisView.parent.categories = [];
	        		thisView.parent.brands = [];
	        		thisView.parent.locations = [];
	        		$('.selectedItems').html('');
	        		thisView.parent.getCollection();
	        	}
			}
			else {
				thisView.xhrReqs++;
				_.each(thisView.parent.brands, function(item){
					var url = this.url + "supplier/segment/" + thisView.model.attributes.id + "/brand/" + item;
					$.ajax({
			            type:   'POST',
			            url:    url
			        }).done(function(response) {
			        	thisView.xhrReqs++;
			        	if(thisView.xhrReqs == catsLength + brandsLength + locLength + 3){
			        		$('#segmentEditor').hide();
			        		$('#segments').show();
			        		thisView.parent.categories = [];
			        		thisView.parent.brands = [];
			        		thisView.parent.locations = [];
			        		$('.selectedItems').html('');
			        		thisView.parent.getCollection();
			        	}
			        }).fail(function() {
			            alert("Failed to edit segment");
			        });
				}, this);
			}

			if(thisView.parent.locations == 0) {
				thisView.xhrReqs++;
				if(thisView.xhrReqs == catsLength + brandsLength + locLength + 3){
	        		$('#segmentEditor').hide();
	        		$('#segments').show();
	        		thisView.parent.categories = [];
	        		thisView.parent.brands = [];
	        		thisView.parent.locations = [];
	        		$('.selectedItems').html('');
	        		thisView.parent.getCollection();
	        	}
			}
			else {
				thisView.xhrReqs++;
				_.each(thisView.parent.locations, function(item){
					var url = this.url + "supplier/segment/" + thisView.model.attributes.id + "/location/" + item;
					$.ajax({
			            type:   'POST',
			            url:    url
			        }).done(function(response) {
			        	thisView.xhrReqs++;
			        	if(thisView.xhrReqs == catsLength + brandsLength + locLength + 3){
			        		$('#segmentEditor').hide();
			        		$('#segments').show();
			        		thisView.parent.categories = [];
			        		thisView.parent.brands = [];
			        		thisView.parent.locations = [];
			        		$('.selectedItems').html('');
			        		thisView.parent.getCollection();
			        	}
			        }).fail(function() {
			            alert("Failed to edit segment");
			        });
				}, this);
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

	return targetSegmentRowView;
});