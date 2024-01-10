/**
 * @author Elvir <eleonard@shipserv.com>
 */
define(['jquery'], function($) {
	$("#changeTnid").live('click', function() {
		location.href="/profile/user-management?tnid=" + $('input[name="tnid"]').val();
	});
	$('#sendButton').live('click', function() {
		
		if( $('input[name="email"]').val() == "" || $('input[name="tnid"]').val() == "" || $('input[name="level"]:checked').val() === undefined || $('input[name="agree"]:checked').val() === undefined){
			alert("Please check the form again");
			return false;
		}
		
	   var emailPattern = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}$/;
	   if( emailPattern.test($('input[name="email"]').val()) == false ){
			alert("Email is invalid");
			return false;
	   }
		
	   $.post('/profile/add-user-send-email/format/json', { eml: $('input[name="email"]').val(), type: 'v', id: $('input[name="tnid"]').val(), userLevel: $('input[name="level"]:checked').val() }, function(data){
			if (data.ok)
			{
				var fullName = 'Unknown Name';
				var firstname = data['userCompany']['firstName'] == null ? '' : data['userCompany']['firstName'];
				var lastname = data['userCompany']['lastName'] == null ? '' : data['userCompany']['lastName'];
				
				if ((firstname + lastname) != '')
				{
					fullName = firstname + ' ' + lastname;
				}
				alert("User has been invited to join this company.");
				window.location.reload(true);
			}
			else
			{
				// Fail - just show alert for now
				alert(data.msg);
			}
		}, 'json');
		return false;	
	});

	
});