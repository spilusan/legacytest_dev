define([
	"jquery"
], function(
	$
){
	var initialize = function(){
		$('.switchContainer').bind('click', function(){
			$('.switchContainer ul.companyList').show();
		});
	}

	return {
		initialize: initialize
	};
});
