$(document).ready(function(){ 

	// select the first x
	var total = $("input.supplier").length;
	var totalAllowed = window.maxSelectedSuppliers;
	var row = 0;
	$('input.supplier').each(function(){
		if( row >= totalAllowed)
		{
			$(this).attr("checked", false);
			$(this).attr("readonly", true);
		}
		row++;
	});
	
	$("input.supplier").live('click',function() {
		var o = $.JSONCookie(cookieName);
		if (this.checked) {
			if (!o.suppliers) {
				o.suppliers = [];
			}

			var tnid = this.id;
			var count = o.suppliers.length;

			if( count > totalAllowed ){
				alert("Sorry, you can only select up to " + totalAllowed + " Suppliers.\n\nIf you send the same RFQ to more than \n" + totalAllowed + " suppliers, in 1 or more batches, the \nsystem will permanently disable your email address.");
				return false;
			}
			
			o.suppliers.push(tnid);
		} else {
			var tnid = this.id;
			for(var i=0;i<o.suppliers.length;i++){
				if( tnid == o.suppliers[i] ){
					o.suppliers.splice(i,1);
				}
			}
		}
		
		$.JSONCookie(cookieName, o, { domain: cookieDomain,
									  path: cookiePath });
	});
	
			
	$("input.attach-files").live('click',function() {
		$('#attach-files').show();
		$(this).hide(); 
        $(this).parent().hide(); 
	});
	
	function checkTotalFileToUpload(){
		var totalChecked = $('input[name="existingEnquiryFile[]"]:checked').length;
		if( totalChecked <= 2){
			$('input[name="enquiryFile[]"]').attr("disabled", false);
			for(var i=0; i<3; i++){
				if( i <= totalChecked ){
					$('input[name="enquiryFile[]"]:nth(' + (3-i) + ')').attr("disabled", true);
				}
			}
			if( window.x == false ){
				$('#attach-files').show();
			}
			window.x=true;
		}else{
			$("input.attach-files").hide();
			$('#attach-files').hide();
			$('input[name="enquiryFile[]"]').val('');
			window.x=false;
		}		
	}
	
	checkTotalFileToUpload();
	
	$('input[name="existingEnquiryFile[]"]').live('click', function(){
		checkTotalFileToUpload();
	});
	
	$("#delivery-date").datepicker({ /*showOn: 'button',
								     buttonImage: '/images/icons/edit.png',
								     buttonImageOnly: true,*/
								     minDate: 0});
	
	$("#enquiryEmail").focus(function() {
		$(this).css('color','#000');
		if ( $(this).val() == 'Your Email')
		{
			$(this).val('');
		}
	});
	
	
	$(".new-captcha").click(function(){
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
	});

	var maxLength =  1800;

	var byteCount = function(s) {
	    return encodeURI(s).split(/%..|./).length - 1;
	};

	function mySplit(s)
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
	}

	function utf8StringSlice(s, max) {
	    var l = byteCount(s);
	    if (l < max) {
	        return s;
	    }

	    var sn = '';
	    var i = max;

	    var basechars = mySplit( encodeURI(s) ).slice(0,i); 
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
	}

	var checkDetailsSpace = function(el) { 
	    var l = byteCount($(el).val());

	    if ( l > maxLength ) {
	 		var slicedText = utf8StringSlice($(el).val(), maxLength);
	 		$(el).val(slicedText);
	 		$('#countdown').html(maxLength - byteCount($(el).val()));
	 	}
	 	else {
	 		$('#countdown').html(maxLength - l);
	 	}

	    return l < maxLength;
	};

	var checkSpace = function(el, ln) { 
	    var l = byteCount($(el).val());

	    if ( l > ln ) {
	 		var slicedText = utf8StringSlice($(el).val(), ln);
	 		$(el).val(slicedText);
	 	}

	    return l < ln;
	};

	var numberCheck = function(el) {
		if (isNaN(parseInt($(el).val().substr($(el).val().length - 1, $(el).val().length)))) {
			$(el).val($(el).val().substring(0, $(el).val().length - 1));
			var i = 0;
			while (i < $(el).val().length) {

				if(isNaN(parseInt($(el).val().substr(i, i+1)))) {
					$(el).val('');
					break;
				}
				else {
					i++;
				}
			}
		}
	};

	var dateCheck = function() {
		var today = new Date();
		var theDate = $('#delivery-date').val();

		if(theDate !=="") {
			var year = theDate.substring(6, 10);
			var month = theDate.substring(0, 2) - 1;
			var day = theDate.substring(3, 5);
			if(month < 12 && month >= 0 && month !== "00" && day < 32 && day > 0 && day !== "00") {
				var myDate = new Date();			
				myDate.setFullYear(year,month,day);

				if(today <= myDate){
					$("#rfq-form").submit();
				}
				else {
					alert('Please enter a valid date.');
				}
			}
			else {
				alert('Please enter a valid date.');
			}
		}
		else {
			$("#rfq-form").submit();
		}
	};


	$('#enquiry-text').bind('input', function(e){ 
		return checkDetailsSpace('#enquiry-text'); 
	});

	$('#enquiry-subject').bind('input', function(e){ 
		return checkSpace('#enquiry-subject', 130); 
	});

	$('#vessel-name').bind('input', function(e){ 
		return checkSpace('#vessel-name', 40); 
	});

	$('#delivery-location').bind('input', function(e){ 
		return checkSpace('#delivery-location', 200); 
	});

	$('#imo').bind('input', function(e){ 
		return checkSpace('#imo', 12); 
	});

	$('#imo').bind('input', function(e){
		return numberCheck('#imo');
	});

	$('#sender-name').bind('input', function(e){ 
		return checkSpace('#sender-name', 80); 
	});

	$('#sender-email').bind('input', function(e){ 
		return checkSpace('#sender-email', 100); 
	});

	$('#sender-phone').bind('input', function(e){ 
		return checkSpace('#sender-phone', 100); 
	});
	
	$('#company-name').bind('input', function(e){ 
		return checkSpace('#company-name', 100); 
	});

	$('form #send').bind('click', function(e){
		e.preventDefault();
		dateCheck();
	});

	checkDetailsSpace('#enquiry-text');
});