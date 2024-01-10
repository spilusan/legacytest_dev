/*
 * This search works only as a sample as the API does not support ShipServ catalogue search, only IMPA search
 */
define([
    'jquery',
    'underscore',
    'Backbone',
    'handlebars',
    '../collections/searchCollection',
    'backbone/catalogue/views/searchItemView',
    'text!templates/catalogue/tpl/search.html'
], function(
    $,
    _,
    Backbone,
    Hb,
    SearchCollection,
    SearchItemView,
    SearchTpl
){
    var searchView = Backbone.View.extend({
        el: null,
        searchTemplate: Handlebars.compile(SearchTpl),

        initialize: function() {
            this.searchCollection = new SearchCollection();
            this.searchCollection.url = '/reports/catalogue/api/catalogues/search/docs';

            this.renderItems = this.renderItems.bind(this);
        },

        render: function(keywords) {

            var thisView = this;

            var params = {
                'search': keywords + '~',
                'queryType': 'full',
                'catalogueDefinition': 'shipserv'
            };

            var fetchOptions = {
                type: 'GET',
                data: $.param(params),

                complete: function () {
                    thisView.renderItems();
                },

                error: function (model, response) {
                    if (response.responseText) {
                        console.log('error', response.responseText);
                    }
                }
            };

            this.searchCollection.reset();
            this.searchCollection.fetch(fetchOptions);
        },

        renderItems: function()
        {
            if (this.searchCollection.models[0]) {
                var el = $('#product-results');
                el.empty();

                var html = this.searchTemplate();
                el.html(html);


                _.each(this.searchCollection.models[0].attributes.value, function (item) {
                    new SearchItemView(item);
                }, this);
            }
        }

    });

    return searchView;
});
