$(document).ready(function(){
	// check for what is/isn't already checked and match it on the fake ones
	$("input:checkbox").live('each', function() {
		(this.checked) ? $("#fake"+this.id).addClass('fakechecked') : $("#fake"+this.id).removeClass('fakechecked');
	});
	// function to 'check' the fake ones and their matching checkboxes - also posts an AJAX call to update the basket
	$(".fakecheck").live('click',function(){
		if ($(this).hasClass('fakechecked'))
		{
			$(this).removeClass('fakechecked');
			$.post('/enquiry/remove-supplier-from-basket/format/json/', { tnid: this.id }, function(data) { }, "json");
		}
		else
		{
			$(this).addClass('fakechecked');
			$.post('/enquiry/add-supplier-to-basket/format/json/', { tnid: this.id }, function(data) { }, "json");
		}
		
		$(this.hash).trigger("click");
		// trigger an ajax call to add this supplier to the basket
		
		return false;
	});
});