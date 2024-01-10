define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
    'backbone/shared/hbh/general',
	'libs/jquery.validity.custom.output',
	'libs/jquery.uniform',
	'jqueryui/datepicker',
	'libs/jquery.tools.overlay.modified',
	'libs/xregexp',
	'libs/jquery.expandable',
	'../collections/imo',
	'../collections/ports',
	'../views/sectionListView',
	'../views/supplierListView',
	'../views/fileUploadView',
	'text!templates/rfq/tpl/standard.html',
	'text!templates/rfq/tpl/ports.html',
	'text!templates/rfq/tpl/captcha.html'
], function(
	$,
	_, 
	Backbone, 
	Hb,
	HbhGen,
	ValidityCustom,
	Uniform, 
	Datepicker,
	Modal,
	Xregexp,
	Expandable,
	imoCollection,
	portsCollection,
	sectionList,
	supplierList,
	fileUpload,
	standardTpl, 
	portsTpl,
	captchaTpl
){
	var standardRfqView = Backbone.View.extend({
		
		el: $('body'),

		template: Handlebars.compile(standardTpl),

		portsTemplate: Handlebars.compile(portsTpl),

		captchaTemplate: Handlebars.compile(captchaTpl),

		rfqData: require('rfq/rfqData'),

		initialize: function () {
			XRegExp.install('natives');
			this.imoCollection = new imoCollection();

			this.portsCollection = new portsCollection();
			
		    this.render(this.renderRegions);
		},

		render: function(callback) {
			var html = this.template(this.rfqData);

			if($('#body').length === 0){
				var that = this;
				setTimeout(function(){
					that.render(that.renderRegions);
				}, 1000);
			}
			else {
				$('#body').html(html);
				callback(this);
			}
		},

		renderRegions: function(that){
			sectionList.parent = that;
			sectionList.render();

			supplierList.render();

			$('textarea[name="lBuyerComments"]').expandable({
				within: 1
			});

			fileUpload.render();

			if( that.rfqData.captcha ){
				var captchaHtml = that.captchaTemplate(that.rfqData.captcha);
				$('form.rfq input[type="submit"]').before(captchaHtml);
			}
			$('form.rfq input[type="text"]').keypress(function(e){
				if (e.which == 13) {
		            return false;
		   		}
			});


			var thisView = that;

			$('form.rfq div.details select').uniform();

			$('input.date').datepicker({ 
				autoSize: false,
				minDate: +0
			});

			$('a[rel].cHelp').overlay({
		        mask: 'black',
		        left: 'center',
		        fixed: 'true',
		 
		        onBeforeLoad: function() {
		 
		            var wrap = this.getOverlay().find('.modalBody');
		 
		            wrap.load(this.getTrigger().attr('href'));

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

		    thisView.setTabIndex();

			$('body').delegate('input[type="text"]','blur', function(e){
				thisView.trim(e);
			});

			$('body').delegate('textarea', 'blur', function(e){
				thisView.trim(e);
			});

			$('body').delegate('#showDetails', 'click', function(e){
				thisView.showDelDet(e);
			});

			$('body').delegate('#hideDetails', 'click', function(e){
				thisView.hideDelDet(e);
			});

			$('body').delegate('#showReqDetails', 'click', function(e){
				thisView.showReqDet(e);
			});

			$('body').delegate('#hideReqDetails', 'click', function(e){
				thisView.hideReqDet(e);
			});

			$('body').delegate('input[type="submit"]', 'click', function(e){
				thisView.submitForm(e);
			});

			$('body').delegate('input.date', 'focus', function(e){
				thisView.resetDate(e);
			});

			$('body').delegate('input.date', 'blur', function(e){
				thisView.defaultDate(e);
			});

			$('body').delegate('input[name="rRfqSubject"]', 'keyup', function(){
				thisView.checkrRfqSubject();
			});

			$('body').delegate('input[name="rRfqSubject"]', 'paste', function(){
				thisView.checkrRfqSubject();
			});

			$('body').delegate('input[name="rRfqReference"]', 'keyup', function(){
				thisView.checkrRfqReference();
			});

			$('body').delegate('input[name="rRfqReference"]', 'paste', function(){
				thisView.checkrRfqReference();
			});

			$('body').delegate('input[name="bPhone"]', 'keyup', function(){
				thisView.checkbPhone();
			});

			$('body').delegate('input[name="bPhone"]', 'paste', function(){
				thisView.checkbPhone();
			});

			$('body').delegate('input[name="vVesselName"]', 'keyup', function(){
				thisView.checkvVesselName();
			});

			$('body').delegate('input[name="vVesselName"]', 'paste', function(){
				thisView.checkvVesselName();
			});

			$('body').delegate('input[name="dDeliveryTo"]', 'keyup', function(e){
				thisView.checkSs(e);
			});

			$('body').delegate('input[name="dDeliveryTo"]', 'paste', function(e){
				thisView.checkSs(e);
			});

			$('body').delegate('input[name="dAddress1"]', 'keyup', function(e){
				thisView.checkAddress(e);
			});

			$('body').delegate('input[name="dAddress1"]', 'paste', function(e){
				thisView.checkAddress(e);
			});

			$('body').delegate('input[name="dAddress2"]', 'keyup', function(e){
				thisView.checkAddress(e);
			});

			$('body').delegate('input[name="dAddress2"]', 'paste', function(e){
				thisView.checkAddress(e);
			});

			$('body').delegate('input[name="dCity"]', 'keyup', function(e){
				thisView.checkSs(e);
			});

			$('body').delegate('input[name="dCity"]', 'paste', function(e){
				thisView.checkSs(e);
			});

			$('body').delegate('input[name="dProvince"]', 'keyup', function(e){
				thisView.checkSs(e);
			});

			$('body').delegate('input[name="dProvince"]', 'paste', function(e){
				thisView.checkSs(e);
			});

			$('body').delegate('input[name="dPostcode"]', 'keyup', function(e){
				thisView.checkSs(e);
			});

			$('body').delegate('input[name="dPostcode"]', 'paste', function(e){
				thisView.checkSs(e);
			});

			$('body').delegate('textarea[name="dPackagingInstructions"]', 'keyup', function(e){
				thisView.checkSL(e);
			});

			$('body').delegate('textarea[name="dPackagingInstructions"]', 'paste', function(e){
				thisView.checkSL(e);
			});

			$('body').delegate('input.pno', 'keyup', function(e){
				thisView.checkS(e);
			});

			$('body').delegate('input.pno', 'paste', function(e){
				thisView.checkS(e);
			});

			$('body').delegate('input.idesc', 'keyup', function(e){
				thisView.checkS(e);
			});

			$('body').delegate('input.idesc', 'paste', function(e){
				thisView.checkS(e);
			});

			$('body').delegate('input.icomments', 'keyup', function(e){
				thisView.checkS(e);
			});

			$('body').delegate('input.icomments', 'paste', function(e){
				thisView.checkS(e);
			});

			$('body').delegate('input.sSerial', 'keyup', function(e){
				thisView.checkS(e);
			});

			$('body').delegate('input.sSerial', 'paste', function(e){
				thisView.checkS(e);
			});

			$('body').delegate('input.sType', 'keyup', function(e){
				thisView.checkS(e);
			});

			$('body').delegate('input.sType', 'paste', function(e){
				thisView.checkS(e);
			});

			$('body').delegate('input.sDraw', 'keyup', function(e){
				thisView.checkS(e);
			});

			$('body').delegate('input.sDraw', 'paste', function(e){
				thisView.checkS(e);
			});

			$('body').delegate('input.sDesc', 'keyup', function(e){
				thisView.checkS(e);
			});

			$('body').delegate('input.sDesc', 'paste', function(e){
				thisView.checkS(e);
			});

			$('body').delegate('input.sMan', 'keyup', function(e){
				thisView.checkS(e);
			});

			$('body').delegate('input.sMan', 'paste', function(e){
				thisView.checkS(e);
			});

			$('body').delegate('input.sModel', 'keyup', function(e){
				thisView.checkS(e);
			});

			$('body').delegate('input.sModel', 'paste', function(e){
				thisView.checkS(e);
			});

			$('body').delegate('input.sFor', 'keyup', function(e){
				thisView.checkS(e);
			});

			$('body').delegate('input.sFor', 'paste', function(e){
				thisView.checkS(e);
			});

			$('body').delegate('input.sRate', 'keyup', function(e){
				thisView.checkS(e);
			});

			$('body').delegate('input.sRate', 'paste', function(e){
				thisView.checkS(e);
			});

			$('body').delegate('textarea[name="lBuyerComments"]', 'keyup', function(e){
				thisView.checkSL(e);
			});

			$('body').delegate('textarea[name="lBuyerComments"]', 'paste', function(e){
				thisView.checkSL(e);
			});

			$('body').delegate('input.find', 'click', function(e){
				thisView.getVessel(e);
			});

			$('body').delegate('select[name="dCountry"]', 'change', function(e){
				thisView.getPorts(e);
			});

			$('body').delegate('.new-captcha', 'click', function(e){
				thisView.changeCaptcha(e);
			});

			$('body').delegate('.freeText', 'click', function(){
				thisView.hideLineItems();
			});

			$('body').delegate('.lineItms', 'click', function(){
				thisView.showLineItems();
			});
		},

		resetDate: function(e) {
			if ($(e.target).val() === "mm/dd/yyyy") {
				$(e.target).val('');
			}
		},

		defaultDate: function(e) {
			if($(e.target).val() === "") {
				$(e.target).val('mm/dd/yyyy');
			}
		},

		showDelDet: function(e) {
			e.preventDefault();
			
			$('fieldset.delivery').addClass('open');

			var errorNum = $('fieldset.delivery div.error').length;
			var boxheight = 465 + errorNum * 29;

			$('#showDetails').hide();
			$('#delDets').slideDown(300);
			$('fieldset.delivery').animate({height: boxheight}, 300, function(){
				$('#hideDetails').show();
			});
			this.setTabIndex();
		},

		hideDelDet: function(e) {
			e.preventDefault();
			$('fieldset.delivery').removeClass('open');
			
			var errorNum = $('fieldset.reqisition div.error').length;
			var boxheight = 86 + errorNum * 29;

			$('#hideDetails').hide();
			$('#delDets').slideUp(300);
			$('fieldset.delivery').animate({height: boxheight+"px"}, 300, function(){
				$('#showDetails').show();	
			});		

			this.setTabIndex();

			$.uniform.update('#delDets select');
		},

		showReqDet: function(e) {
			e.preventDefault();
			
			$('fieldset.reqisition').addClass('open');

			var errorNum = $('fieldset.reqisition div.error').length;
			var boxheight = 190 + errorNum * 29;

			$('#showReqDetails').hide();
			$('#reqDets').slideDown(300);
			$('fieldset.reqisition').animate({height: boxheight}, 300, function(){
				$('#hideReqDetails').show();
			});

			this.setTabIndex();			
		},

		hideReqDet: function(e) {
			e.preventDefault();
			$('fieldset.reqisition').removeClass('open');
			
			var errorNum = $('fieldset.reqisition div.error').length;
			var boxheight = 86 + errorNum * 29;

			$('#hideReqDetails').hide();
			$('#reqDets').slideUp(300);
			$('fieldset.reqisition').animate({height: boxheight+"px"}, 300, function(){
				$('#showReqDetails').show();
			});

			this.setTabIndex();
		},

		showLineItems: function() {
			$('.sectionList').show();
			$('.freeText').removeClass('selected');
			$('.lineItms').addClass('selected');
			$('.commentLabel').show();
			$('textarea[name="lBuyerComments"]').addClass('slim');
			$('.infoNew.free').hide();
			$('.infoNew.line').show();
			$('.rfqinfo.info').hide();
		},

		hideLineItems: function() {
			$('.sectionList').hide();
			$('.freeText').addClass('selected');
			$('.lineItms').removeClass('selected');
			$('.commentLabel').hide();
			$('textarea[name="lBuyerComments"]').removeClass('slim');
			$('.infoNew.free').show();
			$('.infoNew.line').hide();
			$('.rfqinfo.info').hide();
		},

		setTabIndex: function() {
			var reqDetInputs = [
							'input[name="vImoNumber"]',
							'input[name="rRfqReference"]',
							'input[name="rReplyBy"]'
						];

			var delInputs = [
							'input[name="dDeliveryTo"]',
							'input[name="dDeliveryBy"]'
						];

			var delDetInputs = [
							'input[name="dAddress1"]', 
							'input[name="dAddress2"]', 
							'input[name="dCity"]',
							'input[name="dProvince"]',
							'input[name="dPostcode"]',
							'select[name="dCountry"]',
							'select[name="dDeliveryPort"]',
							'select[name="dTransportMode"]',
							'textarea[name="dPackagingInstructions"]'
						];

			if($('fieldset.reqisition').hasClass('open')){
				var reqTabCount = 5 + 1;
			}
			else {
				var reqTabCount = 2 + 1;
			}

			if($('fieldset.delivery').hasClass('open')){
				var delTabCount = 11;
			}
			else {
				var delTabCount = 2;
			}

			$.each(reqDetInputs, function(idx, inp) { 
				if($('fieldset.reqisition').hasClass('open')){
					$(inp).attr('tabindex',idx+2+1);
				}
				else {
					$(inp).attr('tabindex', -1);
				}
			});

			$.each(delInputs, function(idx, inp) {
				$(inp).attr('tabindex',idx+reqTabCount);
			});

			$.each(delDetInputs, function(idx, inp) { 
				if($('fieldset.reqisition').hasClass('open')){
					$(inp).attr('tabindex',idx+reqTabCount+2);
				}
				else {
					$(inp).attr('tabindex', -1);
				}
			});

			var tabSum = reqTabCount + delTabCount + 1;

			$('textarea[name="lBuyerComments"]').attr('tabindex', tabSum);

			$.each($('div.section'), function(idx, section){
				var secTabInd = tabSum;
				if($(section).find('fieldset').hasClass('open')){
					tabSum += 8;
				}

				$.each($(section).find('fieldset .left input[type="text"]'), function(idx, inp){
					if($(section).find('fieldset').hasClass('open')){
						$(inp).attr('tabindex', secTabInd + idx + 1);
					}
					else {
						$(inp).attr('tabindex', -1);
					}
				});
				$.each($(section).find('fieldset .right input[type="text"]'), function(idx, inp){
					if($(section).find('fieldset').hasClass('open')){
						$(inp).attr('tabindex', secTabInd + idx + 4 + 1);
					}
					else {
						$(inp).attr('tabindex', -1);
					}
				});

				var secIdx = idx;
				$.each($(section).find('.items .item'), function(idx, item){
					var itemTabInd = tabSum;
					tabSum += 6;
					var itemInputs = [
							$(item).find('input.qty'), 
							$(item).find('select.unt'),
							$(item).find('select.ptype'),
							$(item).find('input.pno'), 
							$(item).find('input.idesc'),
							$(item).find('input.icomments')  
						];

					$.each(itemInputs, function(idx, inp) { 
						$(inp).attr('tabindex',  itemTabInd + idx + 1);
					});
				});
			});

			$('input#fUp').attr('tabindex', tabSum + 1);
			$('input[type="submit"]').attr('tabindex', tabSum + 1);
		},

		byteCount: function(s) {
		    return encodeURI(s).split(/%..|./).length - 1;
		},

		mySplit: function(s)
		{
		    var splitted = new Array;
		    var i=0;
		    while ( i < s.length )
		    {
		        if ( s.charAt(i) == '%' )
		        {
		            chr =  s[i]+s[i+1]+s[i+2];
		            i+=3;
		        }
		        else
		        {
		            chr =  s[i];
		            i++;
		        }
		        splitted.push(chr);
		    }
		    return splitted;
		},

		utf8StringSlice: function(s, max) {
		    var l = this.byteCount(s);
		    if (l < max) {
		        return s;
		    }

		    var sn = '';
		    var i = max;

		    var basechars = this.mySplit( encodeURI(s) ).slice(0,i); 
		    var chars;

		    while (i > 0) {
		        chars = basechars.slice(0, i);

		        sn = chars.join('');
		        try {
		            dcs = decodeURI(sn);
		            return dcs;
		        }
		        catch (x) {}
		        i--;
		    }
		},

		checkSpace: function(el, ln) {
		    var l = this.byteCount($(el).val());

		    if ( l > ln ) {
		 		var slicedText = this.utf8StringSlice($(el).val(), ln);
		 		$(el).val(slicedText);
		 	}

		    return l < ln;
		},

		checkrRfqSubject: function() {
			var that = this;
			setTimeout(function() {
				that.checkSpace('input[name="rRfqSubject"]', 120)
			}, 4);

		},

		checkrRfqReference: function() {
			var that = this;
			setTimeout(function() {
				that.checkSpace('input[name="rRfqReference"]', 50)
			}, 4);
		},

		checkbPhone: function() {
			var that = this;
			setTimeout(function() {
				that.checkSpace('input[name="bPhone"]', 100);
			}, 4);
		},

		checkvVesselName: function() {
			var that = this;
			setTimeout(function() {
				that.checkSpace('input[name="vVesselName"]', 40);
			}, 4);
		},

		checkAddress: function(e) {
			var el = e.target;
			var that = this;
			setTimeout(function() {
				that.checkSpace(el, 226);
			}, 4);
		},

		checkSs: function(e) {
			var el = e.target;
			var that = this;
			setTimeout(function() {
				that.checkSpace(el, 128);
			}, 4);
		},

		checkS: function(e) {
			var el = e.target;
			var that = this;
			setTimeout(function() {
				that.checkSpace(el, 512);
			}, 4);
		},

		checkSL: function(e) {
			var el = e.target;
			var that = this;
			setTimeout(function() {
				that.checkSpace(el, 1800);
			}, 4);
		},

		trim: function(e){
			e.preventDefault();
			var str = $(e.target).val();
			$(e.target).val(str.replace(/^\s\s*/, '').replace(/\s\s*$/, ''));
		},

		validateForm: function() {
			$.validity.setup({ outputMode:"custom" });

		    // Start validation:
		    $.validity.start();

		    // Validator methods go here:

		    //RFQ Subject
		    $('input[name="rRfqSubject"]')
		    	.require();

		    //Reply by date
		    if ($('input[name="rReplyBy"]').val() !== "mm/dd/yyyy" && $('input[name="rReplyBy"]').val() !=="") {
			    $('input[name="rReplyBy"]')
			    	.match('date','Please enter a valid date')
			    	.greaterThanOrEqualTo(new Date(), 'The date can not be in the past.');
			}

			//IMO number
			if($('input[name="vImoNumber"]').val() !== ""){
			    $('input[name="vImoNumber"]')
			    	.match('number', 'The IMO number can only contain numbers.')
			    	.minLength(7, "The IMO number has to be 7 digits.")
			    	.maxLength(7, "The IMO number has to be 7 digits.")
			    	.assert(this.checkImo, "This is not a valid IMO number.");
		    }
		    
		    //Delivery by date
			if ($('input[name="dDeliveryBy"]').val() !== "mm/dd/yyyy" && $('input[name="dDeliveryBy"]').val() !=="") {
			    $('input[name="dDeliveryBy"]')
			    	.match('date','Please enter a valid date')
			    	.greaterThanOrEqualTo(new Date(), 'The date can not be in the past.');
			}
        				   
		    // All of the validator methods have been called:

		    // End the validation session:
		    var result = $.validity.end();
		    
		    // Return whether the form is valid
		    return result.valid;
		},
		
		checkCaptcha: function(){
			var that = this;

			$.get(
				"/enquiry/data/type/captcha/",
				{
					"captcha[id]": $('#captchaId').val(),
					"captcha[input]" : $('#captchaInput').val()
				},
			    function(response){
					if( response.status == "200" )
					{
						if(that.rfqData.countryIsCompulsory){
							that.checkCountry();
						}
						else {
							$('form.rfq').submit();
						}
					}
					else
					{
						$('form.rfq .formSubmit').append('<div class="error">The captcha text doesn\'t match</div>');
					}
			    }
			);
		},

		checkCountry: function() {
			if($('select[name="dCountry"]').val() === ''){
    			$('fieldset.delivery').addClass('invalid');
    			$('input#showDetails').click();
    			$('select[name="dCountry"]').focus();
    			$('select[name="dCountry"]').parent().addClass('invalid');
    			$('select[name="dCountry"]')
    				.parent()
    				.next('label')
    				.next('div.clear')
    				.after('<div class="error" style="margin: 0 0 5px  135px;">This field is required.</div>');
    		}
    		else {
    			$('form.rfq').submit();
    		}
		},

		checkImo: function() {
			imo = $('input[name="vImoNumber"]').val();
			var ind1, sum = 0;

			for (ind1 = 6; ind1--;) {
				sum += (imo.charCodeAt(ind1) - 48) * (7 - ind1);
			}
			if (sum % 10 == imo.charCodeAt(6) - 48) {
				return true;
			}
			else {
				this.showReqDet();
				return false;
			}
		},

		checkImoVessel: function() {
			if(
        		$('input[name="vImoNumber"]').val() === "" && 
        		$('input[name="vVesselName"]').val() !== "")
        	{
				this.showReqDet();			
        		return false;
        	}
        	else {
        		return true;
        	}
		},

		checkVesselImo: function() {
			if (
        			$('input[name="vVesselName"]').val() === "" && 
        			$('input[name="vImoNumber"]').val() !== ""
        		)
        	{
				this.showReqDet();			
        		return false;
        	}
        	else {
        		return true;
        	}
		},

		numberCheck: function(){
			var intRegex = /^\d+$/;
			var floatRegex = /^((\d+(\.\d *)?)|((\d*\.)?\d+))$/;
			var error = 0;
			var hasError = 0;

			$('input.qty').each(function(index) {
			    var str = $(this).val();
			    if(
			    	!$(this).parent().parent().find('input.pno').val() && 
		    		!$(this).parent().parent().find('input.idesc').val() && 
		    		!$(this).parent().parent().find('input.icomments').val() &&
		    		$(this).parent().parent().find('select.unt').val() === "" &&
		    		$(this).parent().parent().find('select.ptype').val() === "" &&
		    		!$(this).val() &&
		    		hasError === 0
			    ){
			    	error = 0;
			    }
			    else if(!intRegex.test(str) && !floatRegex.test(str))
			    {
			    	error = 1;
			    	hasError = 1;
			    	$(this).addClass('invalid');
			    	$('input.invalid:first').focus();
				}
			});

			return error;
		},
		
		submitForm: function(e){
			e.preventDefault();
			
			if (this.validateForm()) {
				// Check if all of the rfq requirements are met
		        if (
		        	$('input.pno').val() !== "" || 
		        	$('input.idesc').val()!=="" || 
		        	$('input.icomments').val()!=="" || 
		        	$('textarea[name="lBuyerComments"]').val()!=="" ||
		        	$('.documents table tbody tr').length > 0
		        ) {

	        		var error = this.numberCheck();		
					if(error !== 1) {
						$.each($('input.qty'), function(index, ele){
							var value = $(ele).val();
							
							if (value === ""){
								value = "0.0";
							}

							parts = value.split(".");
							if(!parts[1]){
								parts[1] = "0";
							}

							if(parts[0].length > 7 || parts[1].length > 4) {
								alert("The quantity maximally contain 7 whole numbers and 4 decimal digits.");
								$(value).addClass('invalid');
								$(value).focus();
								error=1;
							}
						});

						if(error !== 1){
			   				if($('.suppliers ul li span.checked').length < 1)
			        		{
			   					var msg = '<div class="error">Please select a supplier before sending an RFQ.';
			   					if( $('.suppliers ul li').length < 1) 
			   					{
			   						msg += ' <a onClick="history.back();" href="j#" >Click here to go back to search results</a>.';
			   					}
			   					msg += '</div>';
			        			$('.suppliers ul').after(msg);
			        		}
			        		else {
				        		if( $('#captchaId').length > 0 ){
					        		this.checkCaptcha();
					        	 }else{
					        		if($('input.date').val() === "mm/dd/yyyy") {
						        		$('input.date').val("");
						        	}
						        	if(this.rfqData.countryIsCompulsory) {
						        		this.checkCountry();
						        	}
						        	else {
						        		$('form.rfq').submit();
						        	}
					        	}
					        }
					    }
					}
					else {
						alert("Please enter a valid quantity.");
					}	        		  	
		        }
				else {
					$('.rfqinfo span').css('color', '#e81e24');
					$('.infoNew').hide();
					$('.rfqinfo.info').css('display', 'block');

					$('input.qty:first').focus();

					$(document).scrollTop($('#rfqInfo').offset().top - 20);
				}
		    }
		},

		getVessel: function(e){
			e.preventDefault();
			
			if($('input[name="vImoNumber"]').val().length === 7){
				$('div.error').remove();
				$('fieldset.reqisition input').removeClass("invalid");
				var thisView = this;
				this.imoCollection.fetch({ 
					data: $.param({ imo: $('input.imo').val()}),
					complete: function(){
						thisView.showVessel();
					}
				});

				var errNum = $('fieldset.reqisition div.error').length;

				if($('fieldset.reqisition').hasClass('open')){
                    var boxHeight = 190 + errNum * 29;
                }
                else {
                    var boxHeight = 86 + errNum * 29;
                }

                $('fieldset.reqisition').height(boxHeight);
			}
			else {
				$('div.error').remove();
				$('fieldset.reqisition input').removeClass("invalid");
				$('label[for="vImoNumber"]').next('div.clear').after('<div class="error">The IMO number has to be 7 digits.</div>');
				$('input[name="vImoNumber"]').addClass("invalid");
				$('input[name="vImoNumber"]').focus();

				var errNum = $('fieldset.reqisition div.error').length;

				if($('fieldset.reqisition').hasClass('open')){
                    var boxHeight = 190 + errNum * 29;
                }
                else {
                    var boxHeight = 86 + errNum * 29;
                }

                $('fieldset.reqisition').height(boxHeight);
			}
		},

		showVessel: function() {
			var data = this.imoCollection.models[0];

			if(data){
				if(data.attributes.data[0]){
					$('input[name="vVesselName"]').val(data.attributes.data[0].VESSEL_NAME);
					$('fieldset.reqisition input').removeClass('invalid');
					$('.error').remove();
					var errNum = $('fieldset.reqisition div.error').length;

					if($('fieldset.reqisition').hasClass('open')){
	                    var boxHeight = 190 + errNum * 29;
	                }
	                else {
	                    var boxHeight = 86 + errNum * 29;
	                }

	                $('fieldset.reqisition').height(boxHeight);
				}
				else {
					$('fieldset.reqisition input').removeClass('invalid');
					$('.error').remove();
					$('input[name="vVesselName"]').addClass('invalid');
					$('input[name="vVesselName"]').val('');
					$('label[for="vVesselName"]').next().after('<div class="error imono">Vessel not found.</div>');

					var errNum = $('fieldset.reqisition div.error').length;

					if($('fieldset.reqisition').hasClass('open')){
	                    var boxHeight = 190 + errNum * 29;
	                }
	                else {
	                    var boxHeight = 86 + errNum * 29;
	                }

	                $('fieldset.reqisition').height(boxHeight);
				}
			}
		},

		getPorts: function(e){
			e.preventDefault();
			var thisView = this;

			this.portsCollection.fetch({ 
				data: $.param({ countryCode: $('select[name="dCountry"]').val()}),
				complete: function(){
					thisView.renderPorts();
				}
			});
		},

		renderPorts: function() {		
			var data = this.portsCollection.models[0];

			if(data){
				var data = data.attributes;
				
				if(data.data.length === 0){
					html = '<option value="" disabled="disabled">No port found for this country</option>';
				}
				else {
					html = this.portsTemplate(data);
				}
				
				$('select[name="dDeliveryPort"]').html(html);

				$.uniform.update('select[name="dDeliveryPort"]');
			}
		},
		
		changeCaptcha: function(e){
			$.ajax({
				url: '/enquiry/generate-captcha',
				type: 'GET',
				cache: false,
			    error: function(request, textStatus, errorThrown) {
			    	response = eval('(' + request.responseText + ')');
			    	alert("ERROR " + request.status + ": " + response.error);
			    },
				success: function( response ){
					$(".captcha img").attr("src", response.data)
				}
			});
		}
	});

	return new standardRfqView;
});
