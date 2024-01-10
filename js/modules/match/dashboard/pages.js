var googleChartSetting = {
    vAxis: {minValue: 0},
    seriesType: "bars",
    is3D: true
};

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

function getData(trObject){


	var data = [];
	var columnName = ['Month'];

	if( trObject == '' ){
		objects = $("#resultsTab tbody tr.dr");
	}else{
		objects = $(trObject);
	}

	// populating column name
	objects.each(function(){
		var x = $(this).find('.statsName').html();
		if( x !== null ){
			x = x.trim();
			columnName.push(x);
		}
	});
	data.push(columnName);


	for(var i=1; i<13; i++){
		x = new Array();
		x.push( getMonth(i) );


		objects.each(function(){
			var d = 0;
			d = $(this).find('.m' + i).html();
			d = d.replace(/,/g, '');
			d = ( d != null ) ? d.trim(): 0;
			d = parseInt(((d=='')?0:d));

			var y = $(this).find('.statsName').html();
			y = (y != null) ? y.trim(): 0;
			if( y ==  'Supplier Page Impressions' ){
				d=d/1000;
			}

			x.push( d );
		});

		data.push(x);
	}


	return data;
}

function draw( statsName ){

	if( typeof statsName == 'object' ) statsName = '';

	data = getData(statsName);
 	var d = google.visualization.arrayToDataTable(data);


 	var chart = new google.visualization.ComboChart(document.getElementById('chart_div'));
 	chart.draw(d, googleChartSetting);

}

function redraw(trObject){
	data = getData(trObject);
  	var d = google.visualization.arrayToDataTable(data);

    var chart = new google.visualization.ComboChart(document.getElementById('chart_div'));
    chart.draw(d, googleChartSetting);
}

function isNumber(n) {
	return !isNaN(parseFloat(n)) && isFinite(n);
}

$(document).ready(function(){

	$("#resultsTab tbody tr").click(function(){
		$("#resultsTab tbody tr").each(function(){
			$(this).removeClass("green");
		});
		$(this).addClass("green");

		$("#selectedTnid").val($(this).attr('tnid'));

		var x = $(this).find('.statsName').html();
		statsName = x.trim();


		var buyerName = ' Showing ';
		buyerName += '<u>' + statsName + '</u> chart ';
		buyerName += ' only | <a href="#" id="resetChart">Reset</a>';
		$("#titleOfChart").html(buyerName);

		$("#resetChart").on('click', function(){
			$("#selectedTnid").val('');

			$('#docId option:first').attr('selected', true);

			$("#titleBuyerName").html('');
			$("#resultsTab tbody tr").each(function(){
				$(this).removeClass("green");
			});

			draw('');

		});

		redraw(this);
	});

    $(".nr").parent().unbind("click");

});
