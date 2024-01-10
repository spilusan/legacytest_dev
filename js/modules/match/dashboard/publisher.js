define([
	"jquery",
	"jqueryui/datepicker",
	"libs/jquery.uniform"
], function($, DatePicker, Uniform){
	$(function(){
		$(".uniform").uniform();
		$('.datepicker').datepicker({
			autoSize: false,
			dateFormat: 'dd/mm/yy'			
		});
	});	
});
