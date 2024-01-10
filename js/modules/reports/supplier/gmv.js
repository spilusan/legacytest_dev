require([
	'jquery',
	"libs/jquery.uniform",
	'underscore',
	'modal',
    'help',
    'libs/fileSaver',
    'libs/jquery.validity.min',
    'libs/jquery.validity.custom.output.gmv',
    'libs/jquery-ui-1.10.3/datepicker',
    'libs/waypoints/waypoints-sticky',
    '/js/jquery.auto-complete.js'

], function($,Ui, Uniform){


	$(function(){

		//validate form 

		$('.new').submit(function( e ) {
			if ($('input[name="id"]').val() == '') {
					alert('Required');
				e.preventDefault();
			} else {
				var tnId = $('input[name="id"]').val()
				if (!tnId.match(/^\d+$/)) {
					alert('TNID must be a number');
				e.preventDefault();
				}
			}
		});

		$('.datepicker').datepicker( { dateFormat: 'dd/mm/yy' });
		
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

		$("#resultsTab tbody tr td").each(function(){
			//if( $.trim($(this).html()) == "" ) $(this).html("N/A");
			if( $(this).hasClass('currency') ) {
				html = '<div class="sign">$</div><div class="value">' + number_format($(this).html(), 2, '.', ',') + '</div><div class="clear"></div>';
				html = '' + number_format($(this).html(), 2, '.', ',') + '';
				$(this).html( html );
			}
			
			if( $(this).hasClass('percentage') ) {
				if( $(this).html() != "N/A" ){
					html = '<div class="sign">%</div><div class="value">' + number_format($(this).html(), 0, '.', ',') + '</div><div class="clear"></div>';
					html = '' + number_format($(this).html(), 2, '.', ',') + '';
					$(this).html( html );
				}
			}
			
		});

		$("#resultsTab tbody tr").click(function(){

			e.preventDefault();


			$("#resultsTab tbody tr").each(function(){
				$(this).removeClass("green");
			});
			$(this).addClass("green");
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

	});
	
});
