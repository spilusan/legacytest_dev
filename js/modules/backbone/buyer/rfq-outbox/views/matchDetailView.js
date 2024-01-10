define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/shared/activity/log',
	'libs/jquery.tools.overlay.modified',
	'../views/matchItemView',
	'backbone/shared/itemselector/views/selectorView',
	'../collections/rfqDetail',
	'../collections/terms',
	'text!templates/buyer/rfq-outbox/tpl/matchSection.html',
	'text!templates/buyer/rfq-outbox/tpl/matchAddBtn.html',
	'text!templates/buyer/rfq-outbox/tpl/tagsSelector.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	logActivity,
	Modal,
	matchItemView,
	ItemSelector,
	matchDetail,
	termsCollection,
	matchDetailTpl,
	matchAddTpl,
	tagSelTpl
){
	var matchDetailView = Backbone.View.extend({
		tagName: 'div',
		className: 'details',

		events: {
			'click input[name="refresh"]' : 'onRefresh'
		},

		template: Handlebars.compile(matchDetailTpl),
		addTemplate: Handlebars.compile(matchAddTpl),
		tagSelTemplate: Handlebars.compile(tagSelTpl),

		initialize: function(){
			var thisView = this;

			_.bindAll(this, 'render');
			
			this.collection = new matchDetail();

			this.categorySelector = new ItemSelector({itemType: 'categories'});

			this.brandSelector = new ItemSelector({itemType: 'brands'});

			this.locationSelector = new ItemSelector({itemType: 'locations'});

            this.categorySelector.bind('apply', function(){
            	thisView.reRender();
            });

            this.brandSelector.bind('apply', function(){
            	thisView.reRender();
            });

            this.locationSelector.bind('apply', function(){
            	thisView.reRender();
            });
		},

		getData: function(id){
			this.rfqRefNo = id;
			this.fetchXHR = this.collection.fetch({
				data: $.param({ 
					rfqRefNo: id
				}),
				complete: this.render
			});

			this.rfqRefNo = id;

			return this;
		},

		reRender: function() {
			var thisView = this;

			this.catCollection.reset();
			this.brandCollection.reset();
			this.locationCollection.reset();

			_.each(this.categorySelector.selectedItems, function(item){
				thisView.catCollection.add({
					id: item[thisView.categorySelector.config.primaryKey],
					level: "M",
					output_name: item[thisView.categorySelector.config.displayKey]
				});
			});
			
			_.each(this.brandSelector.selectedItems, function(item){
				thisView.brandCollection.add({
					id: item[thisView.brandSelector.config.primaryKey],
					level: "M",
					output_name: item[thisView.brandSelector.config.displayKey]
				});
			});

			_.each(this.locationSelector.selectedItems, function(item){
				thisView.locationCollection.add({
					id: item[thisView.locationSelector.config.primaryKey],
					level: "M",
					output_name: item[thisView.locationSelector.config.displayKey]
				});
			});

			this.renderItems();
			//fix height of body container due to absolute pos of content container
	    	var height = 0;
	    	if($('#content').height() < $('#sidebar').height()){
	    		height = $('#sidebar').height();
	    	}
	    	else {
	    		height = $('#content').height() + 25;
	    	}

	    	$('#body').height(height);
		},

	    render: function() {
	    	thisView = this;
	    	this.catCollection = new termsCollection();
	    	this.brandCollection = new termsCollection();
	    	this.locationCollection = new termsCollection();
	    	this.tagCollection = new termsCollection();

	    	_.each(this.collection.models[0].attributes.categories, function(item){
	    		thisView.catCollection.add(item);
	    	});

	    	_.each(this.collection.models[0].attributes.brands, function(item){
	    		thisView.brandCollection.add(item);
	    	});

	    	_.each(this.collection.models[0].attributes.tags, function(item){
	    		thisView.tagCollection.add(item);
	    	});

	    	_.each(this.collection.models[0].attributes.locations, function(item){
	    		thisView.locationCollection.add(item);
	    	});

			var html = this.template();
			$(this.el).html(html);
			this.renderItems();
			//fix height of body container due to absolute pos of content container
	    	var height = 0;
	    	if($('#content').height() < $('#sidebar').height()){
	    		height = $('#sidebar').height();
	    	}
	    	else {
	    		height = $('#content').height() + 25;
	    	}

	    	$('#body').height(height);
	    },

	    renderItems: function() {
	    	$(this.el).undelegate('li.add.category', 'click');
			$(this.el).undelegate('li.add.brand', 'click');
			$(this.el).undelegate('li.add.location', 'click');

			$(this.el).find('.matchSection.cat ul.cloud').html('');
			$(this.el).find('.matchSection.prod ul.cloud').html('');
			$(this.el).find('.matchSection.brand ul.cloud').html('');
			$(this.el).find('.matchSection.location ul.cloud').html('');

			this.categorySelector.selectedItems = [];
			this.categorySelector.parent = this;

			var primaryKey = this.categorySelector.config.primaryKey,
            	displayKey = this.categorySelector.config.displayKey,
                temp = [];

			_.each(this.catCollection.models, function(item) {
		        this.renderItem(item, 'cat');
		        temp[primaryKey] = item.attributes.id;
		        temp[displayKey] = item.attributes.output_name;

		        this.categorySelector.selectedItems.push(temp);

		        temp = [];
		    }, this);

		    this.brandSelector.selectedItems = [];
			this.brandSelector.parent = this;

			var bPrimaryKey = this.brandSelector.config.primaryKey,
            	bDisplayKey = this.brandSelector.config.displayKey,
                bTemp = [];

			_.each(this.brandCollection.models, function(item) {
		        this.renderItem(item, 'brand');
		        bTemp[bPrimaryKey] = item.attributes.id;
                bTemp[bDisplayKey] = item.attributes.output_name;

		        this.brandSelector.selectedItems.push(bTemp);

		        bTemp =[];
		    }, this);

		    var lPrimaryKey = this.locationSelector.config.primaryKey,
            	lDisplayKey = this.locationSelector.config.displayKey,
                lTemp = [];

			_.each(this.locationCollection.models, function(item) {
		        this.renderItem(item, 'location');
		        lTemp[lPrimaryKey] = item.attributes.id;
                lTemp[lDisplayKey] = item.attributes.output_name;

		        this.locationSelector.selectedItems.push(lTemp);

		        lTemp =[];
		    }, this);

			this.tags = '';
			var count = this.tagCollection.models.length,
			counter = 0;

		    _.each(this.tagCollection.models, function(item) {
		    	counter++;
		        this.renderItem(item, 'prod');
		        this.tags += item.attributes.output_name 
		        if(counter < count){
		        	this.tags += ', ';
		        }
		    }, this);

		    $(this.el).find('.matchSection.cat ul.cloud').append(this.addTemplate('category'));
		    
		    $(this.el).find('.matchSection.prod ul.cloud').append(this.addTemplate('keyword'));
		    
		    $(this.el).find('.matchSection.brand ul.cloud').append(this.addTemplate('brand'));
		    
		    $(this.el).find('.matchSection.location ul.cloud').append(this.addTemplate('location'));
			
		    var thisView = this;

		    $(this.el).delegate('li.add.category', 'click', function(){
		    	$('#waiting').show();
		    	setTimeout(function(){
		    		thisView.categorySelector.show();
		    	}, 500);
		    });

		    $(this.el).delegate('li.add.brand', 'click', function(){
		    	$('#waiting').show();
		    	setTimeout(function(){
		    		thisView.brandSelector.show();
		    	}, 500);
		    });

		    $(this.el).delegate('li.add.location', 'click', function(){
		    	$('#waiting').show();
		    	setTimeout(function(){
		    		thisView.locationSelector.show();
		    	}, 500);
		    });

		    $(this.el).undelegate('li.add.keyword', 'click');

		    $(this.el).delegate('li.add.keyword', 'click', function(){
		    	var data = thisView.tags;
		    	html = thisView.tagSelTemplate(data);

		    	$('#modal').addClass('tags');
		    	$('#modal .modalBody').html(html);
		    	thisView.openDialog();
		    	$('.applyTags').unbind().bind('click', function(e){
		    		e.preventDefault;
		    		thisView.applyTags();
		    	});
		    });
		},

		renderItem: function(item, ele) {
			var matchItem = new matchItemView({
				model: item
			});

			matchItem.parent = this;

			var elem = ".matchSection."+ele+" ul.cloud";
			$(this.el).find(elem).append(matchItem.render().el);
		},

		applyTags: function() {
			this.tags = $('#selectedTags').val();			

			var selectedTags = this.tags.split(','),
				trimmedTags = [];

			_.each(selectedTags, function(item){
				item = item.replace(/(^\s+|\s+$)/g, '');
				
				var findMe = {};
				findMe['output_name'] = item;
				var found = this.tagCollection.where(findMe)[0];

				if(found){
					level = found.attributes.level;
				}
				else {
					level = "M";
				}

				trimmedTags.push({
					output_name : item,
					level : level
				});

			}, this);

			this.tagCollection.reset();

			_.each(trimmedTags, function(item){
				this.tagCollection.add({
					output_name : item.output_name,
					level: item.level
				});
			}, this);

			$('#modal').overlay().close();
			this.renderItems();
		},

		onRefresh: function(e) {
			e.preventDefault;
			logActivity.logActivity('match-int-on-buy-tab-edit', '');
			this.parent.recommendedDetail.reRender();
			/*this.parent.sendToDetail.refresh = 1;
			this.parent.sendToDetail.getData();*/
		},

	    close: function(){
	    	this.remove();
	    },

        openDialog: function() { 
            $("#modal").overlay({
                mask: 'black',
                left: 'center',
                fixed: false,

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

                        $('#modalContact').css('left', posLeft);
                    });
                }
            });

            $('#modal').overlay().load();
        }
	});

	return matchDetailView;
});