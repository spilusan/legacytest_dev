define(['jquery.auto-complete'], function($) {
	
	function addAutoCompleteToField(field) {
		$(field).autoComplete({
			backwardsCompatible: true,
			postData: {format:'json'},
			ajax:"/search/autocomplete/brands/format/json/",
			useCache: true,
			minChars: 0,
			width: 340,
			leftAdjustment: 1,
			preventEnterSubmit: true,
			onSelect: function(data) {
				$hidden = $(field).siblings('input.hidden.id');
				$hidden.val(data.id);
				$hidden.attr('data-name', data.value);
				return false;
			}
		});
	}
	
	/**
	 * Initalise module (executes on document ready)
	 */
	function init() {
		
		addAutoCompleteToField('#brandname1');
		
		$('#selectmorebrands').click(function(){
			var $brandsFieldset = $('fieldset#brands'),
				$newBrandField = $brandsFieldset.children(':first-child').clone(),
				$newBrandInput = $newBrandField.find('input.textfield'),
				$newBrandIdInput = $newBrandField.find('input.hidden.id');
				
			$newBrandInput.attr('id', 'brandname'+String($brandsFieldset.children().length+1));
			$newBrandField.find('label.error').remove();
			$newBrandInput.attr('name', $newBrandInput.attr('id'));
			$newBrandInput.removeClass('required').removeClass('error');
			$newBrandInput.val('');
			$newBrandField.find('label').html('&nbsp;').removeClass('required');
			$newBrandField.find('input').removeClass('required');
			
			$newBrandIdInput.attr('id', 'brandid'+String($brandsFieldset.children().length+1));
			$newBrandIdInput.attr('name', $newBrandIdInput.attr('id'));
			
			$newBrandField.hide();
			$brandsFieldset.append($newBrandField);
			$newBrandField.slideDown(1000);
			
			addAutoCompleteToField($newBrandInput);
		});
		
		$('.textfield.brandname').live('change', function(){
			$hidden = $(this).siblings('input.hidden.id');
			
			if($hidden.attr('data-name') !== $(this).val()) { 
				$hidden.val('');
				$hidden.attr('data-name', '');
			}
			
		})
		
		$('input.required').each(function(){
			$(this).blur(function(){
				if ($(this).attr("value")=="")
				{
					$(this).addClass("error");
				}
				else
				{
					if ($(this).hasClass("email"))
					{
						var emailPattern = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}$/;
						if (!emailPattern.test($(this).val())) {
							$(this).addClass("error");
						}	
						else
						{
							$(this).removeClass("error");
						}
					}
					else
					{
						$(this).removeClass("error");
					}
					
				}
			});
		});
		
		
		$('#submit').click(function(){
			$('input.required').each(function(){
				if ($(this).attr("value")=="")
				{
					$(this).addClass("error");
				}
			});
			if(!$('#acceptTerms').attr('checked')) {
				$('#acceptTerms').siblings('label').addClass('checkboxError');
			}
			
			if ($('input.error').length > 0 || $('label.checkboxError').length > 0) return false;
		});
		
		$('#acceptTerms').change(function(){
			if($(this).attr('checked')) {
				$(this).siblings('label').removeClass('checkboxError');
			}
			else
			{
				$(this).siblings('label').addClass('checkboxError');
			}
		});
		
	}$(init);
	
});