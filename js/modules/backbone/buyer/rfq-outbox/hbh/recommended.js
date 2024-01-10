define([
	"jquery",
	"handlebars"
], function(
	$, 
	Hb
){

	var positionCounter = 1;

    Handlebars.registerHelper('position', function() {
        return positionCounter++;
    });
	
	Handlebars.registerHelper('getTradeStar', function(tradeRank) {
		var ret = '',
			rank = tradeRank / 20 * 5,
			stars = Math.round(rank * 10) / 10;
			stars = stars.toString();
		var fullStars = parseInt(stars.split(".")[0]),
			partStars = parseInt(stars.split(".")[1]),
			remainingStars = 5 - fullStars;

		for(var i = 0; i <= 5; i++) {
			if(fullStars > i){
				ret +='<img src="/img/icons/trade-rank-stars/small/1tr.png" alt="star" />';
			}
		};

		if(partStars > 0) {
			remainingStars --;
			ret += '<img src="/img/icons/trade-rank-stars/small/0.'+partStars+'tr.png" alt="star" />'
		}

		for(var i = 0; i < remainingStars; i++) {
			ret += '<img src="/img/icons/trade-rank-stars/small/0tr.png" alt="star" />';
		};

		return new Handlebars.SafeString(ret);
	});
});