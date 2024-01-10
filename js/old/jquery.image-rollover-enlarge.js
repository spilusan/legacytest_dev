
	$(".rollover").live('mouseover',function(){
	    var currentImg = $(this).attr('src');
		$(this).attr('src', $(this).attr('hover'));
		$(this).attr('hover', currentImg);
		$(".rollover_").show();
	    }).live('mouseout',function(){
	      	var currentImg = $(this).attr('src');
			$(this).attr('src', $(this).attr('hover'));
			$(this).attr('hover', currentImg);
	});
	
	$(".image").live('mouseover',function(){
			var y = $(this).attr('id'); 
			$("#large_"+y).show();
		    }).live('mouseout',function(){
		    $(".rollover_big").hide();
	});
	
