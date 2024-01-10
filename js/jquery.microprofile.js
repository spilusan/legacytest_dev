$(document).ready(function(){
	$('a.expand').live('click',function() {
		var x = $(this).attr('id');
		$("#"+x+"_detail").show();
		$("div.footer").hide();
		
		$("#"+x+"_detail").html('<img id="' + x + '_preloadImage" style="position: absolute; top: -82px; right: 22px;" src="/images/bluecircle.gif" />');
		
		ts = +new Date;
		$.get('/supplier/microprofile/format/html/s/' + x + '/p/' + current_page + '?_=' + ts,
			function(data) {
					$("#"+x+"_detail").html(data);
			});
		
		$(this).removeClass('expand').addClass('expand_on');
		return false;
	});
	
	$('a.expand_on').live('click',function() {
		var x = $(this).attr('id');
		$("#"+x+"_detail").hide();
		$("#"+x+"_detail").html('');
		$("div.footer").show();
		$("#"+x+"_footer").removeClass('footer_expanded').addClass('footer');
		ts = +new Date;
		$.get('/supplier/remove-microprofile/format/json/s/'+x+ '/p/' + current_page + '?_=' + ts);
		$(this).removeClass('expand_on').addClass('expand');			
		return false;
	});
});