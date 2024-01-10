$(document).ready(function(){

	 function last_msg_function() 
	 { 
		 var ID  = $(".product_items:last").attr("id");
		 var ID2 = $(".text:last").attr("id");
		 var type  = $(".product_items:last").attr("rel");
		
		if(type == 'browse'){
		
		 if (ID != 'end'){
		 $('div#last_msg_loader').html('<img src="/images/longloader.gif">');
		 $.get('/supplier/catalogue/format/html/',
		 {
			 catId: ID2,
			 itemStart: ID,
			 itemRows: 50
		   },function(data){
			 if (data != "") {
				 $(".product_items:last").after(data);
				 $('div#last_msg_loader').show();
				 $('a.top').show();			
			 }
			 
			 $('div#last_msg_loader').empty();
		 })
		 }
	 	 }else{
		
	   		if (ID != 'end'){
			 $('div#last_msg_loader').html('<img src="/images/longloader.gif">');
			 $.get('/supplier/catalogue-search/format/html/',
			 {
				 catId: ID2,
				 itemStart: ID,
				 itemRows: 50,
				 query :type
			   },function(data){
				 if (data != "") {
					 $(".product_items:last").after(data);
					 $('div#last_msg_loader').show();
					 $('a.top').show();			
				 }

				 $('div#last_msg_loader').empty();
			 })
			 } 
		 
		 }
	 }

	 $(window).scroll(function(){
		 if  ($(window).scrollTop() == $(document).height() - $(window).height()){
			last_msg_function();
		 }
	 }); 

});