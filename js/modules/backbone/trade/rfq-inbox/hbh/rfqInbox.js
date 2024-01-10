define([
	"jquery",
	"handlebars"
], function(
	$, 
	Hb
){
	Handlebars.registerHelper("getStatus", function(status) {
		if(status === "Not clicked") {
			return "unread";
		}
		else if(status === "Details viewed") {
			return "waiting for action";
		}
		else if(status === "Replied") {
			return "quoted";
		}
		else if(status === "Not interested") {
			return "declined";
		}
	});

	Handlebars.registerHelper("getStatusStyle", function(status) {
		if(status === "Not clicked") {
			return "unread";
		}
		else if(status === "Details viewed") {
			return "waiting";
		}
		else if(status === "Replied") {
			return "quoted";
		}
		else if(status === "Not interested") {
			return "declined";
		}
	});

	Handlebars.registerHelper("getAttachment", function(attachment) {
		if(attachment === "1") {
			return new Handlebars.SafeString('<img src="/img/icons/rfq-inbox/attachment_red.png" alt="" border="0" class="att"/>');
		}
		else {
			return '';
		}
	});

	Handlebars.registerHelper("getProductType", function(str) {
		if(str === "BP") {
			str = "Buyer's part number";
		}
		else if (str === "EN") {
			str = "EAN No";
		}
		else if (str === "MF") {
			str = "Manufacturer's Part No";
		}
		else if (str === "UP") {
			str = "Universal Product Code";
		}
		else if (str === "VP") {
			str = "Supplier's Part No";
		}
		else if (str === "ZIM") {
			str = "IMPA No";
		}
		else if (str === "ZIS") {
			str = "ISSA No";
		}
		else if (str === "ZIS") {
			str = "ISSA No";
		}
		else if (str === "ZMA") {
			str = "Mariner's Annual No";
		}
		else {
			str = "";
		}

		return str;
	});

	Handlebars.registerHelper("safeString", function(string) {
		return new Handlebars.SafeString(string);
	});
});