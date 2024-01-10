define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'text!templates/reports/priceBench/tpl/yourRow.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	yourRowTpl
){
	var yourRowView = Backbone.View.extend({
		tagName: 'tr',
		yTemplate: Handlebars.compile(yourRowTpl),

		events: {
			'click a.delete' : 'onDelete'
		},

		initialize: function(){
			_.bindAll(this, 'render');
			this.model.view = this;
		},

	    render: function() {
			var data = this.model.attributes,
				od = data.orderDate.substring(0,10),
				splitDate = od.split('-'),
				year = splitDate[0].substring(2,4),
				month = splitDate[1],
				day = splitDate[2];
			od = day + "/" + month + "/" + year;

			data.od = od;
			
			var html = this.yTemplate(data),
			thisView = this;

			$(this.el).html(html);

			return this;
	    },

	    onDelete: function() {
	    	this.parent.yCollection.reset();
			$('.leftData .dataContainer .data table tbody').html('');
	    	this.parent.leftPageNo = 1;
	    	this.parent.excludeLeft.push(this.model.attributes.id);
	    	this.parent.getYourData();
	    }
	});

	return yourRowView;
});