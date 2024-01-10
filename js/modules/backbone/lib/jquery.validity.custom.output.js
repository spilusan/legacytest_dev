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
            $("select").removeClass("invalid");
            $("textarea").removeClass("invalid");
            $("div.error").remove();
            $("div.err").remove();
            if($('fieldset.reqisition').hasClass('open')){
                $('fieldset.reqisition').height(190);
            }
            else {
                $('fieldset.reqisition').height(86);
            }
            if($('fieldset.delivery').hasClass('open')){
                $('fieldset.delivery').height(465);
            }
            else {
                $('fieldset.delivery').height(86);
            }
        },
        
        end:function(results) { 
        
            // If not valid and scrollTo is enabled, scroll the page to the first error.
            if (!results.valid && $.validity.settings.scrollTo) {
                location.hash = $(".fail:eq(0)").attr('id')
            }
            
        },
        
        // Our raise function will display the error and animate the text-box:
        raise:function($obj, msg){
            
            // Style the border of the text box and add error message:

            $obj.addClass("invalid");
            $obj.next().next().after("<div class='error'>"+msg+"</div><div class='clear err'></div>");

            var errNumDet = $('fieldset.delivery div.error').length;

            if($obj.parents('#reqDets') && !$('fieldset.delivery').hasClass('open')){
                $('#showReqDetails').trigger('click');
            }
            
            if ($obj.parent().attr('class') === "delivery" || $obj.parent().attr('class') === "delivery open") {
                if($('fieldset.delivery').hasClass('open')){
                    var boxHeight = 465 + errNumDet * 29;
                }
                else {
                    var boxHeight = 86 + errNumDet * 29;
                }
                $('fieldset.delivery').height(boxHeight);
            }

            var errNum = $('fieldset.reqisition div.error').length;
            
            if ($obj.parent().attr('class') === "reqisition vessel" || $obj.parent().parent().attr('class') === "requisition vessel" || $obj.parent().attr('class') === "reqisition vessel open" || $obj.parent().parent().attr('class') === "reqisition vessel open") {
                
                if($('fieldset.reqisition').hasClass('open')){
                    var boxHeight = 190 + errNum * 29;
                }
                else {
                    var boxHeight = 86 + errNum * 29;
                }

                $('fieldset.reqisition').height(boxHeight);
            }
            
            var lowest = 999999;
            $.each($('input.invalid'), function(idx, inp) {
                if ($(inp).attr('tabindex') < lowest) {
                    lowest = $(inp).attr('tabindex');
                }
            });

            //var ele = 'input[tabindex='+lowest+']';
            var ele = 'input.invalid[tabindex='+lowest+']';
            $(ele).focus();                

        },
        
        // aggregate raise will raise the error on both compared input fields:
        raiseAggregate: function($obj, msg){       
            this.raise($($obj), msg); 
        }
    }
});