define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'libs/jquery.uniform',
	'libs/jquery.tools.overlay.modified',
	'libs/jquery.autocomplete',
	'jqueryui/datepicker',
	'../views/targetSegmentRowView',
	'../collections/collection',
	'../collections/filteredCollection',
	'../collections/countriesCollection',
	'text!templates/shipmate/prime-suppliers/tpl/countryItem.html',
	'text!templates/shipmate/prime-suppliers/tpl/targetSegmentsHead.html',
	'text!templates/shipmate/prime-suppliers/tpl/addSegmentModal.html'
], function(
	$,
	_, 
	Backbone,
	Hb,
	Uniform,
	Modal,
	Autocomplete,
	datepicker,
	targetSegmentRowView,
	collection,
	filteredCollection,
	countriesCollection,
	countryItemTpl,
	targetSegmentsHeadTpl,
	addSegmentTpl
){
	var targetSegmentsView = Backbone.View.extend({
		el: $('body'),

		countryItemTemplate: Handlebars.compile(countryItemTpl),
		headTemplate: Handlebars.compile(targetSegmentsHeadTpl),
		addSegmentTemplate: Handlebars.compile(addSegmentTpl),

		categoryId: "",
		countryCode: "",
		enabled: "",
		period: "",
		countries: [],
		categories: [],
		brands: [],
		locations: [],
		xhrReqs: 0,
		editSegment: false,
		segmentId: 0,
		hostname: require('shipmate/targetSegments/hostname'),
		url: "",

		events: {
			'keyup #categoryInput'         : 'search',
			'change #periodSelector'   	   : 'filterPeriod',
			'click a.newSegment' 	       : 'showAddModal',
			'click input[name="cancel"]'   : 'cancelAdding',
			'click a.clearCats'			   : 'clearCategories',
			'click a.removeCategoryItem'   : 'deleteCategory',
			'click a.clearBrands'		   : 'clearBrands',
			'click a.removeBrandItem'	   : 'deleteBrand',
			'click a.clearCountries'	   : 'clearLocations',
			'click a.removeLocationItem'   : 'deleteLocation',
		},

		initialize: function() {
			var protocol = (window.location.protocol === "https:") ? 'https://' : 'http://';

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

			$('#periodSelector').uniform();
			$('#periodSelectorCurrent').uniform();
			$('#periodSelectorCurrentSecond').uniform();
			$('#periodSelectorCandidate').uniform();
			$('#countrySelector').uniform();

			this.collection = new collection();
			this.collection.url = this.url + "supplier/segment";

			this.filteredCollection = new filteredCollection();

			this.countriesCollection = new countriesCollection();
			this.countriesCollection.url = "/data/source/locations";
			this.countriesCollection.fetch();

			this.getCollection();
		},

		getCollection: function(){
			var thisView = this;
			
			this.collection.fetch({
				data: $.param({
					dateFrom: this.period
				}),
				type: "GET",
				complete: function(){
					thisView.search();
				}
			});
		},

		orderBy: function(node, orderField, isAsc) {
			var nodeCount = node.length;
			var minIndex = 0;

			for (i = 0 ; i < nodeCount ; i++) {
				minIndex = i;
				for (x = i+1 ; x < nodeCount ; x++) {
					var compFrom = (node[x][orderField] === undefined) ? "" : node[x][orderField];
					var compTo = (node[minIndex][orderField] === undefined) ? "" : node[minIndex][orderField];
					if (isAsc) {		
						if (compFrom < compTo) {
							minIndex = x;
						}
					}  else {
						if (compFrom > compTo) {
							minIndex = x;
						}
					}
				}
				if (minIndex != i) { 
					var tempObj = node[i];
					node[i] = node[minIndex];
					node[minIndex] = tempObj;
				}
			}

			return node;
		},

		sortCollectionByCategoryName: function(){
			// update comparator function
			this.collection.comparator = function(model) {
			    return model.get('category').name;
			}

			// call the sort method
			this.collection.sort();
		},

		render: function() {
			var thisView = this;

			var html = this.headTemplate();
			$('#body').removeClass('wide');
			$(this.el).find('#segments .innerContent table thead').html(html);
			$(this.el).find('#suppliers').hide();
			//$(this.el).find('#segments').show();
			this.renderItems();
		},

		renderItems: function(){
			var tbody = $(this.el).find('#segments .innerContent table tbody');
			$(tbody).html('');
			_.each(this.filteredCollection.models, function(item){
				this.renderItem(item);
			}, this);
		},

		renderItem: function(item) {
			var targetSegmentRow = new targetSegmentRowView({
				model: item
			});

			targetSegmentRow.parent = this;

			var tbody = $(this.el).find('#segments .innerContent table tbody');

			tbody.append(targetSegmentRow.render().el);
		},

		filterPeriod: function(){
			this.period = $('#periodSelector').val();
			this.getCollection();
		},

		search: function() {
			var search = $('#categoryInput').val();
			search = search.toLowerCase();

			var results;

			this.filteredCollection.reset();

			if((search && search !== "")){
				_.each(this.collection.models, function(item){
					if(item.attributes.label.toLowerCase().indexOf(search) !== -1) {
						this.filteredCollection.add(item);
					}
				}, this);
			}
			else {
				_.each(this.collection.models, function(item){
					this.filteredCollection.add(item);
				}, this);
			}

			this.render();
		},

		showAddModal: function(e){
			e.preventDefault();
			this.editSegment = false;
			this.brands = [];
			this.categories = [];

			var thisView = this,
				sUrl = this.url + "inbox/autocomplete-categories";
			
			$('input[name="startDate"]').datepicker({
	    		dateFormat: 'dd.mm.yy'
	    	});

			$('input[name="catComplete"]').autocomplete({
                serviceUrl: sUrl,
                width: 370,
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
                    thisView.addCategory(data);
                }
            });

			var bUrl = this.url + "inbox/autocomplete-brands";
			
			$('input[name="brandComplete"]').autocomplete({
                serviceUrl: bUrl,
                width: 370,
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
                    thisView.addBrand(data);
                }
            });

			var cUrl = this.url + "supplier/location";

			$('input[name="countryComplete"]').autocomplete({
                serviceUrl: cUrl,
                width: 370,
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
                    thisView.addLocation(data);
                }
            });

			$('input[name="ok"]').unbind().bind('click', function(e){
	    		e.preventDefault();
	    		thisView.addSegment();
	    	});

            $('#segments').hide();
            $('#segmentEditor').show();
		},

		addCategory: function(data){
			this.categories.push(data.id);

			var html = '<li><a class="removeCategoryItem" href="' + data.id + '"></a><span>' + data.name + '</span><div class="clear"></div></li>';

			$('.selectedItems.categs').append(html);
			$('input[name="catComplete"]').val('');
		},

		clearCategories: function(e){
			e.preventDefault();
			this.categories = [];
			$('.selectedItems.categs').html('');
		},

		deleteCategory: function(e){
			e.preventDefault();
			var index = this.categories.indexOf(parseInt($(e.target).attr('href')));
			if (index > -1) {
			    this.categories.splice(index, 1);
			}
			$(e.target).parent('li').remove();

			if(this.editSegment){
				var url = this.url + "supplier/segment/" + this.segmentId + "/category/" + $(e.target).attr('href');
				
				$.ajax({
					type:   'DELETE',
					url: url
				});
			}
		},

		addBrand: function(data){
			this.brands.push(data.id);

			var html = '<li><a class="removeBrandItem" href="' + data.id + '"></a><span>' + data.name + '</span><div class="clear"></div></li>';

			$('.selectedItems.brands').append(html);
			$('input[name="brandComplete"]').val('');
		},

		clearBrands: function(e){
			e.preventDefault();
			this.brands = [];
			$('.selectedItems.brands').html('');
		},

		deleteBrand: function(e){
			e.preventDefault();
			var index = this.brands.indexOf(parseInt($(e.target).attr('href')));
			if (index > -1) {
			    this.brands.splice(index, 1);
			}
			$(e.target).parent('li').remove();

			if(this.editSegment){
				var url = this.url + "supplier/segment/" + this.segmentId + "/brand/" + $(e.target).attr('href');
				
				$.ajax({
					type:   'DELETE',
					url: url
				});
			}
		},

		addLocation: function(data){
			this.locations.push(data.id);

			var html = '<li><a class="removeLocationItem" href="' + data.id + '"></a><span>' + data.name + '</span><div class="clear"></div></li>';

			$('.selectedItems.countries').append(html);
			$('input[name="countryComplete"]').val('');
		},

		clearLocations: function(e){
			e.preventDefault();
			this.locations = [];
			$('.selectedItems.countries').html('');
		},

		deleteLocation: function(e){
			e.preventDefault();
			var index = this.locations.indexOf(parseInt($(e.target).attr('href')));
			if (index > -1) {
			    this.locations.splice(index, 1);
			}
			$(e.target).parent('li').remove();

			if(this.editSegment){
				var url = this.url + "supplier/segment/" + this.segmentId + "/location/" + $(e.target).attr('href');
				
				$.ajax({
					type:   'DELETE',
					url: url
				});
			}
		},

		cancelAdding: function(){
			if(this.editSegment){
				var conf = confirm('Are you sure you want to discard all changes?');
		    	if(conf == true){
		    		this.doCancel();
		    	}
			}
			else {
				this.doCancel();
			}
		},

		doCancel: function(){
			this.editSegment = false,
			this.categories = [];
			this.brands = [];
			this.countries = [];

			$('.selectedItems.categs').html('');
			$('.selectedItems.brands').html('');
			$('.selectedItems.countries').html('')
			$('input[name="segmentName"]').val('');
			$('input[name="startDate"]').val('');
			$('textarea[name="comments"]').val('');
			$('#segments').show();
            $('#segmentEditor').hide();
		},

		addSegment: function(e){
			//e.preventDeafult;
			var thisView = this,
				startDate = $('input[name="startDate"]').val(),
				name = $('input[name="segmentName"]').val(),
				comments = $('textarea[name="comments"]').val();

			this.collection.create({
				date: {
					start: startDate
				},
				name: name,
				comments: comments
			},
			{
				url: this.url + "supplier/segment/",
				type: "POST",
				success: function(model, response) {
					var id = response.response;

					var catsLength = thisView.categories.length;
					var brandsLength = thisView.brands.length;
					var locLength= thisView.locations.length;

					if(thisView.categories == 0){
						thisView.xhrReqs++;
					}
					else {
						thisView.xhrReqs++;
						_.each(thisView.categories, function(item){
							thisView.xhrReqs++;
							var url = thisView.url + "supplier/segment/" + id + "/category/" + item;

							$.ajax({
					            type:   'POST',
					            url:    url
					        }).done(function(response) {
					        	if(thisView.xhrReqs == catsLength + brandsLength + locLength + 3){
					        		$('.selectedItems.categs').html('');
									$('.selectedItems.brands').html('');
									$('.selectedItems.countries').html('');
									$('input[name="segmentName"]').val('');
									$('input[name="startDate"]').val('');
									$('textarea[name="comments"]').val('');
					        		$('#segmentEditor').hide();
					        		$('#segments').show();
					        		thisView.getCollection();
					        	}
					        }).fail(function() {
					            alert("Failed to add segment");
					        });
						}, this);
					}

					if(thisView.brands == 0){
						thisView.xhrReqs++;
					}
					else {
						thisView.xhrReqs++;
						_.each(thisView.brands, function(item){
							thisView.xhrReqs++;
							var url = thisView.url + "supplier/segment/" + id + "/brand/" + item;
							$.ajax({
					            type:   'POST',
					            url:    url
					        }).done(function(response) {
					        	if(thisView.xhrReqs == catsLength + brandsLength + locLength + 3){
					        		$('.selectedItems.categs').html('');
									$('.selectedItems.brands').html('');
									$('.selectedItems.countries').html('');
									$('input[name="segmentName"]').val('');
									$('input[name="startDate"]').val('');
									$('textarea[name="comments"]').val('');
					        		$('#segmentEditor').hide();
					        		$('#segments').show();
					        		thisView.getCollection();
					        	}
					        }).fail(function() {
					            alert("Failed to add segment");
					        });
						}, this);
					}

					if(thisView.locations == 0) {
						thisView.xhrReqs++;
					}
					else {
						thisView.xhrReqs++;
						_.each(thisView.locations, function(item){
							var url = thisView.url + "supplier/segment/" + id + "/location/" + item;
							$.ajax({
					            type:   'POST',
					            url:    url
					        }).done(function(response) {
					        	thisView.xhrReqs++;
					        	if(thisView.xhrReqs == catsLength + brandsLength + locLength + 3){
					        		$('.selectedItems.categs').html('');
									$('.selectedItems.brands').html('');
									$('.selectedItems.countries').html('');
									$('input[name="segmentName"]').val('');
									$('input[name="startDate"]').val('');
									$('textarea[name="comments"]').val('');
					        		$('#segmentEditor').hide();
					        		$('#segments').show();
					        		thisView.getCollection();
					        	}
					        }).fail(function() {
					            alert("Failed to add segment");
					        });
						}, this);
					}
				}
			});
		},

		//Currently not in use, might be improved in the future for hiding already existing categories and countries when adding new segment
		getCatUrl: function(type){
			var url = this.url + "inbox/autocomplete-categories",
				exclude = "?exclude[]=",
				count = 0;
				
			_.each(this.collection.models, function(item){
				if(count > 0) {
					exclude += "&exclude[]=";
					exclude += item.attributes.category.id;
				}
				else {
					exclude += item.attributes.category.id;
				}
				count++;
			}, this);

			url += exclude;

			return url;
		},

		openDialog: function() {
            $("#modal").overlay({
                mask: 'black',
                left: 'center',
                fixed: true,

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

	return targetSegmentsView;
});
