define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'libs/jquery.dateFormat',
	'text!templates/profile/companyApprovedSuppliers/tpl/childRow.html'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	DateFormat,
	itemViewTpl
){
	var itemView = Backbone.View.extend({
		tagName: 'tr',
		itemViewTemplate: Handlebars.compile(itemViewTpl),
		events: {
			'click input.remove' 			: 'onRemove',
		},

		initialize: function () {
			_.bindAll(this, 'render');

		},

		setParent: function( parent )
		{
			this.parentView = parent;
		},

		render: function() {
			var data = this.model;
			data.ukLastUsed = this.reformatDate(this.model.lastused);

			var html = this.itemViewTemplate(data);
			$(this.el).html(html);
			return this;
		},

		onRemove: function(e) {

			var thisView = this;
			this.parentView.silentAjax = true;
			e.preventDefault();
			$(e.currentTarget).hide();
			$(e.currentTarget).parent().addClass('spinner');
			var data = 'supplierBranchCode='+this.model.tnid;
			$.ajax({
			  type: "POST",
			  url: '/profile/company-approved-suppliers-del',
			  data: data,
			  success: function(result){
			  	//thisView.parentView.getData();


			  	$(e.currentTarget).parent().parent().remove();
			  	thisView.parentView.silentAjax = false;
			  },
			  error: function(error) {
				  thisView.parentView.silentAjax = false;
			  }
			});
		},

		reformatDate: function(dateString)
		{
			if (dateString.length == 10)
			{
				var dParts = dateString.split('-');
				var newDate = new Date(parseInt(dParts[0]), parseInt(dParts[1])-1, parseInt(dParts[2]));
				return $.format.date(newDate, "dd MMM yyyy");
			} else {
				return '';
			}
		}

	});

	return itemView;
});


