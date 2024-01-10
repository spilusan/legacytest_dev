define([
    'jquery',
    'underscore',
    'Backbone',
    'libs/jquery.autocomplete',
    'jqueryui/droppable',
    'libs/jquery.uniform',
    '../collections/blackList',
    '../views/supplierItemView'
], function(
    $, 
    _, 
    Backbone,
    Autocomplete, 
    droppable,
    Uniform,
    blackList,
    supplierItemView
){
    var settingsView = Backbone.View.extend({
        
        el: $('body'),

        events: {
            'click input[name="submit"]' : 'submitData'
        },
        
        initialize: function(){
            $('#waiting').show();
            //supplier match blacklist collection
            this.matchCollection = new blackList();
            this.matchCollection.url = "/buyer/blacklist/get-all/";

            //supplier forward whitelist collection
            this.fwdCollection = new blackList();
            this.fwdCollection.url = "/buyer/blacklist/get-all/";

            //supplier match blacklist status collection
            this.mStatusCollection = new blackList();
            this.mStatusCollection.url = "/buyer/blacklist/enabled/";

            //supplierforward blacklist status collection
            this.fStatusCollection = new blackList();
            this.fStatusCollection.url = "/buyer/blacklist/enabled/";

            this.autoMatchCollection = new blackList();
            this.autoMatchCollection.url = "/buyer/match/settings/get";

            $('form#match-settings-form input[type="checkbox"]').uniform();
            $('form#match-settings-form input[type="radio"]').uniform();
            //get supplier match blacklist
            this.getData('blacklist');
        },

        getData: function(type){
            var thisView = this,
                fetchCollection = null;
            //determine type parameter for fetching collection
            if(type === "mStat") {
                var theType = "blacklist";
            }
            else if(type === "fStat") {
                var theType = "whitelist";
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
            else if(type === "mStat") {
                fetchCollection = this.mStatusCollection;
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
                        //get supplier match white
                        thisView.getData("whitelist");
                    }
                    else if(type === "whitelist") {
                        //get match blacklist status (true/false)
                        thisView.getData('mStat');
                    }
                    else if(type === "mStat") {
                        //get forward white status (true/false)
                        thisView.getData("fStat");
                    }
                    else {
                        thisView.autoMatchCollection.fetch({
                            complete: function(){
                                thisView.render();
                            }
                        });
                    }
                }
            });
        },
        
        render: function() {
            $('#waiting').hide();

            var thisView = this;

            if(this.matchCollection.length > 0){
                this.renderMatchSuppliers();
                $('ul.matchSupplierDisplay').show();
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

            $('input[name="supplierBlacklist"]').autocomplete({
                serviceUrl: '/buyer/blacklist/available/?type=whitelist',
                width:370,
                zIndex: 9999,
                minChars: 3,
                onStart: function(){
                    $('.spinnerMatch').css('display', 'inline-block');
                },
                onFinish: function(){
                    $('.spinnerMatch').hide();
                },
                onSelect: function(response) {
                    thisView.matchCollection.add({
                        id: response.data,
                        name: response.value
                    });
                    thisView.renderMatchSuppliers();
                    $('input[name="submit"]').focus();
                    $('input[name="supplierBlacklist"]').val("Search for Supplier to add (by name or TNID)");
                }
            });

            $('input[name="supplierWhitelist"]').autocomplete({
                serviceUrl: '/buyer/blacklist/available/?type=blacklist',
                width:370,
                zIndex: 9999,
                minChars: 3,
                onStart: function(){
                    $('.spinnerFwd').css('display', 'inline-block');
                },
                onFinish: function(){
                    $('.spinnerFwd').hide();
                },
                onSelect: function(response) {
                    thisView.fwdCollection.add({
                        id: response.data,
                        name: response.value
                    });
                    thisView.renderFwdSuppliers();
                    $('input[name="submit"]').focus();
                    $('input[name="supplierWhitelist"]').val("Search for Supplier to add (by name or TNID)");
                }
            });

            if(this.mStatusCollection.models[0].attributes.result){
                $('#useMatchBlacklist').attr('checked', 'checked');
            }

            if(this.fStatusCollection.models[0].attributes.result){
                $('#specificScope').attr('checked', 'checked');
            }
            else {
                $('#allScope').attr('checked', 'checked');
            }

            if(this.autoMatchCollection.models[0].attributes.autoMatch.participant){
                $('#automate').attr('checked', 'checked');
            }

            if(this.autoMatchCollection.models[0].attributes.autoMatch.cheapQuotesOnly){
                $('#onlyCheap').attr('checked', 'checked');
            }

            if(this.autoMatchCollection.models[0].attributes.maxMatchSuppliers !== null){
                $('#useMaxSuppliers').attr('checked', 'checked');
                $('input[name="numSuppliers"]').val(this.autoMatchCollection.models[0].attributes.maxMatchSuppliers);
            }

            $.uniform.update();
        },

        renderBlacklists: function(){
            this.renderMatchSuppliers();
            this.renderFwdSuppliers();
        },

        renderMatchSuppliers: function(){
            $('ul.matchSupplierDisplay').html('');
            _.each(this.matchCollection.models, function(item){
                this.renderMatchItem(item);
            }, this);
        },

        renderFwdSuppliers: function(){
            $('ul.forwardSupplierDisplay').html('');
            _.each(this.fwdCollection.models, function(item){
                this.renderFwdItem(item);
            }, this);
        },

        renderMatchItem: function(item){
            var matchSupplierItem = new supplierItemView({
                model: item
            });

            matchSupplierItem.parent = this;

            var elem = "ul.matchSupplierDisplay";
            $(elem).append(matchSupplierItem.render().el);
        },

        renderFwdItem: function(item){
            var fwdSupplierItem = new supplierItemView({
                model: item
            });

            fwdSupplierItem.parent = this;

            var elem = "ul.forwardSupplierDisplay";
            $(elem).append(fwdSupplierItem.render().el);
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
            e.preventDefault();
            $('.spinnerSave').css({'display' : 'inline-block'});
            var data = {};
            
            if($('#useMatchBlacklist').attr('checked'))
            {
                var matchBlacklistEnabled = 1;
            }
            else {
                var matchBlacklistEnabled = 0;
            }

            if($('#specificScope').attr('checked'))
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
                var onlyCheap = true;
            }

            if($('#useMaxSuppliers').attr('checked') && typeof parseInt($('input[name="numSuppliers"]').val()) === 'number' ){
                var numSuppliers = parseInt($('input[name="numSuppliers"]').val());
            }
            else {
                var numSuppliers = null;
            }
                
            var postUrl = "/buyer/blacklist/add/";

            var matchBlacklistUrl = postUrl + "?type=blacklist";
            _.each(this.matchCollection.models, function(item){
                matchBlacklistUrl += "&supplierId[]=" + item.id;
            }, this);

            var forwardBlacklistUrl = postUrl + "?type=whitelist";
            _.each(this.fwdCollection.models, function(item){
                forwardBlacklistUrl += "&supplierId[]=" + item.id;
            });

            var postStatusUrl = "/buyer/blacklist/enabled/";

            var autoMatchUrl = "/buyer/match/settings/update/";

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
                                url: matchBlacklistUrl,
                                success: function(){
                                    $.ajax({
                                        type: "POST",
                                        url: forwardBlacklistUrl,
                                        success: function(){
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
                                                            type: 'whitelist',
                                                            enabled: forwardBlacklistEnabled
                                                        },
                                                        success: function(){
                                                            $.ajax({
                                                                type: "POST",
                                                                url: autoMatchUrl,
                                                                data: {
                                                                    settings: JSON.stringify({
                                                                    maxMatchSuppliers : numSuppliers,
                                                                        autoMatch : {
                                                                        participant : autoMatchEnabled,
                                                                        cheapQuotesOnly : onlyCheap
                                                                    }
                                                                })
                                                             },
                                                             success: function(){
                                                              $('.spinnerSave').hide();
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
                }
            });
        }
    });

    return new settingsView;
});