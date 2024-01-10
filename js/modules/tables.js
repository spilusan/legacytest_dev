define(['jsrender'], function($) {
	var defaultOptions = {
		numPages: 1,
		currPage: 1,
		currSortCol: 'id',
		currSortDir: 'asc'
		
	},
	defaultColHeadingTemplate = 'sstableColHeadingDefault',
	defaultCellTemplate = 'sstableCellDefault';

	//Default table definitions
	$.templates( "sstableRow", { markup: "#sstableRow" });
	$.templates( "sstableTable", { markup: "#sstableTable", allowCode: true	});
	$.templates( "sstableColHeadingDefault", { markup: "#sstableColHeadingDefault",	allowCode: true	});
	$.templates( "sstableCellDefault", { markup: "#sstableCellDefault", allowCode: true	});
	$.templates( "sstableCellCheckbox", { markup: "#sstableCellCheckbox"});

	//Custom tags and helpers for jsrender
	$.views.tags({
		//Render a given template from within another. Seems impossible with standard jsrender
		render: function( data ) {
			return this.tmpl.render( data );
		},

		oddOrEven: function() {
			return this.parent.parent.views.length % 2 == 0 ? 'even' : 'odd';
		}
	});

	function formatOptionsForTemplate(options) {
		var templateData = {};
		
		templateData.cols = jQuery.extend(true, [], options.cols);
		//Map columns, adding default options as necessary, formatting for use in template
		$.each(templateData.cols, function(i, col) {
			if (!col.headTemplate) col.headTemplate = defaultColHeadingTemplate;
			if (!col.cellTemplate) col.cellTemplate = defaultCellTemplate;
		});
			
		templateData.data = [];
		templateData.data = $.map(options.data, function(row, i) {
			var newRow = {cells: []};
			$.each(templateData.cols, function(i, col) {
				var newCell = { key: col.key, value: row[col.key] };
				if (!col.hidden) {
					newCell.cellTemplate = col.cellTemplate;
					newCell.hidden = col.hidden ? col.hidden : false;
					newRow.cells.push(newCell);
				}
			});
			return newRow;
		});

		//Remove hidden cols
		templateData.cols = $.map(templateData.cols, function(col) { return col.hidden ? null : col; });
		
		return templateData;		
	}

	$.extend ({
		sstable: function(options) {
			var options = $.extend({}, defaultOptions, options),
				element = $('<table class="sstable placeholder"></table>');

			element.data('templateData', formatOptionsForTemplate(options));
			element.data('ssTableData', options);

			return element.renderTable();
		}
	});
	
	$.fn.extend({
		renderTable: function () {
			var options = this.data('ssTableData'),
				templateData = this.data('templateData');
			
			this.html($($.render.sstableTable( templateData )));
			this.removeClass('placeholder');

			return this;
		},
		
		getRowData: function() {
			var index = this.closest('tr').index();
			return this.closest('table.sstable').data('ssTableData').data[index];
		},

		getCellData: function() {
			var rowIndex = this.closest('tr').index(),
				key = this.closest('td').attr('data-key');
				
			return { key: key, value: this.closest('table.sstable').data('ssTableData').data[rowIndex][key] };
			
		},
		
		getRowIndex: function () {
			return this.closest('tr').index();
		},
		
		getCellIndex: function() {
			return this.closest('td').index();
		},

		//for element IN A ROW
		updateRowData: function (rowData) {
			
			var templateData = this.closest('table.sstable').data('templateData');
			templateData.data[this.getRowIndex()] = rowData;
			this.renderTable();
			
		}, //for table
		
		updateData: function(page, data) {
			
		}, //for whole table
		
		setNumPages: function(p) {
			
		},
		
		switchTemplate: function(template) { 
			var templateData = this.closest('table.sstable').data('templateData'),
				rowIndex = this.getRowIndex(),
				cellIndex = this.getCellIndex();
	
			this.closest('td').html($(template).render(templateData.data[rowIndex].cells[cellIndex]));
		}
	})
	
	/**
	* EVENTS
	*/
	
	// 'sort', {key : 'title', direction: 'asc'}
	
	return $;
});