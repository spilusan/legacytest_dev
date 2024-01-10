define([
    'jquery',
    'underscore',
    'Backbone',
    'handlebars',
    'backbone/shared/hbh/general',
    'text!templates/catalogue/tpl/pagination.html'
], function(
    $,
    _,
    Backbone,
    Hb,
    Hbh,
    PaginationTpl
){
    var paginationView = Backbone.View.extend({
        paginationTemplate: Handlebars.compile(PaginationTpl),
        itemsFound: null,
        itemPerPage: null,
        totalPages: null,
        currentPage: 1,
        onChangeCallback: null,

        initialize: function(props) {
            this.itemsFound = props.itemsFound;
            this.totalPages = props.totalPages;
            this.itemPerPage = props.size;
            this.render = this.render.bind(this);
            this.render();
        },

        render: function() {

            if (this.totalPages > 1) {
                var thisView = this;

                var el = $('#pagination-box');
                el.empty();

                var data = [];
                var pageCount = this.totalPages;
                var selected = null;
                var pageStart = 1;
                var pageEnd = pageCount;

                if (pageCount > this.itemPerPage) {
                    pageStart = Math.floor(this.currentPage / 7) * 7;
                    pageEnd = pageStart + 6;
                    if (this.currentPage >= Math.round(pageCount / 2)) {
                        data.push({
                            page: 1,
                            selected: false
                        });
                        data.push({
                            page: '...',
                        });
                    }
                }

                if (pageStart < 1) {
                    pageStart = 1;
                }

                if (pageEnd > pageCount + 1) {
                    pageEnd = pageCount + 1;
                }

                for (var i = pageStart; i <= pageEnd; i++) {
                    selected = this.currentPage === i;
                    data.push({
                        page: i,
                        selected: selected
                    });
                }

                if (pageCount > this.itemPerPage) {
                    if (this.currentPage < Math.round(pageCount / 2)) {
                        data.push({
                            page: '...'
                        });
                        data.push({
                            page: pageCount + 1,
                            selected: false
                        });
                    }
                }

                var prev = this.currentPage > 1 ? this.currentPage - 1 : null;
                var next = pageCount >= this.currentPage ? this.currentPage + 1 : null;
                var params = {
                    pages: data,
                    prev: prev,
                    next: next
                };

                var html = this.paginationTemplate(params);
                el.html(html);

                $('.product-paginate-page').click(function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (typeof thisView.onChangeCallback === "function") {
                        var selectedPage = $(this).data('id');
                        thisView.currentPage = selectedPage;
                        thisView.onChangeCallback(selectedPage, thisView.itemPerPage);
                    }
                });
            }
        },

        onChange: function(callback) {
            this.onChangeCallback = callback;
        }

    });

    return paginationView;
});
