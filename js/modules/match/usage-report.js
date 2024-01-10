var tableToExcel = (function () {
	var uri = 'data:application/vnd.ms-excel;base64,';
    var template = "<html xmlns:o=\"urn:schemas-microsoft-com:office:office\" xmlns:x=\"urn:schemas-microsoft-com:office:excel\" xmlns=\"http://www.w3.org/TR/REC-html40\"><head><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>{worksheet}</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head><body><table>{table}</table></body></html>";
    var base64 = function (s) {
            return window.btoa(unescape(encodeURIComponent(s)))
        }, format = function (s, c) {
            return s.replace(/{(\w+)}/g, function (m, p) {
                return c[p];
            })
        }
    return function () {
	    table = $('#docId').val();
	    switch(table){
	    	case 'rfqSent': 		worksheetName = 'RFQ sent to Match';
			 						break;
			 						
	    	case 'rfqForwarded': 	worksheetName = 'RFQ forwarded by Match to supplier';
			 						break;
			 						
	    	case 'quoteReceived': 	worksheetName = 'Quote received by buyer from Match';
			 						break;
			 						
	    	case 'quoteRate': 		worksheetName = 'Quote rate';
			 						break;
		}
        if (!table.nodeType) table = document.getElementById(table)
        var ctx = {
            worksheet: worksheetName,
            table: table.innerHTML
        }
        window.location.href = uri + base64(format(template, ctx));
        
    }
})()

function getMonth(i){
	var months = [];
		months[1] = 'Jan';
	 	months[2] = 'Feb';
	 	months[3] = 'Mar';
	 	months[4] = 'Apr';
	 	months[5] = 'May';
	 	months[6] = 'Jun';
	 	months[7] = 'Jul';
	 	months[8] = 'Aug';
	 	months[9] = 'Sep';
	 	months[10] = 'Oct';
	 	months[11] = 'Nov';
	 	months[12] = 'Dec';
	 	
	return months[i];
}
       
function draw (){
	
	var data = [[
     	'Month', 
         'Total RFQ sent to match', 
         'Total RFQ forwarded by match', 
         'Total Quote received by match', 
         'Quote rate (% rounded up)'
 	]];
	for(var i=1; i<13; i++){
 		x = new Array();
 		x.push( getMonth(i) );
 		x.push( typeof rfqSent[i] == 'undefined' ? 0:rfqSent[i]);
 		x.push( typeof rfqForwarded[i] == 'undefined' ? 0:rfqForwarded[i]);
 		x.push( typeof quoteReceived[i] == 'undefined' ? 0:quoteReceived[i]);
 		x.push( typeof quoteRate[i] != 'number' ? 0:quoteRate[i]);
 		data.push(x);
 	}

 	var d = google.visualization.arrayToDataTable(data);

     var options = {
         title: '',
         vAxes: [
 			{ title: 'Documents'}
 			, { title: 'Quote rate (%)'}
         ],
         hAxis: {title: "Month"},
         seriesType: "bars",
         series: {3: {type: "line", targetAxisIndex:1}}
     };
     
     
     var chart = new google.visualization.ComboChart(document.getElementById('chart_div'));
     chart.draw(d, options);

}   

function redraw(){
	
	var data = [ 'Month' ];
	var type = $('#docId option:selected').val();

	if( type == '' ){
		var types = ['rfqSent', 'rfqForwarded', 'quoteReceived', 'quoteRate']
		
		$('#docId option').each(function(){
			if( $(this).text() != 'All' )
			data.push( $(this).text() );
		});
		
		data = [data];
		
	}else{
		var types = [type];

		data.push( $('#docId option:selected').text() );
		data = [ data ];
	}

	var tnid = $('#selectedTnid').val();
	
	if( tnid != "" ){
		
		for(var i=1; i<13; i++){
			x = new Array();
     		x.push( getMonth(i) );
 		
 			types.forEach(function(t) {
	     		var d = $('.' + t + 'b' + tnid + 'm' + i).html().trim();
				d = parseInt(((d=='')?0:d));
	      		x.push( d );	     			
 			});     			
      		
      		data.push(x);
      	}
	}else{
		for(var i=1; i<13; i++){
      		x = new Array();
     		x.push( getMonth(i) );
      		
      		if( type == 'rfqSent' || type == '' ) x.push( typeof rfqSent[i] == 'undefined' ? 0:rfqSent[i]);
      		if( type == 'rfqForwarded' || type == '' ) x.push( typeof rfqForwarded[i] == 'undefined' ? 0:rfqForwarded[i]);
      		if( type == 'quoteReceived' || type == '' ) x.push( typeof quoteReceived[i] == 'undefined' ? 0:quoteReceived[i]);
      		if( type == 'quoteRate' || type == '' ) x.push( typeof quoteRate[i] != 'number' ? 0:quoteRate[i]);
      		data.push(x);
      	}
	}
	


  	var d = google.visualization.arrayToDataTable(data);

  	if( types.length == 1 ){
	    var options = {
	    	title: '',
	        vAxes: [
	  			{ title: (( type == 'quoteRate' ) ? '':"Documents")}
	  			, { title: 'Quote rate (%)'}
	        ],
	        hAxis: {title: "Month"},
	        seriesType: (( type == 'quoteRate' ) ? 'line':"bars"),
	        //series: {3: {type: "line", targetAxisIndex:1}}
	    };
  	} else {
	    var options = {
	            title: '',
	            vAxes: [
	    			{ title: 'Documents'}
	    			, { title: 'Quote rate (%)'}
	            ],
	            hAxis: {title: "Month"},
	            seriesType: "bars",
	            series: {3: {type: "line", targetAxisIndex:1}}
	        };
  	}
    var chart = new google.visualization.ComboChart(document.getElementById('chart_div'));
    chart.draw(d, options);
}
	
$(document).ready(function(){
	function isNumber(n) {
		return !isNaN(parseFloat(n)) && isFinite(n);
	}

	$("#resultsTab tbody tr").click(function(){
		$("#resultsTab tbody tr").each(function(){
			$(this).removeClass("green");
		});
		$(this).addClass("green");
		
		$("#selectedTnid").val($(this).attr('tnid'));
		
		var buyerName = ' Showing ';
		buyerName += '<u>' + $(this).attr("buyerName") + '</u>';
		buyerName += ' only | <a href="#" id="showAllBuyer">Show all</a>';
		$("#titleBuyerName").html(buyerName);
		
		$("#showAllBuyer").on('click', function(){		
			$("#selectedTnid").val('');
			
			$('#docId option:first').attr('selected', true);
			
			$("#titleBuyerName").html('');
			$("#resultsTab tbody tr").each(function(){
				$(this).removeClass("green");
			});
			
			draw();
			
		});

		redraw();		
	});
	
	$("#docId").change(function(){
		$('.resultTables').hide(); 
		$('#' + $(this).val()).show();  
		//$('#selectedTnid').val(''); 
		
		if( $(this).val() != "" ){ 
			redraw();
		}else{
			$('#rfqSent').show();
			draw();
		}
	});
});
