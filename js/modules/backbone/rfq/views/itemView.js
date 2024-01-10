define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'../models/item',
	'text!templates/rfq/tpl/line-item.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	item,
	lineItemTpl
){
	var itemView = Backbone.View.extend({
		tagName: 'div',
		className: 'item',

		template: Handlebars.compile(lineItemTpl),

		events: {
			'click input.remove' : 'deleteItem'
		},

		rfqData: require("rfq/rfqData"),

		initialize: function(){
			_.bindAll(this, 'render', 'deleteItem');
			this.model.view = this;
		},

	    render: function(ele, id) {
	    	var row = $(ele).children().size() + 1;
	    	var crow = $('.item').length + 1;
	    	var lid = row - 1;

			var context = { id: id, row: row, lid: lid, uom: this.rfqData.uom};
			var html = this.template(context);

			$(this.el).html(html);

			if(row === 1) {
	    		$(this.el).find('input.remove').hide();
	    	}
	    	else {
	    		$(ele).find('input.remove').show();
	    	}

	        return this;
	    },

	    //delete an item
	    deleteItem: function(e) {
	    	e.preventDefault();
	    	ele = $(e.target).closest('.items');

	        this.model.destroy();
	        $(this.el).remove();
	        
	        $(ele).children().each(function(index) {
		    	var line = index + 1;
		    	$(this).find('.lineNo').html(line);
		    });

		    var row = $(ele).children().size();

		    if(row === 1) {
	    		$(ele).find('input.remove').hide();
	    	}

	    	$.each($('.item'), function(index, elem) { 
				  $(elem).find('.ln').html(index + 1);
			});
			
			this.parent.parent.parent.setTabIndex();

			idx = $(this.parent.ele).find('input.qty').length-2
		    var unbindEl = 'input.icomments:eq('+idx+')';
		    $(this.parent.ele).find(unbindEl).unbind();

			idx = $(this.parent.ele).find('input.qty').length-1;
		    var addEle = 'input.icomments:eq('+idx+')';
		    $(this.parent.ele).find(addEle).unbind().bind('keydown', {context: this},function(e){
		    	var code = e.keyCode || e.which;
		    	if (code == '9') {
			    	e.data.context.parent.addItem(e);
			    }
		    });
	    }
	});

	return itemView;
});