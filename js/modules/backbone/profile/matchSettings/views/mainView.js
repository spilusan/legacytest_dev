define([
    'jquery',
    'underscore',
    'Backbone',
    'libs/jquery.tools.overlay.modified',
    'libs/jquery.autocomplete.list',
    'jqueryui/droppable',
    'libs/jquery.uniform',
    '../collections/blackList',
    '../views/supplierItemView',
    '../views/availableSupplierItemView',
    '../views/fileUploadView',
    '../views/accountListView'
], function(
    $, 
    _, 
    Backbone,
    Modal,
    Autocomplete, 
    droppable,
    Uniform,
    blackList,
    supplierItemView,
    availableSupplierItemView,
    fileUploadView,
    accountListView
){
    var settingsView = Backbone.View.extend({
        
        el: $('body'),

        events: {
            'click input[name="submit"]' : 'submitData',
            'click div.umatch.level div.switch.position' : 'setSliderPos',
            'click a.editScope' : 'showSupplierSelector',
            'click a.sbEditScope' : 'showSupplierSelector',
            'focus input.supplierList.available' : 'toggleInputValue',
            'blur input.supplierList.available' : 'toggleInputValue',
            'keyup input.selectedList.filter' : 'filterList',
            'click #linktoAppr' : 'validateFormChange',
            'click #useMaxSuppliers' : 'onMaxSupplierChange',
            'change #numSuppliers' : 'onNumSuppliersChange'
        },

        dialog: 'fwd',
        waiting: true,
        redirectUrl: null,
        
        initialize: function(){
            var thisView = this;
            $('body').ajaxStart(function(){
                if(thisView.waiting){
                    $('#waiting').show();
                }
            });
            $('body').ajaxStop(function(){
                $('#waiting').hide();
            });
            //supplier match blacklist collection
            this.matchCollection = new blackList();
            this.matchCollection.url = "/buyer/blacklist/get-all/";

            //supplier match blacklist collection
            this.matchCollectionTemp = new blackList();
            this.matchCollectionTemp.url = "/buyer/blacklist/get-all/";

            //supplier Match exclude from Spend benchmarking collection
            this.matchSbCollection = new blackList();
            this.matchSbCollection.url = "/buyer/blacklist/get-all/";

            this.matchSbCollectionTemp = new blackList();
            this.matchSbCollectionTemp.url = "/buyer/blacklist/get-all/";

            //supplier forward whitelist collection
            this.fwdCollection = new blackList();
            this.fwdCollection.url = "/buyer/blacklist/get-all/";

            //supplier forward temp whitelist collection
            this.fwdCollectionTemp = new blackList();
            this.fwdCollectionTemp.url = "/buyer/blacklist/get-all/";

            //supplier match blacklist status collection
            this.mStatusCollection = new blackList();
            this.mStatusCollection.url = "/buyer/blacklist/enabled/";

            //supplier match Spend Becnhmarking status collection
            this.sbStatusCollection = new blackList();
            this.sbStatusCollection.url = "/buyer/blacklist/enabled/";

            //supplierforward blacklist status collection
            this.fStatusCollection = new blackList();
            this.fStatusCollection.url = "/buyer/blacklist/enabled/";

            this.autoMatchCollection = new blackList();
            this.autoMatchCollection.url = "/buyer/match/settings/get";

            //$('form#match-settings-form input[type="checkbox"]').uniform();
            $('input[type="checkbox"]').uniform();
            $('select').uniform();
            
            //get supplier match blacklist
            this.getData('blacklist');

            var fileUpload = new fileUploadView();
            fileUpload.parent = this;

        },

        getData: function(type, solo){
            var thisView = this,
                fetchCollection = null;

            //determine type parameter for fetching collection
            if(type === "mStat") {
                var theType = "blacklist";
            }
            else if(type === "fStat") {
                var theType = "whitelist";
            }
            else if(type === "sbStat") {
                var theType = "blacksb";
            }
            else {
                var theType = type;
            }

            //determine collection to fetch
            if(type === "blacklist"){
                fetchCollection = this.matchCollection;
            }
            else if(type === "whitelist") {
                fetchCollection = this.fwdCollection;
            }
            else if(type === "blacksb") {
                fetchCollection = this.matchSbCollection;
            }
            else if(type === "mStat") {
                fetchCollection = this.mStatusCollection;
            }
            else if(type === "sbStat") {
                fetchCollection = this.sbStatusCollection;
            }
            else {
                fetchCollection = this.fStatusCollection;
            }
            //fetch collections
           fetchCollection.fetch({
                data: $.param({
                    type: theType
                }),
                complete: function() {
                    if(type === "blacklist"){
                        thisView.matchCollectionTemp.reset();
                        _.each(thisView.matchCollection.models, function(item){
                            thisView.matchCollectionTemp.add(item.attributes);
                        });

                        //get supplier match white
                        if(!solo) {
                            thisView.getData("whitelist");
                        }
                        else {
                            thisView.render();
                        }
                    }
                    else if(type === "whitelist") {
                        thisView.fwdCollectionTemp.reset();
                        _.each(thisView.fwdCollection.models, function(item){
                            thisView.fwdCollectionTemp.add(item.attributes);
                        });
                        //get match blacklist status (true/false)
                        if(!solo) {
                            thisView.getData('blacksb');
                        }
                        else {
                            thisView.render();
                        }
                    }
                    else if(type === "blacksb") {
                        thisView.matchSbCollectionTemp.reset();
                        _.each(thisView.matchSbCollection.models, function(item){
                            thisView.matchSbCollectionTemp.add(item.attributes);
                        });
                        //get match blacklist status (true/false)
                        if(!solo) {
                            thisView.getData('sbStat');
                        }
                        else {
                            thisView.render();
                        }
                    }
                    else if(type === "sbStat") {
                        //get forward white status (true/false)
                        thisView.getData("mStat");
                    }
                    else if(type === "mStat") {
                        //get forward white status (true/false)
                        thisView.getData("fStat");
                    }
                    else {
                        thisView.autoMatchCollection.fetch({
                            data: {
                                showDetails: true
                            },
                            complete: function(){
                                thisView.render();
                            }
                        });
                    }
                }
            });
        },
        
        render: function() {

            var thisView = this;

            if (thisView.autoMatchCollection.models[0]) {
                var accountList = new accountListView({
                    model: thisView.autoMatchCollection.models[0].attributes,
                });

                accountList.render();
            }

            $('#waiting').hide();

                // Match level slider
             var  matchLevel = {
                    elem: $('div.umatch.level'),
                    slider: $('div.umatch.level div.slider'),
                    positions: $('div.umatch.level div.switch.position')
                };
            
            matchLevel.positions.droppable({
                drop: function(event, ui){
                    thisView.setSliderPos(event);
                }
            });
            
            matchLevel.slider.show();

            matchLevel.slider.draggable({ 
                containment: matchLevel.elem,
                axis: 'y',
                snap: '.switch.position',
                snapMode: 'inner',
                snapTolerance: 10,
                revert: 'invalid',
                revertDuration: 200
            });
            /* End match level slider */

            if(this.matchCollection.length > 0){
                this.renderMatchSuppliers();
                $('div.matchSupplierDisplay').show();
            }

            if(this.matchSbCollection.length > 0){
                this.renderSbMatchSuppliers();
                $('div.matchSbSupplierDisplay').show();
            }


            if(this.fwdCollection.length > 0){
                this.renderFwdSuppliers();
                $('ul.forwardSupplierDisplay').show();
            }

            $('input.textInput').bind('focus', function(e){
                e.preventDefault();
                thisView.toggleInputValue(e);
            });

            $('input.textInput').bind('blur', function(e){
                e.preventDefault();
                thisView.toggleInputValue(e);
            });

            // set match forward scope
            if(this.fStatusCollection.models[0] && this.fStatusCollection.models[0].attributes.result ){
                $('input[name="scope"]').attr('checked', false);
                $('input[name="scope"][value="specific"]').attr('checked', 'checked');
                $('div.umatch.level div.slider').css({top: $('input[name="scope"][value="specific"]').parent().parent().position().top - 5});
            }
            else {
                $('input[name="scope"]').attr('checked', false);
                $('input[name="scope"][value="all"]').attr('checked', 'checked');
                $('div.umatch.level div.slider').css({top: $('input[name="scope"][value="all"]').parent().parent().position().top - 2});
            }
            // set match blacklist scope
            if(this.mStatusCollection.models[0] && this.mStatusCollection.models[0].attributes.result){
                $('#useMatchBlacklist').attr('checked', 'checked');
            }

            if(this.sbStatusCollection.models[0] && this.sbStatusCollection.models[0].attributes.result){
                $('#useSbMatchBlacklist').attr('checked', 'checked');
            }
            if (thisView.autoMatchCollection.models[0]) {
                if(this.autoMatchCollection.models[0].attributes.default.hideContactDetails){
                    $('#hideContact').attr('checked', 'checked');
                }
            

                //set max. match suppliers
                if(this.autoMatchCollection.models[0].attributes.default.maxMatchSuppliers !== null){
                    $('#useMaxSuppliers').attr('checked', 'checked');
                    /*
                    $('input[name="numSuppliers"]').val(this.autoMatchCollection.models[0].attributes.maxMatchSuppliers);
                    */
                    $('#numSuppliers').val(this.autoMatchCollection.models[0].attributes.default.maxMatchSuppliers);
                    
                }
            }

            $.uniform.update();

            $('#modal .modalBody input.apply').bind('click', function(e){
                e.preventDefault();
                thisView.saveTempSuppliers();
            });

            $('#match-settings-form').data('serialize',$('#match-settings-form').serialize());

        },

        renderBlacklists: function(){
            this.renderMatchSuppliers();
            this.renderMatchSbSuppliers();
            this.renderFwdSuppliers();
        },

        renderMatchSuppliers: function(){
            $('div.matchSupplierDisplay').html('');
            var count = 0;
            _.each(this.matchCollection.models, function(item){
                count++;
                this.renderMatchItem(item, count);
            }, this);
        },

        renderSbMatchSuppliers: function(){
            $('div.matchSbSupplierDisplay').html('');
            var count = 0;
            _.each(this.matchSbCollection.models, function(item){
                count++;
                this.renderSbMatchItem(item, count);
            }, this);
        },

        renderAvailableMatchSuppliers: function(){
            $('ul.selectlist.selected').html('');
            _.each(this.matchCollectionTemp.models, function(item){
                this.renderAvailableMatchItem(item);
            }, this);
        },

        renderFwdSuppliers: function(){
            $('ul.forwardSupplierDisplay').html('');
            var count = 0;
            _.each(this.fwdCollection.models, function(item){
                count++;
                this.renderFwdItem(item, count);
            }, this);
        },

        renderAvailableFwdSuppliers: function(){
            $('ul.selectlist.selected').html('');
            _.each(this.fwdCollectionTemp.models, function(item){
                this.renderAvailableFwdItem(item);
            }, this);
        },

        renderAvailableSbSuppliers: function(){
            $('ul.selectlist.selected').html('');
            _.each(this.matchSbCollectionTemp.models, function(item){
                this.renderAvailableSbItem(item);
            }, this);
        },
        

        renderMatchItem: function(item, count){
            var matchSupplierItem = new supplierItemView({
                model: item
            });

            matchSupplierItem.parent = this;

            var elem = "div.matchSupplierDisplay";
            if(count <= 5){
                $(elem).append(matchSupplierItem.render().el);
                if(count < this.matchCollection.models.length && count < 6){
                    $(elem).find('span:last-child').append(',&nbsp;');
                }
            }
            else if(count == 6){
                $(elem).find('span:last-child').append(' + '+(this.matchCollection.models.length-count)+' more suppliers');
            }
            
        },

        renderSbMatchItem: function(item, count){
            var matchSupplierItem = new supplierItemView({
                model: item
            });

            matchSupplierItem.parent = this;

            var elem = "div.matchSbSupplierDisplay";
            if(count <= 5){
                $(elem).append(matchSupplierItem.render().el);
                if(count < this.matchSbCollection.models.length && count < 6){
                    $(elem).find('span:last-child').append(',&nbsp;');
                }
            }
            else if(count == 6){
                $(elem).find('span:last-child').append(' + '+(this.matchSbCollection.models.length-count)+' more suppliers');
            }
            
        },

        renderAvailableMatchItem: function(item){
            var availableMatchSupplierItem = new availableSupplierItemView({
                model: item
            });

            availableMatchSupplierItem.type = "blacklist";

            availableMatchSupplierItem.parent = this;

            var elem = "ul.selectlist.selected";
            $(elem).append(availableMatchSupplierItem.render().el);
        },

        renderFwdItem: function(item, count){
            var fwdSupplierItem = new supplierItemView({
                model: item
            });

            fwdSupplierItem.parent = this;

            var elem = "ul.forwardSupplierDisplay";
            if(count <= 5){
                $(elem).append(fwdSupplierItem.render().el);
                if(count < this.fwdCollection.models.length && count < 6){
                    $(elem).find('li:last-child').append(',&nbsp;');
                }
            }
            else if(count == 6){
                $(elem).find('li:last-child').append('...');
            }
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


        renderAvailableSbItem: function(item){
            var availableSbSupplierItem = new availableSupplierItemView({
                model: item
            });

            availableSbSupplierItem.type = "blacksb";

            availableSbSupplierItem.parent = this;

            var elem = "ul.selectlist.selected";
            $(elem).append(availableSbSupplierItem.render().el);
        },

        setSliderPos: function(e){
            if($(e.target).find('input').val() == 'specific'){
                $('div.umatch.level div.slider').css({top: $(e.target).parent().position().top - 5});
            }
            else {
                $('div.umatch.level div.slider').css({top: $(e.target).parent().position().top - 2});
            }

            $('input[name=scope]').attr('checked', false);
            $(e.target).find('input[name=scope]').attr('checked', true);
        },

        toggleInputValue: function(e){
            if($(e.target).val() === ""){
                $(e.target).val("Search for Supplier to add (by name or TNID)");
            }
            else if($(e.target).val() === "Search for Supplier to add (by name or TNID)") {
                $(e.target).val("");
            }
        },
        
        submitData: function(e) {
            if(e){
                e.preventDefault();
            }
            /*$('.spinnerSave').css({'display' : 'inline-block'});*/
            var data = {};
            var thisView = this;
            
            if($('#useMatchBlacklist').attr('checked'))
            {
                var matchBlacklistEnabled = 1;
            }
            else {
                var matchBlacklistEnabled = 0;
            }

            if($('#useSbMatchBlacklist').attr('checked'))
            {
                var matchSbBlacklistEnabled = 1;
            }
            else {
                var matchSbBlacklistEnabled = 0;
            }

            if($('input[name="scope"][value="specific"]').attr('checked'))
            {
                var forwardBlacklistEnabled = 1;
            }
            else {
                var forwardBlacklistEnabled = 0;
            }

            if($('#automate').attr('checked')){
                var autoMatchEnabled = true;
            }
            else {
                var autoMatchEnabled = false;
            }

            if($('#onlyCheap').attr('checked')) {
                var onlyCheap = true;
            }
            else {
                var onlyCheap = false;
            }

            if($('#hideContact').attr('checked')) {
                var hideContact = true;
            }
            else {
                var hideContact = false;
            }

            if($('#useMaxSuppliers').attr('checked') && typeof parseInt($('#numSuppliers').val()) === 'number' ){
               var numSuppliers = parseInt($('#numSuppliers').val());
            }
            else {
                var numSuppliers = null;
            }


            /* 
                Here I overwrite the values of blacklist checkbox states, as the checkboxes are hidden, and always have to store 1 as checked state 
                We keep it hidden, and also keep the functionality in backend
            */
            matchSbBlacklistEnabled = 1;
            matchBlacklistEnabled = 1;
            
            var postStatusUrl = "/buyer/blacklist/enabled/";
            var autoMatchUrl = "/buyer/match/settings/update/";

            var formData = {
                data: [],
                default: {
                    maxMatchSuppliers : numSuppliers,
                    hideContactDetails: hideContact,
                        autoMatch : {
                        participant : autoMatchEnabled,
                        cheapQuotesOnly : onlyCheap
                    }
                }
            }

            /* Creating an array combining the checkbox statuses of automate, and onlyCheap elements*/
            var checkBoxStatuses = [];

            $('input[name="automate[]"]').each(function(){
                var branchId = $(this).data('id');
                var enabled = $(this).attr('checked') === 'checked';
                checkBoxStatuses[branchId] = {
                    amEnabled: enabled
                    ,oCheap: null
                }
            });

            $('input[name="onlyCheap[]"]').each(function(){
                var branchId = $(this).data('id');
                var enabled = $(this).attr('checked') === 'checked';
                checkBoxStatuses[branchId].oCheap = enabled;
            });

            /* Creating a list for the checkbox statuses, and fallback the paremeters of general settings */
            for (key in checkBoxStatuses) {
                formData.data.push({
                    branchId: key,
                    data: {
                        maxMatchSuppliers : numSuppliers,
                        hideContactDetails: hideContact,
                            autoMatch : {
                            participant : checkBoxStatuses[key].amEnabled,
                            cheapQuotesOnly : checkBoxStatuses[key].oCheap,
                        }
                    }
                });
            }

            //Store actual settings
            $.ajax({
                type: "POST",
                url: postStatusUrl,
                data: {
                    type: 'blacklist',
                    enabled: matchBlacklistEnabled
                },
                    success: function(){
                                $.ajax({
                                    type: "POST",
                                    url: postStatusUrl,
                                    data: {
                                        type: 'blacksb',
                                        enabled: matchSbBlacklistEnabled
                                    },
                            success: function(){
                                $.ajax({
                                    type: "POST",
                                    url: postStatusUrl,
                                    data: {
                                        type: 'whitelist',
                                        enabled: forwardBlacklistEnabled
                                    },
                                    success: function(){
                                        $.ajax({
                                            type: "POST",
                                            url: autoMatchUrl,
                                            data: {
                                                showDetails: true,
                                                fallbackGeneralSettings: true,
                                                settings: JSON.stringify(formData)
                                            },
                                            success: function(){
                                                if (null !== thisView.redirectUrl) {
                                                    window.location.href = thisView.redirectUrl;
                                                } else {
                                                    $('#match-settings-form').data('serialize',$('#match-settings-form').serialize());
                                                    $('.savedMsg').fadeIn();
                                                    setTimeout(function(){
                                                        $('.savedMsg').fadeOut();
                                                    }, 3000);
                                                }
                                            }
                                        });
                                    }
                                });
                            }
                         });
                    }
            });
        },

        submitListData: function(e) {
            if(e){
                e.preventDefault();
            }

            var data = {};
                
            var postUrl = "/buyer/blacklist/add/";

            var matchBlacklistUrl = postUrl + "?type=blacklist";
            _.each(this.matchCollection.models, function(item){
                matchBlacklistUrl += "&supplierId[]=" + item.id;
            }, this);

            var forwardBlacklistUrl = postUrl + "?type=whitelist";
            _.each(this.fwdCollection.models, function(item){
                forwardBlacklistUrl += "&supplierId[]=" + item.id;
            });

            var sbBlacklistUrl = postUrl + "?type=blacksb";
            _.each(this.matchSbCollection.models, function(item){
                sbBlacklistUrl += "&supplierId[]=" + item.id;
            });

            $.ajax({
                type: "POST",
                url: "/buyer/blacklist/remove/",
                data: {
                    type: 'blacklist'
                },
                success: function(){
                    $.ajax({
                        type: "POST",
                        url: "/buyer/blacklist/remove/",
                        data: {
                            type: 'whitelist'
                        },
                        success: function(){
                        $.ajax({
                            type: "POST",
                            url: "/buyer/blacklist/remove/",
                            data: {
                                type: 'blacksb'
                            },
                            success: function(){
                                $.ajax({
                                    type: "POST",
                                    url: matchBlacklistUrl,
                                success: function(){
                                    $.ajax({
                                        type: "POST",
                                        url: sbBlacklistUrl,
                                            success: function(){
                                                $.ajax({
                                                    type: "POST",
                                                    url: forwardBlacklistUrl,
                                                    success: function(){
                                                        $('.savedMsg').fadeIn();
                                                        setTimeout(function(){
                                                            $('.savedMsg').fadeOut();
                                                        }, 3000);
                                                    }
                                                });
                                            }
                                        });
                                    }
                                });
                            }
                         });
                       }
                    });
                }
            });
        },

        showSupplierSelector: function(e){
            e.preventDefault();
            


            if($(e.target).hasClass('spec')){
                this.dialog = 'fwd';
            }
            else if ($(e.target).hasClass('sbBlist')){
                this.dialog = 'sbBlackList'
            }
            else {
                this.dialog = 'match'
            }

            if(this.dialog == 'fwd'){
                this.renderAvailableFwdSuppliers();
                var sUrl = '/buyer/blacklist/available/?type=whitelist';
            }
            else if(this.dialog == 'sbBlackList'){
                this.renderAvailableSbSuppliers();
                var sUrl = '/buyer/blacklist/available/?type=blacksb';
            }
            else {
                this.renderAvailableMatchSuppliers();
                var sUrl = '/buyer/blacklist/available/?type=blacklist';
            }

            var thisView = this;

            $('input.supplierList.available').autocomplete({
                serviceUrl: sUrl,
                width:370,
                zIndex: 9999,
                minChars: 3,
                appendTo: 'div.selectlist.available',
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
                    if(thisView.dialog == 'fwd'){
                        thisView.fwdCollectionTemp.add({
                            id: response.data,
                            name: response.value
                        });
                        thisView.renderAvailableFwdSuppliers();
                    }
                   else if(thisView.dialog == 'sbBlackList'){
                        thisView.matchSbCollectionTemp.add({
                            id: response.data,
                            name: response.value
                        });
                        thisView.renderAvailableSbSuppliers();
                    }
                    else {
                        thisView.matchCollectionTemp.add({
                            id: response.data,
                            name: response.value
                        });
                        thisView.renderAvailableMatchSuppliers();
                    }
                    
                }
            });

            this.openDialog();
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
                    if(this.dialog == 'fwd'){
                        thisView.fwdCollectionTemp.reset();
                        _.each(thisView.fwdCollection.models, function(item){
                            thisView.fwdCollectionTemp.add(item.attributes);
                        });
                    }
                    else if(this.dialog == 'sbBlackList'){
                        thisView.matchSbCollectionTemp.reset();
                        _.each(thisView.matchSbCollectionTemp.models, function(item){
                            thisView.matchSbCollectionTemp.add(item.attributes);
                        });
                    }
                    else {
                        thisView.matchCollectionTemp.reset();
                        _.each(thisView.matchCollection.models, function(item){
                            thisView.matchCollectionTemp.add(item.attributes);
                        });
                    }
                }
            });

            $('#modal').overlay().load();
        },

        saveTempSuppliers: function(){

            var thisView = this;

            if(this.dialog == 'fwd'){
                this.fwdCollection.reset();
                _.each(this.fwdCollectionTemp.models, function(item){
                    this.fwdCollection.add(item.attributes);
                }, this);

                this.renderFwdSuppliers();
            }
            else if(this.dialog == 'sbBlackList'){
                this.matchSbCollection.reset();
                _.each(this.matchSbCollectionTemp.models, function(item){
                    this.matchSbCollection.add(item.attributes);
                }, this);

                this.renderSbMatchSuppliers();
            }
            else {
                this.matchCollection.reset();
                _.each(this.matchCollectionTemp.models, function(item){
                    this.matchCollection.add(item.attributes);
                }, this);

                this.renderMatchSuppliers();
            }

            $('input.selectedList.filter').val('');
            $('#modal').overlay().close();
            $('input[name="submit"]').scrollTop(0);

            /* We must clear Spend Benchmarking cache  */
            $.ajax({
                type: "GET",
                url: "/buyer/blacklist/clear-spend-benchmark-cache",
                success: function(){
                    thisView.submitListData();
                },
                error: function(e) {
                    alert('Something went wrong, Please try it later');
                }
            });
            
        },
        
        filterList: function(e) {
            if(e){
                var elem = $(e.target);
            }
            else {
                var elem = $('input.selectedList.filter');
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
        
        formHasChanged : function()
        {
            if($('#match-settings-form').serialize()!=$('#match-settings-form').data('serialize')){
                return true;
            }
            return false ;
        },

        validateFormChange : function( e )
        {
           this.redirectUrl = null;
            if (this.formHasChanged()) {
                    if (confirm("Changes to settings have been made. Save it?") == true) {
                        e.preventDefault();
                        this.redirectUrl = $(e.target).attr('href');
                        this.submitData();
                    } 
            } 
        },

        onMaxSupplierChange: function( e )
        {
           
            if(!$('#useMaxSuppliers').attr('checked') ){
                $('#numSuppliers').find('option').first().attr('selected','selected');
                $.uniform.update("#numSuppliers");
            } else {
                $('#numSuppliers').val('1');
                $.uniform.update("#numSuppliers");
            }
        },
        onNumSuppliersChange: function( e )
        {

            if ($('#numSuppliers').val() == '-') {
                $('#useMaxSuppliers').attr('checked', false);
            } else {
                $('#useMaxSuppliers').attr('checked', 'checked');
            }
            $.uniform.update("#useMaxSuppliers");
        }
    });

    return new settingsView;
});