$(document).ready(function(){	
	$('#searchWhat').keypress(function(e) {
		if (e.which == '13') {
			$('#anonSearch').submit();
		}
	});
	
	$('#searchText').keypress(function(e) {
		if (e.which == '13') {
			$('#anonSearch').submit();
		}
	});
	
	$('#anonSearch').live('submit', function(e) {
		e.preventDefault();		
		this.submit();
		if (!$('#search_box') || !$('#search_box').hasClass('zone_search_box')) {
			$('a.search-button').html('<img src="/images/buttons/search-button-animated-bluebg.gif" alt="" />');
		}
	});
	
	$('#searchButton').live('click', function(e) {
		e.preventDefault();
		$('#anonSearch').submit();
    });
});