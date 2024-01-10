define([
	"jquery",
	"handlebars"
], function(
	$, 
	Hb
){
	Handlebars.registerHelper('getPriority', function(priority) {
		var ret = '';
		if(priority === 'Y'){
			ret = '<img src="/img/buyer/rfq-outbox/priority-red.png" border="0" alt="Priority" />';
		}
		return new Handlebars.SafeString(ret);
	});

	Handlebars.registerHelper('getMatchStat', function(ms) {
		var ret = '';
		if(ms){
			ret = '<span style="color: green;">SpotSource/AutoSource Used</span>';
		}
		else {
			ret = '<span style="color: black;">Buyer selected suppliers only</span>';
		}
		return new Handlebars.SafeString(ret);
	});
});