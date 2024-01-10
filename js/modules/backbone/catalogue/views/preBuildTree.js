define([
    'jquery',
    'underscore',
    'Backbone',
    'handlebars',
    '../collections/catalogueCollection',
    'backbone/catalogue/views/navigationItemView',
    'backbone/catalogue/views/productItemsView',
    'backbone/catalogue/views/searchView',
    'backbone/catalogue/views/singleProductView'
], function(
    $,
    _,
    Backbone,
    Hb,
    CatalogueCollection,
    NavigationItemView,
    ProductItemsView,
    SearchView,
    SingleProductView
){
    var preBuildTree = Backbone.View.extend({
        tnid: require('reports/tnid'),
        topFolderId: null,
        catalogueId: null,
        productId: null,
        trees: [],
        renderedCatalogues: [],

        initialize: function (props, renderedCatalogues) {
            this.getData = this.getData.bind(this);
            this.renderFullTree = this.renderFullTree.bind(this);
            this.renderProduct = this.renderProduct.bind(this);
            this.renderSearch = this.renderSearch.bind(this);

            this.catalogueCollection = new CatalogueCollection();
            this.renderedCatalogues = renderedCatalogues;

            var anchorParts = [];
            var anchor = jQuery.url.attr("anchor");
            if (anchor) {
                anchorParts = anchor.split('/');
            }

            if (anchorParts.length == 2) {
                this.catalogueId = anchorParts[1];
                this.getData(null);
            } else if (anchorParts.length > 2) {
                this.topFolderId = anchorParts[2];
                this.catalogueId = anchorParts[1];
                if (anchorParts.length > 3) {
                    this.productId = anchorParts[3];
                }
                this.getData(this.topFolderId);
            } else {
                this.renderSearch();
            }
        },

        getData: function(folderId) {
            var thisView = this;

            var params = {
                'catalogueId' : this.catalogueId,
                'catalogueDefinition': 'shipserv'
            };

            if (folderId !== null) {
                params.parentId = folderId;
            }

            this.catalogueCollection.url = '/reports/catalogue/api/catalogues/categories/parent/';

            var fetchOptions = {
                type: 'GET',
                    data: $.param(params),
                    complete: function () {
                    thisView.render(folderId);
                },
                error: function (model, response) {
                    if (response.responseText) {
                        console.log('error', response.responseText);
                    }
                }
            };

            this.catalogueCollection.reset();
            this.catalogueCollection.fetch(fetchOptions);
        },

        render: function(folderId) {
            if (this.catalogueCollection.models[0]) {
                var items = this.catalogueCollection.models[0];

                if (items) {
                    this.trees.push({
                        folderId: folderId,
                        items: items.attributes.categories
                    });


                    if (folderId === null) {
                        this.renderFullTree();
                    } else {
                        var previousParents = items.attributes.previousParents;
                        if (previousParents.length > 0) {
                            this.getData(previousParents[0]);
                        } else {
                            this.getData(null);
                        }
                    }
                }
            }
        },

        renderFullTree: function() {

            var rootElement = null;

            for (var key in this.renderedCatalogues) {
                if (parseInt(this.renderedCatalogues[key].attributes.id)  === parseInt(this.catalogueId)) {
                    rootElement = this.renderedCatalogues[key];
                }
            }

            if (rootElement) {
                rootElement.subElements = $('<ul>');
                var attachToElement = rootElement.el;
                var attachUl = rootElement.subElements;
                var subTreeArray = this.trees.pop();
                while (subTreeArray) {
                    var parentUl = null;
                    for (var subKey in subTreeArray.items) {
                        var treeItem = this.renderItem(subTreeArray.items[subKey], attachUl);

                        if (parseInt(this.topFolderId) === parseInt(subTreeArray.items[subKey].id)) {
                            treeItem.el.css('font-weight', 'bold');
                        }

                        if (this.trees.length > 0 && this.trees[this.trees.length - 1].folderId === subTreeArray.items[subKey].id) {
                            treeItem.subElements = $('<ul>');
                            parentUl = treeItem;
                        }
                    }

                    if (attachToElement && subTreeArray.items.length > 0) {
                        attachToElement.append(attachUl);
                        attachUl.show();
                    }

                    if (parentUl) {
                        attachToElement = parentUl.el;
                        attachUl = parentUl.subElements;
                    }

                    subTreeArray = this.trees.pop();
                }

                if (this.topFolderId) {
                    new ProductItemsView(
                        {
                            folderId: this.topFolderId,
                            catalogueId: this.catalogueId
                        },
                        this.renderProduct
                    );
                }

            }
        },

        renderProduct: function(result) {
            if (this.productId) {
                SingleProductView.getData({
                    id: this.productId,
                    catalogueId: this.topFolderId,
                    catalogueDefinition: 'shipserv',
                });
            }
            this.productId = null;
        },

        renderItem: function(item, subElement) {
            var newItem = {
                'attributes': {
                    'id': item.id,
                    'name': item.description,
                    'hasChildren': item.hasChildren
                }
            };

            if (item.searchResultCount) {
                newItem.attributes.searchResultCount = item.searchResultCount;
            }

            return new NavigationItemView(newItem, subElement, this.catalogueId);
        },

        renderSearch: function() {
            var keywords = jQuery.url.param('q');
            if (keywords && keywords.length > 0) {
                var searchView = new SearchView();
                searchView.render(keywords);
            }
          }
    });

    return preBuildTree;
});
