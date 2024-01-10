$(document).ready(function(){

	$('input[name=searchWhat]').autoComplete({
		backwardsCompatible: true,
		ajax: '/search/autocomplete/what/format/json/',
		useCache: false,
		minChars: 3,
		preventEnterSubmit: true,
		tabToNext: 'input[name=searchText]',
		leftAdjustment: -8,
		width: 325,
		onSelect: function(data, $li){
			$('#searchWhat').val(data.code);
			$('#searchType').val(data.type);
		}
	});

	$('input[name=searchText]').autoComplete({
		backwardsCompatible: true,
		ajax: '/search/autocomplete/portsAndCountries/format/json/',
		useCache: false,
		minChars: 3,
		preventEnterSubmit: false,
		width: 325,
		leftAdjustment: -8,
		onSelect: function(data, $li){
			$('#searchWhere').val(data.code);
		}
	});

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
		var self = this;
		if (!$('#search_box') || !$('#search_box').hasClass('zone_search_box')) {
			$('a.search-button').html('<img src="/images/buttons/search-button-animated-whitebg.gif" alt="" />');
		}
		if ($('#search_box') && $('#search_box').hasClass('zone_search_box')) {
			$('a.search-button').html('<div id="animitedSearchButtonIcon"></div>');
		}

		window.setTimeout(function() {
			self.submit();
		}, 100);
	});

	$('#searchButton').live('click', function(e) {
		e.preventDefault();
		if( typeof window.searchIsOngoing == 'undefined' )
		{
			$(this).attr('disabled', 'disabled');
			window.searchIsOngoing = true;
			$('#anonSearch').submit();
		}
    });

	$('#zoneExitButton').live('click', function(e) {
		$('#ssrc').val(zoneExitSSRC);
		$('#anonSearch').attr("action","/search/results");
		$('#anonSearch').submit();
		});
		
	$('#searchBoxZoneTitle').live('click', function(e) {
		$('#searchWhat').val('');
		$('#searchWhere').val('');
		$('#searchText').val('');
		$('#anonSearch').submit();
    });
});
