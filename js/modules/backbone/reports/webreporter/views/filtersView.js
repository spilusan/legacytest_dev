define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/shared/hbh/general',
	'libs/jquery.uniform',
	'libs/jquery-ui-1.10.3/datepicker',
	'libs/jquery.validity.min',
	'libs/jquery.validity.custom.output.webreporter',
	'libs/jquery.dateFormat',
	'libs/jquery.tools.overlay.modified',
	'text!templates/reports/webreporter/tpl/filters.html',
	'text!templates/reports/webreporter/tpl/asofdate.html',
	'text!templates/reports/webreporter/tpl/pocutoff.html',
	'text!templates/reports/webreporter/tpl/podecision.html',
	'text!templates/reports/webreporter/tpl/rfqcutoff.html'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	genHbh,
	Uniform,
	Datepicker,
	validity,
	validityCustom,
	dateFormat,
	Modal,
	filtersTpl,
	asofTpl,
	pocutTpl,
	podecTpl,
	rfqcutTpl
){
	var filtersView = Backbone.View.extend({
		el: $('form.filtersForm'),

		events: {

		},
		template: Handlebars.compile(filtersTpl),
		asofTemplate: Handlebars.compile(asofTpl),
		pocutTemplate: Handlebars.compile(pocutTpl),
		podecTemplate: Handlebars.compile(podecTpl),
		rfqcutTemplate: Handlebars.compile(rfqcutTpl),

		data: require('reports/data'),
		prevData: {},
		csv: 0,

		initialize: function(){
			var thisView = this;
			$(document).ready(function(){
				/* Element had to be reassigned after page loaded, as in case of slow network, and IE it was not present at the loading of this file*/
				thisView.$el = $('form.filtersForm');

				thisView.render();
				$('#waiting').hide();
			});
		},

		render: function(){
			var today = new Date();
			var startDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
			var endDate = new Date(startDate.getFullYear(), startDate.getMonth() + 1, 0);
			startDate = $.format.date(startDate, "dd/MM/yyyy");
			endDate = $.format.date(endDate, "dd/MM/yyyy");
			today = $.format.date(today, "dd/MM/yyyy");

			this.data.today = today;
			this.data.startDate = startDate;
			this.data.endDate = endDate;

			/* console.log(this.data); */
			var html = this.template(this.data);

			this.$el.append(html);

			$('select').uniform();
			$('input[type="checkbox"]').uniform();
			$('form input.date').datepicker({ 
				autoSize: false,
				dateFormat: 'dd/mm/yy'
			});

			//fix height of body container due to absolute pos of content container
	    	var height = 0;
	    	if($('#content').height() < $('#sidebar').height()){
	    		height = $('#sidebar').height();
	    	}
	    	else {
	    		height = $('#content').height() + 25;
	    	}

	    	$('#body').height(height);

	    	var thisView = this;

            /* Trigger events here after the filter section was rendered */
            $('input[name="runReport"]').click(
                function (e) {
                    thisView.runReport(e);
                }
            );

            $('button[name="csvExport"]').click(
                function (e) {
                    thisView.runReportCsv(e);
                }
            );

            $('a.reverForm').click(
                function (e) {
                    thisView.resetForm(e);
                }
            );

            $('select#reportType').change(
                function (e) {
                    thisView.reportTypeChanged(e);
                }
            );

            $('input#showAdv').change(
                function (e) {
                    thisView.toggleAdvanced(e);
                }
            );

            $('select#dateRange').change(
                function (e) {
                    thisView.changeDateRange(e);
                }
            );

            $('select#currency').change(
                function (e) {
                    thisView.displayChanged(e);
                }
            );

            $('select#companyName').change(
                function (e) {
                    thisView.displayChanged(e);
                }
            );

            $(' select#contact').change(
                function (e) {
                    thisView.displayChanged(e);
                }
            );

            $('select#supplier').change(
                function (e) {
                    thisView.displayChanged(e);
                }
            );

            $('select#vessel').change(
                function (e) {
                    thisView.displayChanged(e);
                }
            );


            $('input.date').change(
                function (e) {
                    thisView.displayChanged(e);
                }
            );


            $(' select#rows').change(
                function (e) {
                    thisView.displayChanged(e);
                }
            );

            $('select#numberFormat').change(
                function (e) {
                    thisView.displayChanged(e);
                }
            );

            $('input[name="fromDate"]').keyup(
                function (e) {
                    thisView.selectCustomDts(e);
                }
            );

            $('input[name="toDate"]').keyup(
                function (e) {
                    thisView.selectCustomDts(e);
                }
            );

			$('body').delegate('a.help', 'click', function(e){
				e.preventDefault();
				thisView.showHelp(e);
		    });

		},

		reportTypeChanged: function(e){
			if($(e.target).val() === "GET-ALL-RFQ:RFQSUBDT" || $(e.target).val() === "GET-SPB-ANALYSIS:SPBNAME") {
				$('#rptSearchFilter').addClass('hidden');
				$('select#currency option').removeAttr('selected');
				$('select#currency').prepend('<option value="NA" selected="selected">Not Applicable</option>');
				$('select#currency').attr('disabled', 'disabled');
				$.uniform.update();
			}
			else {
				$('#rptSearchFilter').addClass('hidden');
				$('select#currency option[value="NA"]').remove();
				$('select#currency').removeAttr('disabled');
				$('select#currency option[value="USD"]').attr('selected', 'selected');
				$.uniform.update();
			}
			if($(e.target).val() === "GET-SPB-ANALYSIS:SPBNAME"){
				$('#rptSearchFilter').addClass('hidden');
				$('#spbAnalysis1').removeClass('hidden');
				$('#spbAnalysis2').removeClass('hidden');

				$('form.new .filters').addClass('analysis');
				$('form.new .actions').addClass('analysis');

				$('')
				$('select#contact option').attr('selected', false);
				$('select#contact option[value="NA"]').attr('selected', 'selected');
				$('select#contact').attr('disabled', 'disabled');
				$('select#vessel option').attr('selected', false);
				$('select#vessel option[value="0"]').attr('selected', 'selected');
				$('select#vessel').attr('disabled', 'disabled');

				$.uniform.update();
				var height = 0;
		    	if($('#content').height() < $('#sidebar').height()){
		    		height = $('#sidebar').height();
		    	}
		    	else {
		    		height = $('#content').height() + 25;
		    	}

		    	$('#body').height(height);
			}
			else {
				$('#rptSearchFilter').addClass('hidden');
				$('#spbAnalysis1').addClass('hidden');
				$('#spbAnalysis2').addClass('hidden');

				$('form.new .filters').removeClass('analysis');
				$('form.new .actions').removeClass('analysis');

				$('form.new .filters').removeClass('adv');
				$('form.new .actions').removeClass('adv');
				$('.advancedOpts').addClass('hidden');
				$('input#showAdv').attr('checked', false);

				$('select#contact').removeAttr('disabled');
				$('select#vessel').removeAttr('disabled');

				$.uniform.update();
				var height = 0;
		    	if($('#content').height() < $('#sidebar').height()){
		    		height = $('#sidebar').height();
		    	}
		    	else {
		    		height = $('#content').height() + 25;
		    	}

		    	$('#body').height(height);
			}
			if($(e.target).val() === "GET-ALL-ORD-MMS:ORDSUBDT") {
				//console.log('aaaaaa');
				$('#rptSubFilters').addClass('hidden');
				$('#rptSearchFilter').removeClass('hidden');
                
                                var optHtml = '<option value="1W">Previous Week</option>';
                                $('select#dateRange').append(optHtml);
                                $('#dateRange').val('1W');             

                                var endDate = new Date();
				// var startDate = endDate.getDate() - 7;
                                var startDate = new Date(endDate.getFullYear(), endDate.getMonth(), endDate.getDate() - 7);
				startDate = $.format.date(startDate, "dd/MM/yyyy");
				endDate = $.format.date(endDate, "dd/MM/yyyy");
				$('input[name="fromDate"]').val(startDate);
				$('input[name="toDate"]').val(endDate);
				$('input[name="fromDate"]').blur();

				$.uniform.update();
			} else {
                                if($('#dateRange option:selected').val() === "1W") {
                                    $('#dateRange').val('1');
                                    $('select#dateRange option[value="1W"]').remove();  

                                    var today = new Date();
				    var startDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
				    var endDate = new Date(startDate.getFullYear(), startDate.getMonth() + 1, 0);
				    startDate = $.format.date(startDate, "dd/MM/yyyy");
				    endDate = $.format.date(endDate, "dd/MM/yyyy");
				    $('input[name="fromDate"]').val(startDate);
				    $('input[name="toDate"]').val(endDate);
				    $('input[name="fromDate"]').blur();
                                } else {
                                    $('select#dateRange option[value="1W"]').remove();
                                }
				$.uniform.update();
			}
		},

		toggleAdvanced: function(){
			$('.advancedOpts').toggleClass('hidden');
			$('form.new .filters').toggleClass('adv');
			$('form.new .actions').toggleClass('adv');
			
			var el = $('input[name="rfqCutoff"]');
		    this.toggleAttr(el, 'disabled');
		    el = $('input[name="ordCutoff"]');
		    this.toggleAttr(el, 'disabled');
		    el = $('input[name="qotCutoff"]');
		    this.toggleAttr(el, 'disabled');

			var height = 0;
	    	if($('#content').height() < $('#sidebar').height()){
	    		height = $('#sidebar').height();
	    	}
	    	else {
	    		height = $('#content').height() + 25;
	    	}

	    	$('#body').height(height);
		},

		toggleAttr: function(el, attr){
			attrib = ":" + attr;
			if(el.is(attrib)){
				el.attr(attr, false);
			}
		    else {
		    	el.attr(attr, attr);
		    }
		},

		changeDateRange: function(e){
			var today = new Date();
			var val = $(e.target).val();
			if(val === "CSTMDTS"){
				$('input[name="fromDate"]').val('');
				$('input[name="toDate"]').val('');
				$('input[name="fromDate"]').focus();
			}
			else if(val === "1W"){
				// var startDate = today.getDate() - 7;
				var endDate = today;
                                var startDate = new Date(endDate.getFullYear(), endDate.getMonth(), endDate.getDate() - 7);
				startDate = $.format.date(startDate, "dd/MM/yyyy");
				endDate = $.format.date(endDate, "dd/MM/yyyy");
				$('input[name="fromDate"]').val(startDate);
				$('input[name="toDate"]').val(endDate);
				$('input[name="fromDate"]').blur();
			}			
			else if(val === "1Y"){
				var startDate = new Date(today.getFullYear() - 1, 0, 1);
				var endDate = new Date(startDate.getFullYear(), 12, 0);
				startDate = $.format.date(startDate, "dd/MM/yyyy");
				endDate = $.format.date(endDate, "dd/MM/yyyy");
				$('input[name="fromDate"]').val(startDate);
				$('input[name="toDate"]').val(endDate);
				$('input[name="fromDate"]').blur();
			}
			else {
				val = parseInt(val);
				var startDate = new Date(today.getFullYear(), today.getMonth() - val, 1);
				var endDate = new Date(startDate.getFullYear(), startDate.getMonth() + val, 0);
				startDate = $.format.date(startDate, "dd/MM/yyyy");
				endDate = $.format.date(endDate, "dd/MM/yyyy");
				$('input[name="fromDate"]').val(startDate);
				$('input[name="toDate"]').val(endDate);
				$('input[name="fromDate"]').blur();

			}
		},

		runReportCsv: function(e){
			e.preventDefault();
			this.parent.page = 1;
			this.parent.runClicked = true;
			if(this.validateForm()){
				this.csv = 1;
				this.setData();
			}
		},

		runReport: function(e){
			e.preventDefault();
			this.parent.page = 1;
			this.parent.runClicked = true;
			if(this.validateForm()){
				$('#uniform-reportType').hide();
				$('label[for="reportType"]').hide();
				$('#infoBox').hide();
				if($('#reportType').val() !== "GET-ALL-ORD-MMS:ORDSUBDT"){
					$('#rptSubFilters').show();
				}
				$('form.new .filters').addClass('sub');
				$('form.new .actions').addClass('sub');
				this.setData();
			}
		},

		setData: function(){
			var data = {},
			rptCodeOrd = $('select#reportType').val().split(":");

			data = { 
				rptCode     : rptCodeOrd[0],
				rptOrd      : rptCodeOrd[1],
				fromDateRaw : $('input[name="fromDate"]').val(),
				toDateRaw : $('input[name="toDate"]').val(),
				fromDate    : this.formatDate('input[name="fromDate"]'),
				toDate      : this.formatDate('input[name="toDate"]'),
				currency    : $('select#currency').val(),
				session     : this.data.user.usrsid,
				rows        : $('select#rows').val(),
				vesselId    : $('select#vessel').val(),
				vesselName  : $('select#vessel option:selected').text(),
				bybTnid     : $('select#companyName').val(),
				bybName     : $('select#companyName option:selected').text(),
				spbTnid     : $('select#supplier').val(),
				spbName     : $('select#supplier option:selected').text(),
				username    : this.data.user.usrname,
				bybCntc     : $('select#contact').val(),
				bybCntcName : $('select#contact option:selected').text(),
				rfqCutoff   : $('input[name="rfqCutoff"]').val(),
				ordCutoff   : $('input[name="ordCutoff"]').val(),
				qotCutoff   : $('input[name="qotCutoff"]').val(),
				rptAsOfDate : this.formatDate('input[name="rptAsOfDate"]'),
				dateRange   : $('select[name="dateRange"]').val(),
				numFormat	: $('select[name="numberFormat"]').val(),
				rptSrch		: $('input#rptSrch').val()
			}

			if (data.bybTnid === '__ALL__') {
				data.bybTnid = [];
				$('#companyName option').each(function(){
					if ($(this).val() !== '__ALL__') {
						data.bybTnid.push($(this).val());	
					}
				}) 
			}
			this.prevData = data;

			$.extend(this.parent.formData, data);

			if(this.csv == 0){
				this.parent.getData(1);
			}
			else {
				this.parent.getCsvData(1);
				this.csv = 0;
			}
		},

		formatDate: function(date){
			var splitDate = $(date).val().split('/'),
				formatDate = splitDate[1] + '/' + splitDate[0] + '/' + splitDate[2],
				
				date = new Date(formatDate);
				
				formatDate = $.format.date(date, "dd-MMM-yyyy");
				
				splitDate = formatDate.split('-');
				splitDate[1] = splitDate[1].toUpperCase();
				formatDate = '';
				formatDate = splitDate[0] + '-' + splitDate[1] + '-' + splitDate[2];

				return formatDate;
		},

		validateForm: function(){
			$('div.filters').removeAttr('style');
            $('div.actions').removeAttr('style');
			$.extend($.validity.patterns, {
	            date:/^(((0[1-9]|[12]\d|3[01])\/(0[13578]|1[02])\/((19|[2-9]\d)\d{2}))|((0[1-9]|[12]\d|30)\/(0[13456789]|1[012])\/((19|[2-9]\d)\d{2}))|((0[1-9]|1\d|2[0-8])\/02\/((19|[2-9]\d)\d{2}))|(29\/02\/((1[6-9]|[2-9]\d)(0[48]|[2468][048]|[13579][26])|((16|[2468][048]|[3579][26])00))))$/ 
	        });

	        $.validity.setup({ outputMode:"custom" });

	    	$.validity.start();

	    	$('input[name="toDate"]').match('date','Please enter a valid end date');
	    	$('input[name="fromDate"]').match('date','Please enter a valid start date');
	    	if($('select#reportType').val() === "GET-SPB-ANALYSIS:SPBNAME"){
	    		$('input[name="rptAsOfDate"]').match('date','Please enter a valid date');
	    	}

	    	var result = $.validity.end();

	    	var splitFrom = $('input[name="fromDate"]').val().split('/');
	    	var splitTo = $('input[name="toDate"]').val().split('/');
	    	var formatFrom = splitFrom[1] + '/' + splitFrom[0] + '/' + splitFrom[2];
	    	var formatTo = splitTo[1] + '/' + splitTo[0] + '/' + splitTo[2];
	    	var dateFrom = new Date(formatFrom);
	    	var dateTo = new Date(formatTo);

	    	if($('input[name="fromDate"]').val() == "") {
	    		$('input[name="fromDate"]').after("<div class='error'>Please enter a start date.</div><div class='clear err'></div>");
	    		result.valid = false;
	    		if($('select#reportType').val() === "GET-SPB-ANALYSIS:SPBNAME" || $('div.filters').hasClass('sub')){
                    //do nothing
                }
                else {
                    $('div.filters').height($('div.filters').height() + 27);
                    $('div.actions').height($('div.actions').height() + 27);
                }
	    	}
	    	if($('input[name="toDate"]').val() == "") {
				$('input[name="toDate"]').after("<div class='error'>Please enter an end date.</div><div class='clear err'></div>");
	    		result.valid = false;
	    		if($('select#reportType').val() === "GET-SPB-ANALYSIS:SPBNAME" || $('div.filters').hasClass('sub')){
                    //do nothing
                }
                else {
                    $('div.filters').height($('div.filters').height() + 27);
                    $('div.actions').height($('div.actions').height() + 27);
                }
	    	}
	    	if(dateTo < dateFrom){
	    		$('input[name="fromDate"]').after("<div class='error'>Start date is after end date.</div><div class='clear err'></div>");
	    		result.valid = false;
	    		if($('select#reportType').val() === "GET-SPB-ANALYSIS:SPBNAME" || $('div.filters').hasClass('sub')){
                    //do nothing
                }
                else {
                    $('div.filters').height($('div.filters').height() + 27);
                    $('div.actions').height($('div.actions').height() + 27);
                }
	    	}

	    	if($('select#reportType').val() === "GET-SPB-ANALYSIS:SPBNAME"){
	    		if($('input[name="rptAsOfDate"]').val() == "") {
		    		$('input[name="rptAsOfDate"]').next('.help').after("<div class='error'>Please enter a date.</div><div class='clear err'></div>");
		    		result.valid = false;
		    		$('div.filters').height($('div.filters').height() + 27);
               		$('div.actions').height($('div.actions').height() + 27);
		    	}
	    	}

	    	return result.valid;
		},

		showHelp: function(e){
			var html,
			thisView = this;
			$('#modal .modalBody').html('');

			switch($(e.target).parent().attr('href')) {
				case "asofTemplate":
					html = thisView.asofTemplate();
					break;
				case "pocutTemplate":
					html = thisView.pocutTemplate();
					break;
				case "podecTemplate":
					html = thisView.podecTemplate();
					break;
				case "rfqcutTemplate":
					html = thisView.rfqcutTemplate();
					break;
			}

			$('#modal .modalBody').html(html);
			this.openDialog();
		},

		displayChanged: function(){
			if(this.parent.runClicked){
				$('.changedMsg').show();
			}
			this.fixAmp();
		},

		fixAmp: function(){
			var text = $('#uniform-companyName span').text();
			text = text.replace(/&amp;/g, '&');
			$('#uniform-companyName span').text(text);
		},

		resetForm: function(){
			$('select#companyName option').removeAttr('selected');
			$('select#companyName option[value="'+ this.prevData.bybTnid +'"]').attr("selected", "selected");
			$('select#currency option').removeAttr('selected');
			$('select#currency option[value="'+ this.prevData.currency +'"]').attr("selected", "selected");
			$('select#contact option').removeAttr('selected');
			$('select#contact option[value="'+ this.prevData.bybCntc +'"]').attr("selected", "selected");
			$('select#supplier option').removeAttr('selected');
			$('select#supplier option[value="'+ this.prevData.spbTnid +'"]').attr("selected", "selected");
			$('select#vessel option').removeAttr('selected');
			$('select#vessel option[value="'+ this.prevData.vesselId +'"]').attr("selected", "selected");
			$('select#dateRange option').removeAttr('selected');
			$('select#dateRange option[value="'+ this.prevData.dateRange +'"]').attr("selected", "selected");
			$('input[name="fromDate"]').val(this.prevData.fromDateRaw);
			$('input[name="toDate"]').val(this.prevData.toDateRaw);
			$('select#rows option').removeAttr('selected');
			$('select#rows option[value="'+ this.prevData.rows +'"]').attr("selected", "selected");
			$('select#numberFormat option').removeAttr('selected');
			$('select#numberFormat option[value="'+ this.prevData.numFormat +'"]').attr("selected", "selected");
			$.uniform.update();
			this.fixAmp();
			$('.changedMsg').hide();
		},

		selectCustomDts: function(e){
			var code = e.keyCode || e.which;
			if(code != 9) {
				$('#dateRange').val('CSTMDTS');
				$.uniform.update();
			}
		},

		openDialog: function() { 
            $("#modal").overlay({
                mask: 'black',
                left: 'center',
                fixed: true,

                onBeforeLoad: function() {
                    var windowWidth = $(window).width();
                    var modalWidth = $('#modal').width();
                    var posLeft = windowWidth/2 - modalWidth/2;

                    $('#modal').css('left', posLeft);
                },

                onLoad: function() {
                    $(window).resize(function(){
                        var windowWidth = $(window).width();
                        var modalWidth = $('#modal').width();
                        var posLeft = windowWidth/2 - modalWidth/2;

                        $('#modal').css('left', posLeft);
                    });
                }
            });

            $('#modal').overlay().load();
        }

	});

	return filtersView;
});