define([
	'jquery',
	'underscore',
	'Backbone',
	'libs/jquery.validity.custom.output.match',
	'libs/jquery.tools.overlay.modified',
	'../views/filters'
], function(
	$,
	_, 
	Backbone,
	ValidityCustom,
	Modal,
	filtersView
){
	var matchDashboardView = Backbone.View.extend({
		el: $('body'),

		events: {
			'focus input.date' 			 : 'resetDate',
			'blur input.date'  			 : 'defaultDate',
			'click input[type="submit"]' : 'submitForm'
		},

		resetDate: function(e) {
			if ($(e.target).val() === "dd-mm-yyyy") {
				$(e.target).val('');
			}
		},

		defaultDate: function(e) {
			if($(e.target).val() === "") {
				$(e.target).val('dd-mm-yyyy');
			}
		},

		validateForm: function() {
			return true;
			$.validity.setup({ outputMode:"custom" });

		    // Start validation:
		    $.validity.start();
		    
		    // Validator methods go here:
		    if ($('input[name="from"]').val() !== "dd-mm-yyyy" && $('input[name="to"]').val() !== "dd-mm-yyyy") {
			    $('input[name="to"]')
			    	.match('date','Please enter a valid date')
			    	.greaterThanOrEqualTo(new Date($('input[name="from"]').val()), 'The to date can not be before the from date.');
			    $('input[name="from"]').match('date','Please enter a valid date');
			}
			else if($('input[name="from"]').val() == "dd-mm-yyyy" && $('input[name="to"]').val() !== "dd-mm-yyyy"){
				$('input[name="from"]').val('');
				$('input[name="from"]').require('Please select a from date');
			}
			else if($('input[name="from"]').val() !== "dd-mm-yyyy" && $('input[name="to"]').val() == "dd-mm-yyyy"){
				$('input[name="to"]').val('');
				$('input[name="to"]').require('Please select a to date');
			}
			else {
				$('input[name="from"]').val('');
				$('input[name="to"]').val('');
			}
			// End the validation session:
		    var result = $.validity.end();
		    
		    // Return whether the form is valid
		    $('.clear.err').remove();
		    return result.valid;
		},

		submitForm: function(e){
			e.preventDefault();
			if (!($('input[name="show"]').hasClass('disabled'))) {
				if (this.validateForm()) {
					$('form').submit();
				}
			}
		}
	});

	return new matchDashboardView;
});