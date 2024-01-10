define([
    'jquery',
    'underscore',
    'Backbone',
    'handlebars',
    '../collections/productCollection',
    'backbone/catalogue/views/paginationView',
    'backbone/catalogue/views/singleProductView',
    'text!templates/catalogue/tpl/productItems.html'
    
], function(
    $,
    _,
    Backbone,
    Hb,
    ProductCollection,
    PaginationView,
    SingleProductView,
    ProductItemsTemplate
){
    var productItemsView = Backbone.View.extend({
        tnid: require('reports/tnid'),
        folderId: null,
        pagination: null,
        items: [],
        renderSuccessCallback: null,
        productItemsTemplate: Handlebars.compile(ProductItemsTemplate),

        initialize: function (item, renderSuccessCallback) {
            this.getData = this.getData.bind(this);
            this.render = this.render.bind(this);
            this.renderPagination = this.renderPagination.bind(this);
            this.renderProductItems = this.renderProductItems.bind(this);

            this.renderSuccessCallback = renderSuccessCallback;
            this.folderId = item.folderId;
            this.catalogueId = item.catalogueId;

            this.productCollecton = new ProductCollection();

            this.getData(1, 10);
        },

        render: function() {
            var thisView = this;

            var el = $('#product-results');
            el.empty();

            var html = this.productItemsTemplate({
                tnid: this.tnid,
                catalogueId: this.catalogueId,
                items: this.items
            });

            el.html(html);

            $('.product-item-link').click(function(e){
                e.preventDefault();
                e.stopPropagation();
                var productId = $(this).data('id');
                var productKey = $(this).data('key');
                var productItemData = thisView.items[productKey];
                
                var newHash = 'catalogue/' + thisView.catalogueId + '/' + thisView.folderId + '/' +  productItemData.id;
                window.lastBrowserHash = '#' + newHash;
                window.location.hash = newHash;
                
                SingleProductView.getData({
                    id: productItemData.id,
                    catalogueId: thisView.folderId,
                    catalogueDefinition: 'shipserv',
                });

            });
        },

        renderPagination: function() {
            var thisView = this;

            this.pagination = new PaginationView({
                itemsFound: this.itemsFound,
                totalPages: this.totalPages,
                size: this.size
            });

            this.pagination.onChange(function(itemsFrom, itemsTo) {
                thisView.getData(itemsFrom, itemsTo);
            });
        },

        getData: function(page, size)
        {
            var thisView = this;
            var params = {
                page: page - 1,
                size: size,
                'catalogueDefinition': 'shipserv'
            };

            this.productCollecton.url = '/reports/catalogue/api/catalogues/categories/' + this.folderId + '/products';

            var fetchOptions = {
                type: 'GET',
                data: $.param(params),
                complete: function () {
                    if (thisView.productCollecton.models[0]) {
                        thisView.itemsFound = thisView.productCollecton.models[0].attributes.totalElements;
                        thisView.totalPages = thisView.productCollecton.models[0].attributes.totalPages;
                        thisView.size = thisView.productCollecton.models[0].attributes.size;
                        thisView.renderProductItems(thisView.productCollecton.models[0].attributes.content);
                    }
                },
                error: function (model, response) {
                    if (response.responseText) {
                        console.log('error', response.responseText);
                    }
                }
            };

            this.productCollecton.reset();
            this.productCollecton.fetch(fetchOptions);
        },

        renderProductItems: function (items) {
            var keyedItems = [];

            for(var key in items) {
                keyedItems[key] = items[key];
                keyedItems[key].key = key;
            }

            this.items = keyedItems;
            this.render();

            if (!this.pagination) {
                this.renderPagination();
            }

            this.pagination.render();

            if (typeof this.renderSuccessCallback === "function") {
                this.renderSuccessCallback(this.productCollecton.models[0].attributes.content);
            }
        }
    });

    return productItemsView;
});
