define([
	'jquery',
	'underscore',
	'Backbone',
	'libs/jquery.validity.min',
    'libs/jquery.validity.custom.output.default',
], function(
	$,
	_, 
	Backbone, 
	validity,
    validityCustom
){
	var contactView = Backbone.View.extend({
		
		el: $('body'),

		events: {
			'click input[type="submit"]' : 'postForm'
		},

		initialize: function () {

		},

		validateForm: function(){
			$.validity.setup({ outputMode:"custom" });

			$.validity.start();

			$('input[name="name"]').require('Please enter your Name.');
			$('input[name="email"]').require('Please enter your Email Address.')
			.	match('email', 'Please enter a valid Email Address');
			$('textarea').require('Please enter a Message.');

			var result = $.validity.end();

			return result.valid;
		},

		postForm: function(e){
			e.preventDefault();
			if(this.validateForm()){
				$('form#contact').submit();
			}
		}
	});

	return new contactView;
});
