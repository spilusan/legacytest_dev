define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/shared/hbh/general',
	'libs/jquery.uniform',
	'/js/jquery.auto-complete.js',
	'text!templates/shipmate/salesforce-synch/tpl/rate.html'
], function(
	$,
	_,
	Backbone,
	Hb,
	Hbh,
	Uniform,
	autocomplete,
	rateTpl

){
	var mainView = Backbone.View.extend({
		rateTemplate: Handlebars.compile(rateTpl),
		events: {
		//	'click #sychBtn' : 'onUpdateClick'
		},
		initialize: function() {
			
			var thisView = this;
			
			$(document).ready(function(){
				$('#rateSych').autoComplete({
					backwardsCompatible: true,
					ajax: '/profile/company-search/format/json/type/v/excUsrComps/1/excNonJoinReqComps/1',
					useCache: false,
					minChars: 3,
					spinner: function(event, status){
						if (status.active) {
							thisView.autocompletInProgress = true;
							$(".tnidAutocomplete").show();
						} else {

							$('#waiting').hide();
							$(".tnidAutocomplete").hide();
						}
					},
					onShow: function(){
						thisView.autocompletInProgress = true;
						 $(".tnidAutocomplete").hide();
					},

					list: 'auto-complete-list-wide',
					preventEnterSubmit: true,
					onSelect: function(data) {
	                    $('#rateSych').focus();
	                    $('#updateTnid').html(data.pk);
	                    $('#updatedTnidRates').empty();
	                    $('#waiting').show();
	                    $('#warning').hide();
	        			$.ajax({
	    					type: 'GET',
	    					url: '/shipmate/rate-synch-data',
	    					data: {
	    						'mode' : 'getRate',
	    						'tnid': data.pk,
	    					},
	    					cache: false,
	    					success: function(result){
	    						$('#waiting').hide();
	    						if (result.status == 'error') {
	    							$('#warning').html(result.exception);
	    							$('#warning').show();
	    						} else if (result.status == 'ok') {
	    							html = thisView.rateTemplate(result.data);
	    							$('#selectedTnidRates').html(html);
	    							$('#updatedTnidRates').empty();
	    						} else {
	    							$('#warning').html('Unknown system error, please try again later');
	    							$('#warning').show();
	    						}
	    						
	    					},
	    					error: function(error) {
	    						 $('#waiting').hide();
	    						 $('#warning').html('Unknown system error, please try again later');
	    						 $('#warning').show();
	    					}
	    				});
						return false;
					},

				});
				
				$('#sychBtn').click(function(e){
					e.preventDefault();
					thisView.onUpdateClick();
				});
				
				$('#rateSych').click(function(e){
					$(this).val('');
				});
				
			});

		},
		
		onUpdateClick: function(e) {
			var thisView = this;
			var $updateTnid = $('#updateTnid').html();
			if ($updateTnid !== '') {
				$('#waiting').show();
				$('#warning').hide();
				$.ajax({
					type: 'GET',
					url: '/shipmate/rate-synch-data',
					data: {
						'mode' : 'synchRate',
						'tnid': $updateTnid,
					},
					cache: false,
					success: function(result){
						$('#waiting').hide();
						if (result.status == 'error') {
							$('#warning').html(result.exception);
							$('#warning').show();
						} else if (result.status == 'ok') {
							html = thisView.rateTemplate(result.data);
							$('#updatedTnidRates').html(html);
						} else {
							$('#warning').html('Unknown system error, please try again later');
							$('#warning').show();
						}
					},
					error: function(error) {
						$('#warning').html('Unknown system error, please try again later');
						$('#warning').show();
						$('#waiting').hide();
					}
				});
			} else {
				$('#waiting').hide();
				$('#warning').html('Please select TNID first');
				$('#warning').show();
			}
		}

	});

	return new mainView();
});
