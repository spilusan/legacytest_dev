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
    'backbone/catalogue/views/paginationView',
    'text!templates/catalogue/tpl/search.html'
], function(
    $,
    _,
    Backbone,
    Hb,
    SearchCollection,
    SearchItemView,
    PaginationView,
    SearchTpl
){
    var searchView = Backbone.View.extend({
        tnid: require('reports/tnid'),
        el: null,
        itemsFound: 0,
        totalPages: 0,
        size: 10,
        itemsFrom: 0,
        itemsTo: 10,
        searchTemplate: Handlebars.compile(SearchTpl),

        initialize: function() {
            this.searchCollection = new SearchCollection();
            this.searchCollection.url = '/reports/catalogue-rest';

            this.renderItems = this.renderItems.bind(this);
            this.renderPagination = this.renderPagination.bind(this);
            this.paginationChange  = this.paginationChange.bind(this);
        },

        render: function(keywords) {
            var thisView = this;

            var params = {
                'id': this.tnid,
                'folderStart': 0,
                'folderRows': 10,
                'itemStart': this.itemsFrom,
                'itemRows': this.size,
                'query': keywords
            };

            var fetchOptions = {
                type: 'GET',
                data: $.param(params),

                complete: function () {
                    thisView.renderItems();
                },

                error: function (model, response) {
                    console.log('Error fetching result')
                }
            };

            this.searchCollection.reset();
            this.searchCollection.fetch(fetchOptions);
        },

        renderItems: function()
        {
            if (this.searchCollection.models[0]) {
                this.itemsFound = this.searchCollection.models[0].attributes.itemsFound;
                this.totalPages = Math.floor(this.itemsFound / this.size);

                var el = $('#product-results');
                el.empty();

                var html = this.searchTemplate();
                el.html(html);
                _.each(this.searchCollection.models[0].attributes.items, function (item) {
                    new SearchItemView(item);
                }, this);

                this.renderPagination();
                this.pagination.render();
            }
        },

        renderPagination: function() {
            this.pagination = new PaginationView({
                itemsFound: this.itemsFound,
                totalPages: this.totalPages,
                size: this.size
            });

            this.pagination.onChange(this.paginationChange);
        },

        paginationChange: function(itemsFrom, itemsTo) {
            this.itemsFrom = itemsFrom;
            this.itemsTo = itemsTo;
            var keywords = $('#query').val();
            this.render(keywords);
        }

    });

    return searchView;
});
