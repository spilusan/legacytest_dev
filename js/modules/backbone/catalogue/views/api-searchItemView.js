/*
 * This search works only as a sample as the API does not support ShipServ catalogue search, only IMPA search
 * Render element by element as we have to fetch all image data with a different API call (unfortunately)
 */
define([
    'jquery',
    'underscore',
    'Backbone',
    'handlebars',
    '../collections/productCollection',
    'backbone/catalogue/views/singleProductView',
    'text!templates/catalogue/tpl/search-item.html'
], function(
    $,
    _,
    Backbone,
    Hb,
    ProductCollection,
    ProductView,
    SearchItemTpl,
    LoaderTpl
){
    var searchItemView = Backbone.View.extend({
        el: null,
        searchItemTemplate: Handlebars.compile(SearchItemTpl),

        initialize: function(props) {
            this.props = props;
            this.render = this.render.bind(this);

           this.productCollection = new ProductCollection();
           this.productCollection.url = '/reports/catalogue/api/catalogues/products';

           this.render();
        },

        render: function() {

            var thisView = this;
            var el = $('#search-item-list');
            var html = this.searchItemTemplate(this.props);

            // This must be changed to ShipServ catalogue item ID but it looks like the current API does not support proper lookups for pages catalogue

            var lookupProductId = this.props.impaNumber;
            this.element = $(html);

            this.element.find('a.product-item-link').click(function(e) {
               e.stopPropagation();
               thisView.onProductClick();
            });

            el.append(this.element);

            var params = {
                identifier: lookupProductId,
                identifierType: 'impaNumber',
                catalogueDefinition: 'impa'
            };

            var fetchOptions = {
                type: 'GET',
                data: $.param(params),

                complete: function () {
                    thisView.renderMissingInfo();
                },

                error: function (model, response) {
                    if (response.responseText) {
                        console.log('error', response.responseText);
                    }
                }
            };

            this.productCollection.reset();
            this.productCollection.fetch(fetchOptions);
        },

        renderMissingInfo: function() {
            if (this.productCollection.models[0] && this.productCollection.models[0].attributes.content[0]) {
                var imageUrl = this.productCollection.models[0].attributes.content[0].productImageFull;
                var uom = this.productCollection.models[0].attributes.content[0].uom;

                if (imageUrl) {
                    imageUrl += '?width=40&height=40';
                    this.element.find('.cat-item-image-placeholder').attr('src', imageUrl);
                } else {
                    this.element.find('a.rollover.product-item-link').html('<i class="fa fa-picture-o" aria-hidden="true"></i>');
                }

                this.element.find('.uom-placeholder').html(uom);
            }
        },

        onProductClick: function()
        {
            if (this.productCollection.models[0] && this.productCollection.models[0].attributes.content[0]) {
                // @todo change IMPA number to shipserv ID if it will be supported
                var productId = this.productCollection.models[0].attributes.content[0].impaNumber;
                var productView = new ProductView({

                    id: productId,
                    identifierType: 'impaNumber',
                    catalogueDefinition: 'impa'
                    // catalogueId: thisView.catalogueId
                });

                productView.render();
            }
        }

    });

    return searchItemView;
});
