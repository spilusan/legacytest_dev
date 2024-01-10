define(['jquery'], function($) {
	/**
	 * Initalise module (executes on document ready)
	 */
	function init() {
/*
		$.ajax({
			url: '/alert?filterBy=companyEnquiries',
			type: 'GET',
			cache: false,
		    error: function(request, textStatus, errorThrown) {
		    	response = eval('(' + request.responseText + ')');
		    	if( response.error != "User must be logged in"){
		    		alert("ERROR " + request.status + ": " + response.error);
		    	}
		    },
			success: function( response ){
				if( response.total > 0 ){
					enquiryReminderText = "You have " + response.total + " UNREAD RFQ" + ((response.total>1)?"s":"");
					//$("#global-pending-actions").html( response.total ).fadeIn(500);
					$("#global-pending-actions").html( enquiryReminderText ).fadeIn(500);
				}
			},
			beforeSend: function(xhr) {
				// header authentication for security (addon)
				xhr.setRequestHeader("SS-auth", "somekey");
			}
		});
*/
		
/*		
		var isBuyer = require('alert/isBuyer');
		var tnid = require('alert/tnid');
		var isShipServ = require('alert/isShipmate');

		// if a supplier, then show total number of RFQ missed
		if( isBuyer == false ){
			$.ajax({
				url: '/alert/company-enquiries?tnid=' + tnid,
				type: 'GET',
				cache: false,
			    error: function(request, textStatus, errorThrown) {
			    	response = eval('(' + request.responseText + ')');
			    	if( response.error != "User must be logged in"){
			    		alert("ERROR " + request.status + ": " + response.error);
			    	}
			    },
				success: function( total ){
					if( total > 10 ){
						enquiryReminderText = "You have " + total + " <a title='Click here to view' href='/profile/company-enquiry/' style='color:#ff5f40;'>MISSED RFQ" + ((total>1)?"s":"") + "</a>";
						$("#global-pending-actions")
						.css({"color":"#ff5f40","font-weight":"bold"}).html( enquiryReminderText ).fadeIn(500);
						$("#announcement-new-feature").hide();
					}
					else
					{
						if( isShipServ )
						$("#announcement-new-feature").html("<span style=\"color: #ff5f40;\">Hey Shipmate &gt;</span>");
						else
						$("#announcement-new-feature").html("see what's new &gt;");
					}
				}
			});
		}else{
			$("#global-pending-actions, #announcement-new-feature").hide();
		}
*/		
	
	}$(init);
});