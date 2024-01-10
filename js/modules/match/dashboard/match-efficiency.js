var googleChartSetting = {
    vAxis: {minValue: 0},
    seriesType: "bars",
    is3D: true
};

function number_format(number, decimals, dec_point, thousands_sep) {
  //  discuss at: http://phpjs.org/functions/number_format/
  // original by: Jonas Raoni Soares Silva (http://www.jsfromhell.com)
  // improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
  // improved by: davook
  // improved by: Brett Zamir (http://brett-zamir.me)
  // improved by: Brett Zamir (http://brett-zamir.me)
  // improved by: Theriault
  // improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
  // bugfixed by: Michael White (http://getsprink.com)
  // bugfixed by: Benjamin Lupton
  // bugfixed by: Allan Jensen (http://www.winternet.no)
  // bugfixed by: Howard Yeend
  // bugfixed by: Diogo Resende
  // bugfixed by: Rival
  // bugfixed by: Brett Zamir (http://brett-zamir.me)
  //  revised by: Jonas Raoni Soares Silva (http://www.jsfromhell.com)
  //  revised by: Luke Smith (http://lucassmith.name)
  //    input by: Kheang Hok Chin (http://www.distantia.ca/)
  //    input by: Jay Klehr
  //    input by: Amir Habibi (http://www.residence-mixte.com/)
  //    input by: Amirouche
  //   example 1: number_format(1234.56);
  //   returns 1: '1,235'
  //   example 2: number_format(1234.56, 2, ',', ' ');
  //   returns 2: '1 234,56'
  //   example 3: number_format(1234.5678, 2, '.', '');
  //   returns 3: '1234.57'
  //   example 4: number_format(67, 2, ',', '.');
  //   returns 4: '67,00'
  //   example 5: number_format(1000);
  //   returns 5: '1,000'
  //   example 6: number_format(67.311, 2);
  //   returns 6: '67.31'
  //   example 7: number_format(1000.55, 1);
  //   returns 7: '1,000.6'
  //   example 8: number_format(67000, 5, ',', '.');
  //   returns 8: '67.000,00000'
  //   example 9: number_format(0.9, 0);
  //   returns 9: '1'
  //  example 10: number_format('1.20', 2);
  //  returns 10: '1.20'
  //  example 11: number_format('1.20', 4);
  //  returns 11: '1.2000'
  //  example 12: number_format('1.2000', 3);
  //  returns 12: '1.200'
  //  example 13: number_format('1 000,50', 2, '.', ' ');
  //  returns 13: '100 050.00'
  //  example 14: number_format(1e-8, 8, '.', '');
  //  returns 14: '0.00000001'

  number = (number + '')
    .replace(/[^0-9+\-Ee.]/g, '');
  var n = !isFinite(+number) ? 0 : +number,
    prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
    sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
    dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
    s = '',
    toFixedFix = function(n, prec) {
      var k = Math.pow(10, prec);
      return '' + (Math.round(n * k) / k)
        .toFixed(prec);
    };
  // Fix for IE parseFloat(0.55).toFixed(0) = 0;
  s = (prec ? toFixedFix(n, prec) : '' + Math.round(n))
    .split('.');
  if (s[0].length > 3) {
    s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
  }
  if ((s[1] || '')
    .length < prec) {
    s[1] = s[1] || '';
    s[1] += new Array(prec - s[1].length + 1)
      .join('0');
  }
  return s.join(dec);
}

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
			var d = 0
			d = $(this).find('.m' + i).html();
            d = ( d != null ) ? d.trim(): 0;
            d = d.replace(/,/g,'');
			d = (((d=='')?0:parseFloat(d)));
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

});
