define([
    'backbone/catalogue/views/preBuildTree',
    '../collections/catalogueCollection',
    'backbone/catalogue/views/navigationView',
    'backbone/catalogue/views/searchView',
], function(
    PreBuildTree,
    CatalogueCollection,
    NavigationView,
    SearchView
){
    var mainViewAuth = Backbone.View.extend({
        tnid: require('reports/tnid'),
        preBuildTree: null,
        navigationView: null,
        searchInputElement: $('input#query'),
        profileElement: $('#profile'),
        contactContent: $('#contact_content'),
        reputationBoxElement: $('#reputation_box'),
        mapBoxElement: $('#map_box'),
        mapToggleElement:  $('li#map_toggle'),
        catalogueElement: $('#catalogue'),
        catalogueBoxElement: $('#catalogue_box'),
        catalogueToggleElement: $('li#catalogue_toggle'),
        profileToggleElement: $('li#profile_toggle'),
        contactToggleElement: $('li#contact_toggle'),
        reviewsToggleElement: $('li#reviews_toggle'),
        contentWideBodyRight: $('.content_wide_body_right'),

        initialize: function () {

            var thisView = this;
            this.onHashChange = this.onHashChange.bind(this);
            window.lastBrowserHash = '#' + window.location.hash;

            this.searchView = new SearchView();
            this.onSearch = this.onSearch.bind(this);

            this.catalogueCollection = new CatalogueCollection();
            this.getData();

            $(document).on('click', 'input.magnifer', function() {
               thisView.onSearch();
            });

            this.searchInputElement.on('keyup', function(e) {
                if (e.keyCode === 13) {
                    thisView.onSearch();
                }
            });

            $('a.clear_search').click(function () {
                thisView.searchInputElement.val('');
                $('#product-results').empty();
                $('a.clear_search').hide();
                thisView.getData();
                var newHash = 'catalogue';
                window.lastBrowserHash = '#' + newHash;
                window.location.hash = newHash;
            });

            var currentHash = window.location.hash;

            if (currentHash.match('^#?catalogue.*')) {
                this.toggleElements();
            }

            // for some reason backbone router does not work on this page, I am using jQuery instead
            $(window).on('hashchange',function() {
                thisView.onHashChange();
            });
        },

        onSearch: function() {
            var keywords = this.searchInputElement.val();

            this.toggleElements();

            if (keywords === "") {
                $('a.clear_search').hide();
            }  else {
                $('a.clear_search').show();
            }

            this.searchView.render(keywords);
            var newHash = 'catalogue';
            window.lastBrowserHash = '#' + newHash;
            window.location.hash = newHash;
        },

        getData: function() {
            var thisView = this;

            var params = {
                supplierId: this.tnid
            };

            this.catalogueCollection.url = '/reports/catalogue/api/catalogues';

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

            this.catalogueCollection.reset();
            this.catalogueCollection.fetch(fetchOptions);
        },

        toggleElements: function()
        {
            this.profileElement.hide();
            this.contactContent.hide();
            this.reputationBoxElement.hide();
            this.mapBoxElement.hide();
            this.catalogueElement.show();
            this.catalogueBoxElement.show();
            this.contentWideBodyRight.hide();
            this.catalogueToggleElement.removeClass('off').addClass('on');
            this.profileToggleElement.removeClass('on').addClass('off');
            this.contactToggleElement.removeClass('on').addClass('off');
            this.reviewsToggleElement.removeClass('on').addClass('off');
            this.mapToggleElement.removeClass('on').addClass('off');
        },

        render: function() {
            if (this.catalogueCollection.models[0]) {
                var items = this.catalogueCollection.models;
                var el = $('#sidebar-nav-new');
                el.empty();

                this.navigationView = new NavigationView(items, el);
                var renderedCatalogues = this.navigationView.navigationItems;
                this.preBuildTree = new PreBuildTree({}, renderedCatalogues);
            }
        },

        onHashChange: function() {
            var currentHash = window.location.hash;
            if (window.lastBrowserHash !== currentHash) {
                if (currentHash.match('^#?catalogue.*')) {
                    $('#product-results').empty();
                    this.render();
                    if (this.navigationView) {
                        this.preBuildTree.initialize({}, this.navigationView.navigationItems);
                    }
                }
            }
        },

    });

    return mainViewAuth;
});
