require([
	'jquery',
	'libs/jquery.tools.overlay.modified'
], function(
	$,
	Modal
){
	if ($.browser.msie  && parseInt($.browser.version, 10) === 7) {
		  alert("There are known limitation on this page that doesn't work with IE7. Please use other browser! Chrome is highly recommended");
	}
	/*
	$("body").delegate("#searchText", "keypress", function(event){
		return true;
	    var key = window.event ? event.keyCode : event.which;
	    if (event.keyCode == 8 || event.keyCode == 46
	     || event.keyCode == 37 || event.keyCode == 39) {
	        return true;
	    }
	    else if ( key < 48 || key > 57 ) {
	        return false;
	    }
	    else return true;

	});
	*/

	$("body").delegate("#searchButton", "click", function(event){
		if(isNaN($("#searchText").val())){
			alert("Please enter a valid ORD_INTERNAL_REF_NO");
			return false;
		}
	});

	//Set as valid
	$("body").delegate("input.setValid", "click", function(e){
		e.preventDefault();

		var docId = $(this).attr("docId");
		var docType = $(this).attr("docType");
		$("#docId").val(docId);
		$("#docType").val(docType);
		$("#modal textarea").text($("#commentFor" + docId).text());
		$("#actionButtonOrd").val('Set ORD as Valid');
		$("#actionButtonPoc").val('Set POC as Valid');
		$("#modal .invalidBtn").hide();
		$("#modal .validBtn").show();

		$('#modal h1').html('Set transaction as valid');

		$("#modal").overlay({
	        mask: 'black',
	        left: 'center',
	        fixed: 'true',

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
	});

	//Set as invalid
	$("body").delegate("input.setInValid", "click", function(e){
		e.preventDefault();

		var docId = $(this).attr("docId");
		var docType = $(this).attr("docType");
		$("#docId").val(docId);
		$("#docType").val(docType);
		$("#modal textarea").text("");
		$("#modal .invalidBtn").show();
		$("#modal .validBtn").hide();
		if($(this).attr("pocExist") == "N"){
			$("#actionButtonPoc").hide();
		}
		
		if(docType == 'POC'){
			$("#actionButtonOrd").hide();
			$("#actionButtonPoc").show();
		}else if(docType == 'ORD'){
			$("#actionButtonPoc").hide();
			$("#actionButtonOrd").show();
		}
			
		$('#modal h1').html('Set transaction as invalid');
		$("#modal").overlay({
	        mask: 'black',
	        left: 'center',
	        fixed: 'true',

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
	});
});
