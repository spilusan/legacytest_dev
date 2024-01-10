define([
    "jquery",
    "libs/jquery.validity.min"
], function(
    $, 
    jQValidity 
){
    $.validity.outputs.custom = {
        
        // start function will reset the inputs:
        start:function(){ 
            $("input[type='text']").removeClass("invalid");
            $("textarea").removeClass("invalid");
            $("div.error").remove();
            $("div.err").remove();
        },
        
        end:function(results) { 
            // If not valid and scrollTo is enabled, scroll the page to the first error.
            if (!results.valid && $.validity.settings.scrollTo) {
                location.hash = $(".fail:eq(0)").attr('id');
            }
            
        },
        
        // Our raise function will display the error:
        raise:function($obj, msg){
            // Style the border of the text box and add error message:
            $obj.addClass("invalid"); 

            if($obj.hasClass('emails') || $obj.is('textarea') || $obj.is('#agree')){
                $obj.parent().prepend("<div class='error'>"+msg+"</div><div class='clear err'></div>");
                $('.firstEmail').focus();
            }
            else {
                $obj.parent().parent().append("<div class='error'>"+msg+"</div><div class='clear err'></div>");
                $obj.focus();
            } 
        },
        
        // aggregate raise will raise the error on both compared input fields:
        raiseAggregate: function($obj, msg){       
            this.raise($($obj), msg); 
        }
    }
});