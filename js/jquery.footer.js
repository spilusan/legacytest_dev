$('a.top').live('click',function(){
     $('html, body').animate({scrollTop: '0px'}, 300);
     return false;
});
$('a.bottom').live('click',function(){
	 $('html, body').animate({scrollTop: $('html, body')[0].scrollHeight});
     return false;
});
