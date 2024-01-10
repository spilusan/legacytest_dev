$(document).ready(function(){

	$('#add-people').click(function () {

		if ($('#add-people-block').css('display') !== 'block') {
			$('#add_btn').removeClass('dblue');
			$('#add_btn').addClass('dbluePushed');
			$('#add-people-block').slideDown(300);
		} else {
			$('#add_btn').removeClass('dbluePushed');
			$('#add_btn').addClass('dblue');
			$('#add-people-block').slideUp(300);
		}
	
		return false;
	});
	
	$('input[name=person_search]').click(function() {
		if (this.value == 'Type in the email of the person') {
			$(this).val('');
		}
	});
	
	$('input[name=person_search]').keypress(function(e) {
		if (e.keyCode == 13) {
			$('a[class="invite_person_button"]').click();
		}
	});
	
	$('input[name=person_search]').blur(function() {
		if (this.value == '') {
			$(this).val('Type in the email of the person');
		}
	});
	
	$('a[class="invite_person_button"]').click(function() {
		if( $("#agree").length == 1 )
	 	{
			if( $("input[name='level']:checked").length == 0 || $("#agree").attr('checked') != 'checked' )
	 		{
				alert("Please make sure that you choose the user type, and you have checked the checkbox!");
				return false;
	 		}
	 		
	 	}
		
		if( typeof $('input[name="level"]:checked').val() == "undefined" )
			uLevel = "USR";
		else
			uLevel = ($('input[name="level"]:checked').val()!="")?$('input[name="level"]:checked').val():"ADM";
		
		if ($('#person_search_ctype').val() == 'b') {
			var confMessage = 'You are adding user email address ' + $('#person_search').val()  + ' to '+ $('#person_search_name').val() +', with ORG ID ' + $('#person_search_cid').val() + '. Have you checked that this person is employed by this company? Are you sure you want to add this user?';
		} else {
			var confMessage = 'You are adding user email address ' + $('#person_search').val()  + ' to '+ $('#person_search_name').val() +', with TNID ' + $('#person_search_cid').val() + '. Have you checked that this person is employed by this company? Are you sure you want to add this user?';
		}

		if( confirm(confMessage) === false ) return false;

		$('#waiting').show()
		$.post('/profile/add-user/format/json', { eml: $('#person_search').val(), type: $('#person_search_ctype').val(), id: $('#person_search_cid').val(), userLevel: uLevel }, function(data){
			$('#waiting').hide()
			if (data.ok)	
			{
				// OK - add user block and slide back add section
				
				var fullName = 'Unknown Name';
				
				var firstname = data['userCompany']['firstName'] == null ? '' : data['userCompany']['firstName'];
				var lastname = data['userCompany']['lastName'] == null ? '' : data['userCompany']['lastName'];
				
				if ((firstname + lastname) != '')
				{
					fullName = firstname + ' ' + lastname;
				}

				// added by Yuriy Akopov on 2015-01-06, DE6313
				var userMsg = ''; // <h4 title="Email is not confirmed" style="color: #ff9e38; line-height:14px;">kimderaco12@yahoo.com<br><span style="font-weight:normal;">This user still need to verify his/her email</span></h4>
				if (data.pending) {
					userMsg =
						'<h4 title="Email is not confirmed" style="color: #ff9e38; line-height:14px;">'
						+ String(data['userCompany']['email'])
						+ '<br/>'
						+ '<span style="font-weight:normal;">This user still need to verify his/her email</span>'
						+ '</h4>'
					;
				} else {
					userMsg = '<h4>' + String(data['userCompany']['email']) + '</h4>';
				}
				
				if (data['showTradingAccount'] && data['userCompany']['companyType'] === 'b') {
					tradingAccountLink = '<span class="remove-user"> | </span> <a href="' + data['userCompany']['userId'] + '//' + data['userCompany']['companyId'] + '//Unknown Name" class="showBuyerBranches" title="Access to trading accounts" class="remove-user"><span>Trading accounts</span></a>';
				} else {
					tradingAccountLink = '';
				}
				
				$('#approved-people').prepend(
					'<div class="approved-block" id="userid-' + data['userCompany']['userId'] + '-' + data['userCompany']['companyType'] + '-' + data['userCompany']['companyId'] + '">'
						+ '<div class="logo-holder"><img src="/images/layout_v2/profile/user.png" /></div>'
						+ '<h3>' + fullName + '</h3>'
						+ userMsg
						// + '<h4>' + data['userCompany']['email'] + '</h4>'
						+ '<hr class="dotted" style="width:460px; margin-bottom:10px;" />'
						+ '<div class="clear"></div>'
						+ '<div class="icons-holder">'
							+ '<div class="company-icons sml-action-set">'
								+ '<div style="float:left;"><input type="checkbox" class="administrator" value="1" id="admin-' + data['userCompany']['userId'] + '" ' + ( ($('input[name="level"]:checked').val()=="ADM")?"checked='checked'":"" ) + ' style="margin-right: 5px;"/><label style="font-size:10px; margin-right:5px; padding:0px; display:inline;" for="admin-' + data['userCompany']['userId'] + '">Administrator</label></div>'
								+ '<div style="float:right; margin-right:5px;margin-top: 5px;">'								
								+ '<a href="#" title="Remove this person from your company" class="remove-user"><img src="/images/icons/bin.png" alt="Remove User" /><span>Remove</span></a>'
								+ tradingAccountLink
								+ '</div>'
							+ '</div>'
						+ '</div>'
					+ '</div>'
				);
				
				$('#add_btn').removeClass('dbluePushed');
				$('#add_btn').addClass('dblue');
				$('#add-people-block').slideUp(300);
				
				$('input[name=person_search]').val('Type in the email of the person');

				//remove no user text
				$('#noUserMessage').hide();
				$('#filterBlock').show();

				if( typeof window.refreshAfterAdding != 'undefined' ){
					url = '/profile/company-people/type/v/id/' + $('#person_search_cid').val() + '?email=' + data['userCompany']['email'];
					window.location.href= url;
				}
			}
			else
			{
				// Fail - just show alert for now
				alert(data.msg);
			}
		}, 'json');
	});
	
	$('a[class="reject-request"]').live('click', function(){
		if (confirm('Are you sure you wish to reject this person\'s join request?')) {
			var code = $(this).closest('.pending-block').attr('id');
			codes = code.split('-'); // ids are 'joinreq-<requestId>'
			
			$.post('/profile/reject-user-request/format/json', { reqId: codes[1] }, function(data){
				if (data.ok == true) {
					$('#' + code).slideUp(300);
					
					// update the pending count blocks around the page
					var count = parseInt($('#jq-pending-people-actions-root').html());
					count--;
					
					$('span[class="jq-pending-people-actions-span"]').html(function () {
						return count;
					});
					
					// make sure the text makes sense
					var plural = (count == 1) ? 'person is' : 'people are';
					$('span[class="jqPersonPlural"]').html(function () {
						return plural;
					});
					
					if (count == 0) {
						$('#pending-users').slideUp(300);
						$('#jq-pending-people-actions').fadeOut('fast');
					}
					
					if (count < 10) {
						$('span[class="jq-pending-people-actions-span"]').css('margin-left', '8px')
					} else {
						$('span[class="jq-pending-people-actions-span"]').css('margin-left', '4px')
					}
					
				} else {
					alert(data.msg);
				}
			});
		}
		
		return false;
	});
	
	$('a[class="accept-request"]').live('click', function(){
		var isShip = require('alert/isShipmate');
		
		if (confirm('Are you sure you wish to accept this person\'s join request?')) {
			var code = $(this).closest('.pending-block').attr('id');
			codes = code.split('-'); // ids are 'joinreq-<requestId>'
			
			$.post('/profile/approve-user-request/format/json', { reqId: codes[1] }, function(data){
				if (data.ok == true) {
					$('#' + code).slideUp(300);
					
					// update the pending count blocks around the page
					var count = parseInt($('#jq-pending-people-actions-root').html());
					count--;
					
					$('span[class="jq-pending-people-actions-span"]').html(function () {
						return count;
					});
					
					// make sure the text makes sense
					var plural = (count == 1) ? 'person is' : 'people are';
					$('span[class="jqPersonPlural"]').html(function () {
						return plural;
					});
					
					if (count == 0) {
						$('#pending-users').slideUp(300);
						$('#jq-pending-people-actions').fadeOut('fast');
					}
					
					if (count < 10) {
						$('span[class="jq-pending-people-actions-span"]').css('margin-left', '8px')
					} else {
						$('span[class="jq-pending-people-actions-span"]').css('margin-left', '4px')
					}
					
					$('#global-pending-actions').pendingActions();
					
					// check if the approved users header is showing
					if ($('#approve-people-header').is(":hidden")) {
						$('#approve-people-header').show();
					}
					
					var markup = '<div class="approved-block" id="userid-' + data.user.id + '-' + data.user.companyType + '-' + data.user.companyId + '">';
					markup +='<div class="logo-holder"><img src="/images/layout_v2/profile/user.png" alt="" /></div>';
					markup +='<h3>' + data.user.fullName + '</h3>';
					markup +='<h4>' + data.user.email + '</h4>';
					markup +='<hr class="dotted" style="width:460px; margin-bottom:8px;" />';
					markup +='<div class="clear"></div>';
					markup +='<div class="icons-holder"><div class="company-icons"><div style="float:left;"><input type="checkbox" class="administrator" value="1" id="admin-' + data.user.id + '" style="margin-right: 5px;"/><label for="admin-' + data.user.id + '" style="font-size:10px; margin-right:5px; padding:0px; display:inline;">Administrator</a></label></div><div style="float:right; margin-right:5px;margin-top: 5px;"><a href="#" title="Remove this person from your company" class="remove-user"><span>Remove</span></a>';
					if(isShip){
						markup +='<span class="remove-user"> | </span>';
						markup +='<a href="/profile/user-activity/type/v/id/52323/userId/'+ data.user.id +'" title="See latest activity of this user" class="remove-user user-activity"><span>Show activity</span></a>';
					}
					
					markup +='</div></div>';
					markup +='</div>';
					
					// add an approved user block
					$('#approved-people').append(markup);
					
				} else {
					alert(data.msg);
				}
			});
		}
		
		return false;
	});
	
	$('a[class="remove-user"]').live('click', function(){
		if (confirm('Are you sure you wish to remove this person from your company?')) {
			var code = $(this).closest('.approved-block').attr('id');
			codes = code.split('-');
			
			$.post('/profile/remove-user/format/json', { userId: codes[1], type: codes[2], id: codes[3] }, function(data){
				if (data.ok == true) {
					$('#' + code).slideUp(300,function(){
						/* User removed, if no list, hide top searchbar, and show no user text */

						var elementCnt = 0;
						$('.approved-block').each(function(){
							if (!( $(this).css('display') == 'none' )) {
    							elementCnt ++;
							}		
						});

						$('.pending-block').each(function(){
							if (!($(this).css('display') == 'none' )) {
    							elementCnt ++;
							}		
						});

						if (elementCnt == 0) {
							$('#noUserMessage').show();
							$('#filterBlock').hide();
						}
						
					});

				} else {
					alert(data.msg);
				}
			});
		}
		
		return false;
	});
	
	$('input[class="administrator"]').live('click', function() {
		// set/unset the administrator status
		var code = $(this).closest('.approved-block').attr('id');
		var codes = code.split('-');
		
		var postData = { userId: codes[1],
						 type: codes[2],
						 id: codes[3],
						 status: $(this).is(':checked') }
		
		$.post('/profile/set-administrator-status/format/json', postData , function(data){
			if (data.ok == true) {
				
			} else {
				alert(data.msg);
			}
		}, 'json');
	});
	
	setTimeout(function(){$('h3[class="success-message"]').slideUp(500);}, 5000);
	
	// GROUP SETTING
	$(".group-selector").unbind('change').bind('change', function(){
		if( $(this).val() != '--' ){
			$(".ss-groups").hide();
			$("#ss-group-container-" + $(this).attr("userId")).show();
			$("#ss-group-" + $(this).val() + "-" + $(this).attr("userId")).show();
			$("#group-action-" + $(this).attr("userId")).show();
		}
		else
		{
			alert("Please select user group");
			$(this).focus();
			$(".ss-groups").hide();
			$("#ss-group-container-" + $(this).attr("userId")).hide();
			$("#ss-group-" + $(this).val() + "-" + $(this).attr("userId")).hide();
			$("#group-action-" + $(this).attr("userId")).hide();
		}
	});

	$(".group-selector-action").unbind('click').bind('click', function(){
		var obj = $(this);
		obj.val("Saving...");
		obj.attr("disabled", true);
		$.ajax({
			url: '/profile/save-group/format/json', 
			data: { userId: $(this).attr('userId'), groupId: $('select[userId="' + $(this).attr('userId') + '"]').val()}, 
			success: function(data){
				if (data.status == 200){
					obj.val("Saved");
					setTimeout(function(){ 
						obj.fadeOut(200);
						$("#ss-group-container-" + obj.attr("userId")).hide();
						obj.val("Apply");
					}, 2000);
				}
				obj.attr("disabled", false);
			},
		    error: function(request, textStatus, errorThrown) {
		    	var response = eval('(' + request.responseText + ')');
		    	alert(response.error);
				obj.val("Apply");
				obj.attr("disabled", false);
		    }
		});
	});	
	
});
