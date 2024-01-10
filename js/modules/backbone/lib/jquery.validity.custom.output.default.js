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
        
            $("input[type='password']").removeClass("invalid");
            $("input[type='text']").removeClass("invalid");
            $("input[type='email']").removeClass("invalid");
            $("input[type='phone']").removeClass("invalid");
            $("input[type='date']").removeClass("invalid");
            $(".selector").removeClass("invalid");
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
            if($obj.is('select')){
                $obj.parent().addClass("invalid");
                $obj.parent().after("<div class='error'>"+msg+"</div><div class='clear err'></div>");
            }
            else if($obj.is('input[type="checkbox"]')){
                $obj.parent().parent().next('label').after("<div class='error'>"+msg+"</div><div class='clear err'></div>");
            }
            else {
                $obj.addClass("invalid");
                $obj.next().after("<div class='error'>"+msg+"</div><div class='clear err'></div>");   
            }
             $obj.focus();
        },
        
        // aggregate raise will raise the error on both compared input fields:
        raiseAggregate: function($obj, msg){       
            this.raise($($obj), msg); 
        }
    }
});