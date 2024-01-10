$(document).ready(function(){
	function last_msg_funtion() 
	{ 

       var ID=$(".searchResult:last").attr("id");
		$('div#last_msg_loader').html('<img src="/images/longloader.gif">');
		$.get('/search/results/page/format/html/',
		{
			searchWhat: "<?php echo $this->searchValues['searchWhat']; ?>",
			searchWhere: "<?php echo $this->searchValues['searchWhere']; ?>",
			searchStart: ID,
			searchRows: 10
		  },function(data){
			if (data != "") {
				$(".searchResultdetail:last").after(data);
				$('div#last_msg_loader').show();
				$('a.top').show();
				$('a.enquiry').show();
				$('#mask').hide();
				$('.window').hide();			
			}
			$('div#last_msg_loader').empty();
		});
	};  

	$(window).scroll(function(){
		if  ($(window).scrollTop() == $(document).height() - $(window).height()){
		   last_msg_funtion();
		}
	});
	
});