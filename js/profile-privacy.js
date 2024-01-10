$(document).ready(function(){
	
	$('#global_anon').change(function () {
		if (this.value == 'selective')
		{
			$('#profile-privacy-selective').slideDown(300);
		}
		else
		{
			$('#profile-privacy-selective').slideUp(300);
		}
	});
	
	$('input[name=privacy_search_supplier]').click(function() {
		if (this.value == 'Type supplier name to exclude') {
			$(this).val('');
		}
	});
	
	$('input[name=privacy_search_supplier]').blur(function() {
		if (this.value == '') {
			$(this).val('Type supplier name to exclude');
		}
	});
	
	if( $('input[name=privacy_search_supplier]').length > 0 ){
		$('input[name=privacy_search_supplier]').autoComplete({
			backwardsCompatible: true,
			ajax: '/profile/supplier-search/format/json',
			useCache: false,
			minChars: 3,
			list: 'auto-complete-list-wide',
			preventEnterSubmit: true,
			onSelect: function(data) {
				if ($('#selective_anon-' + data.code ).length == 0)
				{
					$('#selective_anon-element').append(
						'<label for="selective_anon-' + data.code + '"><input type="checkbox" class="checkbox" checked="checked" value="' + data.code + '" id="selective_anon-' + data.code + '" name="selective_anon[]">' + data.value + '</label>');
				}
				return false;
			},
			width: 400
		});
	}
	setTimeout(function(){$('h3[class="success-message"]').slideUp(500);}, 5000);
	
});
