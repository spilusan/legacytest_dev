define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'modal',
    'help',
    'libs/fileSaver',
    'libs/jquery.validity.min',
    'libs/jquery.validity.custom.output.gmv',
    'libs/jquery-ui-1.10.3/datepicker',
    'libs/waypoints/waypoints-sticky',
	'../collections/gmvList',
	'../views/gmvRowView',
	'/js/jquery.auto-complete.js'
], function(
	$,
	_,
	Backbone,
	Hb,
	modal,
	help,
    saveAs,
    validity,
    validityCustom,
    Datepicker,
    Sticky,
	gmvList,
	gmvRowView,
	autocomplete
){
	var mainView = Backbone.View.extend({

		el: $('body'),
		autocompletInProgress: false,
		events: {
			'click a.expandAll'   : 'toggleAll',
			'click a.collapseAll' : 'toggleAll',
			'click a.expandSel'   : 'toggleSel',
			'click a.collapseSel'   : 'toggleSel',
			'click input[name="generate"]' : 'submitForm',
			'focus input.datepicker' : 'focusDate',
			'blur input.datepicker' : 'blurDate'
		},


		initialize: function () {
		thisView = this;
			_.bindAll(this, 'render');
			this.collection = new gmvList();
			this.collection.url = "/reports/gmv-data/";

	        //Set up datepicker
	        $('.datepicker').datepicker({dateFormat: 'yy-mm-dd'});

			$('body').ajaxStart(function(){
				if (!thisView.autocompletInProgress) {
					$('#waiting').show();
				}
			});

			$('body').ajaxStop(function(){
				if (!thisView.autocompletInProgress) {
					$('#waiting').hide();
				}
			});


			$('input[name="tnidForGmv"]').autoComplete({
				backwardsCompatible: true,
				ajax: '/profile/company-search/format/json/type/v/excUsrComps/1/excNonJoinReqComps/1',
				useCache: false,
				minChars: 3,
				spinner: function(event, status){
					if (status.active) {
						thisView.autocompletInProgress = true;
						$(".tnidAutocomplete").show();
					} else {

						$('#waiting').hide();
						$(".tnidAutocomplete").hide();
					}
				},
				onShow: function(){
					thisView.autocompletInProgress = true;
					 $(".tnidAutocomplete").hide();
				},

				list: 'auto-complete-list-wide',
				preventEnterSubmit: true,
				onSelect: function(data) {
                    $('input[name="tnidForGmv"]').focus();
                    $('input[name="tnidForGmv"]').val(data.pk);
					return false;
				},

			});


		        //Handle CSV - either saveAs if available, otherwise show in modal textarea
		    $('a.view.csv').click(function() {

		    	    var isFileSaverSupported = false;
		            try { isFileSaverSupported = !!new Blob(); } catch(e){}

		            if (isFileSaverSupported) {
		                var blob = new Blob([$('textarea.csv').val()]);
		                saveAs(blob, "GMV Report.csv");
		            } else {
		                $('textarea.csv').ssmodal({});
		            }
		        });
		},

		getData: function() {
			var datefrom = $('input[name="datefrom"]').val(),
				dateto = $('input[name="dateto"]').val(),
				tnid = $('input[name="tnidForGmv"]').val(),
				thisView = this;

			this.collection.fetch({
				data: $.param({
					datefrom: datefrom,
					dateto: dateto,
					tnid: tnid
				}),
				success: function() {
					thisView.sortData();
				},
				error: function(collection, response, options){
					data = JSON.parse( response.responseText);
					alert(data.error );
				}
			});
		},

		render: function() {
			$('.gmvSupplierDetail div.supplierDetail').html(this.collection.models[0].attributes.supplier.name);
			$('.gmvSupplierDetail span.publicTnid').html(this.collection.models[0].attributes.supplier.publicTnid);
			$('.gmvSupplierDetail span.countryName').html(this.collection.models[0].attributes.supplier.countryName);
			$('.gmvSupplierDetail span.isPublished').html((this.collection.models[0].attributes.supplier.isPublished==true)?"Yes":"No");
			$('.gmvSupplierDetail span.accountManager').html('<a href="' + this.collection.models[0].attributes.supplier.accountManagerEmail + '">' + this.collection.models[0].attributes.supplier.accountManager + '</a>');

			 if(this.collection.models.length > 0){ 
				$('.noItems').hide();
				$('.gmvData').html('');



				if (this.collection.models[0].attributes.totalTrans) {
					_.each(this.collection.models, function(item){
							this.renderItem(item);
					}, this);
			        var total = this.collection.models[0].attributes.totalTrans['adjusted'].toFixed(2);
			        	total = total.replace(/\B(?=(\d{3})+(?!\d))/g, ",");

		        } else {
		        	var total = 0;
		        	alert('No data found!');
		        }
		        $('.totalTrans').html(total);
		        $('.actions').show();
			}
			else {
				$('.gmvData').html('');
				$('.noItems').show();
				$('.totalTrans').html('0');
				$('.actions').show();
			}


			var datefrom = $('input[name="datefrom"]').val(),
			dateto = $('input[name="dateto"]').val(),
			tnid = $('input[name="tnidForGmv"]').val()
			;

			// get the content of CSV
			$.get("/reports/gmv-data/?type=csv&datefrom=" + datefrom + "&dateto=" + dateto + "&tnid=" + tnid, function(data){
				$("#csvData").val(data);
			});

			$('.actions').waypoint('sticky', {offset: 60});

		},

		renderItem: function(item) {
		    var gmvListRow = new gmvRowView({
		        model: item
		    });
		    $('.gmvData').append(gmvListRow.render().el);
		    $('.gmvData').append('<div class="firstChildContainer"></div>');
		},

		sortData: function() {
			_.each(this.collection.models, function(item){
				item.attributes.totalSum = 0;
				_.each(item.attributes.CHILDREN, function(child){
					child.childSum = 0;
					child.totalChildSum = 0;
					child.currencies = "";
					child.sortedData = {};
					_.each(child.DATA, function(dataItem){
						item.attributes.totalSum = item.attributes.totalSum + dataItem['adjusted-cost'];
						child.childSum = child.childSum + dataItem['adjusted-cost'];
						child.totalChildSum = child.totalChildSum + dataItem['total-cost-usd'];

						if(child.currencies !== ""){
							if(child.currencies.search(dataItem['currency']) === -1){
								child.currencies += ", " + dataItem['currency'];
							}
						}
						else {
							child.currencies += dataItem['currency'];
						}

						if(!origID){
							var origID = dataItem['internalRefNo'];

							if(!child.sortedData[origID]){
								child.sortedData[origID] = [];
							}

							child.sortedData[origID].push(dataItem);
						}
						else {
							if(dataItem['internalRefNo'] == origID){
								child.sortedData[origID].push(dataItem);
							}
							else {
								origID = dataItem['internalRefNo'];

								if(!child.sortedData[origID]){
									child.sortedData[origID] = [];
								}

								child.sortedData[origID].push(dataItem);
							}
						}

						child.sortedData[origID].sort(function(a, b){
						    if(a['doc-type'] < b['doc-type']) return -1;
						    if(a['doc-type'] > b['doc-type']) return 1;
						    return 0;
						});

					}, child);
				});
			}, this);

			this.render();
		},

		toggleAll: function(e){
			e.preventDefault();
			if($(e.target).hasClass('expandAll')){
				var state = false;
			}
			else {
				var state = true;
			}
			$('table.group').each(function() {
                if($(this).find('tbody').hasClass('ui-state-active') === state){
                    $(this).trigger('click');
                }
            });
            $('table.parent').each(function() {
                if($(this).find('tbody').hasClass('ui-state-active') === state){
                    $(this).trigger('click');
                }
            });
            $('table.child').each(function() {
                if($(this).find('tbody').hasClass('ui-state-active') === state){
                    $(this).trigger('click');
                }
            });
		},

		toggleSel: function(e){
			e.preventDefault();
			if($(e.target).hasClass('expandSel')){
				var state = false;
			}
			else {
				var state = true;
			}
            $('table.gmv').each(function() {
                var el = $(this).find('input.groupExpand');
                if($(el).prop('checked')){
                    if($(this).find('tbody').hasClass('ui-state-active') === state){
                        $(this).trigger('click');
                    }
                }
            });
		},

		focusDate: function(e){
			if($(e.target).val() === "yyyy-mm-dd"){
				$(e.target).val('');
			}
		},

		blurDate: function(e){
			if($(e.target).val() === ""){
				$(e.target).val('yyyy-mm-dd');
			}
		},

		validateForm: function(){
			$.extend($.validity.patterns, {
	            date:/^\d{4}[-]\d{2}[-]\d{2}$/
	        });

	    	$.validity.setup({ outputMode:"custom" });

	    	// Start validation:
	        $.validity.start();
	        var isValid = true;

	        if ($('input[name="datefrom"]').val() !== "yyyy-mm-dd" && $('input[name="datefrom"]').val() !=="") {
			    $('input[name="datefrom"]').match('date','Please enter a valid date (yyyy-mm-dd)');
			}
			else {
				$('input[name="datefrom"]').val('');
				$('input[name="datefrom"]').require('Please enter a start date.');
			}

			if ($('input[name="dateto"]').val() !== "yyyy-mm-dd" && $('input[name="dateto"]').val() !=="") {
			    $('input[name="dateto"]').match('date','Please enter a valid date (yyyy-mm-dd)');
			}
			else {
				$('input[name="dateto"]').val('');
				$('input[name="dateto"]').require('Please enter an end date.');
			}

			/* validate if TNID is a number */

			$('input[name="tnidForGmv"]').match('number','Please enter a number.');


			/* Checking date range, could not be more then one year */


			var dateFrom = new Date($('input[name="datefrom"]').val());
			var dateTo = new Date($('input[name="datefrom"]').val());

			dateTo.setMonth(dateTo.getMonth() + 12);

			$('input[name="dateto"]')
			.match('date')
		    .range(dateFrom, dateTo,'Date range must be less then one year');

	        // End the validation session:
		    var result = $.validity.end();

		    // Return whether the form is valid
		    return result.valid;
		},



		submitForm: function(){
			this.autocompletInProgress = false;
			if(this.validateForm()) {
				var x = new Date($('input[name="datefrom"]').val());
				var y = new Date($('input[name="dateto"]').val());
				if(x > y){
					$('input[name="dateto"]').addClass("invalid");
					$('input[name="dateto"]').parent().after("<div class='error'>The end date has to be after the start date.</div><div class='clear err'></div>");
					//$('input[name="dateto"]').focus();
				}
				else {
					this.getData();
				}
			}
		}
	});

	return new mainView;
});
