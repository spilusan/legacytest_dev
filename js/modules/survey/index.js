/**
 * Handles form to invite brand owner from Supplier Page.
 * 
 * @author Elvir <eleonard@shipserv.com>
 */
define(['jqModal'], 
	function($) {
		function init(){
			$("#form-survey").submit(function(){
				var a = $('input[name="question[1]"]:checked').val();
				if( typeof a == 'undefined' )
				{
					alert("Please provide an answer.");
					return false;
				}
				return true;
			});
	
			$("#form-survey textarea").unbind('keyup').bind("keyup", function(){
				var maxChar = 500;
				if( $(this).val().length > maxChar )
				{
					$(this).val( $(this).val().substr(0,maxChar) )
					return false;
				}
			});
		}$(init); //Exec on document ready
		return {};
	}
);