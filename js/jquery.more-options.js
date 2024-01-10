$(document).ready(function(){
	$('#categoriesMore').click(function() {

		if ($('#moreOptions').is(':hidden')){
			var position = $('#categoriesMore').offset();
			var searchResultsPosition = $('#searchResults').offset();
			$('#moreOptionsTitle').text('Browse more categories');
			$('#moreOptionsSearchLabel').text('Type for a category:');
			$('#moreOptionsFilterType').attr("value","categories");
			$('input[name=moreOptionsWhat]').autoComplete('destroy');
			$('input[name=moreOptionsWhat]').autoComplete({
				backwardsCompatible: true,
				//dataSupply: allCategories,
				postData: {format:'json',autoCompleteFilter:'categories',optionsCacheId:optionsCacheId},
				ajax:"/search/autocomplete/searchRefinement/format/json/",
				useCache: false,
				minChars: 0,
				width: 335,
				leftAdjustment: -8,
				preventEnterSubmit: true,
				onRollover: function(data, $li){
					$('#moreOptionsWhat').val(data.value);
				},
				onSelect: function (data, $li){
					if ($('#category_'+data.id).length)
					{
						$('#category_'+data.id).attr("checked","true");
					}
					else
					{
						$('<li><input name="filters[categoryId][]" value="'+data.id+'" id="category_'+data.id+'" type="checkbox" checked="checked"/> <label for="category_'+data.id+' ?>">'+data.value+'</label></li>').insertAfter('#categoriesOptionsBox');
					}
					$('form[name=sideSearch]').submit();
					$('#moreOptionsWhat').val('');
					$('#moreOptions').hide();

				}
			});
			$("#moreOptions").css( { "left": (searchResultsPosition.left-5) + "px", "top":(position.top-35) + "px" } );

			$('#moreOptions').show();
			$('input[name=moreOptionsWhat]').autoComplete('search');
			$('#moreOptionsWhat').focus();
		}
		else
		{
			$('#moreOptionsWhat').val('');
			$('input[name=moreOptionsWhat]').autoComplete('destroy');
			$('#moreOptions').hide();
		}
		

	});

	$('#brandsMore').click(function() {
		if ($('#moreOptions').is(':hidden')){
			var position = $('#brandsMore').offset();
			var searchResultsPosition = $('#searchResults').offset();
			$('#moreOptionsTitle').text('Browse more brands');
			$('#moreOptionsSearchLabel').text('Type for a brand:');
			$('#moreOptionsFilterType').attr("value","brands");
			$('input[name=moreOptionsWhat]').autoComplete('destroy');
			$('input[name=moreOptionsWhat]').autoComplete({
				backwardsCompatible: true,
				postData: {format:'json',autoCompleteFilter:'brands',optionsCacheId:optionsCacheId},
				ajax:"/search/autocomplete/searchRefinement/format/json/",
				useCache: false,
				minChars: 0,
				width: 335,
				leftAdjustment: -8,
				preventEnterSubmit: true,
				onRollover: function(data, $li){
					$('#moreOptionsWhat').val(data.value);
				},
				onSelect: function (data, $li){
					if ($('#brand_'+data.id).length)
					{
						$('#brand_'+data.id).attr("checked","true");
					}
					else
					{
						$('<li><input name="filters[brandId][]" value="'+data.id+'" id="brand_'+data.id+'" type="checkbox" checked="checked"/> <label for="brand_'+data.id+' ?>">'+data.value+'</label></li>').insertAfter('#brandsOptionsBox');
					}
					$('form[name=sideSearch]').submit();
					$('#moreOptionsWhat').val('');
					$('#moreOptions').hide();

				}
			});
			$("#moreOptions").css( { "left": (searchResultsPosition.left-5) + "px", "top":(position.top-35) + "px" } );

			$('#moreOptions').show();
			$('input[name=moreOptionsWhat]').autoComplete('search');
			$('#moreOptionsWhat').focus();
		}
		else
		{
			$('#moreOptionsWhat').val('');
			$('input[name=moreOptionsWhat]').autoComplete('destroy');
			$('#moreOptions').hide();
		}
		
	});

	$('#membershipsMore').click(function() {
		if ($('#moreOptions').is(':hidden')){
			var position = $('#membershipsMore').offset();
			var searchResultsPosition = $('#searchResults').offset();
			$('#moreOptionsTitle').text('Browse more memberships');
			$('#moreOptionsSearchLabel').text('Type for a membership:');
			$('#moreOptionsFilterType').attr("value","memberships");
			$('input[name=moreOptionsWhat]').autoComplete('destroy');
			$('input[name=moreOptionsWhat]').autoComplete({
				backwardsCompatible: true,
				postData: {format:'json',autoCompleteFilter:'memberships',optionsCacheId:optionsCacheId},
				ajax:"/search/autocomplete/searchRefinement/format/json/",
				useCache: false,
				minChars: 0,
				width: 335,
				leftAdjustment: -8,
				preventEnterSubmit: true,
				onRollover: function(data, $li){
					$('#moreOptionsWhat').val(data.value);
				},
				onSelect: function (data, $li){
					if ($('#membership_'+data.id).length)
					{
						$('#membership_'+data.id).attr("checked","true");
					}
					else
					{

						$('<li><input name="filters[membershipId][]" value="'+data.id+'" id="membership_'+data.id+'" type="checkbox" checked="checked"/> <label for="membership_'+data.id+' ?>">'+data.value+'</label></li>').insertAfter('#membershipsOptionsBox');
					}
					$('form[name=sideSearch]').submit();
					$('#moreOptionsWhat').val('');
					$('#moreOptions').hide();

				}
			});
			$("#moreOptions").css( { "left": (searchResultsPosition.left-5) + "px", "top":(position.top-35) + "px" } );

			$('#moreOptions').show();
			$('input[name=moreOptionsWhat]').autoComplete('search');
			$('#moreOptionsWhat').focus();
		}
		else
		{
			$('#moreOptionsWhat').val('');
			$('input[name=moreOptionsWhat]').autoComplete('destroy');
			$('#moreOptions').hide();
		}

	});

	$('#moreOptionsClose').click(function() {
		$('#moreOptionsWhat').val('');
		$('input[name=moreOptionsWhat]').autoComplete('destroy');
		$('#moreOptions').hide();
	});

	$('#certificationsMore').click(function() {
		if ($('#moreOptions').is(':hidden')){
			var position = $('#certificationsMore').offset();
			var searchResultsPosition = $('#searchResults').offset();
			$('#moreOptionsTitle').text('Browse more certifications');
			$('#moreOptionsSearchLabel').text('Type for a certification:');
			$('#moreOptionsFilterType').attr("value","certifications");
			$('input[name=moreOptionsWhat]').autoComplete('destroy');
			$('input[name=moreOptionsWhat]').autoComplete({
				backwardsCompatible: true,
				postData: {format:'json',autoCompleteFilter:'certifications',optionsCacheId:optionsCacheId},
				ajax:"/search/autocomplete/searchRefinement/format/json/",
				useCache: false,
				minChars: 0,
				width: 335,
				leftAdjustment: -8,
				preventEnterSubmit: true,
				onRollover: function(data, $li){
					$('#moreOptionsWhat').val(data.value);
				},
				onSelect: function (data, $li){
					if ($('#certification_'+data.id).length)
					{
						$('#certification_'+data.id).attr("checked","true");
					}
					else
					{

						$('<li><input name="filters[certificationId][]" value="'+data.id+'" id="certification_'+data.id+'" type="checkbox" checked="checked"/> <label for="certification_'+data.id+' ?>">'+data.value+'</label></li>').insertAfter('#certificationsOptionsBox');
					}
					$('form[name=sideSearch]').submit();
					$('#moreOptionsWhat').val('');
					$('#moreOptions').hide();

				}
			});
			$("#moreOptions").css( { "left": (searchResultsPosition.left-5) + "px", "top":(position.top-35) + "px" } );

			$('#moreOptions').show();
			$('input[name=moreOptionsWhat]').autoComplete('search');
			$('#moreOptionsWhat').focus();
		}
		else
		{
			$('#moreOptionsWhat').val('');
			$('input[name=moreOptionsWhat]').autoComplete('destroy');
			$('#moreOptions').hide();
		}

	});

});


