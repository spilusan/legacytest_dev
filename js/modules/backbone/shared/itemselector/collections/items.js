define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/items'
], function(
	$, 
	_, 
	Backbone, 
	categoriesModel
) {
	var itemsCollection = Backbone.Collection.extend({
		model: categoriesModel,
		url: null,
        primaryKey: "ID",
        
        init: function() {
            
        },
        
        findChildren: function(parentItem) {
            var children = [],
                thisCollection = this;
            
            children = _.filter(this.models, function(item){
                return item.get("PARENT_ID") == parentItem.get(thisCollection.primaryKey);
            });

            /*_.each(children, function(item) {
                children = children.concat(thisCollection.findChildren(item));
            });*/
            
            return children;
        },
        
        getSelected: function() {
            _.filter(this.models, function(m){
                return m.selected;
            });
            
        }        
	});

	return itemsCollection;
});