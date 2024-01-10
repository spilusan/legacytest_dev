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
    SearchItemTpl
){
    var searchItemView = Backbone.View.extend({
        tnid: require('reports/tnid'),
        el: null,
        searchItemTemplate: Handlebars.compile(SearchItemTpl),
        /*
         * console.log(props); @todo investigate, if we search then the result comes from an another catalogue (problem only when refreshing the page)
         * Sample URL: https://local.shipserv.com/supplier/profile/s/maryland-nautical-sales-inc-53038?q=332386&publicTnid=53038#catalogue/33406/1114006/MP2386JETV
         * After investigation the issue here is that the catlogue searches in inactive and expired catalogues, and the data is not up-to-date with the db (SOLR index)
         * Need a totally new and working concept here to fix this  
        */
        initialize: function(props) {
           thisView = this;
           this.props = props;
           this.render = this.render.bind(this);

           this.productCollection = new ProductCollection();
           this.productCollection.url = '/reports/catalogue/api/catalogues/products';

           this.render();

           $('.product-item-link').click(function(e){
            e.preventDefault();
            e.stopPropagation();
            var productId = $(this).data('id');

            var newHash = 'catalogue/' + thisView.props.catalogueId + '/' + thisView.props.folderId + '/' +  encodeURIComponent(productId);
            window.lastBrowserHash = '#' + newHash;
            window.location.hash = newHash;

            var productView = new ProductView({
                id: productId,
                catalogueId: thisView.props.catalogueId,
                identifierType: 'partNumber',
                catalogueDefinition: 'shipserv'
            });

            productView.render();
        });
        },

        render: function() {

            var thisView = this;
            var data = this.props;
            data.tnid = thisView.tnid
            var el = $('#search-item-list');
            var html = this.searchItemTemplate(data);

            // This must be changed to ShipServ catalogue item ID but it looks like the current API does not support proper lookups for pages catalogue

            var lookupProductId = this.props.impaNumber;
            this.element = $(html);

            this.element.find('a.product-item-link').click(function(e) {
               e.stopPropagation();
               thisView.onProductClick();
            });

            el.append(this.element);
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
