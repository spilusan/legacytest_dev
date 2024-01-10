define([
	'jquery',
	'underscore',
	'Backbone'
], function(
	$, 
	_, 
	Backbone
){
	var DefaultItemView = Backbone.View.extend({
		tagName: 'li',
		className: null,
        side: 'left',
        counterpart: null, /* the corresponding view in the *other* list, e.g. if this view is in the left list, this will reference the right */
        
        events: {
            'click' : 'onClick'
        },
        
        config: {
             displayKey: 'DISPLAYNAME',
             hierarchy: false
        },
        
		initialize: function(options) {
            var thisView = this;
            this.opts = options;

			this.model.on('move', function(e){thisView.modelMoved(e)});
		},
        
        modelMoved: function(e) {
            this.model.selected ? this.$el.addClass('selected') : this.$el.removeClass('selected');
        },
        
        onClick: function() {
            if(!this.$el.hasClass('hasVisibleChild')){
                this.trigger('move');
            }
        },

        setConfig: function() {
            $.extend(this.config, this.opts);
        },

        /**
         * itemType
         */
		render: function() {
            if(this.model.attributes.continent){
                this.className = this.model.attributes.continent;    
            }
            
            this.model.view = this;
            this.setConfig();
			var data = this.model.attributes,
                $el = $(this.el),
                thisView = this;
            if(this.model.attributes.continent){
                $el.addClass(this.model.attributes.continent);
            }
            $el.append(data[this.config.displayKey]);

            if (this.config.hierarchy) {
                $el.addClass('indent'+(data.DEPTH -1));
                $el.data({depth: data.DEPTH - 1});
            }

            if (this.model.selected) {
                $el.addClass('selected');
            }
            //$el.delegateEvents();
            return this;
		}
	});

	return DefaultItemView;
});