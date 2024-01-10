define([
    'jquery',
    'underscore',
    'Backbone',
    'libs/jquery.tools.overlay.modified',
    'libs/jquery.uniform',
    'libs/jquery.shipserv-tooltip',
    'components/infoPopup/views/mainView',
    '../collections/collection',
    '../collections/keywordCollection',
    '../views/pendingRowView',
    '../views/targetedRowView',
    '../views/excludedRowView',
    '../views/settingsRowView',
    '../views/keywordRowView',
    'text!templates/profile/targetCustomers/tpl/how-it-works.html'
], function(
    $, 
    _, 
    Backbone,
    Modal,
    Uniform,
    shTooltip,
    infoPopup,
    collection,
    keywordCollection,
    pendingRowView,
    targetedRowView,
    excludedRowView,
    settingsRowView,
    keywordRowView,
    howItWorksTemplate
){
    var targetCustomersView = Backbone.View.extend({
    		howItWorksTpl : Handlebars.compile(howItWorksTemplate),
        el: $('body'),
        keywordStatuses : null,
        keywordList: null,
              events: {
            'click .tab.pending'                : 'showPending',
            'click .tab.targeted'               : 'showTargeted',
            'click .tab.excluded'               : 'showExcluded',
            'click .btn.settings'               : 'showSettings',
            'click .btn.closeSettings'          : 'closeSettings',
            'click input[name="saveSettings"]'  : 'saveSettings',
            'click input#maxTrue'               : 'toggleActiveMax',
            'click .btn.howitworks'			   : 'showHowItWorks',
            'click .tab-list-holder div'		   : 'onChangeSettingsTab',
            'click .cat-settings-clear-all'	   : 'onCatSetClearAll',
            'click .cat-settings-select-all'	   : 'onCatSetSelectAll',
            'click .cat-settings-save'		   : 'onCatSave',
            'change select[name="segments"]'	   : 'onSelectSegment',
            'mouseover img.rfqrecinfo'          : 'showRfqTooltip',
            'mouseout img.rfqrecinfo'           : 'hideRfqTooltip',
            'mouseover img.qotinfo'             : 'showQotTooltip',
            'mouseout img.qotinfo'              : 'hideQotTooltip',
            'mouseover img.ordvalinfo'          : 'showOrdvalTooltip',
            'mouseout img.ordvalinfo'           : 'hideOrdvalTooltip',
            'mouseover img.sbinfo'              : 'showSbTooltip',
            'mouseout img.sbinfo'               : 'hideSbTooltip'
        },

        fetched: 0,
        settings: "",
        notificationSettings: "",
        tab: require('profile/targetCustomers/tab'),
        matchUrl: require('match/url'),
        activeTnid: require('alert/tnid'),
        dontRenderSettings: false,

        initialize: function(){
            var thisView = this;
            
            $('body').ajaxStart(function(){
            	$('#waiting').show();
            });
            
            $('body').ajaxStop(function(){
            	$('#waiting').hide();
            });
            
            $('#maxTrue').uniform();
            $('#maxQotDrop').uniform();
            $('#waiting').hide();

            this.pendingCollection = new collection();
            this.targetedCollection = new collection();
            this.excludedCollection = new collection();
            this.settingsCollection = new collection();
            this.keywordsCollection = new keywordCollection();
                      
            this.getData();
        },

        getData: function(){
            var thisView = this;
            
            if(this.tab === "promo"){
            	this.showTargeted();
            } else if(thisView.tab === "exclude"){
            	this.showExcluded();
            } else {
            	this.getPending();
            }

        },
        
        
        getPending: function(){
            var thisView = this;
            
            this.pendingCollection.fetch({
                data: $.param({
                    type: 'pending'
                }),
                type: "GET",
                complete: function(){
                	thisView.renderPendingItems();
                }
            });
        },
        
        getTargeted: function(){
            var thisView = this;

            this.targetedCollection.fetch({
                data: $.param({
                    type: 'targeted'
                }),
                type: "GET",
                complete: function(){
                	thisView.renderTargetedItems();

                    $('.cont.targeted .rightBox .rate').html(thisView.targetedCollection.models[0].attributes.currentRate.supplierTargetRate.toFixed(2));
                    $('.promotePopup .rate').html(thisView.targetedCollection.models[0].attributes.currentRate.supplierTargetRate.toFixed(2));
                    var defLock = '';
                    if(thisView.targetedCollection.models[0].attributes.currentRate.supplierLockPeriod === null) {
                        defLock = "permanently";
                    }
                    else {
                        defLock = parseInt(thisView.targetedCollection.models[0].attributes.currentRate.supplierLockPeriod)/365;
                        defLock = defLock.toFixed(1);

                        var defaultLock = defLock.split(".");
                        var end = ' ';
                        if (defaultLock[1] === "0") {
                            defLock = defaultLock[0];
                            
                            if(defLock === "1"){
                                end = " year";
                            } else {
                                end = " years";
                            }
                        }
                        else {
                            defLock = defLock;
                        }

                        defLock = "for " + defLock;
                        defLock += end;
                    }
                    
                    $('.cont.targeted .defLock').html(defLock);
                    $('.promotePopup .defLock').html(defLock);
                }
            });
        },

        getExcluded: function(){
            var thisView = this;
            
            this.excludedCollection.fetch({
                data: $.param({
                    type: 'excluded'
                }),
                type: "GET",
                complete: function(){
                    thisView.renderExcludedItems();
                    
                    $('.cont.excluded .rightBox .rate').html(thisView.excludedCollection.models[0].attributes.currentRate.supplierStandardRate.toFixed(2));
                    $('.excludePopup .rate').html(thisView.excludedCollection.models[0].attributes.currentRate.supplierStandardRate.toFixed(2));

                }
            });
        },

        getSettings: function(){
        	var thisView = this;
        	
            this.settingsCollection.fetch({
                data: $.param({
                    type: 'settings'
                }),
                type: "GET",
                complete: function(){

                    if(thisView.settingsCollection.models[0]){
                        if(thisView.settingsCollection.models[0].attributes.maxQots.max === null){
                        	thisView.settingsCollection.models[0].attributes.maxQots.max = 100;
                        }

                        if(thisView.settingsCollection.models[0].attributes.maxQots.status){
                            $('#maxTrue').attr('checked', 'checked');
                            $('#maxQotDrop').attr('disabled', false);
                            $('#maxQotDrop option').attr('selected', false);
                        }
                        else {
                            $('#maxQotDrop').attr('disabled', 'disabled');
                        }

                        _.each($('#maxQotDrop option'), function(item){
                            if(thisView.settingsCollection.models[0].attributes.maxQots.max == $(item).val()){
                                $(item).attr('selected', 'selected');
                            }
                        }, thisView);

                        thisView.renderSettingsItems();
                        
                        $.uniform.update('#maxTrue');
                        $.uniform.update('#maxQotDrop');
                        $.uniform.update('.receive');
                    }
                	 
                }
            });	
        },
        
        showRfqTooltip: function(){
            $('.rfqRec .helpTooltip').show();
        },

        hideRfqTooltip: function(){
            $('.rfqRec .helpTooltip').hide();
        },

        showQotTooltip: function(){
            $('.qotRate .helpTooltip').show();
        },

        hideQotTooltip: function(){
            $('.qotRate .helpTooltip').hide();
        },

        showOrdvalTooltip: function(){
            $('.ordVal .helpTooltip').show();
        },

        hideOrdvalTooltip: function(){
            $('.ordVal .helpTooltip').hide();
        },

        showSbTooltip: function(){
            $('.sb .helpTooltip').show();
        },

        hideSbTooltip: function(){
            $('.sb .helpTooltip').hide();
        },

        renderSettingsItems: function(){
            var tbody = $(this.el).find('.cont.settings table.settingsTable tbody');
            $(tbody).empty();
            _.each(this.settingsCollection.models, function(item){
                this.renderSettingsItem(item);
            }, this);

            $('.receive').uniform();
           
        },

        renderSettingsItem: function(item) {
            var settingsRow = new settingsRowView({
                model: item
            });

            settingsRow.parent = this;

            var tbody = $(this.el).find('.cont.settings table.settingsTable tbody');

            tbody.append(settingsRow.render().el);
        },

        renderPendingItems: function(){
            var tbody = $(this.el).find('.cont.pending .data table.pendingTable tbody');
            $(tbody).html('');
            _.each(this.pendingCollection.models, function(item){
                this.renderPendingItem(item);
            }, this);
            
            $('.shTooltip').shTooltip();
        },

        renderPendingItem: function(item) {
            if (item.attributes.buyerId) {
                var pendingRow = new pendingRowView({
                    model: item
                });

                pendingRow.parent = this;

                var tbody = $(this.el).find('.cont.pending .data table.pendingTable tbody');

                tbody.append(pendingRow.render().el);
            }
        },

        renderTargetedItems: function(){
            var tbody = $(this.el).find('.cont.targeted .data table.targetedTable tbody');
            $(tbody).html('');
            _.each(this.targetedCollection.models, function(item){
                this.renderTargetedItem(item);
            }, this);
            
            $('.shTooltip').shTooltip();

        },

        renderTargetedItem: function(item) {
            if (item.attributes.buyerId) {
                var targetedRow = new targetedRowView({
                    model: item
                });

                targetedRow.parent = this;

                var tbody = $(this.el).find('.cont.targeted .data table.targetedTable tbody');

                tbody.append(targetedRow.render().el);
            }
        },

        renderExcludedItems: function(){
            var tbody = $(this.el).find('.cont.excluded .data table.excludedTable tbody');
            $(tbody).html('');
            _.each(this.excludedCollection.models, function(item){
                this.renderExcludedItem(item);
            }, this);
            
            $('.shTooltip').shTooltip();
        },

        renderExcludedItem: function(item) {
            if (item.attributes.buyerId) {
                var excludedRow = new excludedRowView({
                    model: item
                });

                excludedRow.parent = this;

                var tbody = $(this.el).find('.cont.excluded .data table.excludedTable tbody');

                tbody.append(excludedRow.render().el);
            }
        },

        toggleActiveMax: function(){
            $('#maxQotDrop').prop('disabled', function(i, v) { return !v; });
            $.uniform.update('#maxQotDrop');
        },

        saveSettings: function(){

            this.dontRenderSettings = true;

            _.each($('input[name="receive"]'), function(item){
                if($(item).prop( "checked" )){
                    this.notificationSettings += "&notification[]=" + $(item).attr('id');
                }
            }, this);

            if($('#maxTrue').is(':checked')){
                this.settings += '&max=';
                this.settings += $('select#maxQotDrop').val();
                this.settings += '&status=true';
            } else {
                this.settings += '&max=';
                this.settings += $('select#maxQotDrop').val();
                this.settings += '&status=false';
            }

            var thisView = this;
            $.ajax({
                method: "GET",
                url: "/profile/target-customers-request?type=store-user-settings" + thisView.notificationSettings
            })
            .done(function( msg ) {
                $.ajax({
                    method: "GET",
                    url: "/profile/target-customers-request?type=store-max-quote-count" + thisView.settings
                })
                .done(function( msg ) {
                	
                })
                .fail(function(msg){
                    alert('An error occurred.');
                });
                
            })
            .fail(function(msg){
                alert('An error occurred.');
            });


        },

        showPending: function(){
        	this.getPending();
            $('.cont.excluded').hide();
            $('.cont.targeted').hide();
            $('.cont.pending').show();
        },

        showTargeted: function(){
        	this.getTargeted();
            $('.cont.excluded').hide();
            $('.cont.targeted').show();
            $('.cont.pending').hide();
        },

        showExcluded: function(){
        	this.getExcluded();
            $('.cont.excluded').show();
            $('.cont.targeted').hide();
            $('.cont.pending').hide();
        },

        showSettings: function(){
        
            $('.profile-body-right.companyProfile.targetCust').hide();
            $('.profile-body-right.companyProfile.targetCustSet').show();
            
	        	if ($('#settings-subcat').hasClass('selected')) {
	        		$('.settings-tab2').show();
	        		$('.settings-tab1').hide();
	        		this.loadCategories();
	        		
	        	}
	        	
	        	if ($('#settings-notifications').hasClass('selected')) {
	        		$('.settings-tab2').hide();
	        		$('.settings-tab1').show();
	        	 	this.getSettings();
	        	}
        },

        closeSettings: function(){
            $('.profile-body-right.companyProfile.targetCust').show();
            $('.profile-body-right.companyProfile.targetCustSet').hide();

            if(this.settingsCollection.models[0] && !this.dontRenderSettings){
                this.renderSettingsItems();
                $.uniform.update('.receive');
            }
            this.dontRenderSettings = false;
        },
        
        showHowItWorks: function() {
        
        		infoPopup.show('How it works', 	this.howItWorksTpl());
        },
        
        onChangeSettingsTab: function(e) {
        		$('p.save-confirmation').hide();
        		$('.tab-list-holder div').each(function(){
        			$(this).removeClass('selected');
        		});
        	
        		$(e.currentTarget).addClass('selected');
        		this.showSettings();
        	},
        	
        	onSelectSegment: function() {
        		$('p.save-confirmation').hide();
        		var thisView = this;
        		var id = $('select[name="segments"]').val();
        		if (!id) {
        			this.emptyContainers();
        			return;
        		}
        		
        		this.keywordsCollection.reset();
        		var sortableList = [];
        		
            for (var key in this.keywordList) {
            		element = this.keywordList[key];
	    	    		if (element.enabled && element.segment) {
	    	    			if (parseInt(id) === parseInt(element.segment.id)) {
	    	    				sortableList.push({
		    	    				mssId: element.id,
		    	    				mssName: element.name
		    	    			});
	    	    				
	    	    			}
	    	    		}
            }
            
            sortableList.sort(function(a, b){
  			  var nameA = a.mssName.toUpperCase();
  			  var nameB = b.mssName.toUpperCase(); 
  			  if (nameA < nameB) {
  			    return -1;
  			  }
  			  if (nameA > nameB) {
  			    return 1;
  			  }
  			  return 0;
            });
    		
            	for (var listKey in sortableList) {
            		this.keywordsCollection.add(sortableList[listKey]);
            	}
            
            thisView.getMatchKeywordStatuslist();
                
         },
        	
        	/*
        	 * Render this into colums this way, as the order must be aligned by columns
        	 *  
        	 */
        renderKeywordItems: function(data) {
        		
        		var elementCount = this.keywordsCollection.models.length;
        		var $container = null;
        		this.emptyContainers();

        		if (elementCount > 0) {
        			$('form.filters .al-right').show();
        			$('form.filters .no-items-message').hide();
        			var elementPerColumn = Math.ceil(elementCount / 4);
        			var elementCounter = elementPerColumn;
        			var currentColumn = 1;
        			
        			for (var i = 0 ; i < elementCount; i++) {
        				$container = $('#keyworListContainer-col' + currentColumn);
        				this.keywordsCollection.models[i].attributes.highlight = i % 2;
        				this.keywordsCollection.models[i].attributes.keywordStatus = this.getKeywordStatusById(parseInt(this.keywordsCollection.models[i].attributes.mssId));

        				this.renderKeywordItem(this.keywordsCollection.models[i], $container);
        				if (elementCounter-- === 0) {
        					elementCounter = elementPerColumn;
        					if (currentColumn < 4) {
        						currentColumn++;
        					}
        				}
        			}
        		} else {
        			$('form.filters .no-items-message').show();
        			$('form.filters .al-right').hide();
        		}
        },

        renderKeywordItem: function(item, $container) {
            var row = new keywordRowView({
                model: item
            });
            row.parent = this;
            $container.append(row.render().el);
        },
        
        onCatSetClearAll: function(e) {
        		e.preventDefault();
        		$('#keyworListContainer').find('i').each(function(){
					$(this).removeClass('fa-check-square-o');
					$(this).addClass('fa-square-o');
        		});
        },
        
        onCatSetSelectAll: function(e) {
    		e.preventDefault();
	    		$('#keyworListContainer').find('i').each(function(){
	    				$(this).removeClass('fa-square-o');
					$(this).addClass('fa-check-square-o');
	    		});
        },
        
        onCatSave: function(e) {
        		e.preventDefault();
	        	
        		var thisView = this;
        		var matchUrl = null;
	    		var callist = [];
        		
        		$('#keyworListContainer').find('i').each(function(){
	    			
	    			matchUrl = thisView.matchUrl + 'supplier/' + thisView.activeTnid + '/keyword-set/' + $(this).data('id');
	    				if ($(this).hasClass('fa-check-square-o')) {
	    					if ($(this).data('loadedstatus') === "unchecked") {
	    						switch ($(this).data('enabled')) {
		    						case 'disabled':
		    							callist.push({
		    								url: matchUrl,
		    								type: 'PUT',
		    							    data: JSON.stringify({
	    							    			enabled: true
		    							    }),
		    							});
		    							//put, enable element
		    							break;
		    						case 'unset':
		    							callist.push({
		    								url: matchUrl,
		    								type: 'POST',
		    							    data: JSON.stringify({
	    							    			enabled: true
		    							    }),
		    							});
		    							break;
		    						default:
		    							break;
		    					}
	    					}
	    				} else {
	    					if ($(this).data('loadedstatus') === "checked") {
	    						//put, disable element
    							callist.push({
    								url: matchUrl,
    								type: 'DELETE',
    							    data: null
    							});
	    					}
	    				}
	    		});
        		
        		var numberOfCalls = callist.length;
        		var errors = [];
        		if (numberOfCalls === 0) {
            		this.keywordStatuses = null;
            		this.getMatchKeywordStatuslist(function(){
                		$('html, body').animate({scrollTop: '0px'}, 300, function(){
                    		$('p.save-confirmation').show();
                		});
            		});
        		} else {
	        		for (var listKey in callist) {
					$.ajax({
					    url: callist[listKey].url,
					    type: callist[listKey].type,
					    dataType: 'json',
					    data: callist[listKey].data,
					    success: function(result) {
					        numberOfCalls--;
					        if (numberOfCalls === 0) {
					        		thisView.saveDone(errors, thisView);
					        }
					    },
						error: function(xhr, ajaxOptions, thrownError) {
							errors.push(xhr.status + ' ' + thrownError);
							 numberOfCalls--;
							 if (numberOfCalls === 0) {
					        		thisView.saveDone(errors, thisView);
					        }
						}
					});
				}
        		}
        },
        
        saveDone: function(error, thisView) {

        		if (error.length > 0) {
        			alert("Error saving data \n" + error.join("\n"));
        		}
        		
        		this.keywordStatuses = null;
        		this.getMatchKeywordStatuslist(function(){
            		$('html, body').animate({scrollTop: '0px'}, 300, function(){
                		$('p.save-confirmation').fadeIn('slow', function() {
                			$(this).delay(5000).fadeOut('slow');
                		});
            		});
        		});

        },
        
        getMatchKeywordStatuslist: function(callback) {
        		var thisView = this;        
        		if (!thisView.keywordStatuses) {
        		var matchUrl = this.matchUrl + 'supplier/' + this.activeTnid + '/keyword-set';  
        		$.get(matchUrl, function(data) {
        			var jsonData = JSON.parse(data);
        			thisView.keywordStatuses = jsonData.response;
        			thisView.renderKeywordItems();
        			if (typeof callback === "function") {
        				callback();
        			}
        			});
        		} else {
        			thisView.renderKeywordItems();
        			if (typeof callback === "function") {
        				callback();
        			}
        		}
        },
        
        getKeywordStatusById: function(id) {
        		for (var key in this.keywordStatuses) {
        			if (parseInt(this.keywordStatuses[key].id) == id) {
        				return this.keywordStatuses[key];
        			}
        		}
        		
        		return null;
        },
        
        emptyContainers: function() {
	    		$('#keyworListContainer-col1').empty();
	    		$('#keyworListContainer-col2').empty();
	    		$('#keyworListContainer-col3').empty();
	    		$('#keyworListContainer-col4').empty();
        },
        
        loadCategories: function() {
	    		var thisView = this;    
	    		this.emptyContainers();
	    		
	    		if (thisView.keywordList === null) {
		    		var matchUrl = this.matchUrl + 'supplier/keyword-set';
		    		$.get(matchUrl, function(data) {
		    			var jsonData = JSON.parse(data);
		    				thisView.keywordList = jsonData.response;
		    				thisView.fillCategoryDropdown();
		    				});
	    		} else {
	    				this.onSelectSegment();
	    		}
	    	},
	    	
	    	fillCategoryDropdown: function() {
	    		var element = null;
	    		var segments = [];
	    		var found = null;
	    		var key = null;
	    		var founderKey = null;
	    		
	    		for (key in this.keywordList) {
	    			element = this.keywordList[key];
	    			if (element.enabled && element.segment) {
	    				found = false;
	    				for (founderKey in segments) {
	    					if (segments[founderKey].id === element.segment.id) {
	    						found = true;
	    					}
	    				}
	    				
	    				if (found === false) {
	    					segments.push({
	    						id: element.segment.id,
	    						name: element.segment.name
	    					});
	    				}
	    				
	    			}
	    		}
	    		
	    		segments.sort(function(a, b){
	    			  var nameA = a.name.toUpperCase();
	    			  var nameB = b.name.toUpperCase(); 
	    			  if (nameA < nameB) {
	    			    return -1;
	    			  }
	    			  if (nameA > nameB) {
	    			    return 1;
	    			  }
	    			  return 0;
	    		});
	    		
	    		var $selectBox = $('select[name="segments"]');
	    		for (key in segments) {
	    			$option = $('<option>');
	    			$option.val(segments[key].id);
	    			$option.html(segments[key].name);
	    			$selectBox.append($option);
	    			
	    		}

	    		this.onSelectSegment();
	    	}

    });

    return new targetCustomersView();
});