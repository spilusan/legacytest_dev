define([
	"jquery",
	"handlebars"
], function(
	$, 
	Hb
){
	Handlebars.registerHelper('eachVisible', function(context, options) {
		var ret = "";
		var count = 0;
		for (var i in context) {
			if(context[i].status !== "HIDDEN") {
				ret = ret + options.fn(context[i]);
				count++;
			}
		}
		if(count == 0){
			return '<h2>No contact person available.</h2>';
		}
		else {
			return ret;
		}
	});
});