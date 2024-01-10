define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'../collections/emailItem',
	'text!templates/profile/companyApprovedSuppliers/tpl/emailItem.html'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	emailCollection,
	itemViewTpl
){
	var itemView = Backbone.View.extend({
		itemViewTemplate: Handlebars.compile(itemViewTpl),
		initialize: function () {
			_.bindAll(this, 'render');
			$(this.tagName).addClass('emailItemRow');
			this.collection = new emailCollection();
            this.collection.url = "/data/source/supplier/approvedsupplierEmails";
		},

		getData: function()
		{
			 var thisView = this;
                this.collection.reset();
                this.collection.fetch({
                data: $.param({
                    'type': 'get',
                }),
                complete: function(result) {
                    thisView.render(result); 
                }
            });
		},

		saveEmail: function(emailAddress)
		{
			 var thisView = this;
                this.collection.reset();
                this.collection.fetch({
                data: $.param({
                    'type': 'set',
                    'email': emailAddress,
                }),
                complete: function(result) {
                    thisView.render(result); 
                }
            });
		},

		removeEmail: function(emailAddress)
		{
			 var thisView = this;
                this.collection.reset();
                this.collection.fetch({
                data: $.param({
                    'type': 'remove',
                    'email': emailAddress,
                }),
                complete: function(result) {
                    thisView.render(result); 
                }
            });
		},

		render: function() {
			var thisView = this;
			$('#emailList').html('');
			_.each(this.collection.models, function(item){
                this.renderItem(item);
            }, this);
            $('a.remove').click(function(e) {
            	e.preventDefault();
            	thisView.removeItem(e);
            });

		},

		renderItem: function(item)
		{
			var html = this.itemViewTemplate(item.attributes);
			$('#emailList').append(html);
		},

		removeItem: function(e)
		{
			this.removeEmail($(e.currentTarget).prev().html());
		},

		isEmailExists: function(email)
		{
			for (key in this.collection.models) {
				if (this.collection.models[key].attributes.BSM_MAIL == email) {
					return true;
				}
			}
			return false;
		}

	});

	return itemView;
});


