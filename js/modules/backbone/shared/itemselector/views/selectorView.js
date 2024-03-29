define([
    'jquery',
    'underscore',
    'Backbone',
    'handlebars',
    'backbone/shared/hbh/general',
    'libs/jquery.tools.overlay.modified',
    '../collections/items',
    '../views/items/defaultItemView',
    'text!templates/shared/itemselector/tpl/selector.html'
], function(
    $,
    _,
    Backbone,
    Hb,
    generalHbh,
    Modal,
    ItemsCollection,
    DefaultItemView,
    selectorTpl
){
    var selectorCount = 0;
    
    var SelectorView = Backbone.View.extend({
        
        /*Config section*/
        backupSelectedStatus: [],
        applyClicked: false,

        itemTypes: ['categories', 'suppliers' , 'brands', 'locations'],
        
        itemTypeConfigs: {
        
            categories: {
                displayKey: 'DISPLAYNAME',
                primaryKey: 'ID',
                showRightFilter: true,
                url: ' /data/source/categories',
                singluar: 'category',
                plural: 'categories',
                title: 'Select categories',
                itemtype: 'categories',
                leftItemView : DefaultItemView,
                rightItemView : DefaultItemView
            },
            
            suppliers: {
                displayKey: 'name',
                primaryKey: 'id',
                showRightFilter: true,
                url: '/data/source/supplier-branches',
                singluar: 'supplier',
                plural: 'suppliers',
                title: 'Select suppliers',
                itemtype: 'suppliers',
                leftItemView : DefaultItemView,
                rightItemView : DefaultItemView
            },

            brands: {
                displayKey: 'NAME',
                primaryKey: 'ID',
                showRightFilter: true,
                url: '/data/source/brands',
                singular: 'brand',
                plural: 'brands',
                title: 'Select brands',
                itemtype: 'brands',
                leftItemView : DefaultItemView,
                rightItemView : DefaultItemView
            },
            
            locations: {
                displayKey: 'name',
                primaryKey: 'id',
                showRightFilter: true,
                url: '/data/source/locations',
                singular: 'location',
                plural: 'locations',
                title: 'Select locations',
                itemtype: 'locations',
                leftItemView: DefaultItemView,
                rightItemView: DefaultItemView
            }
        },
        
        /*End config section*/
        
        events: {
            'keyup .filterField' : 'filterList',
            'click .remove.all' : 'removeAll',
            'click input.apply' : 'onApply'
        },
        
        itemCollection: null,
        
        selectorTemplate: Handlebars.compile(selectorTpl),
        
        selectedItems: [], /*temporary storage of selected items until complete left list is loaded*/
        
        initialized: false,
        
        initialize: function(opts) {
            itemType = opts.itemType;
            /*Immediately*/
            this.itemCollection = new ItemsCollection();

            /* Define default config here. Anything passed to the constructor will override these. */
            if(!itemType) alert('itemType must be set in selectorView(opts) paramter');
            this.config = this.itemTypeConfigs[itemType];
            
            //Configure collections
            this.itemCollection.url = this.config.url;
            
            //Create a new div to hold our itemselector modal
            var settingsEl = $('<div id="itemselector-'+(++selectorCount)+'"></div>');
            this.setElement(settingsEl);
        },//End initialize
        
        saveState: function() {
            var thisView = this;
            this.selectedItems = [];

            var primaryKey = thisView.config.primaryKey,
                displayKey = thisView.config.displayKey;

            _.each(this.itemCollection.models, function(item) {
                if (item.selected) {
                    temp = [];
                    primKeyValue = item.attributes[primaryKey],
                    displayValue = item.attributes[displayKey]

                    temp[primaryKey] = primKeyValue;
                    temp[displayKey] = displayValue;
                    thisView.selectedItems.push(temp);
                }
                item.selected = false;
            });
        },
        
        onApply: function() {
            this.applyClicked = true;
            this.saveState();
            $('#modal').overlay().close();
            $('#modal .modalBody').empty();
            this.trigger('apply');
        },
        
        show: function() {
            var thisView = this;
            //Fetch data
            //Left
            if (this.initialized) {
                if (thisView.selectedItems) {
                    _.each(thisView.selectedItems, function(item) {
                        var findMe = {};
                        
                        findMe[thisView.config.primaryKey] = item[thisView.config.primaryKey];
                        var found = thisView.itemCollection.where(findMe)[0];

                        if(!found) {
                            findMe = {};
                            findMe[thisView.config.primaryKey] = item[thisView.config.primaryKey].toString();
                        }

                        found = thisView.itemCollection.where(findMe)[0];

                        if (found) {
                            found.selected = true;
                        }
                    });
                }
                
                if(thisView.itemCollection.models[0].has('DEPTH') && thisView.config.hierarchy === undefined) {
                    thisView.config.hierarchy = true;
                }
                
                this.render();
            } else {
                this.itemCollection.fetch({
                    complete: function() {
                        //Remove left elements that are pre-set in right list
                        if (thisView.selectedItems) {
                            _.each(thisView.selectedItems, function(item) {
                                var findMe = {};
                                
                                findMe[thisView.config.primaryKey] = item[thisView.config.primaryKey];

                                var found = thisView.itemCollection.where(findMe)[0];

                                if(!found) {
                                    findMe = {};
                                    findMe[thisView.config.primaryKey] = item[thisView.config.primaryKey].toString();
                                }

                                found = thisView.itemCollection.where(findMe)[0];

                                if (found) {
                                    found.selected = true;
                                }
                            });
                        }
                        
                        if(thisView.itemCollection.models[0].has('DEPTH') && thisView.config.hierarchy === undefined) {
                            thisView.config.hierarchy = true;
                        }
                        
                        thisView.initialized = true;
                        thisView.render();
                    }
                });
            }  
        },
        
        render: function() {
            // hide spinner
            //var thisView = this,
            $(this.el).empty();
            var html = this.selectorTemplate({
                    showRightFilter: this.config.showRightFilter,
                    itemtype: this.config.itemtype,
                    title: this.config.title
                }),
                leftElements, 
                rightElements;
                
            $(this.el).html(html);
            
            //Render items
            this.$el.find('ul.left').empty();
            this.$el.find('ul.right').empty();
            
            _.each(this.itemCollection.models, function(item) {
                this.renderItem(item);
            }, this);
            
            //Display modal
            $('#modal .modalBody').html(this.el);
            this.openDialog();
            
            //thisView.delegateEvents();
            
            if(this.config.hierarchy) {
                //Run setHasVisibleChild to update hasVisibleChild classes on whole list
                leftElements = this.$el.find('ul.left>li');        
                this.setHasVisibleChild(leftElements);
                rightElements = this.$el.find('ul.right>li');
                this.setHasVisibleChild(rightElements);
            }

            this.delegateEvents();
            $('#waiting').hide();
        },
        
        setHasVisibleChild: function (elements) {
            //Set 'hasVisibleChild' class on items where parent is selected and child is not selected
            
            elements.removeClass('hasVisibleChild');
            
            $.each(elements.filter(':visible'), function(i, elem) {
                var elemDepth = $(elem).data('depth');
                if( elemDepth > 0) {
                    var foundTopmost = false,
                        nextPrevious = $(elem).prev();
                        
                    while(!foundTopmost) {
                        if (nextPrevious.data('depth') < elemDepth && nextPrevious.is(':hidden')) {
                            nextPrevious.addClass('hasVisibleChild');
                        }
                        if (nextPrevious.data('depth') == 0) {
                            foundTopmost = true;
                        }else {
                            nextPrevious = $(nextPrevious[0]).prevAll('.indent'+(Number(nextPrevious.data('depth')) - 1)+':first');
                        }
                    }
                }
            });
        },
        
        renderItem: function(item) {
            var selectorView = this,
                leftItemView,
                rightItemView;
                
            //Do appropriate item rendering depending on item type
            item.attributes.itemType = this.config.itemType;

            leftItemView = new this.config.leftItemView({model: item, displayKey: this.config.displayKey, hierarchy: this.config.hierarchy});
            rightItemView = new this.config.rightItemView({model: item, displayKey: this.config.displayKey, hierarchy: this.config.hierarchy});
            
            leftItemView.parent = this;
            rightItemView.parent = this;
            leftItemView.side = 'left';
            rightItemView.side = 'right';
            
            leftItemView.counterpart = rightItemView;
            rightItemView.counterpart = leftItemView;
            
            leftItemView.bind('move', function(){
                selectorView.moveItem(this);
            });
            
            rightItemView.bind('move', function(){
                selectorView.moveItem(this);
            })
            
            this.$el.find('ul.left').append(leftItemView.render().el);
            this.$el.find('ul.right').append(rightItemView.render().el);
        },
        
        moveItem: function(itemView) {
            var childModels;
            
            //Toggle selected property of model to cause move
            itemView.model.toggleSelected();
            
            if (this.config.hierarchy) {
                childModels = this.itemCollection.findChildren(itemView.model);
            
                //Toggle selected on each child's model
               /* _.each(childModels, function(childModel){
                    childModel.setSelected(itemView.model.selected);
                });*/
            
                //setHasVisibleChild on left side
                if (this.config.hierarchy) {
                    var leftItemEl = itemView.side == 'left' ? itemView.$el : itemView.counterpart.$el,
                        parent = leftItemEl.hasClass('indent0') ? leftItemEl : leftItemEl.prevAll('.indent0:first'),
                        rightItemEl = itemView.side == 'right' ? itemView.$el : itemView.counterpart.$el,
                        elements = parent.add(parent.nextUntil('.indent0'));

                    this.setHasVisibleChild(elements);
                
                    parent = rightItemEl.hasClass('indent0') ? rightItemEl : rightItemEl.prevAll('.indent0:first');
                    elements = parent.add(parent.nextUntil('.indent0'));
                
                    //this.setHasVisibleChild(elements);
                }
            }
        },
        
        removeAll: function() {
            _.each(this.itemCollection.models, function(m){
                m.setSelected(false);
            });
        },
        
        
        filterList: function(e) {
            var ul = $(e.target).nextAll('ul:first'),
                substr = $(e.target).val(),
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
        
        setSelectedItems: function(items){
            this.selectedItems = items;            
        },
        
        getSelectedItems: function() {
            var thisView = this;
            
            var data = [];
            _.each(thisView.itemCollection.models, function(item){
                if (item.selected) {
                    data.push(item.attributes);
                }
            });
            return data;
        },

        openDialog: function() { 

            var thisView = this;
           
            for (key in thisView.itemCollection.models) {
                thisView.backupSelectedStatus[key] = thisView.itemCollection.models[key].selected;
            }

            $('#modal').removeClass('tags');
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

                onClose: function() {
                    $('#modal .modalBody').empty();
                    if (thisView.applyClicked === true) {
                        thisView.applyClicked = false;
                    } else {
                        for (key in thisView.itemCollection.models) {
                            thisView.itemCollection.models[key].selected = thisView.backupSelectedStatus[key];
                        }
                    }
                },
            });

            $('#modal').overlay().load();
        }

    });
    
    return SelectorView;
});
