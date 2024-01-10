/**
 * Handles form to invite brand owner from Supplier Page.
 * 
 * @author Elvir <eleonard@shipserv.com>
 */
define(['jquery','modal', 'jquery.validate', 'cssp!css/jqModal.css?cssp-jQmodal', 'cssp!css/forms.css?cssp-forms', 'cssp!css/brandowner/invite-brand-owner-form.css?cssp-invite-brand-owner-form'], 
	function($) {
		
		//Private 
		function init(){
			
			var $popup = $('#brand_invitation_form_popup');
			
			// bind the jquery module with the link
			$('.brand-verification-form').live('click', function(e) {
				
				e.preventDefault();
				
				var brandId = $(this).attr('brandId');
				var supplierId = $(this).attr('supplierId');
				var supplierName = $(this).attr('supplierName');
				var authLevel = $(this).attr('authLevel');

				$popup.load('/supplier/invite-brand-owner-form/brandId/'+brandId, function(){
					$('#requestedAuthLevel').val( authLevel );	
					$('#supplierId').val( supplierId );	
					$('#companyId').val( supplierId );	
					$('#brandId').val( brandId );
					$('#supplierName').val( supplierName );
					
					$('form.main').validate({
						errorPlacement: function(error, element) {
							if(element.attr('id') == "acceptTerms") {
								element.siblings('label').addClass('checkboxError');
							}else{
								error.appendTo(element.parent('div'));
							}
						},
						submitHandler: function(){
							// attach action when clicking the button
							$.ajax({
								url: '/supplier/invite-brand-owner-to-authorise',
								type: 'POST',
								data: $('form.main').serialize(),
								cache: false,
							    error: function(request, textStatus, errorThrown) {
							    	response = eval('(' + request.responseText + ')');
							    	alert("ERROR " + request.status + ": " + response.error);					    	
							    },
								success: function( response ){
									// close the window
									$('.spinner').css('display', 'none');
									$($popup).fadeOut(300);
                                    $('#mask').fadeOut(500);
								},
								beforeSend: function(xhr) {
									$('.spinner').css('display', 'inline-block');
									// header authentication for security (addon)
									xhr.setRequestHeader("SS-auth", "somekey");
								}
							});
						}
					});
                    
					if (!$('#mask')){
                        bodyEl = $$('body')[0];
                        bodyEl.append('<div id="mask"></div>');
                    }

                    if (!$($popup)){
                        bodyEl = $$('body')[0];
                        bodyEl.append('<div id="brand_invitation_form_popup"></div>');
                    }

                    //Get the screen height and width
                    var maskHeight = $(document).height();
                    var maskWidth = $(window).width();

                    //Set heigth and width to mask to fill up the whole screen
                    $('#mask').css({'width':maskWidth,'height':maskHeight});
                    
                     //transition effect

                    //Set the popup window to center
                    
                    var contHeight = 364;
                    var contWidth = 645;
                    
                    var postop = ($(window).height() - contHeight - 150 ) / 2+$(window).scrollTop();
                    var posleft = ( $(window).width() - contWidth ) / 2+$(window).scrollLeft();
                                     
                    if (postop < 40) {
                        postop = 40;
                    }
                    
                    if (posleft < 40) {
                        posleft = 40;
                    }

                    $($popup).css("position","absolute");
                    $($popup).css("z-index","10700");
                    $($popup).css("width",contWidth);
                    $($popup).css("height",contHeight);
                    $($popup).css("top", postop);
                    $($popup).css("left", posleft);
                    
                    
                    
                    //transition effect
                    $('#mask').fadeIn(500);
                    $('#mask').fadeTo("slow",0.8);
                    $($popup).fadeIn(300);
                    
                    
				});
			});
			
		}$(init); //Exec on document ready
		
		
		return {};

});