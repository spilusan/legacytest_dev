// automatically move the focus to the top using a nice movement
function scrollToTop( speed, id, offset ) {
	if( id != undefined && id!=""){
		if( $("#" + id).length > 0 )
		var top = $("#" + id).position().top;
		else
		var top = 0;
	}else{
		var top = 0;	
	}
	
	if( offset != undefined ){
		top = top - ( offset );	
	}
	
	if (!speed) speed = "slow";
	$("html, body").animate( {scrollTop:top+"px"}, speed);
}

/**
 * Handles form to invite brand owner from Supplier Page.
 * 
 * @author Elvir <eleonard@shipserv.com>
 */
define(['jqModal', 'jquery.validate', 'cssp!css/jqModal.css?cssp-jQmodal'], 
	function($) {
		
		//Private 
		function init(){
			$(" textarea").unbind('keyup').bind("keyup", function(){
				var maxChar = 1000;
				if( $(this).val().length > maxChar )
				{
					$(this).val( $(this).val().substr(0,maxChar) )
					return false;
				}
			});
			
			var $popup = $('#validation-message');
			$popup.prependTo('body');
			
			
			$popup.jqm({
				overlay: 80,
				modal: true,
				onShow: function(popup) {
					popup.w.show();
				},
				onHide: function(popup) {
					popup.w.fadeOut('1000',function(){ popup.o.remove(); });		
				}
			});
			
			$('#big-button-continue-without').live('click', function(){
				$('form.main').submit();
			});
			
			// when user click 
			$('#big-button-continue-with').live('click', function(){
				$popup.jqmHide();
			});
			
			// bind the jquery module with the link
			$('#decline').bind('click', function(e) {
				if( $('form.main input[name="reason[]"]:checked').length == 0 ){
					e.preventDefault();
					scrollToTop();
					$popup.jqmShow();
				}else{
					$('form.main').submit();
				}
			});
			$("input[name='reason[]']:checked").live('click', function(){
				if( $("#option8").attr('checked') == 'checked'){
					$('#decline').html("Send Survey and Block Buyer")
				}else{
					$('#decline').html("Send Survey")
				}
			})
			$("#option8").live('click', function(){
				if( $(this).attr('checked') == "checked" ){
					if( $("input[name='reason[]']:checked").length > 0 ){
						$('#decline').html("Send Survey and Block Buyer")
					}else{
						$('#decline').html("Block Buyer")
					}
				}else{
					$('#decline').html("Send Survey")

				}
			});
		}$(init); //Exec on document ready
		return {};
});