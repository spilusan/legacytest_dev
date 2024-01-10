
define([
	'jquery',
	'libs/cookie',
    'modal'
], function(
	$,
	cookie,
	modal
){
	var initialize = function(){
		var isShipservUser = require('alert/isShipmate');
		var cookieTTL = (isShipservUser) ? 1 : 14; 
		var announcementData = cookie.getJSON('announcement');
		var today = new Date();	
        var id = "#announcement-message";
		
		$('#announcement-new-feature').click(function(){
            
            if (!$('#mask')){
                bodyEl = $$('body')[0];
                bodyEl.append('<div id="mask"></div>');
            }

            if (!$(id)){
                bodyEl = $$('body')[0];
                bodyEl.append('<div id="announcement-message"></div>');
            }
            
            //Get the screen height and width
            var maskHeight = $(document).height();
            var maskWidth = $(window).width();
            
            //Set heigth and width to mask to fill up the whole screen
            $('#mask').css({'width':maskWidth,'height':maskHeight});
            
            
			$.ajax({
				url: '/help/new-features-ajax',
				type: 'GET',
				cache: false,
			    error: function(request, textStatus, errorThrown) {
			    	response = eval('(' + request.responseText + ')');
			    	if( response.error != "User must be logged in"){
			    		alert("ERROR " + request.status + ": " + response.error);
			    	}
			    },
				success: function( response ){
					$(id).html(response);
                    
                    //transition effect

                    //Set the popup window to center
                    
                    //var contHeight = $(id).height();
                    
                    if (isShipservUser) {
                        var contHeight = 796;
                    }
                    else {
                        var contHeight = 880;
                    }
                
                    //var contWidth = $(id).width();
                    var contWidth = 810;
                    var postop = ($(window).height() - contHeight ) / 2+$(window).scrollTop();
                    var posleft = ( $(window).width() - contWidth ) / 2+$(window).scrollLeft();
                                     
                    if (postop < 40) {
                        postop = 40;
                    }
                    
                    if (posleft < 40) {
                        posleft = 40;
                    }

                    $(id).css("position","absolute");
                    $(id).css("z-index","10700");
                    $(id).css("width",contWidth);
                    $(id).css("height",contHeight);
                    $(id).css("top", postop );
                    $(id).css("left", posleft);
                    
                    
                    
                    //transition effect
                    $('#mask').fadeIn(500);
                    $('#mask').fadeTo("slow",0.8);
                    $(id).fadeIn(300);
                    
					//('#announcement-message').ssmodal({title: 'What\'s New on ShipServ Pages'});
					
					// set cookie
					announcementData = {'lastDisplayed':today.getTime(), 'isShipservUser':isShipservUser};
					cookie.setJSON('announcement', announcementData, cookieTTL);
				}
			});			
		});
		

		// check if cookie exists
		if( announcementData === null || ( isShipservUser === false && announcementData !== null && announcementData.lastDisplayed + (cookieTTL * 3600000*24) < today ) ){
            $('#announcement-new-feature').trigger("click");
		}
	}

	initialize();
});
