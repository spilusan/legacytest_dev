define([
	'jquery',
	'underscore',
	'libs/jquery.uniform'
], function(
	$, 
	_, 
	Uniform
){
	var initialize = function(){
		$(document).ready(function(){
		    $('input[type="text"]').uniform(); 
		});
	}

	initialize();
});
