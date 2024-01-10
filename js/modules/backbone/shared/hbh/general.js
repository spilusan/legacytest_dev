define('hbhHelpers', [
	"jquery",
	"handlebars"
], function (
	$,
	Hb
) {
	Handlebars.registerHelper('each', function (context, options) {
		var ret = "";
		for (var i in context) {
			ret = ret + options.fn(context[i]);
		}

		return ret;
	});

	Handlebars.registerHelper("formatAddress", function (str) {
		if (str == null) {
			str = "";
		}
		if (str === " ") {
			str = "";
		} else if (str.charAt(str.length - 1) === ",") {
			str = str.slice(0, -1) + ", ";
		} else {
			str = str + ", "
		}

		return str;
	});

	Handlebars.registerHelper("formatTitleCase", function (str) {
		var ret;

		if (str === undefined) {
			ret = '';
		} else {
			ret = str.replace(/\w\S*/g, function (txt) {
				return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase();
			});
		}

		return ret;
	});

	Handlebars.registerHelper("nl2br", function (str) {
		if (str && str !== null) {
			str = str.replace(/\n/g, '<br />');
			return new Handlebars.SafeString(str);
		} else {
			return str;
		}
	});

	Handlebars.registerHelper("truncateText", function (str, chars) {
		if (str && str !== null) {
			if (str.length > chars) {
				str = str.substring(0, chars) + '...';
			}
			return new Handlebars.SafeString(str);
		} else {
			return str;
		}
	});

	Handlebars.registerHelper("formatCurrency", function (str, decimalPlaces) {
		if (str) {
			var type = typeof str;
			if (type !== 'number') {
				str = parseFloat(str);
			}
			str = str.toFixed(typeof decimalPlaces !== 'number' ? 2 : decimalPlaces);
			str = str.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
			return str;
		} else {
			str = 0;
			str = str.toFixed(typeof decimalPlaces !== 'number' ? 0 : decimalPlaces);
			return str;
		}
	});

	Handlebars.registerHelper("formatCurrencyAbs", function (str) {
		if (str) {
			var type = typeof str;
			if (type !== 'number') {
				str = parseFloat(str);
			}
			str = Math.abs(str);
			str = str.toFixed(2);
			str = str.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
			return str;
		}
	});

	Handlebars.registerHelper("formatNumber", function (str) {
		if (str) {
			str = str.toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,");
			return str;
		} else {
			return 0;
		}
	});

	Handlebars.registerHelper("formatIsoDateToUK", function (date) {
		if (date) {
			var date = new Date(date),
				dateParts = date.toISOString().split('T').shift().split('-');

			return [dateParts[2], dateParts[1], dateParts[0]].join('/');
		} else {
			return "";
		}
	});

	Handlebars.registerHelper("formatIsoDate", function (date) {
		if (date) {
			var newDate = date.split(" ");
			newDate = newDate[0].split("-");
			date = newDate[2] + "." + newDate[1] + "." + newDate[0];
			return date;
		} else {
			return "";
		}
	});


	Handlebars.registerHelper("formatIsoDate", function (date) {
		if (date) {
			var newDate = date.split(" ");
			newDate = newDate[0].split("-");
			date = newDate[2] + "." + newDate[1] + "." + newDate[0];
			return date;
		} else {
			return "";
		}
	});

	Handlebars.registerHelper("formatIsoDateTarget", function (date) {
		if (date) {
			var newDate = date.split(" ");
			newDate = newDate[0].split("-");

			switch (newDate[1]) {
				case "01":
					newDate[1] = "Jan"
					break;
				case "02":
					newDate[1] = "Feb"
					break;
				case "03":
					newDate[1] = "Mar"
					break;
				case "04":
					newDate[1] = "Apr"
					break;
				case "05":
					newDate[1] = "May"
					break;
				case "06":
					newDate[1] = "Jun"
					break;
				case "07":
					newDate[1] = "Jul"
					break;
				case "08":
					newDate[1] = "Aug"
					break;
				case "09":
					newDate[1] = "Sep"
					break;
				case "10":
					newDate[1] = "Oct"
					break;
				case "11":
					newDate[1] = "Nov"
					break;
				case "12":
					newDate[1] = "Dec"
					break;
			}

			date = newDate[2] + " " + newDate[1] + " " + newDate[0];
			return date;
		} else {
			return "Yet to trade";
		}
	});

	Handlebars.registerHelper("formatIsoDateTargetLocked", function (date) {
		if (date) {
			var newDate = date.split(" ");
			newDate = newDate[0].split("-");

			switch (newDate[1]) {
				case "01":
					newDate[1] = "Jan"
					break;
				case "02":
					newDate[1] = "Feb"
					break;
				case "03":
					newDate[1] = "Mar"
					break;
				case "04":
					newDate[1] = "Apr"
					break;
				case "05":
					newDate[1] = "May"
					break;
				case "06":
					newDate[1] = "Jun"
					break;
				case "07":
					newDate[1] = "Jul"
					break;
				case "08":
					newDate[1] = "Aug"
					break;
				case "09":
					newDate[1] = "Sep"
					break;
				case "10":
					newDate[1] = "Oct"
					break;
				case "11":
					newDate[1] = "Nov"
					break;
				case "12":
					newDate[1] = "Dec"
					break;
			}

			date = newDate[2] + " " + newDate[1] + " " + newDate[0];
			return date;
		} else {
			return "permanently";
		}
	});

	Handlebars.registerHelper("formatNumRound", function (str) {
		if (str) {
			var type = typeof str;
			if (type !== 'number') {
				str = parseFloat(str);
			}
			str = str.toFixed(0).replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,");
		} else {
			str = 0;
		}

		return str;
	});

	Handlebars.registerHelper("formatNumRoundTarget", function (str) {
		if (str && str != 0) {
			var type = typeof str;
			if (type !== 'number') {
				str = parseFloat(str);
			}
			str = str.toFixed(0).replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,");
		} else {
			str = "-";
		}

		return str;
	});

	Handlebars.registerHelper("formatNumRoundOne", function (str) {
		if (!str || str == null) {
			str = 0;
		}
		if (typeof str == "string") {
			str = parseInt(str);
		}
		str = str.toFixed(1).replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,");
		return str;
	});

	Handlebars.registerHelper("formatNumRoundCustom", function (str, decimals) {
		if (!str || str == null) {
			str = 0;
		}
		if (typeof str == "string") {
			str = parseInt(str);
		}
		str = str.toFixed(decimals).replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,");
		return str;
	});

	/**
	 * @usage
	 * {{#ifCond v1 v2}}
	 *    {{v1}} is equal to {{v2}}
	 * {{else}}
	 *   {{v1}} is not equal to {{v2}}
	 * {{/ifCond}}
	 **/
	Handlebars.registerHelper('ifCond', function (v1, v2, options) {
		if (v1 === v2) {
			return options.fn(this);
		}
		return options.inverse(this);
	});

	Handlebars.registerHelper('ifNotCond', function (v1, v2, options) {
		if (v1 !== v2) {
			return options.fn(this);
		}
		return options.inverse(this);
	});

	Handlebars.registerHelper('ifCondNoType', function (v1, v2, options) {
		if (v1 == v2) {
			return options.fn(this);
		}
		return options.inverse(this);
	});

	Handlebars.registerHelper('ifLowerThan', function (v1, v2, options) {
		if (v1 < v2) {
			return options.fn(this);
		}
		return options.inverse(this);
	});

	Handlebars.registerHelper('ifGreaterThan', function (v1, v2, options) {
		if (v1 > v2) {
			return options.fn(this);
		}
		return options.inverse(this);
	});

	Handlebars.registerHelper("safeString", function (string) {
		return new Handlebars.SafeString(string);
	});

	Handlebars.registerHelper('selectedIfEqual', function (a, b) {
		if ((a == b) && a != '') {
			return 'selected="selected"';
		}
	});

	Handlebars.registerHelper('selectedIfEqualandNotEmpty', function (a, b) {
		if ((a == b) && a != '') {
			return 'selected="selected"';
		}
	});

	Handlebars.registerHelper('formattedDivision', function (a, b) {

		if (b == 0) {
			return '';
		} else {
			var str = Math.round(a / b);
			str = str.toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,");
			return str;
		}
	});

	Handlebars.registerHelper('selectedIfEqualandNotEmpty', function (a, b) {
		if (a == b) {
			return 'selected="selected"';
		}
	});

	Handlebars.registerHelper('MultiplyAndRoundTwo', function (a, b) {
		num = a * b;
		str = num.toFixed(2).replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,");
		return str;
	});

	Handlebars.registerHelper('roundTwoDec', function (str) {
		str = str.toFixed(2)
		return str;
	});

	Handlebars.registerHelper('roundOneUp', function (num) {
		if (!num || num == "") {
			num = 0;
		}
		if (num < 1) {
			num = Math.ceil(num);
		} else {
			num = num.toFixed(0);
		}
		return num;
	});

	Handlebars.registerHelper('getPrecentOneDigit', function (a, b) {
		if ((a + b) == 0) {
			return '';
		} else {
			var prec = (a / (a + b) * 100);
			return prec.toFixed(1).replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,");
		}
		return num;
	});

	Handlebars.registerHelper('getPercentOf', function (a, b) {
		var devide = parseFloat(a);
		var devider = parseFloat(b);

		if (devide == 0 || isNaN(devide) || isNaN(devider)) {
			return 'N/A';
		} else {
			var prec = devide / devider * 100;
			return prec.toFixed(1).replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,");
		}
		return num;
	});

	Handlebars.registerHelper('getPercentOfRound2', function (a, b) {
		var devide = parseFloat(a);
		var devider = parseFloat(b);

		if (devide == 0 || isNaN(devide) || isNaN(devider)) {
			return 'N/A';
		} else {
			var prec = devide / devider * 100;
			return prec.toFixed(2).replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,");
		}
		return num;
	});

	Handlebars.registerHelper('ifLength', function (v1, cond, v2, options) {
		if (cond === 'eq') {
			if (v1.length === v2) {
				return options.fn(this);
			}

			return options.inverse(this);
		} else if (cond === 'gt') {
			if (v1.length > v2) {
				return options.fn(this);
			}

			return options.inverse(this);
		} else if (cond === 'lt') {
			if (v1.length < v2) {
				return options.fn(this);
			}

			return options.inverse(this);
		}
	});

	Handlebars.registerHelper('ifCondLower', function (v1, v2, options) {
		if (v1.toLowerCase() === v2.toLowerCase()) {
			return options.fn(this);
		}
		return options.inverse(this);
	});

	Handlebars.registerHelper("formatAwaiting", function (str) {
		if (str == null) {
			str = "";
		}
		if (str === " ") {
			str = "";
		}
		return str;
	});

	Handlebars.registerHelper('fixCurrency', function (curr, name) {
		var retVal;

		if (curr === "ZMW") {
			retVal = "Zambian Kwacha";
		} else if (curr === "XBT") {
			retVal = "BitCoin";
		} else {
			retVal = name;
		}

		return retVal;
	});

	Handlebars.registerHelper("foreach", function (arr, options) {
		(function (fn) {
			if (!fn.map) fn.map = function (f) {
				var r = [];
				for (var i = 0; i < this.length; i++) r.push(f(this[i]));
				return r
			}
			if (!fn.filter) fn.filter = function (f) {
				var r = [];
				for (var i = 0; i < this.length; i++)
					if (f(this[i])) r.push(this[i]);
				return r
			}
		})(Array.prototype);
		if (options.inverse && !arr.length)
			return options.inverse(this);

		return arr.map(function (item, index) {
			item.$index = index;
			item.$first = index === 0;
			item.$last = index === arr.length - 1;
			return options.fn(item);
		}).join('');
	});

	Handlebars.registerHelper('toUpperCase', function (str) {
		return str.toUpperCase();
	});

	Handlebars.registerHelper('urlEncode', function (str) {
		return encodeURIComponent(str);
	});
});