/*! CSV exporter
* Author: Danil Galeev - MIT licensed
*/
/**
* @summary CSV client side Exporter
* @description CSV button for DataTables for importing table WYSIWYG.
* @version 1.0
* @file dataTables.CSV.js
* @author Danil Galeev (www.profitbricks.com)
*
* This source file is free software, available under the following license:
* MIT license - http://datatables.net/license/mit
*
* This source file is distributed in the hope that it will be useful, but
* WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
* or FITNESS FOR A PARTICULAR PURPOSE. See the license files for details.
*
* For details please refer to: http://www.datatables.net
*/

/*
Usage:
  - for DataTables version >= 1.10:
		add table control element "V" to dom configuration,
		for example:
			$('#example').DataTable({
				"dom": 'V'
			});
		for more information please visit https://datatables.net/reference/option/dom
  - for DataTables version < 1.10:
		add table control element "V" to sDom configuration,
		for example:
			$('#example').dataTable({
				"sDom": 'V'
			});
		for more information please visit http://legacy.datatables.net/ref
*/


$(document).ready(function(){

	var ua = window.navigator.userAgent;
	var msie = ua.indexOf("MSIE ") + ua.indexOf("Trident") + 2;
	var ie = false;

	if (msie > 0)      // If Internet Explorer
	 	ie = true;
	else                // If another browser
		ie = false;
	if(!ie){
		$.fn.dataTableExt.aoFeatures.push({
			fnInit: function(oSettings) {
				oSettings.aoDrawCallback.push({
					fn: function () {},
					sName: "CSVButton"
				});

				var btn = document.createElement("a");
				btn.classList.add("datatable-get-csv");
				btn.setAttribute("href", "javascript: void(0);");

				btn.addEventListener("click", function() {

		            function strip(html){
		               var tmp = document.createElement("DIV");
		               tmp.innerHTML = html;
		               var data = tmp.textContent || tmp.innerText || "";
		               return data;
		            }

					var contentParts = [], rowParts = [], visibleColumns = [], column, data, dataHtml, field;
					var filename = oSettings.sTableId || "items_list";
					var table = oSettings.oInstance.DataTable();
					var rows = table.rows({filter: "applied"});
					var rowsData = rows.data();
					var columns = table.columns();
					var columnsCount = columns.data().length;

					// Columns and headers
					for (var i = 0; i < columnsCount; i++) {
						column = table.column(i);
						if (column.visible() && ($.type(column.dataSrc()) === "string" || $.type(column.dataSrc()) === "number")) {
							visibleColumns.push(column.dataSrc());
		                    var header = $(column.header()).html();
		                    header = '"' + strip(header) + '"';
							rowParts.push(header);
						}
					}

					if (rowParts.length > 0) {
						contentParts.push(rowParts.join(","));
					}

					// Rows
					for (var rowNum = 0; rowNum < rowsData.length; rowNum++) {
						rowParts = [];
						for (var j = 0; j < visibleColumns.length; j++) {
							field = visibleColumns[j];
							data = rowsData[rowNum][field];
							dataHtml = '';
							if ($.type(data) === "array") {
		                        rowParts.push('"' + data[0].replace(/,/i,'') + '"');
							} else {
								try {
									dataHtml = strip(data);
								}
								catch (e) {
									dataHtml = data;
								}
								if (dataHtml) {
									rowParts.push('"' + dataHtml.replace(/,/i,'') + '"');
								} else {
									rowParts.push('"' + data.replace(/,/i,'') + '"');
								}
							}
						}
						contentParts.push(rowParts.join(","));
					}
					
					btn.setAttribute("href", "data:text/csv;charset=utf-8," + encodeURIComponent(contentParts.join("\n")));
					btn.setAttribute("download", filename + ".csv");
					return false;
				});

				btn.appendChild(document.createTextNode("Export to CSV"));
				return btn;
			},
			cFeature: "V",
			sFeature: "CSVButton"
		});
	}
});
