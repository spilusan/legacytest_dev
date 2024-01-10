$(function(){
	//This cannot be move to require because used also by WP
    var $msgContainer = $('#preHeader');
    var today = new Date();	
    var msg = '<div id="cAlert" style="z-index: 9999; padding: 20px 0; color: white; bottom: 0; left: 0; right: 0;position: fixed; width: 100%; background-color: black; text-align: center; font-family: Lato, sans-serif;">This site uses cookies. By continuing to browse the site you are agreeing to our use of cookies. <a style="color: white; text-decoration: underline;" href="/info/legal-notes/cookie-policy/">Learn more</a> <a style="display: inline-block; vertical-align: middle; color: white; font-size: 20px;" href="#" id="accept"><i style="font-size: 30px; padding-left: 20px;" class="fa fa-times-circle fa-3" aria-hidden="true"></i></a></div>';
    var cookieId = 'cookiemsg';
    if(document.cookie.indexOf(cookieId) === -1) {
    	$msgContainer.css("height", "auto");
    	$msgContainer.prepend(msg);
    }
    
    $("#accept").live('click', function(e){
        e.preventDefault();
		var today = new Date();
		var expire = new Date();
		expire.setTime(today.getTime() + 3600000*24*365); //expire after 1 year
        document.cookie = cookieId + '={"accepted":1}' + '; path=/; expires=' + expire.toGMTString();
        $msgContainer.find('#cAlert').remove();
    });
});