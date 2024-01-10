define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'libs/jquery.uniform',
	'libs/jquery.dateFormat',
    'jqueryui/datepicker',
    '../collections/supplierList',
	'text!templates/reports/kpi-trend/tpl/filters.html',
	'text!templates/reports/kpi-trend/tpl/noSupplier.html'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	Uniform,
	dateFormat,
	datePicker,
	supplierListColl,
	filtersTpl,
	noSupplierTpl
){
	var filtersView = Backbone.View.extend({
		el: $('#filters'),
		selectedElement: null,
		template: Handlebars.compile(filtersTpl),
		noSupplierTemplate: Handlebars.compile(noSupplierTpl),
		
		events: {
			'click input.show' : 'onShowClicked',
			'click #tnid-selector' : 'onSelectElement',
		},

		initialize: function() {
			var thisView = this;			
			this.supplierListCollection = new supplierListColl();
			this.supplierListCollection.url = '/reports/supplier/supplier-companies-list';

			$('body').click(function(e){
				if (!$(e.target).hasClass('noClose')) {
					$('#pullDown').hide();

				}
			});

		},

		getData: function() {
			var thisView = this;
			this.selectedElement = null;
			this.supplierListCollection.reset();	
			this.supplierListCollection.fetch({
				add: true,
				remove: false,
				complete: function() {
					thisView.render();
				}
			});
		},

		buildTree: function( data, level )
		{

			var thisView = this;
			var parent = $('<ul>');

			if (level>0) {
				$(parent).css('display','none');
			}

			for (var key in data) {
				var element = $('<li>');
				$(element).data('branchCode', data[key].data.spbBranchCode);
				if (this.selectedElement === null) {
					$(element).addClass('selected');
					this.selectedElement = element;
				} 

				var toggleElement = $('<span>');
				$(toggleElement).addClass('noClose');
				if (data[key].data.childCount > 0) {
					$(toggleElement).addClass('toggleUp');
					
					$(toggleElement).click(function(){
						thisView.toggleList(this);
					});
				} else {
					$(toggleElement).addClass('noToggle');
				}

				var contentElement = $('<span>');
				$(contentElement).addClass('noClose');
				$(contentElement).html(data[key].data.spbBranchCode+' - '+data[key].data.spbName+' ('+data[key].data.childCount+')');
				$(contentElement).click(function() {
					thisView.onPullDownClick(this);
				});	


				$(element).append(toggleElement);
				$(element).append(contentElement);

				$(element).data('id', data[key].data.spbBranchCode);
				
				parent.append(element);

				if (data[key].children) {
					parent.append(this.buildTree(data[key].children, level+1));	
				}
			}
			return parent;
		},

		onSelectElement: function()
		{
			$('#pullDown').slideDown(100);
		},

		onPullDownClick: function(e)
		{
			this.selectedElement.removeClass('selected');
			this.selectedElement = $(e).parent();
			$(e).parent().addClass('selected');
			$('#selText').html($(e).html());
			$('#pullDown').hide();

		},

		toggleList: function(e)
		{
			if ($(e).hasClass('toggleDown')) {
				$(e).removeClass('toggleDown');
				$(e).addClass('toggleUp');
				$(e).parent().next('ul').slideUp();
			} else {
				$(e).removeClass('toggleUp');
				$(e).addClass('toggleDown');
				$(e).parent().next('ul').slideDown();	
			}
		},
		
		render: function() {

			if (this.supplierListCollection.models[0]) {
				var selectedElement = null;
				var html = '';
				if (this.supplierListCollection.models[0].attributes.data.length > 0) {
					selectedElement = this.supplierListCollection.models[0].attributes.data[0].data.spbBranchCode+' - '+this.supplierListCollection.models[0].attributes.data[0].data.spbName+' ('+this.supplierListCollection.models[0].attributes.data[0].data.childCount+')';

					var startDate = new Date();
					var endDate = new Date(startDate.getFullYear(), startDate.getMonth()-1, startDate.getDate());

					var toDate = $.datepicker.formatDate("dd/mm/yy",startDate);
					var fromDate = $.datepicker.formatDate("dd/mm/yy",endDate);

					html = this.template({
						fromDate: fromDate,
						endDate: toDate,
						selectedElement: selectedElement,
					});

					$(this.el).html(html);
					$('input.date').datepicker({
					  dateFormat: "dd/mm/yy"
					});
					$('input[type="checkbox"]').uniform();
					$('select[name="dateRange"]').uniform();
					var tree = this.buildTree(this.supplierListCollection.models[0].attributes.data, 0);
					$('#pullDown').append(tree);
				} else {
					html = this.noSupplierTemplate();
					$(this.el).html(html);
				}
			}
		},

		onShowClicked: function(e)
		{
			e.preventDefault();
			this.parent.getData();
		}

	});

	return filtersView;
});
