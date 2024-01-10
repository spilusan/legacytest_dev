define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/shared/hbh/general',
	'../collections/collection',
	'text!templates/components/supplierSelector/tpl/index.html',
	'text!templates/components/supplierSelector/tpl/item.html',
	'text!templates/components/supplierSelector/tpl/selectedItem.html'
], function (
	$,
	_,
	Backbone,
	Hb,
	HbhGen,
	Collection,
	Tpl,
	itemTpl,
	selectedItemTpl
) {
	var view = Backbone.View.extend({
		events: {
			/* 'click a' : 'render' */
		},
		template: Handlebars.compile(Tpl),
		itemTemplate: Handlebars.compile(itemTpl),
		selectedItemTemplate: Handlebars.compile(selectedItemTpl),
		parent: null,
		fetchXhr: null,
		dataContainer: null,
		selectedContainer: null,
		filterBuyerList: [],
		selectedSupplierList: [],
		confirmedSupplierList: [],
		onSubmitFunction: null,
		onInactiveClickFunction: null,
		lastExecutionParams: null,
		initialize: function () {
			var thisView = this;

			this.isLoaded = false;
			this.collection = new Collection();
			this.collection.url = '/reports/data/supplier-performance-data/supplier-branches';

			$(function () {
				// When the user clicks anywhere outside of the modal, close it
				// On window click
				$(window).click(function (event) {
					var $target = $(event.target);
					$('body').addClass('no-scroll');
					// We close the modal if:
					if (
						// We do NOT click the launch button AND 
						!$target.closest("#supplier-btn-filter").length &&
						(
							// We do NOT click in the modal, EXCEPT
							!$target.closest('#suppliers-modal').length ||

							// On the overlay or
							$target.attr("id") === 'suppliers-modal' ||

							// the submit button
							$target.closest('#suppliers-modal input[type="submit"]').length ||

							// or we click the close button
							$target.is(".supplier-cancel-btn")
						)
					) {
						// Close modal
						$('#suppliers-modal').hide();
						$('body').removeClass('no-scroll');
					}
				});
			});

			$(window).resize(function () {
				if (thisView.dataContainer) {
					thisView.fixupHeaders(thisView.dataContainer);
					thisView.fixupHeaders(thisView.selectedContainer);
				}
			});
		},

		loadSuppliers: function () {
			var thisView = this;
			$value = $('input#supplier-name').val();

			thisView.isLoaded = false;

			var params = {
                keywords: $value,
                byo: thisView.filterBuyerList,
                pevMonths: 12,
                limit: 50
            };

			if (JSON.stringify(params) !== this.lastExecutionParams) {

				if (this.dataContainer) {
                    $('.supplier-selector-spinner').show();
                    this.dataContainer.empty();
                }

                thisView.collection.reset();
                thisView.collection.fetch({
                    type: 'POST',
                    data: params,
                    complete: function (result) {
                        thisView.isLoaded = true;
                        $('.supplier-selector-spinner').hide();

                        if (result.readyState === 4) {
                            thisView.renderItems();
                            thisView.toggleClearButton();
                        }
                    }
                });

                this.lastExecutionParams = JSON.stringify(params);
            } else {
                this.isLoaded = true;
                this.renderItems();
                this.toggleClearButton();
			}
		},

		preLoadSuppliers: function () {
			var thisView = this;
			if (thisView.filterBuyerList.length > 0) {

				thisView.isLoaded = false;

				var params = {
                    keywords: '',
                    byo: thisView.filterBuyerList,
                    pevMonths: 12,
                    limit: 50
				};

			    if (thisView.collection.length === 0 || JSON.stringify(params) !== this.lastExecutionParams) {

                    if (this.dataContainer) {
                        $('.supplier-selector-spinner').show();
                        this.dataContainer.empty();
                    }

                    thisView.collection.reset();
                    thisView.collection.fetch({
                        type: 'POST',
                        data: params,
                        complete: function (result) {
                            thisView.isLoaded = true;
                            $('.supplier-selector-spinner').hide();

                            if (result.readyState === 4) {
                                //if ($('#suppliers-modal').is(":visible")) {
                                thisView.renderItems();
                                //}
                                thisView.toggleClearButton();
                            }
                        }
                    });

                    this.lastExecutionParams = JSON.stringify(params);

                }
			}
		},

		autoCompleteKeyUp: function () {
			var thisView = this;

			$value = $('input#supplier-name').val();

			if ($value.length === 0) {
				this.loadSuppliers();
				//thisView.renderItems();
			} else {
				// Added timout functionality to prevent requests being generated to every letter
				clearTimeout(this.fetchActionTimeout);
				this.fetchActionTimeout = setTimeout(function () {
					if ($value.length > 2) {
                        thisView.loadSuppliers();
						///thisView.renderItems();
					}
				}, 100);
			}

		},

		render: function (modalId, componentId) {
			var html = this.template();
			var thisView = this;
			$(modalId).html(html);
			this.dataContainer = $('#supplier-selector-body');
			this.selectedContainer = $('#supplier-added-body');

			$(componentId).click(function (e) {
				e.preventDefault();
				/* Initalize all data containers, and input on modal show
				 * Then show the modal
				 */
				if (!$(this).hasClass('inactive')) {
					$('input#supplier-name').val('');
					thisView.selectedSupplierList = thisView.confirmedSupplierList.slice(0);
					thisView.renderItems();
					thisView.renderSelectedSupplierList();

					$(modalId).show();
					//Emulate key up, to populate initial data
					thisView.autoCompleteKeyUp();
					thisView.fixupHeaders(thisView.selectedContainer);


					// fix to allow supplier selection click to open modal
					setTimeout(function () {
						$(modalId).show();

						$('input#supplier-name').focus();
					}, 100);
				} else {
					if (typeof thisView.onInactiveClickFunction === "function") {
						thisView.onInactiveClickFunction();
					}
				}
			});

			$('input#supplier-name')
				.keypress(function (e) {
					// Prevent the search modal closing when enter key pressed
					if (e.keyCode === 13)
						e.preventDefault();
				})
				.keyup(function (e) {
					e.preventDefault();
					thisView.autoCompleteKeyUp();

					var addAllDisabled = $(this).val().length < 3;

					$('#supplier-add-all').toggleClass('disabled', addAllDisabled);
				});

			$('#supplier-add-all').addClass('disabled');

			$('#supplier-add-all').click(function (e) {
				e.preventDefault();

				var isDisabled = $(this).is('.disabled');

				if (!isDisabled) {
					thisView.addAllSupplier();
				}
			});

			$('#supplier-clear-all').click(function (e) {
				e.preventDefault();
				if (!$(this).hasClass('disabled')) {
					thisView.clearAllSupplier();
					thisView.toggleClearButton();
				}
			});

			$('#supplier-selector-submit').click(function (e) {
				e.preventDefault();
				thisView.confirmedSupplierList = thisView.selectedSupplierList.slice(0);

				if (typeof thisView.onSubmitFunction === "function") {
					thisView.onSubmitFunction(thisView.selectedSupplierList);
				}
			});
		},

		renderItems: function () {
			var thisView = this;
			var html;

            this.dataContainer.empty();

            $value = $('input#supplier-name').val();
			$searchString = $value.toLowerCase();

			if (this.isLoaded) {


				for (var i = 0; i < this.collection.models.length; i++) {
					var item = this.collection.models[i];
					var supplierKey = this.getAddedSuppierKeyById(parseInt(item.attributes.pk));

					if (supplierKey === null) {
						html = this.itemTemplate({
							value: item.attributes.value,
							pk: item.attributes.pk,
							country: item.attributes.country,
							countryName: item.attributes.countryName,
							hasOrder: item.attributes.hasOrder
						});
						this.dataContainer.append($(html));
					}

				}

				if (this.collection.models.length > 0) {
                    var resultCount = parseInt(this.collection.models[0].attributes.itemcount);

                    if (this.collection.models.length < resultCount) {
						var dataEnd = $('<div class="data-end"></div>')
                            .append('<i>Showing top 50 results of ' + resultCount + '</i>')
                            .append('<br />')
                            .append('<i>For more results refine supplier name.</i>');

                        this.dataContainer.append(dataEnd);
                    }
                }
			}

			$('.add-supplier').click(function (e) {
				e.preventDefault();
				$(this).closest('.data-row').hide();
				thisView.addSupplier($(this).data('id'));
				thisView.renderSelectedSupplierList();
				thisView.fixupHeaders(thisView.dataContainer);

			});

			this.fixupHeaders(this.dataContainer);
		},

		setFilterBuyerList: function (list) {
			this.filterBuyerList = list;
		},

		addSupplier: function (itemId) {
			var saveItemId = parseInt(itemId);
			if (this.getAddedSuppierKeyById(saveItemId) === null) {
				var supplierKey = this.getLookupSuppierKeyById(saveItemId);
				this.selectedSupplierList.push({
					attributes: this.collection.models[supplierKey].attributes
				});
			}
			this.toggleClearButton();
		},

		renderSelectedSupplierList: function () {
			var html;
			var thisView = this;
			this.selectedContainer.empty();
			_.each(this.selectedSupplierList, function (item) {
				html = this.selectedItemTemplate({
					value: item.attributes.value,
					pk: item.attributes.pk,
					country: item.attributes.country,
					countryName: item.attributes.countryName,
					hasOrder: item.attributes.hasOrder
				});

				this.selectedContainer.append($(html));

			}, this);

			this.selectedContainer.scrollTop(this.selectedContainer[0].scrollHeight);
			$('.supplier-remove').click(function (e) {
				e.preventDefault();
				thisView.removeSupplier($(this).data('id'));
				$(this).closest('.data-row').hide();
				thisView.fixupHeaders(thisView.selectedContainer);
			});

			this.fixupHeaders(this.selectedContainer);
			this.toggleClearButton();
		},

		getAddedSuppierKeyById: function (itemId) {
			for (var key in this.selectedSupplierList) {
				if (parseInt(this.selectedSupplierList[key].attributes.pk) === parseInt(itemId)) {
					return key;
				}
			}
			return null;
		},

		getLookupSuppierKeyById: function (itemId) {
			for (var key in this.collection.models) {
				if (parseInt(this.collection.models[key].attributes.pk) === parseInt(itemId)) {
					return key;
				}
			}
			return null;
		},

		removeSupplier: function (itemId) {
			var safeItemId = parseInt(itemId);
			var supplierKey = this.getAddedSuppierKeyById(safeItemId);
			this.selectedSupplierList.splice(supplierKey, 1);
			this.renderItems();
			this.toggleClearButton();
		},

		clearAllSupplier: function () {
			this.selectedSupplierList = [];
			this.selectedContainer.empty();
			this.renderItems();
		},

		fixupHeaders: function (dataContainer) {
			var sizeArray = [];
			var i;
			var width;
			$headContainer = (this.dataContainer === dataContainer) ? $('#supplier-selector-head') : $('#supplier-selected-head');
			$headContainer.hide();
			dataContainer.find('.data-row').each(function () {
				if ($(this).is(":visible")) {
					i = 0;
					$(this).find('.data-cell').each(function () {
						width = $(this)[0].getBoundingClientRect().width;
						if (sizeArray[i] === undefined || sizeArray[i] < width) {
							sizeArray[i] = width;
						}
						i++;
					});
				}
			});

			i = 0;

			if (sizeArray.length > 0) {
				$headContainer.find('.data-cell').each(function () {
					if (sizeArray[i] !== undefined) {
						$(this).css('width', (sizeArray[i]) + 'px');
					}
					i++;
				});
			} else {
				$headContainer.find('.data-cell').each(function () {
					$(this).css('width', '');
				});
			}

			$headContainer.show();
		},

		onSubmit: function (callback) {
			this.onSubmitFunction = callback;
		},

		onInactiveClick: function (callback) {
			this.onInactiveClickFunction = callback;
		},

		toggleClearButton: function () {
			if (this.selectedSupplierList.length === 0) {
				$('#supplier-clear-all').addClass('disabled');
			} else {
				$('#supplier-clear-all').removeClass('disabled');
			}
		},

		isSearchKeyMatch: function (content, searchTerm) {
			var keywords = searchTerm.split(' ');
			for (var key in keywords) {
				if (content.indexOf(keywords[key]) >= 0) {
					return true;
				}
			}

			return false;
		}

	});

	return new view();
});