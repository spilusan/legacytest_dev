define([
	"jquery",
	"handlebars"
], function(
	$, 
	Hb
){
	Handlebars.registerHelper('getTradeStar', function(tradeRank) {
		var ret = '',
			stars = tradeRank / 100 * 5;
			stars = stars.toString();
			fullStars = parseInt(stars.split(".")[0]),
			partStars = parseInt(stars.split(".")[1]),
			remainingStars = 5 - fullStars;

		for (var i = 0; i <= 5; i++) {
			if(fullStars > i){
				ret +='<img src="/img/icons/trade-rank-stars/small/1tr.png" alt="star" />';
			}
		};

		if(partStars > 0) {
			if(partStars > 9) {
				partStars = partStars.toString();
				var partStarsOne = parseInt(partStars.substring(0,1));
				var partStarsTwo = parseInt(partStars.substring(1,2));

				if(partStarsTwo >= 5) {
					partStarsOne++;
				}
			}
			else {
				var partStarsOne = partStars;
			}

			remainingStars --;
			ret += '<img src="/img/icons/trade-rank-stars/small/0.'+partStarsOne+'tr.png" alt="star" />'
		}

		for (var i = 0; i < remainingStars; i++) {
			ret += '<img src="/img/icons/trade-rank-stars/small/0tr.png" alt="star" />';
		};

		return new Handlebars.SafeString(ret);
	});
});