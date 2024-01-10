define([
    'jquery',
    'underscore',
    'Backbone',
    'handlebars',
    '../collections/productCollection',
    'text!templates/catalogue/tpl/product.html'
], function(
    $,
    _,
    Backbone,
    Hb,
    ProductCollection,
    ProductTpl
){
    var productView = Backbone.View.extend({
        imageUrl: require('reports/imageUrl'),
        supplierId: require('reports/tnid'),
        productTemplate: Handlebars.compile(ProductTpl),
        
        initialize: function () {
            this.productCollection = new ProductCollection();
            this.render = this.render.bind(this);
            this.getData = this.getData.bind(this);
        },

        getData: function(props)
        {
            var thisView = this;
            var params = {
                productId: props.id,
                catalogueDefinition: props.catalogueDefinition,
                enableSupplierQuery: true
            };
            var fetchOptions = {
                type: 'GET',
                data: $.param(params),
                complete: function () {
                    thisView.render();
                },
                error: function (model, response) {
                    if (response.responseText) {
                        console.log('error', response.responseText);
                    }
                }
            };
            
            this.productCollection.url = '/reports/catalogue/api/catalogues/categories/' + props.catalogueId + '/products';
            this.productCollection.reset();
            this.productCollection.fetch(fetchOptions);
        },

        render: function() {
            var el = $('#product-results');
            
            if (this.productCollection.models[0] && this.productCollection.models[0].attributes.content[0]) {
                var content = this.productCollection.models[0].attributes.content[0];

                var html = this.productTemplate(content);
                el.html(html);
    
                $("a#fbox-prod-img").fancybox({
                    autoScale: true
                });
    
                $("a#fbox-spec-sheet-img").fancybox({
                    autoScale : true
                });
            }
        }
    });

    return new productView();
});
