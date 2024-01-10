/**
 * Handles form to invite brand owner from Supplier Page.
 * 
 * @author Elvir <eleonard@shipserv.com>
 */
define(['jquery.validate'], function ($) {
	"use strict";

	//Private 
	function init () {

		$('.searchFeedback .questionPane .question .yesBtn').bind('click', function () {
			$('.searchFeedback .questionPane .question').hide();
			$('.searchFeedback .questionPane .result').fadeIn();
			setTimeout(function () {
			    $('.searchFeedback').fadeOut(1000);
			}, 3000);
		});

		$('.searchFeedback .questionPane .question .noBtn').bind('click', function () {
		});

		$('.searchFeedback .feedbackPane .feedbackForm .optInSearchEngine').bind('click', function () {
			if ($(this).attr('checked') === false) {
				$('.feedbackForm .personalDetailForm').hide();
			} else {
				$('.feedbackForm .personalDetailForm').show();
			}
		});

		$('.feedbackPane .close').live('click', function () {
			$('.feedbackPane, .thankYouPane, .questionPane .result').hide();
			$('.questionPane, .questionPane .question').show();
		});

		$(".yesBtn, .noBtn, #sendFeedbackBtn").live('click', function () {
			// get the mood
			$('input[name="mood"]').val( ( $(this).hasClass('yesBtn') ) ? 'positive':'negative' );
			
			var err = [];

			// validate the email if user decided to optIn for the improvement on search engine 
			if( $('#negform-displayed').val() == 'Y' && $('input[name="mood"]').val() == 'negative' )
			{
				if( $('input[name="reason"]:checked').length == 0 ){
					err.push('Reason is empty')
				}

				if( $('.searchFeedback .feedbackPane .feedbackForm .optInSearchEngine').attr("checked") === true ){
					if( $('input[name="name"]').val() == "" ){
						err.push("name is empty");
					}
					
					if( $('input[name="email"]').val() == "" ){
						err.push("email is empty");
					}else{
					   var emailPattern = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}$/;
					   if( emailPattern.test($('input[name="email"]').val()) == false ){
						   err.push("email is invalid");
					   }
					}
				}
			}
			
			if( err.length > 0 )
			{
				alert("Please check the following: \n- " + err.join("\n- "));
				return false;
			}
			
			$.ajax({
				url: '/supplier/send-search-feedback/format/json/',
				type: 'POST',
				data: $('.feedbackPane form').serialize(),
				cache: false,
				success: function (response) {
					if('data' in response){
						$('.searchFeedback .feedbackPane, .searchFeedback .questionPane').hide();
							$('.searchFeedback .thankYouPane').show();
							setTimeout(function () {
								$('.searchFeedback').fadeOut(1000);
							}, 3000);
							$('#negform-displayed').val('N');
					}else{
						if(  $('input[name="mood"]').val() == 'positive' ){
							$('.searchFeedback .feedbackPane, .searchFeedback .questionPane').hide();
							$('.searchFeedback .thankYouPane').show();
							setTimeout(function () {
								$('.searchFeedback').fadeOut(1000);
							}, 3000);
							$('#negform-displayed').val('N');
						}else{
							$('.searchFeedback .questionPane').hide();
							$('.searchFeedback .feedbackPane').fadeIn();
							$('#negform-displayed').val('Y');
						}
					
					}
				}
			});
		});	
		$(".feedbackPane textarea[name='message']").unbind('keyup').bind("keyup", function(){
			var maxChar = 1000;
			if( $(this).val().length > maxChar )
			{
				$(this).val( $(this).val().substr(0,maxChar) )
				return false;
			}
		});
		
		$(".feedbackPane .close").live('click', function(){
			$('.searchFeedback').fadeOut(1000);
		});
	} $(init); //Exec on document ready

	return {};
});