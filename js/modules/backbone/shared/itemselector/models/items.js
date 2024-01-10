define([
	'jquery',
 	'underscore',
 	'Backbone'
], function(
	$,
	_, 
	Backbone
){
	var itemsModel = Backbone.Model.extend({
        selected: false,
        
        toggleSelected: function() {
            this.selected = !this.selected;
            this.trigger('move');
        },
        
        setSelected: function(val) {
            this.selected = val;
            this.trigger('move');
        }
	});

	return itemsModel;
});