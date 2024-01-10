define(['jquery'], function($) {
	$('#block-user').live('click', function(){	
		var url = "";
		url += "/tnid/" + $(this).attr('tnid');
		url += "/uid/" + $(this).attr('userId');
		url += "/a/Y";
		
		$.ajax({
			url: '/enquiry/blocked-sender' + url,
			type: 'GET',
			cache: false,
		    error: function(request, textStatus, errorThrown) {
		    	response = eval('(' + request.responseText + ')');
		    	if( response.error != "User must be logged in"){
		    		alert("ERROR " + request.status + ": " + response.error);
		    	}
		    },
			success: function( response ){
				alert("You have successfully blocked this Buyer.");
			}
		});	
	});
	
	$('#reply-button').live('click', function(){
		if( $(this).attr('status') == 'replied' ){
			confirmationMessage = "Your colleague " + $(this).attr('actor') + " clicked this button on " + $(this).attr('repliedDate') + " and so might have already replied to this RFQ.\n\nWould you like to continue to reply to this RFQ?";
			if( confirm(confirmationMessage) == false ) return false
		}
		if( $(this).attr('status') == 'declined' ){
			confirmationMessage = "Your colleague declined this RFQ on " + $(this).attr('declinedDate') + ".\n\nWould you like to continue to reply to this RFQ?";
			if( confirm(confirmationMessage) == false ) return false
		}
		var urlRedirect = $(this).attr('url');
		var url = "";
		url += "/tnid/" + $(this).attr('tnid');
		url += "/enquiryId/" + $(this).attr('enquiryId');
		
		var win = window.open($(this).attr('mailto'),'emailWindow');

		if (win && win.open &&!win.closed) {
			win.close();
		}
		
		$.ajax({
			url: '/profile/enquiry-update-response-date' + url,
			type: 'GET',
			cache: false,
		    error: function(request, textStatus, errorThrown) {
		    	response = eval('(' + request.responseText + ')');
		    	
		    	if( response.error != "User must be logged in"){
		    		alert("ERROR " + request.status + ": " + response.error);
		    	}
		    },
			success: function( response ){
				setTimeout(function(){ location.href = urlRedirect; }, 500);
			}
		});	
	});
	
	$('#decline-button').live('click', function(){
		if( $(this).attr('status') == 'replied' ){
			confirmationMessage = "Your colleague " + $(this).attr('actor') + " might have responded to this RFQ on " + $(this).attr('repliedDate') + ".\n\nWould you like to continue to decline this RFQ?";
			if( confirm(confirmationMessage) == false ) return false
		}
		if( $(this).attr('status') == 'declined' ){
			confirmationMessage = "Your colleague clicked this button on " + $(this).attr('declinedDate') + ".\n\nWould you like to continue to decline this RFQ?";
			if( confirm(confirmationMessage) == false ) return false
		}
		$("#decline-form").submit();
	});


});