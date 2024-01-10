require([
	"jquery",
	"libs/jquery.dataTables.min",
	"jqueryui/datepicker",
	"libs/jquery.uniform",
	"/js/jquery.auto-complete.js"
], function($,
	dt,
	DatePicker,
	Uniform,
	autoComplete
 ){
	require([
		"libs/dataTables.tableTools",
		"libs/dataTables.fixedHeader",
		"libs/dataTables.CSV"
		], function() {
		$(document).ready(function(){
			var page = require('/match/reportType');
			if ($('#resultsTab').length && $('#body').length && $('#content').length) {
				$('#resultsTab').on( 'draw.dt', function () {
					var newWidth = $('#resultsTab').width() + 20;
					$('#body').width(newWidth + 255);
					$('#content').width(newWidth);
				});	
			}
			renderAll(page);
		});
    });

	var dataTableConfig = {
		'autoWidth': true,
    	'lengthMenu': [ [-1, 50, 100], ['All', 50, 100] ],
		'order': [],
		retrieve: true
	};

	var brandDetailsLoaded = false;

	function renderPage( page ){
		switch (page){
			case 'buyer-gmv':
				renderBuyerGmv();
				break;
			case 'buyer-gmv-breakdown':
				renderBuyerGmvBreakdown();
				break;
			case 'match-conversion-report':
				renderMatchConversionReport();
				break;
			case 'supplier-conversion-report':
				renderSupplierConversionReport();
				break;
			case 'supplier-conversion-competitiveness-report':
				renderSupplierConversionCompetitivenessReport();
				break;
			case 'pages-dashboard':
				renderPagesDashboard();
				break;
			case 'sso-dashboard':
				renderSSODashboard();
				break;
			case 'shipmate-po-rate':
				renderPoRate();
				break;
			case 'supplier-response-rate-dashboard':
				renderResponseRateDashboard();
				break;
			case 'brand-management':
				renderBrandManagementDashboard();
				break;
			case 'supplier-statistic-report':
				renderSupplierStatisticReport();
				break;
		}
	}

	function renderResponseRateDashboard(){
		var table = $('#resultsTab').DataTable(dataTableConfig);
	}

	function renderSupplierStatisticReport(){
		var dataTableConfig = {
			"sDom": 'V<"clear">lfrtip',
			'autoWidth': true,
			'lengthMenu': [ [-1, 10, 20], ['All', 10, 20] ],
			retrieve: true
		};

		var table = $('#resultsTab').DataTable(dataTableConfig);

	    try{ new $.fn.dataTable.FixedHeader( table ); }catch(e){}

		$(".uniform").uniform();

		$('#submitBtn').unbind('click').bind('click', function(){
			var emptyFilter = 0;
			$('.filter').each(function(){
				if( $(this).val() == "" ){
					emptyFilter++;
				}
			});

			if( $('#spbName').val() == "" && $('#tnid').val() == "" ){
				emptyFilter++;
			}

			if( emptyFilter == 6 ){
				if(confirm('You did not choose any filter. This report can take a long time (approximately 20 minutes).') ){
					$('#supplierStatsForm').submit();
				}
			}else{
				$('#supplierStatsForm').submit();
			}
		});
	}

	function renderSSODashboard(){
		dataTableConfig.pageLength = parseInt(window.paginationPageLenth);
		var table = $('#resultsTab').DataTable(dataTableConfig);
	}

	function renderPoRate(){
		var table = $('#resultsTab').DataTable(dataTableConfig);
		//console.log(table);
	}

	function renderPagesDashboard(){
		//console.log("A");
		//var table = $('#resultsTab').DataTable(dataTableConfig);
	    //try{ new $.fn.dataTable.FixedHeader( table ); }catch(e){}( table );

	}

	function renderMatchConversionReport(){
		var dataTableConfig = {
			"sDom": 'V<"clear">lfrtip',
			'autoWidth': true,
			'lengthMenu': [ [-1, 10, 20], ['All', 10, 20] ],
			retrieve: true
		};

		var table = $('#resultsTab').DataTable(dataTableConfig);
	  //try{ new $.fn.dataTable.FixedHeader( table ); }catch(e){}( table );

	}

	function renderSupplierConversionCompetitivenessReport(){
		renderSupplierConversionReport();

		var dataTableConfig = {
			"sDom": 'V<"clear">lfrtip',
			'autoWidth': true,
			'lengthMenu': [ [-1, 10, 20], ['All', 10, 20] ],
			retrieve: true
		};

		var table = $('#resultsTab').dataTable(dataTableConfig);
		try{ new $.fn.dataTable.FixedHeader( table ); }catch(e){}( table );
	}

	function renderSupplierConversionReport(){

		$("#periodSelector").uniform();
		$(".child").hide();
		$(".child").each(function(){
			$(".parent[buyerTnid='" + $(this).attr("parentId") + "']").addClass("bold");
		});
		$(".parent").click(function(){
			$(".child[parentId='" + $(this).attr("buyerTnid") + "']").toggle(500);
		});

		$(".toggleDetail").click(function(){
			$(this).parent().parent().find(".detail").toggle();
		});

		$(".drilldown").click(function(){
			$("#parentMode").val('1');
			$("input[value='Go']").click();
		});

		$("input[value='Back']").click(function(){
			$("#parentMode").val('');
			$("input[value='Go']").click();
		});

		$('.copyClip').bind('click', function(e){
			e.preventDefault();
			copyToClipboard();
		});

		/* auto complete for TNID */
		$('input[name="id"]').autoComplete({
			backwardsCompatible: true,
			ajax: '/profile/company-search/format/json/type/v/excUsrComps/1/excNonJoinReqComps/1',
			useCache: false,
			minChars: 3,
			spinner: function(event, status ){
				if (status.active) {
					$(".tnidAutocomplete").show();
				} else {
					$(".tnidAutocomplete").hide();
				}
			},
			onShow: function(){
				 $(".tnidAutocomplete").hide();
			},

			list: 'auto-complete-list-wide',
			preventEnterSubmit: true,
			onSelect: function(data) {
                $('input[name="id"]').focus();
                $('input[name="id"]').val(data.pk);
				return false;
			},
		});

		/* Validate TNID intput */


		$('.new').submit(function( e ) {
			if ($('input[name="id"]').val() == '') {
					alert('TNID Required');
				e.preventDefault();
			} else {
				var tnId = $('input[name="id"]').val();
				if (!tnId.match(/^\d+$/)) {
					alert('Please enter a TNID number!');
				e.preventDefault();
				}
			}
		});
	}

	function renderBuyerGmv(){

		$(".child").hide();
		$(".child").each(function(){
			$(".parent[buyerTnid='" + $(this).attr("parentId") + "']").addClass("bold");

		});
		$(".parent").click(function(){
			$(".child[parentId='" + $(this).attr("buyerTnid") + "']").toggle(500);
		});

		$(".toggleDetail").click(function(){
			$(this).parent().parent().find(".detail").toggle();
		});

		$("#accountManagerSelector").uniform();
		$("#regionSelector").uniform();
		$('.uniform').uniform();
		var dataTableConfig = {
			"sDom": 'V<"clear">lfrtip',
			'autoWidth': true,
		  	'lengthMenu': [ [-1, 10, 20], ['All', 10, 20] ],
		  	'retrieve': true
		};

		var table = $('#resultsTab').dataTable(dataTableConfig);
		if( table.length>0 )
		try{ new $.fn.dataTable.FixedHeader( table ); }catch(e){}( table );

		// for all editable td, we need to swap it with input box when user is double cliking it
		$(".editable").dblclick(function(e){

	    	// make sure if parent is double clicked, it shouldn't be editable
	    	if( $(e.target).is('input') == true ) return ;

	    	$('#cancel').trigger('click');

	    	if( $(this).attr("editingMode") != '1' ){
		    	$(this).attr('editingMode', '1');


		    	var type = $(this).attr('col');
		    	var tnid = $(this).parent().attr('tnid');
		    	var currentValue = $(this).html();

		    	currentValue = currentValue.replace(/,/,'');

		    	html = '<div style="width: 96px; ">';
		    	html += '<input class="editableInputBox" style=" " type="text" value="' + currentValue + '" name="information" id="information" />';
		    	html += '<input type="hidden" value="' + currentValue + '" id="oldValue" />';
		    	html += '<input type="hidden" value="' + type + '" name="type" id="type" />';
		    	html += '<input type="hidden" value="' + tnid + '" name="tnid" id="tnid" />';
		    	html += '<img src="/img/icons/small/save.png" id="save" />';
		    	html += '<img src="/img/icons/small/cancel.png" id="cancel" />';
		    	html += '<div class="clear"></div>';
		    	html += '</div>';

		    	$(this).html(html);

		    	$('#information')
		    		.width( 50 )
		    		.bind('keypress', function(e){
		    			 var code = e.keyCode || e.which;
		    			 if(code == 13) {
		    				 $("#save").trigger('click');
			    			 return false;
		    			 }

		    			 return true;
		    		})
		    		.select();

			    $("#save").on('click', function(){
			    	var div = $(this).parent();
			    	var td = $(this).parent().parent();
			    	var type = $(this).parent().find('#type').val();
			    	var information = $(this).parent().parent().find('#information').val();
			    	var tnid = $(this).parent().parent().find('#tnid').val();

			    	// do js call here
			    	$.ajax({
			    		url: '/buyer/gmv',
			    		data: {'a': 'save', 'information': information, 'type': type, 'buyerTnid': tnid },
			    		success: function(){
			    			div.html('saved');
			    			div.css('background-color', '#e5f8d8');
			    			div.animate({
			    				'opacity': 0
			    			}, 800, function(){
						    	td.attr('editingMode', '0');
						    	td.html(information);
			    			});

			    			$("#refreshFromDbButton").show();
			    			$("#runReport").hide();
			    		},
			    		error: function(data){
			    			alert("error" + data);
			    			$('#cancel').trigger('click');
			    		}
			    	});
			    });

				// When user click cancel
			    $("#cancel").on('click', function(){
			    	$(this).parent().parent().attr('editingMode', '0');
			    	$(this).parent().parent().html($(this).parent().parent().find('#oldValue').val());
			    });
	    	}
	    });

	}

	function renderBuyerGmvBreakdown(){
		var dataTableConfig = {
			"sDom": 'V<"clear">lfrtip',
			'autoWidth': true,
			'lengthMenu': [ [-1, 10, 20], ['All', 10, 20] ]
		};

	    var table = $('#resultsTab').DataTable(dataTableConfig);
	    try{ new $.fn.dataTable.FixedHeader( table ); }catch(e){}( table );
		//xxx
	}


	/**
	 *
	 */
	function renderAll(page){
		$('.datepicker').datepicker( { dateFormat: 'dd/mm/yy' });

		$("#resultsTab tbody tr td, #resultsTab tfoot tr td").each(function(){

			var beautify = true;

			if( beautify === true ){
				var html = $.trim($(this).html());
				var allowedString = ['???'];
				if( $(this).hasClass('currency') ) {

					if( html != '<span title="Please populate this data for all children">???</span>'){
						if( html == "" ){
							if( $(this).hasClass('currency') || $(this).hasClass('percentage') ) {
								$(this).html(0);
							}else{
								$(this).html("N/A");
							}

						}else{
							html = '' + number_format($(this).html(), 0, '.', ',') + '';
							$(this).html( html );
						}
					}
					else{
						console.log(html);
					}
				}

				if( $(this).hasClass('percentage') ) {

					if( html == "" ) {
						if( $(this).hasClass('currency') || $(this).hasClass('percentage') ) {
							$(this).html(0);
						}else{
							$(this).html("N/A");
						}
					}
					if(html != "N/A" && html != 0 ){

						// for price competitiveness report, there's a logic where we need to show price differences between one quote to another
						// if quote is cheaper, then this should be GREEN with NEG sign
						// if quote is more expensive, then this should be RED with POS sign
						var textColor = '';
						if( $(this).hasClass('colored') ){
							var p = parseInt($(this).html());
							var sign = '';
							if( p < 0 ){
								textColor='red';
								sign = '+';
							} else if( p > 0 ){
								sign = '-';
								textColor='green';
							} else if( p == 0 ) {
								textColor='';
							}
							html = '<div class="' + textColor + '">' + sign + number_format(Math.abs(p), (($(this).hasClass('1dc')?1:2)), '.', ',') + '</div>';
						} else {
							html = '<div class="' + textColor + '">' + number_format($(this).html(), (($(this).hasClass('1dc')?1:2)), '.', ',') + '</div>';
						}


						$(this).html( html );
					}
				}

				if( $(this).hasClass('emptyas0') ) {
					if( html == "" ) {
						$(this).html( '0' );
					}
				}

			}
		});

		$("#resultsTab tbody tr").click(function(e){

			if( !$(e.target).is('a') ){
				$("#resultsTab tbody tr").each(function(){
					$(this).removeClass("selectedRow");
				});
				$(this).addClass("selectedRow");
			}


		});
		
		renderPage(page);

	}

	/**
	 * Auxiliary functions
	 **/
	function selectElementContents(el) {
	    var body = document.body, range, sel;
	    if (document.createRange && window.getSelection) {
	        range = document.createRange();
	        sel = window.getSelection();
	        sel.removeAllRanges();
	        try {
	            range.selectNodeContents(el);
	            sel.addRange(range);
	        } catch (e) {
	            range.selectNode(el);
	            sel.addRange(range);
	        }
	    } else if (body.createTextRange) {
	        range = body.createTextRange();
	        range.moveToElementText(el);
	        range.select();
	    }
	}


	function copyToClipboard(){
		selectElementContents(document.getElementById('resultsTab'));
		alert("Copy to clipboard: Ctrl+C (windows) or Cmd + C (mac), Enter");

	}

	function changeParentId(parentId){
		$('#parentId').val(parentId);
		$("#buyerGmvForm").submit();
	}

	function isNumber(n) {
		return !isNaN(parseFloat(n)) && isFinite(n);
	}

	function number_format (number, decimals, dec_point, thousands_sep) {
		//return number;
	    // http://kevin.vanzonneveld.net/techblog/article/javascript_equivalent_for_phps_number_format/
	    number = (number + '').replace(/[^0-9+\-Ee.]/g, '');
	    var n = !isFinite(+number) ? 0 : +number,
	        prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
	        sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
	        dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
	        s = '',
	        toFixedFix = function (n, prec)
	        {
	            var k = Math.pow(10, prec);
	            return '' + Math.round(n * k) / k;
	        };
	    // Fix for IE parseFloat(0.55).toFixed(0) = 0;
	    s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
	    if (s[0].length > 3)
	    {
	        s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
	    }
	    if ((s[1] || '').length < prec)
	    {
	        s[1] = s[1] || '';
	        s[1] += new Array(prec - s[1].length + 1).join('0');
	    }
	    return s.join(dec);
	}

	function renderBrandManagementDashboard() {
		$('.tabs li').click(  function( e ) {

			e.preventDefault();
			var parentLi = $(this).parent();
			$(this).parent().find('li').removeClass("selected");
			$(this).addClass('selected');
			var selectedTabId = $(this).index();

			if (selectedTabId == 1) {
				$('#summaryTab').hide();
				$('#detailsTab').show();
				loadBrandDetails();
				fixHeight();
			} else {
				$('#summaryTab').show();
				$('#detailsTab').hide();
				fixHeight();
			}

		});

		fixHeight();

		$( window ).resize(function() {
			var newWidth = $(window).width()-260;
				if (newWidth<800)  {
					newWidth=800;
		    		}
			$('#content').css('width' , newWidth+'px');
		});

	}

	function loadBrandDetails()
	{
		if (brandDetailsLoaded == false)
		{
			brandDetailsLoaded = true;
			$('#waiting').show();
			$('#resultsTab').dataTable( {
		    'autoWidth': true,
	        'lengthMenu': [ [25, 50, 100, 200, 500], [25, 50, 100, 200, 500] ],
	        "ajax": "/reports/brand-report",
	        "columns": [
			    { "data": "ID" },
			    { "data": "BRAND_NAME" },
			    { "data": "SYNONYM_LIST" },
			    { "data": "OWNER_LIST" },
			    { "data": "IS_ACTIVELY_MANAGED" },
			    { "data": "TOTAL_SUPPLIER_BRAND_LINKS" },
			    { "data": "TOTAL_AUTHORISED_CLAIMS" },
			    { "data": "LISTED_ONLY_COUNT" },
			    { "data": "VERIFIED_CLAIMS" },
			    { "data": "CLAIMS_WHERE_BRAND_IS_OWNED" },
			    { "data": "CLAIMS_PENDING_VERIFICATION" },
			    { "data": "PERCENT_CLAIMS_VERIFIED" },
			    { "data": "AUTH_AGENT_TOTAL" },
			    { "data": "AUTH_AGENT_VERIFIED" },
			    { "data": "AUTH_AGENT_PENDING" },
			    { "data": "AUTH_AGENT_NOT_VERIFIED" },
			    { "data": "OEM_TOTAL" },
			    { "data": "OEM_VERIFIED" },
			    { "data": "OEM_PENDING" },
			    { "data": "OEM_NOT_VERIFIED" },
			    { "data": "REP_TOTAL" },
			    { "data": "REP_VERIFIED" },
			    { "data": "REP_PENDING" },
			    { "data": "REP_NOT_VERIFIED" },
			    { "data": "RELATED_CATEGORY_COUNT" },
			    { "data": "RELATED_CATEGORY_LIST" }
	        ] ,
	        retrieve: true
	    	});

			$('#resultsTab').on( 'draw.dt', function () {
	    		$('#waiting').hide();
	    		fixHeight();
			});
		}
	}

	function fixHeight() {
		//fix content widht and height, for liquid layout

		var newHeight = $('#content').height();

		if (newHeight > 0) {
	    	$('#body').height(newHeight);
		    	var newWidth = $(window).width()-260;
		    	if (newWidth<800)  {
					newWidth=800;
		    	}
				$('#content').css('width' , newWidth+'px');
		}
	}
	/*
	$(document).ready(function(){
		var page = require('/match/reportType');
		renderAll(page);

		// $("#content").prepend('<div id="hideLeftMenuButton">&laquo;&laquo;</div>');
		// $("#hideLeftMenuButton").bind('click', function(){
		// 	$("#sidebar").toggle();
		// });
	})
	*/

});
