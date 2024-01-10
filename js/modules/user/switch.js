define([
	'cookie',
	'jquery'
], function(cookie, $) {
	$('#userCompanySwitch select').bind('change', function(e){
		e.preventDefault(e);
		$('#userCompanySwitch').submit();
	});
});