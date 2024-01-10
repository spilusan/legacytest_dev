$(document).ready(function(){
	$('body').ajaxStart(function(){
        $('.spinner').show();
    });
    $('body').ajaxStop(function(){
        $('.spinner').hide();
    });
	
	// Default text for company name search buyer/supplier options
	var autoCompleteTxt = 
		{
			'cns-opt-b': "Type in the name of the buyer",
			'cns-opt-s': "Type in the name of the supplier"
		};
	
	$('input[name=company_search]').val(autoCompleteTxt['cns-opt-s']);
	
	$('#add-company').click(function () {
		$('#no-companies-msg').hide();
		$('#add-company-block').slideDown(300, function () {
			$('input[name=company_search]').val(autoCompleteTxt[$('.company-name-search [class=on]').attr('name')]);
		});
		return false;
	});
	
	$('[class=cancel-company-search]').click(function() {
		$('#add-company-block').slideUp(300, function() {
			// Do nothing
		});
		return false;
	});
	
	$('input[name=company_search]').click(function() {
		for (var s in autoCompleteTxt)
		{
			if (this.value == autoCompleteTxt[s])
			{
				$(this).val('');
				break;
			}
		}
	});
	
	$('input[name=company_search]').blur(function() {
		if (this.value === '') {
			$(this).val(autoCompleteTxt[$(this).parents('.company-name-search:eq(0)').find('[class=on]').attr('name')]);
		}
	});
	
	// Form Ajax Url for Company name search
	var cnsGetSearchUrl = function (cnsBlock, sType)
	{
		var sUrl = '/profile/company-search/format/json';
		
		if (sType == 'b')
		{
			sUrl += '/type/b';
		}
		else
		{
			sUrl += '/type/v';
		}
		
		if (cnsBlock.hasClass('excUsrComps'))
		{
			sUrl += '/excUsrComps/1';
		}
		
		if (cnsBlock.hasClass('excNonJoinReqComps'))
		{
			sUrl += '/excNonJoinReqComps/1';
		}
		
		return sUrl;
	};
	
	$('input[name=company_search]').autoComplete({
		backwardsCompatible: true,
		ajax: cnsGetSearchUrl($('.company-name-search:eq(0)'), 'v'),
		postData: {
			unpublished: 0,
			hideTestAccounts: 1,
			supplierActiveStatus: 1
		},
		useCache: false,
		minChars: 3,
		list: 'auto-complete-list-wide',
		preventEnterSubmit: true,
		onSelect: function(data) {
			if (confirm('Are you sure you wish to add ' + data.value + ' to your list of companies?')) {
				// split the code
				var codes = data.code.split('-');
				
				// make an ajax call to request an invite
				$.post('/profile/join-company/format/json', { type: codes[0], id: codes[1] }, function(data){
					if (data.ok === true) {
						// check if the pending companies block is showing
						if ($('#pending-companies').is(":hidden")) {
							$('#pending-companies').show();
						}
						
						// add a pending block
						$('#pending-companies').append('<div class="pending-block" id="joinreq-' + data.company.joinRequest.PUCR_ID + '">' +
													   '<div class="logo-holder"><img src="/images/layout_v2/profile/pending-company.png" alt="" /></div>' +
													   '<h3>' + data.company.name + '</h3>' +
													   '<h4>' + (data.company.type == 'v' ? 'Supplier' : 'Buyer') + ', ' + data.company.location + '</h4>' + 
													   '<hr class="dotted" style="width:460px; margin-bottom:8px;" />' +
													   '<div class="clear"></div>' + 
													   '<div class="icons-holder"><div class="company-icons sml-action-set"><a href="#" title="Cancel Request" class="cancel-request"><img src="/images/icons/bin.png" alt="Cancel Request" /><span>Withdraw Request</span></a></div></div>' +
													   '</div>');
						
						$('#add-company-block').slideUp(300);
						
						if ($('#jqPendingCompaniesOverlay').is(":hidden")) {
							$('#jqPendingCompaniesOverlay').fadeIn('fast');
						}
						
						// update the pending count blocks around the page
						var count = parseInt($('#jqPendingCountRoot').html());
						count++;
						
						$('span[class="jqPendingCount"]').html(function () {
							return count;
						});
						
						// make sure the text makes sense
						var plural = (count == 1) ? 'company is' : 'companies are';
						$('span[class="jqCompanyPlural"]').html(function () {
							return plural;
						});
						
						if (!$('#no-companies-msg').is(":hidden")) {
							$('#no-companies-msg').hide();
						}
						
					} else {
						// show an error message - alert for now
						alert(data.msg);
					}
				});
			}
			
			return false;
		},
		width: 450
	});
	
	// Handle click on company name search supplier/buyer tab
	$('.company-name-search [name|=cns-opt] a').click(function() {
		
		var cnsBlock = $(this).parents('.company-name-search:eq(0)');
		var clickedOpt = $(this).parents('[name|=cns-opt]:eq(0)').get(0);
		
		cnsBlock.find('[name|=cns-opt]').each(function () {
			if ($(this).get(0) == clickedOpt)
			{
				$(this).removeClass('off').addClass('on');
			}
			else
			{
				$(this).removeClass('on').addClass('off');
			}
		});
		
		var selectedName = cnsBlock.find('[class=on]').attr('name');
		var searchBox = cnsBlock.find('[name=company_search]');
		
		if (selectedName == 'cns-opt-b')
		{
			searchBox.val(autoCompleteTxt[selectedName]);
			searchBox.autoComplete('option', 'ajax', cnsGetSearchUrl(cnsBlock, 'b'));
		}
		else if (selectedName == 'cns-opt-s')
		{
			searchBox.val(autoCompleteTxt[selectedName]);
			searchBox.autoComplete('option', 'ajax', cnsGetSearchUrl(cnsBlock, 'v'));
		}
		
		return false;
	});
	
	$('a[class="cancel-company-search"]').click(function() {
		$('#add-company-block').slideUp(300);
	});
	
	$('a[class="cancel-request"]').live('click', function(){
		if (confirm('Are you sure you want to cancel your request to join this company?')) {
			var code = $(this).closest('.pending-block').attr('id');
			codes = code.split('-');
			
			$.post('/profile/withdraw-join-request/format/json', { reqId: codes[1] }, function(data){
				if (data.ok === true) {
					$('#' + code).slideUp(300);
					
					// update the pending count blocks around the page
					var count = parseInt($('#jqPendingCountRoot').html());
					count--;

					$('span[class="jqPendingCount"]').html(function () {
						return count;
					});

					// make sure the text makes sense
					var plural = (count == 1) ? 'company is' : 'companies are';
					$('span[class="jqCompanyPlural"]').html(function () {
						return plural;
					});
					
					if (count === 0) {
						$('#pending-companies').slideUp(300);
						$('#jqPendingCompaniesOverlay').fadeOut('fast');
					}
				} else {
					alert(data.msg);
				}
			});
		}
		
		return false;
	});
	
	$('button[class="remove-company"]').click(function(){
		var code = $(this).closest('.approved-block').attr('id');
		if (confirm('Are you sure you wish to remove this company from your profile?')) {
			var codes = code.split('-');
			$.post('/profile/leave-company/format/json', { type: codes[0], id: codes[1] }, function(data){
				if (data.ok === true) {
					$('#' + code).slideUp(300);
				} else {
					alert(data.msg);
				}
			});
		}
		
		return false;
	});

	setTimeout(function(){$('h3[class="success-message"]').slideUp(500);}, 5000);
});

$(window).load(function () {
    $('img.logo').each(function () {
        $(this).load();
        var w = $(this).width();
        var h = $(this).height();
        
        if (w >= h) {
			$(this).addClass('landscape');
		}
		else
		{
			$(this).addClass('portrait');
		}
	});
});
