define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'text!templates/profile/matchSettings/tpl/accountList.html'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	accountListTpl
){
	var accountListView = Backbone.View.extend({
		accountListTemplate: Handlebars.compile(accountListTpl),

		events: {
		},

		render: function() {

			var html = this.accountListTemplate(this.model);

			$('#accountList').html(html);
			$('.acListCb').uniform();

			return this;
		}

	});

	return accountListView;
});