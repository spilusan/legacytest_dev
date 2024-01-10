require([
	"jquery",
	'libs/jquery-ui-1.10.3/datepicker'
], function($,DatePicker){

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
		
		$(document).ready(function(){
			//Set up datepicker
	        $('.datepicker').datepicker({dateFormat: 'dd/mm/yy'});
	        
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
			

			$("#resultsTab tbody tr").click(function(e){
				if( !$(e.target).is('a') ){
					$("#resultsTab tbody tr").each(function(){
						$(this).removeClass("selectedRow");
					});
					$(this).addClass("selectedRow");					
				}
			});
			
		});
		
        
});
