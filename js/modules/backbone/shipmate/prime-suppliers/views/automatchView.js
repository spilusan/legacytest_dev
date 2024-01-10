define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'libs/jquery.uniform',
	'libs/jquery.tools.overlay.modified',
	'libs/jquery.autocomplete',
	'libs/SimpleAjaxUploader.min',
	'../views/automatchRowView',
	'../collections/collection',
	'../collections/filteredCollection'
], function(
	$,
	_, 
	Backbone,
	Hb,
	Uniform,
	Modal,
	Autocomplete,
	ss,
	automatchRowView,
	collection,
	filteredCollection
){
	var btn = $('.uploadFile');

	var uploader = new ss.SimpleUpload({
		method: 'POST',
		button: btn,
		url: "",
		name: 'csv',
		multipart: true,
		responseType: 'json',
		form: $('form[name="newSet"]'),
		startXHR: function() {
		},
		onSubmit: function() {
		},
		onComplete: function( filename, response ) {
		},
		onError: function() {
		}
	});

	var automatchView = Backbone.View.extend({
		el: $('body'),

		events: {
			'click .addNewSet'		: 'newSet'
		},

		hostname: require('shipmate/targetSegments/hostname'),
		url: "",
		setId: null,

		initialize: function(){
			var protocol = (window.location.protocol === "https:") ? 'https://' : 'http://';
			var thisView = this;

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

			this.selectedCollection = new collection();
			this.availableCollection = new collection();

			this.filteredCollection = new filteredCollection();
		},

		sortCollections: function(field){
			this.selectedCollection.comparator = function(model) {
			    return model.get(field);
			}

			this.selectedCollection.sort();

			this.availableCollection.comparator = function(model) {
			    return model.get(field);
			}

			this.availableCollection.sort();

			this.filteredCollection.comparator = function(model) {
			    return model.get(field);
			}

			this.filteredCollection.sort();
		},

		closeView: function(){
			$.uniform.restore('#tabMatch');
			$.uniform.restore('#tabOverall');
			$.uniform.restore('#tabMatchOv');
			$.uniform.restore('#tabOverallOv'); 

			$('#amSetup').hide();
			$('#suppliers').show();
			$('#segments').hide();
			$('#body').addClass('wide');
			this.parent.parent.getCandidateCollection();
		},

		getData: function(){
			var thisView = this;

			this.availableCollection.url = this.url + 'supplier/' + this.parent.model.attributes.id + "/keyword-set/?enabledOnly=0&available=1";
			this.selectedCollection.url = this.url + 'supplier/' + this.parent.model.attributes.id + "/keyword-set/?enabledOnly=0&available=0";

			this.availableCollection.fetch({
				complete: function(){
					thisView.selectedCollection.fetch({
						complete: function(){
							thisView.search();
						}
					});
				}
			});
			
		},

		search: function() {
			var search = $('#keywordInput').val();
			search = search.toLowerCase();

			this.filteredCollection.reset();

			if((search && search !== "")){
				_.each(this.availableCollection.models, function(item){
					if(item.attributes.name.toLowerCase().indexOf(search) !== -1) {
						this.filteredCollection.add(item);
					}
				}, this);
			}
			else {
				_.each(this.availableCollection.models, function(item){
					this.filteredCollection.add(item);
				}, this);
			}

			this.sortCollections('name');
			this.render();
		},

		render: function() {
			var thisView = this;

			$('.autoSupplier').html(this.supplierName);

			$('.closeAutoMatch').unbind().bind('click', function(e){
				e.preventDefault();
				thisView.closeView();
			});

			$('#keywordInput').unbind().bind('keyup', function(e){
				e.preventDefault();
				thisView.search();
			});

			$('body').undelegate('input[name="upload"].create', 'click').delegate('input[name="upload"].create', 'click', function(e){
				e.preventDefault();
				thisView.createSet();
			});

			$('body').undelegate('input[name="upload"].update', 'click').delegate('input[name="upload"].update', 'click', function(e){
				e.preventDefault();
				thisView.updateSet();
			});

			$('#amSetup').show();
			$('#suppliers').hide();
			$('#segments').hide();
			$('#body').removeClass('wide');

			this.renderKeywords("available");
			this.renderKeywords("selected");
		},

		renderKeywords: function(type){
			if(type == "available") {
				var tbody = $(this.el).find('#amSetup .innerContent .leftTable table tbody');
				$(tbody).html('');
				var collection = this.filteredCollection;
			}
			else {
				var tbody = $(this.el).find('#amSetup .innerContent .rightTable table tbody');
				$(tbody).html('');
				var collection = this.selectedCollection;
			}

			_.each(collection.models, function(item){
				this.renderKeywordItem(item, type);
			}, this);
		},

		renderKeywordItem: function(item, type) {
			var automatchRow = new automatchRowView({
				model: item
			});

			automatchRow.parent = this;

			if(type == "available") {
				var tbody = $(this.el).find('#amSetup .innerContent .leftTable table tbody');
			}
			else {
				var tbody = $(this.el).find('#amSetup .innerContent .rightTable table tbody');
			}

			tbody.append(automatchRow.render(type).el);
		},

		newSet: function(e){
			e.preventDefault();
			this.openDialog();
		},

		createSet: function(){
			var thisView = this;
			var postUrl = this.url + "supplier/keyword-set/";
			var name = $('form[name="newSet"] input[name="name"]').val();
			var type = $('form[name="newSet"] select[name="type"]').val();
			var str = $('input[name="tnids"]').val();
			var str_array = [];
			str_array = str.split(',');
			if (str_array[0] == ""){
				str_array = [];
			}

			for(var i = 0; i < str_array.length; i++) {
			   // Trim the excess whitespace.
			   str_array[i] = str_array[i].replace(/^\s*/, "").replace(/\s*$/, "");
			   // Add additional code here, such as:
			}
			str_array.push(this.tnid);

			if(name && name !== ""){
				$.ajax({
					type: "POST",
					url: postUrl,
					dataType: "json",
					data: JSON.stringify({
						"name" : name,
						"type" : type,
						"enabled" : true,
						"threshold" : null
					}),
					success: function(response){
						var keywordId = response.response;
						var postUrl = this.url + response.response + "/csv/";
						uploader.setOptions({
							url: postUrl,
							onComplete: function(filename, response){
								$('form[name="newSet"] input[name="name"]').val('');
								$('#modal').overlay().close();
								_.each(str_array, function(item){
									$.ajax({
										type: "POST",
										url: thisView.url + "supplier/" + item + "/keyword-set/" + keywordId
									});
								});
								$('input[name="tnids"]').val('');
								thisView.getData();
							},
							onError: function(filename, response) {
								alert('There was an error in your uploaded keyword set, please search for your new set and click on upload to try again.');
								$('form[name="newSet"] input[name="name"]').val('');
								$('#modal').overlay().close();
								_.each(str_array, function(item){
									$.ajax({
										type: "POST",
										url: thisView.url + "supplier/" + item + "/keyword-set/" + keywordId
									});
								});
								$('input[name="tnids"]').val('');
								thisView.getData();
							}
						});
						$('form[name="newSet"]').submit();
					}
				});
			}
			else {
				alert('Please enter a keyword set name');
			}
		},

		updateSet: function(id){
			var thisView = this;
			var postUrl = this.url + "supplier/keyword-set/" + this.setId + "/csv/";
			uploader.setOptions({
				url: postUrl,
				onComplete: function(filename, response){
					$('form[name="newSet"] input[name="name"]').val('');
					$('form[name="newSet"] input[name="name"]').removeAttr('disabled');
					$('#modal').overlay().close();
					$('input[name="upload"]').removeClass('update');
	    			$('input[name="upload"]').addClass('create');
	    			$('label[for="tnids"]').show();
					$('input[name="tnids"]').show();
					$('#modal .modalBody h1').html('Add new keyword set:');
					thisView.getData();
				},
				onError: function(filename, response) {
					$('label[for="type"]').show();
					$('select[name="type"]').show();
					alert('There was an error in your uploaded keyword set, please search for your new set and click on upload to try again.');
					$('form[name="newSet"] input[name="name"]').val('');
					$('form[name="newSet"] input[name="name"]').removeAttr('disabled');
					$('#modal').overlay().close();
					$('input[name="upload"]').removeClass('update');
	    			$('input[name="upload"]').addClass('create');
	    			$('label[for="tnids"]').show();
					$('input[name="tnids"]').show();
					$('#modal .modalBody h1').html('Add new keyword set:');
					thisView.getData();
				}
			});
			$('form[name="newSet"]').submit();
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
                        var modalWidth = $('#modalX').width();
                        var posLeft = windowWidth/2 - modalWidth/2;
                        $('#modal').css('left', posLeft);
                    });

                }
            });

            $('#modal').overlay().load();
        }
	});

	return automatchView;
});
