define([
    'jquery',
    'underscore',
    'Backbone',
    'handlebars',
    'libs/jquery.tools.overlay.modified',
    'libs/jquery.uniform',

], function(
    $, 
    _, 
    Backbone,
    Hb,
    Modal,
    Uniform
){
    var companyAutoReminderView = Backbone.View.extend({
        el: 'body',
        changesMade: false, 
        lastSelected: false,
        events: {

        },

        initialize: function(){

             var thisView = this;
             this.changesMade = false;


            $(document).ready(function(){
                thisView.render();
            });

        },

        render: function(){

            //all event handlers, and uniforms moved here, cos a IE9 bug, where the DOM is not loaded at initalize.
            var thisView = this;

            if (!$('#rfq_all_reminder_enabled').is(":checked")) {
                this.enableDisableAllLeft( false ); 
            }

            if (!$('#ord_all_reminder_enabled').is(":checked")) {
                this.enableDisableAllRight( false ); 
            }

            if (!$('#rfq_reminder_enabled').is(":checked")) {
                this.enableDisableLeft( false ); 
            }

            if (!$('#ord_reminder_enabled').is(":checked")) {
                this.enableDisableRight( false ); 
            }

            thisView.toggleBottomCheckboxes();

            $("input[type='text']").uniform(); 
            $("input[type='checkbox']").uniform(); 
            $("select").uniform(); 

            $('input[type="text"]').keypress(function (e) {
                thisView.changesMade = true;
                if (String.fromCharCode(e.keyCode).match(/[^0-9]/g)) return false;
            });

             $('#rfq_reminder_enabled').click(function(){
                var sendO = new Object;
                sendO.currentTarget = $(this);
                thisView.leftCheckBoxClick(sendO);
                $.uniform.update();
             });

             $('#ord_reminder_enabled').click(function(){
                var sendO = new Object;
                sendO.currentTarget = $(this);
                thisView.rightCheckBoxClick(sendO);
                $.uniform.update();
             });

             $('#rfq_all_reminder_enabled').click(function(){
                var sendO = new Object;
                sendO.currentTarget = $(this);
                thisView.leftAllCheckBoxClick(sendO);

                if($('#rfq_all_reminder_enabled').is(":checked")){
                    $('#bbs_rmdr_include_match').prop("disabled", false);
                    $('#bbs_rmdr_include_match').click();
                }
                else {
                    if($('#bbs_rmdr_include_match').is(":checked")){
                        $('#bbs_rmdr_include_match').click();
                    }
                    $('#bbs_rmdr_include_match').prop("disabled", true);
                }

                $.uniform.update();
             });

             $('#ord_all_reminder_enabled').click(function(){
                var sendO = new Object;
                sendO.currentTarget = $(this);
                thisView.rightAllCheckBoxClick(sendO);
                $.uniform.update();
             });

             $('#bbs_byb_tnid').click(function(){
                var sendO = new Object;
                sendO.currentTarget = $(this);
                thisView.storeLastSelectedValue(sendO);
                $.uniform.update();
             });


             $('input[type="checkbox"]').click(function(){
                thisView.checkboxClicked();
             });


             $('.spin-up').click(function(){
                var sendO = new Object;
                sendO.currentTarget = $(this);
                thisView.spinUpClick(sendO);
             });

             $('.spin-down').click(function(){
                var sendO = new Object;
                sendO.currentTarget = $(this);
                thisView.spinDownClick(sendO);
             });

             $('#bbs_byb_tnid').change(function(){
                var sendO = new Object;
                sendO.currentTarget = $(this);
                thisView.redirectToNewTnid(sendO);
             });

             $('input[type="text"]').keypress(function(){
                var sendO = new Object;
                sendO.currentTarget = $(this);
                thisView.inputKeyPressed(sendO);
             });

         },

        leftCheckBoxClick: function(e)
        {
            this.enableDisableLeft($(e.currentTarget).is(":checked"));
        },

        rightCheckBoxClick: function(e)
        {
            this.enableDisableRight($(e.currentTarget).is(":checked"));
        },

        leftAllCheckBoxClick: function(e)
        {
            this.enableDisableAllLeft($(e.currentTarget).is(":checked"));
        },

        rightAllCheckBoxClick: function(e)
        {
            this.enableDisableAllRight($(e.currentTarget).is(":checked"));
        },

        enableDisableLeft: function( isEnabled )
        {
            if (isEnabled) {
                $(".enableLeft").removeAttr("disabled"); 
                $(".enableLeft").removeClass("disabled"); 
                $('.enableLeft').each(function(){
                    $(this).val($(this).data("defaultvalue"));
                    $(this).parent().children().first().children().first().css('border-color', 'transparent transparent #084886 transparent');
                    $(this).parent().children().first().next().children().first().css('border-color', '#084886 transparent transparent transparent');
                });
            } else {
               $('.enableLeft').each(function(){
                    $(this).parent().children().first().children().first().css('border-color', 'transparent transparent #d9d9d9 transparent');
                    $(this).parent().children().first().next().children().first().css('border-color', '#d9d9d9 transparent transparent transparent');
                });
                $(".enableLeft").attr("disabled", "disabled"); 
                $(".enableLeft").val(''); 
                $(".enableLeft").addClass("disabled"); 
            }
        },

        enableDisableRight: function( isEnabled )
        {
            if (isEnabled) {
               $(".enableRight").removeAttr("disabled"); 
               $(".enableRight").removeClass("disabled"); 
               $('.enableRight').each(function(){
                    $(this).val($(this).data("defaultvalue"));
                    $(this).parent().children().first().children().first().css('border-color', 'transparent transparent #084886 transparent');
                    $(this).parent().children().first().next().children().first().css('border-color', '#084886 transparent transparent transparent');
                });
            } else {
               $('.enableRight').each(function(){
                    $(this).parent().children().first().children().first().css('border-color', 'transparent transparent #d9d9d9 transparent');
                    $(this).parent().children().first().next().children().first().css('border-color', '#d9d9d9 transparent transparent transparent');
               });
               $(".enableRight").attr("disabled", "disabled"); 
               $(".enableRight").val(''); 
               $(".enableRight").addClass("disabled"); 
            }
        },

        enableDisableAllLeft: function( isEnabled )
        {

            if (isEnabled) {

                $("input[name='bbs_rmdr_rfq_send_after']").removeAttr("disabled"); 
                $("input[name='bbs_rmdr_rfq_send_after']").removeClass("disabled"); 
                $("input[name='bbs_rmdr_rfq_send_after']").val($("input[name='bbs_rmdr_rfq_send_after']").data("defaultvalue"));
                $("#rfq_reminder_enabled").removeAttr("disabled"); 
                $("input[name='bbs_rmdr_rfq_send_after']").parent().children().first().children().first().css('border-color', 'transparent transparent #084886 transparent');
                $("input[name='bbs_rmdr_rfq_send_after']").parent().children().first().next().children().first().css('border-color', '#084886 transparent transparent transparent');

            } else {
                this.enableDisableLeft(false);

                $("input[name='bbs_rmdr_get_copy']").attr("disabled", "disabled"); 
                $("input[name='bbs_rmdr_include_match']").attr("disabled", "disabled"); 
                $("input[name='bbs_rmdr_rfq_send_after']").val(''); 
                $(".leftElement").attr("disabled", "disabled"); 
                $(".leftElement").addClass("disabled"); 
                $('#rfq_reminder_enabled').attr('checked', false);
                $("input[name='bbs_rmdr_rfq_send_after']").parent().children().first().children().first().css('border-color', 'transparent transparent #d9d9d9 transparent');
                $("input[name='bbs_rmdr_rfq_send_after']").parent().children().first().next().children().first().css('border-color', '#d9d9d9 transparent transparent transparent');
                
            }
            this.toggleBottomCheckboxes();

        },

        enableDisableAllRight: function( isEnabled )
        {

            if (isEnabled) {

                $("input[name='bbs_rmdr_ord_send_after']").removeAttr("disabled"); 
                $("input[name='bbs_rmdr_ord_send_after']").removeClass("disabled"); 
                $("input[name='bbs_rmdr_ord_send_after']").val($("input[name='bbs_rmdr_ord_send_after']").data("defaultvalue"));
                $("#ord_reminder_enabled").removeAttr("disabled"); 
                $("input[name='bbs_rmdr_ord_send_after']").parent().children().first().children().first().css('border-color', 'transparent transparent #084886 transparent');
                $("input[name='bbs_rmdr_ord_send_after']").parent().children().first().next().children().first().css('border-color', '#084886 transparent transparent transparent');

            } else {
                this.enableDisableRight(false);
                $("input[name='bbs_rmdr_ord_send_after']").val(''); 
                $(".rightElement").attr("disabled", "disabled"); 
                $(".rightElement").addClass("disabled"); 
                $('#ord_reminder_enabled').attr('checked', false);
                $("input[name='bbs_rmdr_ord_send_after']").parent().children().first().children().first().css('border-color', 'transparent transparent #d9d9d9 transparent');
                $("input[name='bbs_rmdr_ord_send_after']").parent().children().first().next().children().first().css('border-color', '#d9d9d9 transparent transparent transparent'); 
            }
            this.toggleBottomCheckboxes()
        },

        toggleBottomCheckboxes: function()
        {

             if ($('#rfq_all_reminder_enabled').is(":checked") || $('#ord_all_reminder_enabled').is(":checked")) {
   
                $("#bbs_rmdr_get_copy").removeAttr("disabled"); 
                $("#bbs_rmdr_include_match").removeAttr("disabled"); 


            } else {
                $("#bbs_rmdr_get_copy").attr("disabled", "disabled"); 
                $('#bbs_rmdr_get_copy').attr('checked', false);
                $("#bbs_rmdr_include_match").attr("disabled", "disabled"); 
                $('#bbs_rmdr_include_match').attr('checked', false);
              }
        },

        redirectToNewTnid: function(e)
        {
            var redirectURL = $(e.currentTarget).val();
            if (redirectURL != '') {
                if (this.changesMade) {
                    var r = confirm("You made changes, if you select a new trading account, your changes will be lost. Are you sure?!");
                    if (r == true) {
                        window.location.href = redirectURL;
                    } 
                } else {
                    window.location.href = redirectURL;
                }
            }

            //reset the select box to its previous value
            if (this.lastSelected) {
                $(this.lastSelected).attr('selected', true);
                //jquey uniform bug, frees this time, this is used instead
                $("#bbs_byb_tnid").parent().find('span').html(this.lastSelected.text());
            }
        },

        storeLastSelectedValue: function(e) 
        {
            this.lastSelected = $(e.currentTarget).find('option:selected');
        },

        checkboxClicked: function()
        {
             this.changesMade = true;
        },

        spinDownClick: function(e) {
            var spinnerElement = $(e.currentTarget).parent();
            var inputElement = spinnerElement.find('input').first();
            if (!inputElement.hasClass('disabled')) {
                this.changesMade = true;
                if ($(inputElement).val()=='') {
                    var currValue = parseFloat($(inputElement).data("defaultvalue"));
                } else {
                    var currValue = parseFloat($(inputElement).val());
                    currValue--;
                }
                var currText = (currValue > 0) ? currValue : 1;
                $(inputElement).val(currText);

            }
        },

        spinUpClick: function(e) {
            var spinnerElement = $(e.currentTarget).parent();
            var inputElement = spinnerElement.find('input').first();

            if (!inputElement.hasClass('disabled')) {
                this.changesMade = true;
                if ($(inputElement).val()=='') {
                    var currValue = parseFloat($(inputElement).data("defaultvalue"));
                } else {
                    var currValue = parseFloat($(inputElement).val());
                    currValue++;
                }
                var maxValue = parseFloat($(inputElement).data("maxvalue"));
                var currText = (currValue <= maxValue) ? currValue : maxValue;
                $(inputElement).val(currText);
            }
        }
    });

    return new companyAutoReminderView;
});