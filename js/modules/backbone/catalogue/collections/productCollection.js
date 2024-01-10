define([
    'jquery',
    'underscore',
    'Backbone',
    '../models/productModel'
], function(
    $,
    _,
    Backbone,
    productModel
){
    var productCollection = Backbone.Collection.extend({
        model: productModel,
		parse: function(response){			
             if (response.content) {
                response.content = response.content.map(function(x){
                     x.specSheetFull = x.specSheetFull && x.specSheetFull.replace('www.', 'legacy.');
                     x.productImageFull = x.productImageFull && x.productImageFull.replace('www.', 'legacy.');
                     return x;
                 });
             }
            
		 	return response;
		}
    });

    return productCollection;
});
