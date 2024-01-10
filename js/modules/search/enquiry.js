define(['modal'], function ($) {
	$('div.contextual.help.howtouse').live('click', function() {
		$('#howtouse').ssmodal({title: "How to use the 'Send New RFQ' tool"});
	});
});