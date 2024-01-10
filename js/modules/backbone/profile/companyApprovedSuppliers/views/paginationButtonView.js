define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'text!templates/profile/companyApprovedSuppliers/tpl/paginationButton.html',
	'text!templates/profile/companyApprovedSuppliers/tpl/paginationNext.html',
	'text!templates/profile/companyApprovedSuppliers/tpl/paginationPrior.html'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	buttonViewTpl,
	nextButtonViewTpl,
	priorButtonViewTpl
){
	var buttonView = Backbone.View.extend({
		tagName: 'span',
		buttonViewTemplate: Handlebars.compile(buttonViewTpl),
		nextButtonViewTemplate: Handlebars.compile(nextButtonViewTpl),
		priorButtonViewTemplate: Handlebars.compile(priorButtonViewTpl),

		initialize: function () {
			_.bindAll(this, 'render');
			$(this.el).addClass()
		},

		render: function() {

			var thisView = this;
	
			switch(this.model.buttonType)
			{
				case 1:
					var html = this.priorButtonViewTemplate();
					break;
				case 2:
					var html = this.nextButtonViewTemplate();
					break;
				default:
					var html = this.buttonViewTemplate(this.model);
					break;
			}
			if (this.model.isSelected) {
				$(this.el).addClass('selected');
			}

			$(this.el).html(html);

			$(this.el).unbind().bind('click', function(e){
				e.preventDefault;
				thisView.parent.currentPage = thisView.model.pageNr;
				thisView.parent.getData();
			});
			return this;
		},

	});

	return buttonView;
});

