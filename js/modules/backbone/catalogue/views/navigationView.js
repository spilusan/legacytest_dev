define([
    'jquery',
    'underscore',
    'Backbone',
    'handlebars',
    'backbone/catalogue/views/navigationItemView',
    'text!templates/catalogue/tpl/navigation.html'
], function(
    $,
    _,
    Backbone,
    Hb,
    NavigationItem,
    NavigationTpl
){
    var navigation = Backbone.View.extend({
        el: null,
        element: null,
        items: [],
        navigationItems: [],
        navigationTemplate: Handlebars.compile(NavigationTpl),

        initialize: function(items, el) {

            this.items = items;
            this.el = el;

            this.renderItem = this.renderItem.bind(this);
            this.render();
        },

        render: function() {
            var html = this.navigationTemplate();
            this.element = $(html);
            this.el.append(this.element);
            _.each(this.items, function(item) {
                this.renderItem(item);
            }, this);
        },

        renderItem: function (item) {
            this.navigationItems.push(new NavigationItem(item, this.element, item.attributes.id));
        }
    });

    return navigation;
});
