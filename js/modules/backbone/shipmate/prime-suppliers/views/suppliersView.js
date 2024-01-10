define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'libs/jquery.uniform',
	'libs/jquery.tools.overlay.modified',
	'libs/jquery.autocomplete',
	'../views/supplierRowView',
	'backbone/shared/pagination/views/paginationViewTarget',
	'../collections/supplierCollection'
], function(
	$,
	_, 
	Backbone,
	Hb,
	Uniform,
	Modal,
	Autocomplete,
	supplierRowView,
	paginationView,
	supplierCollection
){
	var suppliersView = Backbone.View.extend({
		el: $('body'),

		events: {
			'click .segmentMatch' 				: 'showSegmentMatch',
			'click .overall'	 				: 'showOverall'
		},

		dateFrom: "",
		dateFromCandidate: "",
		setId: "",
		page: 1,
		paginationLimit: 20,
		hostname: require('shipmate/targetSegments/hostname'),
		url: "",
		orderBy: "countryCode",
		orderDir: "desc",
		supplierNameFilter: "",

		initialize: function(){
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

			this.supplierCollection = new supplierCollection();
			this.supplierCandidateCollection = new supplierCollection();
			this.segmentKeywordsCollection = new supplierCollection();
		},

		closeView: function(e){
			e.preventDefault();
		    
			$('#suppliers').hide();
			$('#segments').show();
			$('#body').removeClass('wide');
			$('#header').removeClass('wide');
			$('.divider').removeClass('wide');

			$.uniform.restore('#tabMatch');
			$.uniform.restore('#tabOverall');
			$.uniform.restore('#tabMatchOv');
			$.uniform.restore('#tabOverallOv');
		},

		showSegmentMatch: function() {
			$('.segmentMatchPerf').show();
			$('.overallPerf').hide();
			$.uniform.update();
		},

		showOverall: function() {
			$('.segmentMatchPerf').hide();
			$('.overallPerf').show();
			$.uniform.update();
		},

		getCandidateCollection: function(refresh){			
			this.segmentId = this.parent.model.attributes.id;
			this.collectionUrl = this.url + "supplier/segment/" + this.segmentId;

			var thisView = this;

			this.supplierCandidateCollection.url = this.collectionUrl + "/candidate";

			this.supplierCandidateCollection.fetch({
				data: $.param({
					pageNo: this.page,
					pageSize: this.paginationLimit,
					dateFrom: this.dateFromCandidate,
					orderBy: this.orderBy,
					orderDir: this.orderDir,
					supplierNameFilter: this.supplierNameFilter
				}),
				complete: function(){
					if(refresh){
						thisView.render();
					}
					else {
						thisView.getSegmentKeywords();
					}
				}
			});
		},

		getSegmentKeywords: function(){
			var thisView = this;

			this.segmentKeywordsCollection.url = this.collectionUrl + "/keyword-set";

			this.segmentKeywordsCollection.fetch({
				data: $.param({
					enabled: 0
				}),
				complete: function(){
					thisView.getData();
				}
			});
		},

		getData: function(){
			var thisView = this;

			this.supplierCollection.url = this.collectionUrl + "/member";

			this.supplierCollection.fetch({
				data: $.param({
					dateFrom: this.dateFrom,
					setId: this.setId
				}),
				complete: function(){
					thisView.render();
				}
			});
		},

		sortCandidates: function(e){
			var thisView = this;
			e.preventDefault();
			this.orderBy = $(e.target).parent('a').attr('href');
			if($(e.target).parent('a').hasClass('asc')){
				this.orderDir = "desc";
			}
			else {
				this.orderDir = "asc";
			}

			$('a.sort').removeClass('asc');
			$('a.sort').removeClass('desc');
			$('a.sort').removeClass('sorted');
			$(e.target).parent('a').addClass('sorted');
			$(e.target).parent('a').addClass(this.orderDir);
			$.uniform.restore('#tabMatch');
			$.uniform.restore('#tabOverall');
			$.uniform.restore('#tabMatchOv');
			$.uniform.restore('#tabOverallOv');
			this.getCandidateCollection(true);
		},

		filterMembers: function(second){
			if(second){
				this.dateFrom = $('#periodSelectorCurrentSecond').val();
			}
			else {
				this.dateFrom = $('#periodSelectorCurrent').val();
			}
			$.uniform.restore('#tabMatch');
			$.uniform.restore('#tabOverall');
			$.uniform.restore('#tabMatchOv');
			$.uniform.restore('#tabOverallOv');
			this.getSegmentKeywords();
		},

		filterCandidates: function(){
			this.dateFromCandidate = $('#periodSelectorCandidate').val();
			this.supplierNameFilter = $('#nameInputCandidate').val();
			$.uniform.restore('#tabMatch');
			$.uniform.restore('#tabOverall');
			$.uniform.restore('#tabMatchOv');
			$.uniform.restore('#tabOverallOv');
			this.getCandidateCollection(true);
		},

		filterKeyword: function(second){
			if(second){
				this.setId = $('#segmentKeywordSelSecond').val();
			}
			else {
				this.setId = $('#segmentKeywordSel').val();
			}
			$.uniform.restore('#tabMatch');
			$.uniform.restore('#tabOverall');
			$.uniform.restore('#tabMatchOv');
			$.uniform.restore('#tabOverallOv');
			this.getData();
		},

		render: function() {
			var thisView = this;
			var selected ="";

			$('select[name="segmentKeywords"]').html('');
			if(this.setId === "") {
				selected = 'selected="selected"';
			}
			$('select[name="segmentKeywords"]').append('<option value="" ' + selected + '>All keywords</option>');
			
			_.each(this.segmentKeywordsCollection.models, function(item){
				var selectedItem = '';

				if(item.attributes.id == this.setId){
					selectedItem = ' selected="selected" ';
				}
				
				$('select[name="segmentKeywords"]').append('<option value="' + item.attributes.id + '"' + selectedItem + '>' + item.attributes.name + '</option>');
			}, this);

			if($('#uniform-segmentKeywordSel').length < 1){
				$('select[name="segmentKeywords"]').uniform();
			}
			else {
				$.uniform.update();
			}

			$('a.sort').unbind().bind('click', function(e){
				e.preventDefault();
				thisView.sortCandidates(e);
			});
			$('input[name="tabs"]').unbind().bind('click', function(e){
				e.preventDefault();
			});
			$('input[name="tabsOv"]').unbind().bind('click', function(e){
				e.preventDefault();
			});
			$('.back').unbind().bind('click', function(e){
				thisView.closeView(e);
			});
			$('input[name="filterByName').unbind().bind('click', function(e){
				e.preventDefault();
				thisView.filterCandidates();
			});
			$('#nameInputCandidate').unbind().bind('keyup', function(e){
				e.preventDefault();
				if(e.keyCode == 13){
					$('input[name="filterByName').click();
				}
				else {
					return;
				}
			});

			$('#tabMatch').uniform();
			$('#tabOverall').uniform();
			$('#tabMatchOv').uniform();
			$('#tabOverallOv').uniform();

			if(this.dateFrom){
				$('#periodSelectorCurrent option[value="'+ this.dateFrom + '"]').attr('selected', 'selected');
				$('#periodSelectorCurrentSecond option[value="'+ this.dateFrom + '"]').attr('selected', 'selected');
				$.uniform.update();
			}
			else {
				$('#periodSelectorCurrent option[value=""]').attr('selected', 'selected');
				$('#periodSelectorCurrentSecond option[value=""]').attr('selected', 'selected');
				$.uniform.update();
			}

			if(this.dateFromCandidate){
				$('#periodSelectorCandidate option[value="'+ this.dateFromCandidate + '"]').attr('selected', 'selected');
				$.uniform.update();
			}
			else {
				$('#periodSelectorCandidate option[value=""]').attr('selected', 'selected');
				$.uniform.update();
			}

			$('#periodSelectorCurrent').unbind().bind('change', function(){
				thisView.filterMembers();
			});

			$('#periodSelectorCurrentSecond').unbind().bind('change', function(){
				thisView.filterMembers(true);
			});

			$('#periodSelectorCandidate').unbind().bind('change', function(){
				thisView.filterCandidates();
			});

			$('#segmentKeywordSelSecond').unbind().bind('change', function(){
				thisView.filterKeyword(true);
			});

			$('#segmentKeywordSel').unbind().bind('change', function(){
				thisView.filterKeyword();
			});

			$('input[name="addTnid"]').unbind().bind('click', function(e){
				e.preventDefault();
				thisView.addSupplier();
			});

			$('#body').addClass('wide');
			$('#header').addClass('wide');
			$('.divider').addClass('wide');
			$(this.el).find('#segments').hide();

			$('#suppliers h1 span.segmentName').html(this.parent.model.attributes.name);

			$(this.el).find('#suppliers').show();
			this.renderSuppliers("current");
			this.renderSuppliers("candidate");
			$('#waiting').hide();
			this.formatIsoDate();
		},

		renderSuppliers: function(type){
			var collection = {};
			
			if(type == "current") {
				var tbodyMatch = $(this.el).find('#suppliers .innerContent .segmentMatchPerf table.' + type + ' tbody');
				var tbodyOverall = $(this.el).find('#suppliers .innerContent .overallPerf table.' + type + ' tbody');
				$(tbodyMatch).html('');
				$(tbodyOverall).html('');
				collection = this.supplierCollection;
			}
			else {
				var tbody = $(this.el).find('#suppliers .innerContent table.' + type + ' tbody');
				$(tbody).html('');
				collection = this.supplierCandidateCollection;

				//pass params to pagination
				paginationView.parent = this;
				paginationView.paginationLimit = this.paginationLimit;
				paginationView.page = this.page;

		    	//render pagination view
		    	if(this.supplierCandidateCollection.models.length > 0){
		    		paginationView.render(this.supplierCandidateCollection.models[0].attributes.page.total);
		    	}
		    	
			}

			_.each(collection.models, function(item){
				this.renderSupplierItem(item, type);
			}, this);
		},

		renderSupplierItem: function(item, type) {
			var supplierRow = new supplierRowView({
				model: item
			});

			var supplierRowOverall = new supplierRowView({
				model: item
			});

			supplierRow.parent = this;
			supplierRowOverall.parent = this;

			if(type == "current") {
				var tbodyMatch = $(this.el).find('#suppliers .innerContent .segmentMatchPerf table.' + type + ' tbody');
				tbodyMatch.append(supplierRow.render("current", false).el);

				var tbodyOverall = $(this.el).find('#suppliers .innerContent .overallPerf table.' + type + ' tbody');
				tbodyOverall.append(supplierRowOverall.render("current", true).el);
			}
			else {
				var tbody = $(this.el).find('#suppliers .innerContent table.' + type + ' tbody');
				tbody.append(supplierRow.render(type).el);
			}

			var thisView = this;

			supplierRow.model.bind('destroy', function(){
				thisView.getCandidateCollection();
			});
		},

		addSupplier: function(e){
			var thisView = this;
			var postUrl = this.url + "supplier/segment/" + this.parent.model.attributes.id + "/member/" + $('input[name="tnidtoadd"]').val();
			
			$.post( postUrl, function( data ) {
				$.uniform.restore('#tabMatch');
				$.uniform.restore('#tabOverall');
				$.uniform.restore('#tabMatchOv');
				$.uniform.restore('#tabOverallOv');
				
				thisView.getData();
			});
		},

		formatIsoDate: function(){
			var date = this.parent.model.attributes.date.start;
			var newDate = date.split(" ");
	    	newDate = newDate[0].split("-");
	    	date = newDate[2] + "." + newDate[1] + "." + newDate[0];
	    	$('#periodSelectorCurrent option:first-child').html('Since: ' + date);
	    	$('#periodSelectorCurrentSecond option:first-child').html('Since: ' + date);
	    	$('#periodSelectorCandidate option:first-child').html('Since: ' + date);
	    	$.uniform.update();
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

	return suppliersView;
});
