define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../views/sectionView',
 	'../views/itemListView'
], function(
	$, 
	_, 
	Backbone,
	sectionList,
	itemList
){
	var sectionListView = Backbone.View.extend({

		el: $('div.rfqDet'),

		id: 0,

		initialize: function () {
			_.bindAll(this, 'render', 'addSection');
		},

		render: function() {
		    this.renderSection(this.id);

		    ele = $('.detailBox .sectionList .items:last');

		    itemList.parent = this;
		    itemList.render(ele);

		    var row = $('.sectionList .section').size();

		   $('.addSection').unbind('click').bind('click', {context: this}, function(e) {
  				e.data.context.addSection(e);
			});

		    if(row === 1) {
	    		$('.sectionList .section .removeSec').hide();
	    	}
	    	else {
	    		$('.sectionList input.removeSec').show();
	    	}
		},

		renderSection: function(id) {
		    var theSectionView = new sectionList({});
		    theSectionView.parent = this;
		    $('div.sectionList').append(theSectionView.render(id).el);
		},

		addSection: function(e) {		
			e.preventDefault();
			this.id++;

	        this.renderSection(this.id);

	        ele = $('.rfqDet').find('.detailBox .sectionList .items:last');

		    itemList.newSection(ele);

		    var row = $('.sectionList .section').size();

		    if(row > 1) {
		    	$('.sectionList input.removeSec').show();
		    }
		    this.parent.setTabIndex();

		    $('.addSection').unbind('click').bind('click', {context: this}, function(e) {
  				e.data.context.addSection(e);
			});
   		}
	});

	return new sectionListView;
});