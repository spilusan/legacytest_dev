define([
	"jquery",
	"handlebars"
], function(
	$, 
	Hb
){
	Handlebars.registerHelper("getMargin", function(depth) {
		var margin = 40 * depth - 40;
		var style = 'style="margin-left:'+margin+'px;"';
		return new Handlebars.SafeString(style);
	});

	Handlebars.registerHelper("getOpen", function(open) {
		if(open) {
			var string = "expanded";
			
		}
		else {
			var string = "open";
		}
		return new Handlebars.SafeString(string);
	});
});