define([
    'jquery',
    'underscore',
    'Backbone',
    'handlebars',
     '../collections/treeItemCollection',
    'backbone/catalogue/views/productItemsView',
    'text!templates/catalogue/tpl/navigationItem.html',
    'text!templates/catalogue/tpl/loadersmall.html'
], function(
    $,
    _,
    Backbone,
    Hb,
    TreeItemCollection,
    ProductItemsView,
    NavigationItemTpl,
    LoaderSmallTemplate
){
    var navigation = Backbone.View.extend({
        el: null,
        subElements: null,
        catalogueId: null,
        items: [],
        item: {
            id: null,
            name: ''
        },
        navigationItemTemplate: Handlebars.compile(NavigationItemTpl),
        loaderSmallTemplate: Handlebars.compile(LoaderSmallTemplate),

        initialize: function (item, element, catalogueId) {
            var thisView = this;
            this.hasLink = true;

            if (item.attributes.hasChildren !== undefined && item.attributes.hasChildren === false) {
                this.hasLink = false;
            }

            this.catalogueId = catalogueId;
            this.renderSubItems = this.renderSubItems.bind(this);
            this.treeItemCollection = new TreeItemCollection();
            this.elementClick = this.elementClick.bind(this);
            this.productItemsLoadClick = this.productItemsLoadClick.bind(this);

            this.item = item;
            this.el = $('<li>');
            this.el.handlerObject = this;

            if (item.id) {
                this.el.attr('id', 'catalogue-' + item.id);
            }

            if (this.hasLink) {
                this.el.addClass('has-link');
            }

            this.element = element;
                this.el.click(function (e) {
                    e.stopPropagation();
                    if (thisView.hasLink) {
                        thisView.elementClick();
                    } else {
                        thisView.productItemsLoadClick();
                    }
                });

            this.render();
        },

        render: function() {

            var html = this.navigationItemTemplate(this.item);

            this.el.html(html);
            this.element.append(this.el);
        },

        elementClick: function() {
            var thisView = this;
            if (this.subElements) {
                this.subElements.slideUp(
                    function() {
                        thisView.subElements.remove();
                        thisView.subElements = null;
                    }
                );
            } else {
                var loaderHtml = this.loaderSmallTemplate();
                thisView.subElements = $('<span>');
                thisView.subElements.html(loaderHtml);
                this.el.append(thisView.subElements);
                this.getData();
                this.productItemsLoadClick();
                var newHash = (this.catalogueId !== this.item.attributes.id) ? 'catalogue/' + this.catalogueId + '/' + this.item.attributes.id : 'catalogue/' + this.catalogueId;
                window.lastBrowserHash = '#' + newHash;
                window.location.hash = newHash;
            }
        },

        productItemsLoadClick: function () {
            $('#catalog-tree-ul li ul li').each(function() {
                $(this).css('font-weight', 'normal');
            });

            this.el.css('font-weight', 'bold');

            var productItemId = this.item.attributes.id;
            var newHash = (this.catalogueId !== this.item.attributes.id) ? 'catalogue/' + this.catalogueId + '/' + this.item.attributes.id : 'catalogue/' + this.catalogueId;
            window.lastBrowserHash = '#' + newHash;
            window.location.hash = newHash;

            if (this.catalogueId !== this.item.attributes.id) {
                new ProductItemsView({
                    folderId: productItemId,
                    catalogueId: this.catalogueId
                });
            }
        },

        getData: function() {
            var thisView = this;
            var query = $('input#query').val();

            var params = {
                'catalogueId': this.catalogueId,
                'catalogueDefinition': 'shipserv'
            };

            if (this.catalogueId !== this.item.attributes.id) {
                params.parentId = this.item.attributes.id;
            }

            if (query) {
                params.keywords = query;
            }

            this.treeItemCollection.url = '/reports/catalogue/api/catalogues/categories/parent/';

            var fetchOptions = {
                type: 'GET',
                data: $.param(params),
                complete: function () {
                    if (thisView.treeItemCollection.models[0]) {
                        thisView.renderSubItems(thisView.treeItemCollection.models[0].attributes.categories);
                    }
                },
                error: function (model, response) {
                    if (response.responseText) {
                        console.log('error', response.responseText);
                    }
                }
            };

            this.treeItemCollection.reset();
            this.treeItemCollection.fetch(fetchOptions);
        },

        renderSubItems: function(items) {
            var thisView = this;

            if (this.subElements) {
                this.subElements.remove();
            }

            this.subElements = $('<ul>');
            this.el.append(this.subElements);
            _.each(items, function(item) {
                this.renderItem(item, thisView.subElements);
            }, this);

            this.subElements.slideDown();
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

            new navigation(newItem, subElement, this.catalogueId);
        }

    });

    return navigation;
});
