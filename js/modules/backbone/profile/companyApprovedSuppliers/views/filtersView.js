define([
	'jquery',
	'underscore',
	'Backbone',
    'handlebars',
     'libs/jquery.tools.overlay.modified',
	'libs/jquery.uniform',
     'libs/ajaxfileupload',
    'libs/jquery.autocomplete.list',
    '../collections/approvedSuppliers',
    '../collections/blackList',
    '../views/availableSupplierItemView',
    '../views/emailItemView',
    'text!templates/profile/companyApprovedSuppliers/tpl/filters.html',

], function(
	$, 
	_, 
	Backbone,
    Hb,
    Modal,
	Uniform,
    Upload,
    Autocomplete,
    approvedSupplierCollection,
    blackList,
    availableSupplierItemView,
    emailItemView,
    filtersTpl

){
	var filtersView = Backbone.View.extend({
		el: 'body',
        events: {
          'change input#scopeFile' : 'uploadFile',
          'click #showBtn' : 'searchAgain',
          'submit #filterForm' : 'filterFormSubmit',
          'click #addEmailBtn' : 'emailClick',
          'click .close' : 'emailCloseClick',
          'click #allowNotification' : 'allowNotificationClick',
          'click #editBtn' : 'showSupplierSelector',
          'focus input.supplierList.available' : 'toggleInputValue',
          'blur input.supplierList.available' : 'toggleInputValue',
          'keyup input.selectedList.filter' : 'filterList',
          'click #modal .modalBody input.apply' : 'saveTempSuppliers',
          'click #addEmailButton': 'openEmailDialog',
          'click button.cancel' : 'onCancelEmailDialog',
          'click button.add': 'onAddEmailDialog',
        },

        filtersTemplate: Handlebars.compile(filtersTpl),

		initialize: function(){
            var thisView = this;

                        //supplier forward temp whitelist collection
            this.fwdCollectionTemp = new blackList();
            this.fwdCollectionTemp.url = "/buyer/blacklist/get-all/";

            this.fwdCollection = new blackList();
            this.fwdCollection.url = "/buyer/blacklist/get-all/";

            this.collection = new approvedSupplierCollection();
            this.collection.url = "/data/source/supplier/approvedsupplierState";

            var html = this.filtersTemplate();
            $("#filters").html(html);

           
            this.emailItems =  new emailItemView();
            this.emailItems.getData();

            $('input[type="checkbox"]').uniform();

		},

         uploadFileOld: function(){
            var thisView = this;
            $.ajaxFileUpload(
                {
                    url:'/profile/company-approved-suppliers-add',
                    secureuri: false,
                    fileElementId: 'scopeFile',
                    dataType: 'json',
                    
                    success: function (data, status)
                    {
                        thisView.render();
                    },
                    
                    error: function (data, status, e)
                    {
                        /* Comment this callback is not implemented in this plugin correctly */
                        alert(e);
                    }
                }
            );

            return false;
        },
		
	    uploadFile: function(){
	    	var thisView = this;
	    	$.ajaxFileUpload(
				{
                    url:'/buyer/blacklist/add-and-remove',
					secureuri: false,
					fileElementId: 'scopeFile',
					dataType: 'json',
                    data:{
                        type:'whitelist',
                        resultType: 'plainText',
                    },
					
					success: function (data, status)
					{
						thisView.render();
					},
					
					error: function (data, status, e)
					{
						alert(e);
					}
				}
			);

			return false;
	    },

        render: function(){
        	this.parent.getData();
        },

        filterFormSubmit: function(e)
        {
        	e.preventDefault();
        	this.parent.getData();
        	return false;
        }, 

        searchAgain: function()
        {
        	this.parent.getData();
        },

        /* TODO Do these properly, as modal shoul be */
        emailClick: function()
        {
        	alert('Under development');
        	/* $('#modalEmail').show(); */
        },

        emailCloseClick: function()
        {
        	/* $('#modalEmail').hide(); */
        },

        allowNotificationClick: function( e )
        {
            var thisView = this;
            var checkedState = ($(e.currentTarget).is(':checked')) ? 1 : 0;
            //TODO call backend to store changes

           
                this.collection.reset();

                this.collection.fetch({
                data: $.param({
                    'checkedState': checkedState,
                }),
                complete: function() {
                   /* thisView.render(); */
                }
            });
        },

        /* Supplier selector */
        showSupplierSelector: function(e) 
        {
            e.preventDefault();
            this.fetchWhitelist();
        },

        renderSupplierSelector: function()
        {

            this.renderAvailableFwdSuppliers();
            var sUrl = '/buyer/blacklist/available/?type=whitelist&nofilter=1';

            var thisView = this;

            $('input.supplierList.available').autocomplete({
                serviceUrl: sUrl,
                width:370,
                zIndex: 9999,
                minChars: 3,
                appendTo: 'div.selectlist.available',
                noCache: true,
                onSearchStart: function(){
                    thisView.waiting = false;
                    $('div.selectlist.available ul').html('');
                    $('.spinnerFwd').css('display', 'inline-block');
                },
                onSearchComplete: function(){
                    $('.spinnerFwd').hide();
                    thisView.waiting = true;
                },
                onSelect: function(response) {
                       thisView.fwdCollectionTemp.add({
                           id: response.data,
                           name: response.value
                       });
                       $('.autocomplete-suggestions.available.selectlist').children('li').each(function(){
                           if ($(this).data('index') == response.idx) {
                             $(this).data('tnid',response.data);
                             $(this).hide();
                           }
                       });
                       thisView.renderAvailableFwdSuppliers();
                }, 
                transformResult: function(response) {
                    var jsonResponse = JSON.parse(response);
                    var hasToDisplay = true;

                    return {
                        suggestions: $.map(jsonResponse.suggestions, function(dataItem) {
                            
                            hasToDisplay = true;
                            _.each(thisView.fwdCollectionTemp.models, function(item){

                                if (dataItem.data == item.attributes.id) {
                                    hasToDisplay = false;
                                }
                                
                            });

                            if (hasToDisplay) {
                                return { value: dataItem.value, data: dataItem.data };
                            } else {
                                return null;
                            }
                        })
                    };
                }
            });

            this.openDialog();
        },

        renderAvailableFwdSuppliers: function(){
            $('ul.selectlist.selected').html('');
            _.each(this.fwdCollectionTemp.models, function(item){
                this.renderAvailableFwdItem(item);
            }, this);
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
                },

                onLoad: function() {
                    $(window).resize(function(){
                        var windowWidth = $(window).width();
                        var modalWidth = $('#modal').width();
                        var posLeft = windowWidth/2 - modalWidth/2;

                        $('#modalContact').css('left', posLeft);
                    });
                },
                onClose: function(){
                    $('input.supplierList.available').val('Search for Supplier to add (by name or TNID)');
                    $('input.selectedList.filter').val('');
                        thisView.fwdCollectionTemp.reset();
                        _.each(thisView.fwdCollection.models, function(item){
                            thisView.fwdCollectionTemp.add(item.attributes);
                        });
                }
            });

            $('#modal').overlay().load();
        },

        openEmailDialog: function() {

            var thisView = this;

            $('input[name="addEmailAddress"]').val('');
            
            $("#emialmodal").overlay({
                mask: 'black',
                left: 'center',
                fixed: false,

                onBeforeLoad: function() {
                    var windowWidth = $(window).width();
                    var modalWidth = $('#emialmodal').width();
                    var posLeft = windowWidth/2 - modalWidth/2;
                    $('#emialmodal').css('left', posLeft);
                },

                onLoad: function() {

                    $(window).resize(function(){
                        var windowWidth = $(window).width();
                        var modalWidth = $('#emialmodal').width();
                        var posLeft = windowWidth/2 - modalWidth/2;

                        $('#emailModalContact').css('left', posLeft);
                    });
                },
                onClose: function(){

                }
            });

            $('#emialmodal').overlay().load();
        },

         renderAvailableFwdItem: function(item){
            var availableFwdSupplierItem = new availableSupplierItemView({
                model: item
            });

            availableFwdSupplierItem.type = "whitelist";

            availableFwdSupplierItem.parent = this;

            var elem = "ul.selectlist.selected";
            $(elem).append(availableFwdSupplierItem.render().el);
        },

        toggleInputValue: function(e){
            if($(e.target).val() === ""){
                $(e.target).val("Search for Supplier to add (by name or TNID)");
            }
            else if($(e.target).val() === "Search for Supplier to add (by name or TNID)") {
                $(e.target).val("");
            }
        },

        filterList: function(e) {
            var elem = null;
            if(e){
                elem = $(e.target);
            }
            else {
                elem = $('input.selectedList.filter');
            }
            var ul = elem.parent().next('ul'),
                substr = elem.val(),
                thisView = this,
                rx = new RegExp(substr,'i'),
                replaceRx = new RegExp("("+ substr +")", "gi");
                
            if (substr === '') {
                $(ul).removeClass('filtered');
                
                $.each($(ul).find('li'), function() {
                    $(this).removeClass('filteredOut')
                            .html($(this).text());
                });
            }else {
                
                $(ul).addClass('filtered');
                
                $.each($(ul).find('li'), function() {
                    if(rx.exec($(this).text())){
                    
                        $(this).html($(this).text().replace(replaceRx,"<strong>$1</strong>"))
                                .removeClass('filteredOut');
                    
                    }else{
                        $(this).addClass('filteredOut');
                    }
                });
            }
        },


    fetchWhitelist: function()
    {
         var thisView = this;
           this.fwdCollection.fetch({
                data: $.param({
                    type: 'whitelist'
                }),
                complete: function() {
                        thisView.fwdCollectionTemp.reset();
                        _.each(thisView.fwdCollection.models, function(item){
                            thisView.fwdCollectionTemp.add(item.attributes);
                        });
                        thisView.renderSupplierSelector();
                        //get match blacklist status (true/false)
                    }
            });
    },
    saveTempSuppliers: function(e)
    {
        e.preventDefault();

        this.fwdCollection.reset();
        _.each(this.fwdCollectionTemp.models, function(item){
            this.fwdCollection.add(item.attributes);
            }, this);

            $('input.selectedList.filter').val('');
            $('#modal').overlay().close();
            $('input[name="submit"]').scrollTop(0);

            this.submitListData();

    },
     submitListData: function(e) {

            var thisView = this;

            var forwardBlacklistUrl =  "/buyer/blacklist/add-and-remove/?type=whitelist";
            var data = '';
            var index = 0;
            _.each(this.fwdCollection.models, function(item){
                data += (index === 0) ? "supplierId[]=" + item.id : "&supplierId[]=" + item.id;
                index++;
            });

            $.ajax({
                type: "POST",
                data: data,
                url: forwardBlacklistUrl,
                success: function(){
                    thisView.render();
                    $('.savedMsg').fadeIn();
                    setTimeout(function(){
                        $('.savedMsg').fadeOut();
                    }, 3000);
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    alert(thrownError + ' ('+xhr.status+')');
                }
            });
        },

     /* can be deleted, if backend is corrected */
     oldSubmitListData: function(e) {

            var thisView = this;
            var data = {};

            var forwardBlacklistUrl =  "/buyer/blacklist/add-and-remove/?type=whitelist";
            _.each(this.fwdCollection.models, function(item){
                forwardBlacklistUrl += "&supplierId[]=" + item.id;
            });

                    $.ajax({
                        type: "POST",
                        url: "/buyer/blacklist/remove/",
                        data: {
                            type: 'whitelist'
                        },
                                success: function(){
                                    $.ajax({
                                        type: "POST",
                                        url: forwardBlacklistUrl,
                                        success: function(){
                                            thisView.render();
                                            $('.savedMsg').fadeIn();
                                            setTimeout(function(){
                                                $('.savedMsg').fadeOut();
                                            }, 3000);
                                        }
                                    });
                                }
                            });
 
        },

        onCancelEmailDialog : function()
        {
            $('#emialmodal').overlay().close();
        },

        onAddEmailDialog: function()
        {
            var emailAddress = ($('input[name="addEmailAddress"]').val()).trim();
            if (this.isEmail(emailAddress)) {

                if (this.emailItems.isEmailExists(emailAddress)) {
                    alert('This email address already exists');
                } else {
                    this.emailItems.saveEmail(emailAddress);
                }
                
                $('#emialmodal').overlay().close();
            } else {
                alert('Invalid email address');
            }
        },

        isEmail : function(email) {
              var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;
              return regex.test(email);
        }

   });

	return new filtersView();
});