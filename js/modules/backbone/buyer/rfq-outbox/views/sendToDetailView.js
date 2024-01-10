define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'../collections/rfqDetail',
	'../views/sendToRowView',
	'text!templates/buyer/rfq-outbox/tpl/sendToSection.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	sendToDetail,
	sendToRow,
	sendToDetailTpl
){
	var sendToDetailView = Backbone.View.extend({
		tagName: 'div',
		className: 'details',
		template: Handlebars.compile(sendToDetailTpl),

		refresh: 0,

		initialize: function(){
			_.bindAll(this, 'render');
			this.collection = new sendToDetail();
			this.collection.url = '/buyer/search/rfq-suppliers/';
		},

		getData: function(id){
			if(id)
				this.rfqRefNo = id;

			this.collection.reset();

			var thisView = this;
			
			this.fetchXHR = this.collection.fetch({
				add: true,
				data: $.param({ 
					rfqRefNo: this.rfqRefNo
				}),
				complete: function(){
					thisView.render();
				}
			});
			
			return this;
		},

	    render: function() {
	    	this.elem = '.section.sendTo .details table tbody#sent' + this.rfqRefNo;
	    	var data = new Object();
	    	data.id = this.rfqRefNo;
			var html = this.template(data);

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
	    	
	    	if($(this.el).find('.section.recommended .details').length < 1 && this.refresh != 1){
	    		$(this.parent.el).find('input[name="recommended"]').trigger('click');
	    	}
	    	this.parent.sendToDetail.refresh = 0;

	    	/* TODO add sub table width calculation */
	    	var newW = $(window).width()-900;
    		$('.section.match').width(newW);
    		$('.section.sendTo').width(newW);
    		$('.section.recommended').width(newW);	    },

	    renderItems: function() {
	    	_.each(this.collection.models, function(item) {
		        this.renderItem(item);
		    }, this);
	    },

	    renderItem: function(item) {
		    var sendToListRow = new sendToRow({
		        model: item
		    });

		    $(this.elem).append(sendToListRow.render().el);
		},

	    close: function(){
	    	this.remove();
	    	//fix height of body container due to absolute pos of content container
	    	var height = 0;
	    	if($('#content').height() < $('#sidebar').height()){
	    		height = $('#sidebar').height();
	    	}
	    	else {
	    		height = $('#content').height() + 25;
	    	}

	    	$('#body').height(height);
	    }
	});

	return sendToDetailView;
});