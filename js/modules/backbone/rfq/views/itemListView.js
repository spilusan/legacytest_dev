	define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'libs/jquery.uniform',
 	'../collections/items',
 	'../models/item',
 	'../views/itemView'
], function(
	$, 
	_, 
	Backbone,
	Uniform,
	itemList,
	item,
	itemView
){
	var itemListView = Backbone.View.extend({

		el: $('div.itemBox'),
		
		ele: $('.items'),

		id: 1,

		initialize: function () {
			_.bindAll(this, 'render', 'renderItem', 'addItem');

			var items = [
				{id: "1", qty: "", partType: "", partNo: "", desc: "", comm: ""}
			];

		    this.collection = new itemList(items);

		    this.collection.on('add', this.renderItem, this);
		    this.collection.on('remove', this.removeItem, this);
		},

		render: function(elem) {
		    this.ele = elem;

		    var children = this.ele.children('.item');
		    $(children).remove();

		    _.each(this.collection.models, function(item) {
		        this.renderItem(item);
		    }, this);

		    $('.button.addLine').unbind().bind('click', {context: this}, function(e) {
  				e.data.context.addItem(e);
			});
		},

		newSection: function(elem) {
		    this.ele = elem;

		    var children = ele.children('.item');
		    $(children).remove();

		    $('.button.addLine').unbind().bind('click', {context: this}, function(e) {
  				e.data.context.addItem(e);
			});

			$(this.ele).siblings('.lineItemFoot').find('.addLine').click();
		},

		renderItem: function(item) {
		    var theItemView = new itemView({
		        model: item
		    });

		    theItemView.parent = this;

		    var uniforms = $.uniform.elements;
		    if(uniforms.length > 3) {
		    	$.uniform.restore('.items select');
		    }
		    
		    var sid = $(this.ele).parent().find('input[name="sid"]').val();

		    $(this.ele).append(theItemView.render(this.ele, sid).el);

		    var idx = $(this.ele).find('input.qty').length-1;
		    var elem = 'input.qty:eq('+idx+')';
		    if($('.section').length === 1 && idx === 0) {
		    	$('input[name="rRfqSubject"]').focus();
		    }
		    else {
		    	$(this.ele).find(elem).focus();
		    }

		    idx = $(this.ele).find('input.qty').length-2
		    var unbindEl = 'input.icomments:eq('+idx+')';
		    $(this.ele).find(unbindEl).unbind();

		    idx = $(this.ele).find('input.qty').length-1;
		    var addEle = 'input.icomments:eq('+idx+')';
		    $(this.ele).find(addEle).unbind().bind('keydown', {context: this},function(e){
		    	var code = e.keyCode || e.which;
		    	if (code == '9') {
			    	e.data.context.addItem(e);
			    }
		    });
		    
		    $.each($('.item'), function(index, elem) { 
				  $(elem).find('.ln').html(index + 1);
			});

		    $('.items select').uniform();
		},

		addItem: function(e) {
			e.preventDefault();
			this.id++;

			this.ele = $(e.target).closest('.itemBox').find('.items');

	        this.collection.add(new item({id: this.id}));
	        this.parent.parent.setTabIndex();
		},

		removeItem: function(removedModel) {
		    var removed = removedModel.attributes;

		    _.each(itemList, function(item) {
		        if (_.isEqual(item, removed)) {
		            itemList.splice(_.indexOf(itemList, item), 1);
		        }
		    });
		}
	});

	return new itemListView;
});