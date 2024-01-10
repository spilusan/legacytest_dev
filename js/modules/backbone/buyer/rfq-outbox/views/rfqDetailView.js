define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/shared/hbh/general',
	'backbone/shared/hbh/inbox',
	'../collections/rfqDetail',
	'text!templates/buyer/rfq-outbox/tpl/rfqSection.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	genHbh,
	inboxHbh,
	rfqDetail,
	rfqDetailTpl
){
	var rfqDetailView = Backbone.View.extend({
		tagName: 'div',
		className: 'details',
		template: Handlebars.compile(rfqDetailTpl),
		tnid: require('buyer/rfq-outbox/tnid'),

		initialize: function(){
			_.bindAll(this, 'render');
			this.collection = new rfqDetail();
			this.collection.url = '/buyer/search/rfq-details';
		},

		getData: function(id, hash){
			var thisView = this;
			this.collection.fetch({
				data: $.param({
					rfqRefNo: id,
					hash: hash
				}),
				complete: function() {
					thisView.render();
				}
			});

			return this;
		},

	    render: function() {
	    	var data = this.collection.models[0].attributes;
			var html = this.template(data);
			$(this.el).html(html);
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

	return rfqDetailView;
});