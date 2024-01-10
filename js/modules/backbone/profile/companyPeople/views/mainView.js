define([
	'jquery',
	'underscore',
	'Backbone',
    'handlebars',
    'backbone/shared/hbh/general',
	'libs/jquery.tools.overlay.modified',
	'libs/jquery.uniform',
    'libs/jquery.shipserv-tooltip',
    '../collections/checklist',
    'text!templates/profile/companyPeople/tpl/list.html'
], function(
	$, 
	_, 
	Backbone,
    Hb,
    genHbh,
	Modal,
	Uniform,
    shTooltip, 
    checkList,
    checkListTpl
){
	var companyPeopleView = Backbone.View.extend({
		el: 'body',
		titleForModal: '',
        formData: '',
        events: {
            'click .showBuyerBranches' : 'getData',
            'click .button.search' : 'searchProfilesClick',
            'keypress input[name="userFilter"]' : 'searchProfilesKey',
            'click .button.reset' : 'searchResetClick',
            'click .editUserEmailBtn' : 'editUserClick',
            'click .checkUserEmailBtn' : 'checkUserClick',
            'click .cancelUserEmailBtn' : 'cancelUserClick',
            'click .toggleOnOff' : 'toggleOnOff',
            'click .toggleCheckBox' : 'onCheckBoxChecked',
            'click .passwordReset' : 'onPasswordReset',
            'click a.vResend' : 'onResendVerification'

        },
        template: Handlebars.compile(checkListTpl),

		initialize: function(){
            this.collection = new checkList();
            this.collection.url = "/data/source/user/company/list/";
            $('input[type="checkbox"]').uniform();
            $(document).ready(function(){
                $('.administratorInfo').shTooltip({
                    displayType : 'left'
                });
            });

		},
		
        getData: function(e) {
        	e.preventDefault();
        	
        	var el = null;
        	if($(e.target).is('a')){
        		el = $(e.target);
        	}
        	else if($(e.target).is('span')){
        		el = $(e.target).parent();
        	}
        	
        	var t = $(el).attr('href').split('//');
            var thisView = this;
            this.titleForModal = "Trading Account Privileges for " + t[2];
            this.userId = t[0];
            this.collection.fetch({
                data: $.param({
                	'userId': t[0], 
                	'byoOrgCode': t[1]
                }),
                complete: function() {
                    thisView.render();
                }
            });
        },

        render: function(){

        	var d = [];
        	var m = this.collection.models;
            var showDeadlineMgrColumn = false;
        	_.each(m, function(item){
                if (item.attributes.branchIsDeadlineManager === true) {
                    showDeadlineMgrColumn = true;
                }
                d.push(item);
        	}, this);
        	
        	this.collection.models = d;
        	this.collection.length = d.length;
        	
            var data = this.collection;
            data.hasList = (this.collection.models[0]) ? true: false;
            if (data.hasList == true) {
                data.isDeadlineManager = showDeadlineMgrColumn;
            } 
            var html = this.template(data);

            $('#modal .modalBody').html(html);
            $('#modal input[type="checkbox"]').uniform();
            $('#modal input[type="radio"]').uniform();
            this.openDialog();
            var thisView = this;
            $('input.save').bind('click', function(e){
                e.preventDefault();
                thisView.saveList();
            });
            $('input.cancel').bind('click', function(e){
                e.preventDefault();
                thisView.cancelList();
            });

            this.initalizePopups();
            this.initalizeAllCheckBoxes();
        },

        saveList: function(){
//        	if( $(".buyerBranchCode:checked").length == 0 ){
//        		alert("Please select at least one buyer branch");
//        	}else{
        		
        		var data = $('form.tradingAccForm').serializeArray();
        		$('form.tradingAccForm').serializeArray().forEach(function(e){ 
        			if (e.name=='userId') {
        				data.push({name:'isAdmin', value:$('#admin-' + e.value).is(':checked')});
    				}
    			});
	            var url = "/profile/store-byb-user/";
	            $.ajax({
	                type: "POST",
	                url: url,
	                data: data,
	                success: function(){
                        $.ajax({
                            type: "POST",
                            url: '/user/get-menu-options-to-display?type=analyse',
                            data: data,
                            success: function(result){
                                $('#modal').overlay().close();

                                if (result.menuOptions.length > 0)
                                {
                                    if (result.menuOptions[0]['href'] != $('a.analyse').attr('href'))
                                    {
                                        $('a.analyse').attr('href',result.menuOptions[0]['href']);
                                    }
                                } 
                                else
                                {
                                    $('a.analyse').attr('href','/search');
                                }
                            },
                        });
	                },
	            });
//        	}
        },

        cancelList: function(){

            /*
            if ($('form.tradingAccForm').serialize() != this.formData) {
                var r = confirm("You have made changes, If you close this window, you will loose all your changes!");
                    if (r == true) {
                         $('#modal').overlay().close();
                    } 
                } else {
                    $('#modal').overlay().close();
                }
            */
            $('#modal').overlay().close();
        },

        openDialog: function() {

        	var thisView = this;
            $("#modal").overlay({
                mask: 'black',
                left: 'center',
                fixed: false,

                onBeforeLoad: function() {
                    var windowWidth = $(window).width();
                    var modalWidth = $('#modal').width();
                    var posLeft = windowWidth/2 - modalWidth/2;

                    $('#modal').css('left', posLeft);
                    
                    ;
                },

                onLoad: function() {
                    $(window).resize(function(){
                        var windowWidth = $(window).width();
                        var modalWidth = $('#modal').width();
                        var posLeft = windowWidth/2 - modalWidth/2;

                        $('#modalContact').css('left', posLeft);
                    });
                }
            });

            $('#modal').overlay().load();
            $('h1.styled').html(thisView.titleForModal);
            $("#tradingAccountUserId").val(this.userId);
            this.formData = $('form.tradingAccForm').serialize();
        },

        openStandardDialog: function(title, body)
        {
            var thisView = this;
            $('#generalModal .modalContent').html(body);
            $('#generalModal h1.styled').html(title);
            $('#generalModal .close').show();
            $("#generalModal").overlay({
                mask: 'black',
                left: 'center',
                fixed: false,

                onBeforeLoad: function() {
                    var windowWidth = $(window).width();
                    var modalWidth = $('#generalModal').width();
                    var posLeft = windowWidth/2 - modalWidth/2;

                    $('#generalModal').css('left', posLeft);
                },

                onLoad: function() {
                    $(window).resize(function(){
                        var windowWidth = $(window).width();
                        var modalWidth = $('#generalModal').width();
                        var posLeft = windowWidth/2 - modalWidth/2;

                        $('#modalContact').css('left', posLeft);
                    });
                }
            });

            $('#generalModal').overlay().load();
        },

        searchProfilesClick: function(e) {
            var searchTxt = $('input[name="userFilter"]').val().toLowerCase();
            if (searchTxt.length == 0) {

                /* Reset approved suppliers */
                $('.approved-block').each(function(){
                     $(this).show();
                });

                /* Reset pending suppliers */
                $('.pending-block').each(function(){
                     $(this).show();
                });

            } else {

                /* Filter approved block*/
                $('.approved-block').each(function(){
                    var companyName = $(this).find('h3').first().html().toLowerCase();
                    companyName += ' ' + $(this).find('h4').first().html().toLowerCase();
                    if (companyName.indexOf(searchTxt) == -1) {
                        $(this).fadeOut(600);
                    } else {
                        $(this).fadeIn(600);
                    }
                });

                /* Filter approved block */

                $('.pending-block').each(function(){
                    var companyName = $(this).find('h3').first().html().toLowerCase();
                    companyName += ' ' + $(this).find('h4').first().html().toLowerCase();

                    if (companyName.indexOf(searchTxt) == -1) {
                        $(this).fadeOut(600);
                    } else {
                        $(this).fadeIn(600);
                    }
                });

            }
        },

        searchResetClick: function(e)
        {
          $('input[name="userFilter"]').val('');

           /* Reset approved suppliers */
          $('.approved-block').each(function(){
            $(this).fadeIn(600);
          });

          /* Reset pending suppliers */
          $('.pending-block').each(function(){
               $(this).show();
          });

        },

        editUserClick: function(e)
        {
            var element = $(e.target).next();
            var editElement = $(element).next();
            $(editElement).show();
            $(element).hide();
            $(e.target).hide();
        },

        checkUserClick: function(e)
        {
            /* @todo Call save AJAX, then close edit area */
           var thisView = this;
           var containerElement = $(e.target).parent();
           var userInfo = $(containerElement).parent().attr('id').split('-');
           if ( userInfo[0] == 'joinreq') {
                userInfo = $(containerElement).parent().data('id').split('-');
           }
           var hash = $(containerElement).parent().data('hash');
           var firstName = $(containerElement).find('.firstNameEdit').first().val();
           var lastName = $(containerElement).find('.lastNameEdit').first().val();
           var staticElement = $(containerElement).prev();

           /* Send modification request to AJAX */
           $('.fa-spinner').show();
           $('.checkUserEmailBtn').hide();
           $('.cancelUserEmailBtn').hide();

           $.ajax({
                  type: "POST",
                  url: '/profile/change-user-name',
                  data: {
                    id: userInfo[1],
                    fistName: firstName,
                    lastName: lastName,
                    hash: hash
                  },
                  success: function() {
                    var newName =  (firstName != '' || lastName != '') ? firstName+' '+lastName : 'Unknown Name';
                    $(containerElement).find('.firstNameEdit').first().data('initvalue',firstName);
                    $(containerElement).find('.lastNameEdit').first().data('initvalue',lastName);
                    $(staticElement).html(newName);
                     thisView.closeEditArea(e);
                      $('.fa-spinner').hide();
                      $('.checkUserEmailBtn').show();
                      $('.cancelUserEmailBtn').show();
                  },
                  error: function (jqXHR, textStatus, errorThrown)
                  {
                    $('.fa-spinner').hide();
                    $('.checkUserEmailBtn').show();
                    $('.cancelUserEmailBtn').show();
                    alert(errorThrown);
                    thisView.closeEditArea(e);
                  }
                });
           
        },
        
        cancelUserClick: function(e)
        {
            var containerElement = $(e.target).parent();
            var firstNameElement = $(containerElement).find('.firstNameEdit').first();
            var lastNameElement = $(containerElement).find('.lastNameEdit').first();
            firstNameElement.val(firstNameElement.data('initvalue'));
            lastNameElement.val(lastNameElement.data('initvalue'));
            this.closeEditArea(e);
        },

        closeEditArea: function(e) {
            var element = $(e.target).parent();
            var staticElement = $(element).prev();
            var openCloseButton = $(element).parent().find('.editUserEmailBtn').first().show();
            $(staticElement).show();
            $(element).hide();
            
        },

        toggleOnOff: function(e)
        {
            var element = $(e.target);
            var elementsName = 'input[name="'+$(element).data('toggle')+'[]"]';
            if ($(element).hasClass('selected')) {
                $(element).removeClass('selected');
                $(elementsName).each(function(){
                    $(this).attr('checked', false);
                });
            } else {
                $(element).addClass('selected');
                
                $(elementsName).each(function(){

                    if ($(this).attr('disabled') === 'disabled') {
                         $(this).attr('checked', false);
                    } else {
                        $(this).attr('checked', true);
                    }
                    
                });
                
            }
            $.uniform.update(elementsName);
        },

        initalizeAllCheckBoxes: function()
        {
            var thisView = this;
            $('.toggleOnOff').each(function(){

                var elementsName = 'input[name="'+$(this).data('toggle')+'[]"]';
                thisView.setAllCheckByCol(elementsName, $(this));
 
            });
        },

        initalizePopups: function()
        {
            $('.taHelp').shTooltip({
                displayType : 'top'
            });
        },

        setAllCheckByCol: function(elementsName, checkBox)
        {
                $(checkBox).removeClass('disabled');
                var allSelected = true;
                var allUnSelected = true;
                $(elementsName).each(function(){
                    if ($(this).attr('checked')) {
                        allUnSelected = false;
                    } else {
                        allSelected = false;
                    }

                });

                if (allSelected == true) {
                    $(checkBox).addClass('selected');

                } else if (allUnSelected == true) {
                    $(checkBox).removeClass('selected');
                } else {
                    $(checkBox).removeClass('selected');
                    //$(checkBox).addClass('disabled');
                }
        },

        onCheckBoxChecked: function(e)
        {
             var thisView = this;
             var element = $(e.target);
             var elementsName = 'input[name="'+$(element).attr('name')+'"]';
                 $('.toggleOnOff').each(function(){
                    if ($(this).data('toggle')+'[]' == $(element).attr('name')) {
                        thisView.setAllCheckByCol(elementsName, $(this));
                 }
             });
            
        },

        onPasswordReset: function(e)
        {
            e.preventDefault();

            var thisView = this;
            var element = $(e.currentTarget);
            var email = $(element).data('email');
            var spinnerElement = $(element).next();
            $(spinnerElement).show();
            $.ajax({
              type: "POST",
              url: '/user/send-pasword-reset-email',
              data: {
                email: email
              },
              success: function( response ) {
                $(spinnerElement).hide();
                if (response.result != 'ok') {
                    thisView.openStandardDialog('Reset Password', "The email was not sent, please try again later");
                } else {
                    thisView.openStandardDialog('Reset Password', 'A password reset request email has been sent to the user.');
                }

              },
              error: function (jqXHR, textStatus, errorThrown)
              {
                $(spinnerElement).hide();
                alert(errorThrown);
              }
            });


        },

        searchProfilesKey: function(e) 
        {
            if (e.charCode == 13) {
                this.searchProfilesClick(e);
            }
            
        },

        onResendVerification: function(e)
        {
            e.preventDefault();
            var thisView = this;
            var element = $(e.currentTarget);
            var email = $(element).data('email');
            var spinnerElement = $(element).next();
            $(spinnerElement).show();
            $(element).hide();
            
            $.ajax({
              type: "POST",
              url: '/user/resend-verification-email-to',
              data: {
                email: email
              },
              success: function( response ) {
                $(spinnerElement).hide();
                if (response.result != 'ok') {
                    $(element).show();
                    thisView.openStandardDialog('Resend Verification', "The email was not sent, please try again later");
                } else {

                    thisView.openStandardDialog('Resend Verification', "An account verification request email has been sent to the user.");
                }
              },
              error: function (jqXHR, textStatus, errorThrown)
              {
                $(spinnerElement).hide();
                $(element).show();
                alert(errorThrown);
              }
            });


        },

	});

	return new companyPeopleView();
});