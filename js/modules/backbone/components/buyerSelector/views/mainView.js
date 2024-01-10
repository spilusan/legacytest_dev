define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'../collections/collection',
	'text!templates/components/buyerSelector/tpl/index.html',
	'text!templates/components/buyerSelector/tpl/item.html'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	Collection,
	Tpl,
	itemTpl
){
	var view = Backbone.View.extend({
		events: {
			/* 'click a' : 'render' */
		},
		template: Handlebars.compile(Tpl),
		itemTemplate: Handlebars.compile(itemTpl),
		itemParentEl: null,
		onSubmitFunction: null,
		thisForm: null,
		renderOneCallback: null,
		skipAjax: false,
		initialize: function () {
			var thisView = this;
			this.branchCollection = new Collection();
			this.branchCollection.url = '/data/source/buyer-branches';
			$(function() {
				// When the user clicks anywhere outside of the modal, close it
				// On window click
				$(window).click(function(event) {
					if ($('#buyers-modal').is(":visible")) {
						var $target = $(event.target);
					    
					    // We close the modal if:
						if (
						    // We do NOT click the launch button AND 
						    !$target.closest("#buyer-btn-filter").length &&
						(
						        // We do NOT click in the modal, EXCEPT
						        !$target.closest('#buyers-modal').length ||
	
						        // On the overlay or
						        $target.attr("id") === 'buyers-modal' ||
	
						        // the submit button
						        $target.closest('#buyers-modal input[type="submit"]').length ||
	
						        // or we click the close button
						        $target.is(".buyer-cancel-btn")
						    )
						) {
							if ($target.attr('id') !== 'buyer-selector-submit') {
								thisView.restoreFormStatus();
							}
                            event.preventDefault();

                            // Close modal
						    $('#buyers-modal').hide();
						    $('body').removeClass('no-scroll');
						}
					}
				});
			});
		},
		
		getData: function()
		{
			var thisView = this;
			if (this.skipAjax === false) {
				this.branchCollection.fetch({
					complete: function(result){
						thisView.renderItems();
                        if (typeof thisView.onSubmitFunction === "function") {
                            thisView.onSubmitFunction(thisView.onOkClick());
                        }
					}
				});
			} else {
				thisView.renderItems();
			}

		},
		
		render: function(modalId, componentId) {
			
			var thisView = this;
			var html = this.template({hasInactive: false});
			$(modalId).html(html);
			
			this.thisForm = $(modalId).find('form').first();
		
			$(componentId).click(function(e) {
				if (!$(componentId).hasClass('inactive')) {
					e.preventDefault();

                    thisView.setForcableCheckboxStatus();

					// fix to allow buyer selection click to open modal
					setTimeout(function () {

						$(modalId).show();
					}, 100);
				}
			}); 
			
			this.itemParentEl = $('#buyer-selector-modal');
			$('#buyer-selector-submit').click(function(e) {
				e.preventDefault();
				thisView.storeFormStatus();
				if (typeof thisView.onSubmitFunction === "function") {
					thisView.onSubmitFunction(thisView.onOkClick());
				}
			}); 
			
			$('#inactive-accounts').click(function() {
				if ($(this).is(':checked')) {
					$('.modal-checkbox-block.inactive').slideDown();
				} else {
					$('.modal-checkbox-block.inactive').slideUp();
				}

                thisView.validateSelectedCheckboxStatus();

			});

            $('#select-all-accounts').click(function() {
                if ($(this).is(':checked')) {
                    thisView.checkAll(true);
                } else {
                    thisView.checkAll(false);
                }
            });

         	this.getData();
			var hasInactive = false;
			
			_.each(this.branchCollection.models, function(item) {
				if (item.attributes.inactive === true) {
					hasInactive = true;
				}
			}, this);
			
			if (hasInactive === false) {
				$('.toggle-show-inactive').hide();
			}

		},
		
		renderItems: function() {
			var thisView = this;

			this.sortBrances();
			if (this.branchCollection.models.length === 1) {
				if (typeof this.renderOneCallback === "function") {
					renderIt = false;
					this.renderOneCallback(this.branchCollection.models[0].attributes);
				}
			}
			
			this.itemParentEl.empty();
			_.each(this.branchCollection.models, function(item) {
				this.renderItem(item.attributes);
			}, this);

            $('.modal-checkbox-block input').click(function() {
                thisView.validateSelectedCheckboxStatus();
            });
 		},
		
		renderItem: function(item) {
			
			var html = this.itemTemplate(item);
			var element = $(html);
			if (parseInt(item.default) === 1) {
				element.find('input[type="checkbox"]').attr('checked','checked');
			}
			this.itemParentEl.append(element);
		},
		
		onOkClick: function() {
			var result = [];
			//TODO continue here adding the callback
			$('input[name^="byb"]').each(function(){
				if ($(this).is(':checked')) {
					result.push({
						tnid: $(this).val(),
						name: $('label[for="'+$(this).attr('id')+'"]').html().trim()
					});
					
				}
			});
			
			return result;
		},
		
		onSubmit: function(callback) {
			this.onSubmitFunction = callback;
		},
		
		storeFormStatus: function() {
			this.thisForm.find(':input').each(function(i, elem) {
				 var input = $(elem);
				 if (input.is("input")) {
					 if (input.attr('checked') === 'checked') {
						 input.data('initialState', 1);
					 } else {
						 input.data('initialState', 0);
					 }
					 
				 } 
		    });
		},

		setForcableCheckboxStatus: function()
		{
            var thisView = this;
            this.thisForm.find(':input').each(function(i, elem) {
                var input = $(elem);
                if (input.is("input")) {
                    if (thisView.parent.selectedBuyers.indexOf(parseInt(input.val())) !== -1) {
                        input.attr('checked', true);
                    }
                }
            });

            this.autoCheckList = [];
		},

		restoreFormStatus: function() {
			var thisView = this;
			this.thisForm.find(':input').each(function(i, elem) {
			     var input = $(elem);
				 if (input.is("input")) {
					 if (input.data('initialState') === 1) {
						 input.attr('checked', true);
					 } else {
						 input.attr('checked', false);
					 }
				 } 
		    });


		},
		
		onRenderOne: function(callback) {
			this.renderOneCallback = callback;
		},
		
		/**
		 * We can add buyer brances before loading the popup, in that case the json call will be omitted, and will used the passed data instead
		 */
		addBuyerBrances: function(branchList) {
			this.skipAjax = true;
			for (var key in branchList) {
				this.branchCollection.add(branchList[key]);
			}
			
		},
		
		sortBrances: function() {
			var i;
			var y;
			var tempCollectionId;
			var tempCollection;
			
			if (this.branchCollection.models.length > 1) {
				for (i=0;i<this.branchCollection.models.length;i++) {
					tempCollectionId = i;
					for (x=i;x<this.branchCollection.models.length;x++) {
						
						if (this.branchCollection.models[x].attributes.inactive < this.branchCollection.models[tempCollectionId].attributes.inactive) {
							tempCollectionId = x;
						} else if (this.branchCollection.models[x].attributes.inactive === this.branchCollection.models[tempCollectionId].attributes.inactive) {
							if (this.branchCollection.models[x].attributes.name.substring(this.branchCollection.models[x].attributes.name.indexOf('-') +1).toUpperCase() < this.branchCollection.models[tempCollectionId].attributes.name.substring(this.branchCollection.models[tempCollectionId].attributes.name.indexOf('-') +1).toUpperCase()) {
								tempCollectionId = x;
							} else if (this.branchCollection.models[x].attributes.name.substring(this.branchCollection.models[x].attributes.name.indexOf('-') +1).toUpperCase() === this.branchCollection.models[tempCollectionId].attributes.name.substring(this.branchCollection.models[tempCollectionId].attributes.name.indexOf('-') +1).toUpperCase()) {
								if (this.branchCollection.models[tempCollectionId].attributes.id < this.branchCollection.models[x].attributes.id) {
									tempCollectionId = x;
								}
							}
						}
					}
					
					if (tempCollectionId !== i) {
						tempCollection = this.branchCollection.models[i];
						this.branchCollection.models[i] = this.branchCollection.models[tempCollectionId];
						this.branchCollection.models[tempCollectionId] = tempCollection;
					}
				}
			}
		},

        checkAll: function(status) {
			this.thisForm.find('.modal-checkbox-block').each(function(i, elem) {
				if (!$(elem).hasClass('inactive') || $('#inactive-accounts').is(':checked')) {
                    var input = $(elem).find(':input');
                    input.attr('checked', status);
                }
            });
		},

		validateSelectedCheckboxStatus: function() {
			var shouldBeChecked = true;
            this.thisForm.find('.modal-checkbox-block').each(function(i, elem) {
                if (!$(elem).hasClass('inactive') || $('#inactive-accounts').is(':checked')) {
                    var input = $(elem).find(':input');
                    if (!input.is(':checked')) {
                        shouldBeChecked = false;
					}
                }
            });

            $('#select-all-accounts').attr('checked', shouldBeChecked);

		}


	});

	return new view();
});
