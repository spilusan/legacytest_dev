define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'text!templates/rfq/tpl/section.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	sectionTpl
){
	var sectionView = Backbone.View.extend({
		tagName: 'div',
		className: 'section',

		template: Handlebars.compile(sectionTpl),

		events: {
			'click input.removeSection' : 'deleteSection',
			'click .addSecDet'          : 'showSecDet',
			'click .removeSecDet'       : 'hideSecDet',
			'click .removeSec'			: 'deleteSection'
		},

		initialize: function(){
			_.bindAll(this, 'render');
		},

	    render: function(id) {
	    	this.html = this.template({id: id});
			$(this.el).html(this.html);
	        return this;
	    },

	    deleteSection: function(e) {
	    	e.preventDefault();
	        $(this.el).remove();

	        var row = $('.sectionList .section').size();
	        
	        if(row === 1) {
	    		$('.sectionList .section .removeSec').hide();
	    	}

	    	else {
	    		$('.sectionList input.removeSec').show();
	    	}

	    	$.each($('.item'), function(index, elem) { 
				  $(elem).find('.ln').html(index + 1);
			});
			this.parent.parent.setTabIndex();
	    },

		showSecDet: function(e) {
			e.preventDefault();

			var ele = $(this.el).find('fieldset');
			$(ele).slideDown('fast');
			$(ele).addClass('open');
			$(ele).parent().find('.addSecDet').hide();
			$(ele).parent().find('.removeSecDet').show();
			this.parent.parent.setTabIndex();
		},

		hideSecDet: function(e) {
			e.preventDefault();

			var ele = $(this.el).find('fieldset');
			$(ele).slideUp('fast');
			$(ele).removeClass('open');
			$(ele).parent().find('.removeSecDet').hide();
			$(ele).parent().find('.addSecDet').show();

			var inputs = $(ele).find('input');
			$(inputs).val("");
			this.parent.parent.setTabIndex();			
		}
	});

	return sectionView;
});