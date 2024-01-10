require([
	"jquery"
	, "match/match"
], function($, match){

	function initialise(){
		$(".child").hide();

		$(".trRow").each(function(){
			$(this).find(".individualTotal").show()
			$(this).find(".groupTotal").hide();
		});

		
		// making row bold
		$(".child").each(function(){
			var parent = $(".parent[buyerTnid='" + $(this).attr("parentId") + "']");
			if( parent.length > 0 ){
				parent.addClass("bold");

				parent.find(".individualTotal").hide()
				parent.find(".groupTotal").show();
			}
		});		
	}
	
	$(document).ready(function(){
		initialise();
		
		// showing the children
		$(".parent").click(function(e){
			if( $(".child[parentId='" + $(this).attr("buyerTnid") + "']").length == 0 && e.target.nodeName == 'TD'){
				return false;
			}
			$(this).siblings().hide();
			$(this).show();
			$(".backBtn").show();
			$(this).find(".groupTotal").hide();
			$(this).find(".individualTotal").show();
			$(".child[parentId='" + $(this).attr("buyerTnid") + "']").toggle(500);
		});

		$(".backBtn").click(function(){
			$(this).hide();
			$(this).siblings().show();
			
			initialise();
		});
		
		$(".toggleDetail").click(function(){
			$(this).parent().parent().find(".detail").toggle();
		});
		
	});
});
