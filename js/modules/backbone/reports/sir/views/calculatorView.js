define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'text!templates/reports/sir/tpl/calculator.html',
	'../collections/supplierInsightDataCollection'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	calculatorView,
	supplierInsightData
){
	var analysisView = Backbone.View.extend({

		el: $('body'),
		calculatorTemplate: Handlebars.compile(calculatorView),
		events: {
			/* 'click a.expandAll'   : 'toggleAll', */
		},

		supplier: require('supplier/profile'),

		initialize: function ()
		{
			this.leadConversionCollection = new supplierInsightData();
		},

		/*
		* This is the entry point, get the initial data for the calculator from the backend
		*/
		getData: function()
		{

			this.preRender();
			/* return; */
			this.leadConversionCollection.reset();
			var thisView = this;
			
			this.leadConversionCollection.fetch({
			type: this.ajaxType,
			data: $.param({
				tnid: this.parent.supplier.tnid,
				type: 'lead-conversion',
				startDate: this.parent.dateConvertToShortMonthFormat(this.parent.dateFrom),
				endDate: this.parent.dateConvertToShortMonthFormat(this.parent.dateTo),
				skipTokenCheck: 1,
				}),
				complete: function(){
					thisView.render();
				},
				error: function( model, response, options ) {
					if (response.responseText) {
						var errorObj = $.parseJSON(response.responseText);
					}
				}
			});	
		},

		/*
		* After render, all fields are set here, and click functions are assigned
		*/
	   afterRender: function()
	   {
			/* Copy default values */
			var thisView = this;

            $('#rfqValue').html($('#rfqValueSrc').html());
            $('#quoteValue').html($('#quoteValueSrc').html());
            $('#winValue').html($('#winValueSrc').html());
            

			/* Spin edit decrease button  */
			$('.spin-down').click(function(){
				var inputElement = $(this).next();
				if (!$(inputElement).data('disabled') === true) {
					var currValue = parseFloat($(inputElement).val());
					//if (currValue>0)
					currValue--;
					var currText = (currValue > 0) ? '+'+currValue : currValue;
					$(inputElement).val(currText);
					thisView.inputChanged( inputElement );
				}
			});

			/* Spin edit increase button  */
			$('.spin-up').click(function(){
				var inputElement = $(this).next().next();
				var currValue = parseFloat($(inputElement).val());
				if (currValue < 100 || inputElement.attr('name') == 'rfqValue') {
					currValue++;
				}
				var currText = (currValue > 0) ? '+'+currValue : currValue;
				$(inputElement).val(currText);
				thisView.inputChanged( inputElement );
			});

			/* Restrint value to numeric input only */
			$('.numOnly').keypress(function(e) {
	            var verified = (e.which == 8 || e.which == undefined || e.which == 0) ? null : String.fromCharCode(e.which).match(/[^0-9+-]/);
	            if (verified) {e.preventDefault();
            }

            /* Calculate values */
	        $('.numOnly').keyup(function(){
	            	thisView.inputChanged($(this));
	          });
    		});

	   },

	   /*
	   	Pre prender calculator view  with spinners
	   */
	   	preRender: function()
	   	{
			var html = this.calculatorTemplate({hasSpinner: true});
			$('#sir-calculator').html(html);
	   	},

		render: function() {
			var thisView = this;
			if (thisView.leadConversionCollection.models[0]) {
			if (parseInt(thisView.leadConversionCollection.models[0].attributes['po-value']) === 0) {
				$('#poValueContent table.poValue').hide();
			}

			var html = thisView.calculatorTemplate(thisView.leadConversionCollection.models[0]);
			$('#sir-calculator').html(html);

				thisView.afterRender();
				/* The next calls are to force the values to be recalculated */
				thisView.inputChanged($('input[name="rfqValue"]'));
				thisView.inputChanged($('input[name="quoteValue"]'));
				thisView.inputChanged($('input[name="winValue"]'));
				thisView.calcValues();
			}
		},

		/*
		* In case of input fields are changed, validate and call recalculate values
		*/

		inputChanged: function( element )
		{
			var elementName = '#'+element.attr('name');
			if ('#rfqValue' == elementName) {
				if (element.val().length >8) {
					element.val(element.val().substr(0,8));
				}
			}

			var multiplierValue = parseFloat(element.val());
			
			var srcValue  = parseFloat($(elementName+'Src').html().replace(/,/g, ""));
	
			if ('#rfqValue' == elementName) {
				var newValue = Math.round( srcValue * ((100+multiplierValue)/100));
			} else {
				var newValue = Math.round((srcValue + multiplierValue) *10)/10;
			}
			var downElement = element.parent().find(':first').next().find(':first');
			if (newValue<0)  newValue = 0;
			if (newValue === 0) {
				$(element).data('disabled', true);
				$(downElement).css('border-color', '#D9D9D9 transparent transparent transparent');
			} else {
				$(element).data('disabled', false);
				$(downElement).css('border-color', '#084886 transparent transparent transparent');
			}
			if (!isNaN(newValue)) {
				if ('#rfqValue' == elementName) {
					$(elementName).html(this.formatNumber(newValue));
				} else {
					$(elementName).html(newValue+"%");
				}
			} else {
				$(elementName).html('');
			}
			this.calcValues();
		},

		/*
		* Recalculate values
		*/

		calcValues: function()
		{
			/* Calculate total values, and increase */
			var posValueSrc = parseInt(this.leadConversionCollection.models[0].attributes['po-count']);
			var poValue = parseInt(this.leadConversionCollection.models[0].attributes['po-value']);
			var rfqs = Math.round(parseInt(this.leadConversionCollection.models[0].attributes['rfq-count']));
			var qotCount = Math.round(parseInt(this.leadConversionCollection.models[0].attributes['qot-count'])); 
			var quoteRate = (rfqs != 0) ? Math.round((qotCount / rfqs)*100) : 0;

			if ( parseInt($('#quoteValue').html()) >= 100) 
			{
				var qotVal = 100 - quoteRate;
				qotVal = qotVal.toString();
				
				$('input[name="quoteValue"]').val('+' + qotVal);
				$('#quoteValue').html('100%');
			}

			var winRate = (qotCount != 0) ? Math.round((posValueSrc / qotCount)*100) : 0;

			if ( parseInt($('#winValue').html()) >= 100) 
			{
				var winVal = 100 - winRate;
				winVal = winVal.toString();
				
				$('input[name="winValue"]').val('+' + winVal);
				$('#winValue').html('100%');
			}

			var rfqEditValue = parseInt($('input[name="rfqValue"]').val().replace(/\+/, ""));
			var qotEditValue = parseInt($('input[name="quoteValue"]').val().replace(/\+/, ""));
			var winEditValue = parseInt($('input[name="winValue"]').val().replace(/\+/, ""));

			if (qotEditValue > 100) {
				qotEditValue = 100;
				$('input[name="quoteValue"]').val('+100');
			}
			
			if (winEditValue > 100) {
				winEditValue = 100;
				$('input[name="winValue"]').val('+100');
			}

			var avgPoValue = (posValueSrc == 0) ? 0 : poValue/posValueSrc;
			var poOrdersTotal = Math.round(((rfqs*(100+rfqEditValue))/100) * ((quoteRate+qotEditValue)/100) * ((winRate+winEditValue)/100));
			if (poOrdersTotal < 0) poOrdersTotal = 0;
			var increaseBy = poOrdersTotal - posValueSrc;
			if (increaseBy < 0) increaseBy = 0;
			var increase =  Math.round( poOrdersTotal / posValueSrc  * 100 ) - 100;
			var increaseText = (increaseBy > 0) ? "+"+increaseBy : increaseBy;

			/* Calculating total values */

			if (!isNaN(poOrdersTotal)) {
				var avgPoValueTxt = $('#totalAvgPoValue').html();
				var newPoValue = Math.round(avgPoValue) * increaseBy;
				$('#poValue').html(this.formatNumber(poOrdersTotal));
				$('#increaseBy').html(increaseBy);
				$('#totalIncreasedValue').html('$ '+this.formatNumber(newPoValue));

				if(newPoValue > 5000 && this.supplier.premiumListing != 1){
					$('#sir-calculator').show();
				}
			}

			this.resizeFontForFit($('#rfqValue'), 25, 120);
			this.resizeFontForFit($('#poValue'), 25, 0);
		},
		
		/*
		* Format number helper for the view
		*/
		formatNumber: function( str )
		{
			str = str.toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,");
			return str;
		},

		resizeFontForFit :function( element, maxFontSize, maxWidth  )
		{
			var fontSize = maxFontSize;
 	 	    $(element).css('font-size', fontSize+'px');
			
			if (maxWidth != 0) {
				var parentWidht = maxWidth;
			} else {
				var parentWidht = $(element).parent().width()-6;
			}	
			
			while ($(element).width() > parentWidht && fontSize>5) {
	 	 	    	fontSize -= 1;
	 	 	    	$(element).css('font-size', fontSize+'px');
 	 	    }



		}
		
	});

	return analysisView;
});
