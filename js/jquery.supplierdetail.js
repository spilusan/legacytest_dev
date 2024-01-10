$(window).load(function () {
	$('body').delegate('a.backBtn', 'click', function (e) {
		e.preventDefault();
		$('a.contact_toggle').click();
	});

	var loginUrl = '/user/redirect-to-cas/';

	if (getProfileRecId) {
		$("body").delegate(".tnidHolder", "copy", function() {
			$.get(
				"/supplier/log-value-event?getprofilerecid=" +
				getProfileRecId +
				"&a=TNID_IS_COPIED"
			);
		});
	}

	$(".competitor .block").live('click', function (e) {

		if (!jQuery.browser.msie) {
			window.location = $(this).find("a").attr("href");
			return false;
		}

		var a = document.createElement("a");
		a.setAttribute("href", $(this).find("a").attr("href"));
		a.style.display = "none";
		$("body").append(a);
		a.click();

		return false;
	});

	var thisAnchor = jQuery.url.attr("anchor") ? jQuery.url.attr("anchor") : '';
	var anchor = thisAnchor.split('/')[0] ? thisAnchor.split('/')[0] : (SS.defaultTab ? SS.defaultTab : 'profile');

	if (anchor == 'catalogue') {
		$('#profile').hide();
		$('#contact_content').hide();
		$('#catalogue_box').show();
		$('#reputation_box').hide();
			$('html, body').animate({
			scrollTop: 0
		}, 0);
		$('li#catalogue_toggle').removeClass('off').addClass('on');
		$('li#profile_toggle').removeClass('on').addClass('off');
		$('li#contact_toggle').removeClass('on').addClass('off');
		$('li#reviews_toggle').removeClass('on').addClass('off');
		$('.content_wide_body_right').hide();
		if (getProfileRecId) {
			$.get('/supplier/log-catalogue-impression-event?getprofilerecid=' + getProfileRecId);
		}
	} else if (anchor == 'contact_box') {
		$('#profile').hide();
		$('#catalogue_box').hide();
		$('#contact_content').show();
		$('#reputation_box').hide();
		$('.contact_supplier_button.contact_toggle').hide();
		$('html, body').animate({
			scrollTop: 0
		}, 0);

		$('li#contact_toggle').removeClass('off').addClass('on');
		$('li#profile_toggle').removeClass('on').addClass('off');
		$('li#catalogue_toggle').removeClass('on').addClass('off');
		$('li#reviews_toggle').removeClass('on').addClass('off');
		if (getProfileRecId) {
			$.get('/supplier/log-value-event?getprofilerecid=' + getProfileRecId + '&a=TAB_TO_VIEW_CONTACT_IS_CLICKED');
		}
		
		//Log regular event
		$.ajax({
			type: "POST",
			url: '/reports/log-js-event',
			data: {
				event: 'contact-requests',
				info: supplierTnid
			},
			success: function (response) {

			},
			error: function (error) {

			}
		});

		$('.content_wide_body_right').show();
	} else if (anchor == 'reviews') {
		$('#profile').hide();
		$('#catalogue_box').hide();
		$('#contact_content').hide();
		$('#reputation_box').show();
		$('html, body').animate({
			scrollTop: 0
		}, 0);
		$('li#contact_toggle').removeClass('on').addClass('off');
		$('li#profile_toggle').removeClass('on').addClass('off');
		$('li#catalogue_toggle').removeClass('on').addClass('off');
		$('li#reviews_toggle').removeClass('off').addClass('on');
		$('#').attr('href', loginUrl + '?returnUrl=' + $.URLEncode(window.location) + '#reg');

		var suplierURL = jQuery.url.segment(jQuery.url.segment() - 1);
		$.get('/reviews/supplier/s/' + suplierURL, function (data) {
			$('#reputation_box').html(data);
		});
		$('.content_wide_body_right').hide();
		
	} else {
		$('#contact_content').hide();
		$('#catalogue_box').hide();
		$('#profile').show();
		$('#reputation_box').hide();
		$('html, body').animate({
			scrollTop: 0
		}, 0);
		$('li#profile_toggle').removeClass('off').addClass('on');
		$('li#contact_toggle').removeClass('on').addClass('off');
		$('li#catalogue_toggle').removeClass('on').addClass('off');
		$('li#reviews_toggle').removeClass('on').addClass('off');
		$('.content_wide_body_right').show();
	}

	$('li a.profile_toggle').live('click', function (e) {
		$.cookie('detShow', null, {
			path: '/'
		});
		$.cookie('tnidShow', null, {
			path: '/'
		});
		$('#contact_content').hide();
		$('#catalogue_box').hide();
		$('#reputation_box').hide();
		$('#profile').show();
		$('.mainMenu').hide();
		$('.contact_supplier_button.contact_toggle').show();
		$('li#profile_toggle').removeClass('off').addClass('on');
		$('li#contact_toggle').removeClass('on').addClass('off');
		$('li#catalogue_toggle').removeClass('on').addClass('off');
		$('li#reviews_toggle').removeClass('on').addClass('off');
		window.location.hash = '';
		if (map1) {
			google.maps.event.trigger(map1, 'resize');
			map1.setCenter(marker1.getPosition());
		}
		$('.content_wide_body_right').show();
		$('.mainMenu').hide();
		$('.loginForm').hide();
		return false;
	});

	$('.tnidLink').live('click', function (e) {
		$.cookie('detShow', null, {
			path: '/'
		});
		$.cookie('tnidShow', null, {
			path: '/'
		});
		$('#profile').hide();
		$('#catalogue_box').hide();
		$('#contact_content').show();
		$('#reputation_box').hide();
		$('.contact_supplier_button.contact_toggle').hide();
		$('li#contact_toggle').removeClass('off').addClass('on');
		$('li#profile_toggle').removeClass('on').addClass('off');
		$('li#catalogue_toggle').removeClass('on').addClass('off');
		$('li#reviews_toggle').removeClass('on').addClass('off');
		$('.contact_toggle_tnid_content').show();

		window.location.hash = 'contact_box';
		$( 'html, body' ).animate( { scrollTop: 0 }, 0 );
		if (map3) {
			google.maps.event.trigger(map3, 'resize');
			map3.setCenter(marker3.getPosition());
		}
		$('.mainMenu').show();
		$('.loginForm').hide();
		$('.content_wide_body_right').show();

		if (getProfileRecId) {
			$.get('/supplier/log-value-event?getprofilerecid=' + getProfileRecId + '&a=TBUTTON_TO_VIEW_TNID_IS_CLICKED');
			$.get('/supplier/log-value-event?getprofilerecid=' + getProfileRecId + '&a=TNID_IS_VIEWED');
		}

		//Log regular event
		$.ajax({
			type: "POST",
			url: '/reports/log-js-event',
			data: {
				event: 'contact-requests',
				info: supplierTnid
			},
			success: function (response) {

			},
			error: function (error) {

			}
		});
		return false;
	});
	
	$('a.contact_toggle').live('click', function (e) {
		$.cookie('detShow', null, {
			path: '/'
		});
		$.cookie('tnidShow', null, {
			path: '/'
		});
		$('#profile').hide();
		$('#catalogue_box').hide();
		$('#contact_content').show();
		$('#reputation_box').hide();
		$('.contact_supplier_button.contact_toggle').hide();
		$('li#contact_toggle').removeClass('off').addClass('on');
		$('li#profile_toggle').removeClass('on').addClass('off');
		$('li#catalogue_toggle').removeClass('on').addClass('off');
		$('li#reviews_toggle').removeClass('on').addClass('off');
		window.location.hash = 'contact_box';
		$( 'html, body' ).animate( { scrollTop: 0 }, 0 );
		if (map3) {
			google.maps.event.trigger(map3, 'resize');
			map3.setCenter(marker3.getPosition());
		}
		$('.mainMenu').show();
		$('.loginForm').hide();
		$('.content_wide_body_right').show();
		if (getProfileRecId) {
			$.get('/supplier/log-value-event?getprofilerecid=' + getProfileRecId + '&a=TAB_TO_VIEW_CONTACT_IS_CLICKED');
		}

		//Log regular event
		$.ajax({
			type: "POST",
			url: '/reports/log-js-event',
			data: {
				event: 'contact-requests',
				info: supplierTnid
			},
			success: function (response) {

			},
			error: function (error) {

			}
		});
		return false;
	});

	$('div.map').live('click', function (e) {

		//Get the A tag
		var id = "#map_tooltip";

		if (!$('#mask')) {
			bodyEl = $$('body')[0];
			bodyEl.append('<div id="mask"></div>');
		}

		//Get the screen height and width
		var maskHeight = $(document).height();
		var maskWidth = $(window).width();

		//Set heigth and width to mask to fill up the whole screen
		$('#mask').css({
			'width': maskWidth,
			'height': maskHeight
		});

		//transition effect
		$('#mask').fadeIn(500);
		$('#mask').fadeTo("slow", 0.8);

		//Set the popup window to center

		$(id).css("position", "absolute");
		$(id).css("z-index", "10700");
		$(id).css("width", "466px");
		$(id).css("height", "647px");
		$(id).css("top", ($(window).height() - $(id).height()) / 2 + $(window).scrollTop() + "px");
		$(id).css("left", ($(window).width() - $(id).width()) / 2 + $(window).scrollLeft() + "px");

		//transition effect
		$(id).fadeIn(300);
		if (map2) {
			google.maps.event.trigger(map2, 'resize');
			map2.setCenter(marker2.getPosition());
		}
	});

	$('li a.reviews_toggle').live('click', function (e) {
		$.cookie('detShow', null, {
			path: '/'
		});
		$.cookie('tnidShow', null, {
			path: '/'
		});
		$('.mainMenu').hide();
		$('#contact_content').hide();
		$('#catalogue_box').hide();
		$('#profile').hide();
		$('#reputation_box').show();
		$('.contact_supplier_button.contact_toggle').show();
		$('.content_wide_body_right').hide();
		//$( 'html, body' ).animate( { scrollTop: 0 }, 0 );
		$('li#profile_toggle').removeClass('on').addClass('off');
		$('li#contact_toggle').removeClass('on').addClass('off');
		$('li#catalogue_toggle').removeClass('on').addClass('off');
		$('li#reviews_toggle').removeClass('off').addClass('on');
		var suplierURL = jQuery.url.segment(jQuery.url.segment() - 1);
		$.get('/reviews/supplier/s/' + suplierURL, function (data) {
			$('#reputation_box').html(data);
		});
		window.location.hash = 'reviews';
		return false;
	});

	$('li a.catalogue_toggle').live('click', function (e) {
		e.preventDefault();
		$.cookie('detShow', null, {
			path: '/'
		});
		$.cookie('tnidShow', null, {
			path: '/'
		});

		$('.mainMenu').hide();
		$('#profile').hide();
		$('#contact_content').hide();
		$('#catalogue_box').show();
		$('#reputation_box').hide();
		$('.content_wide_body_right').hide();
		$('.contact_supplier_button.contact_toggle').show();
		$('li#catalogue_toggle').removeClass('off').addClass('on');
		$('li#profile_toggle').removeClass('on').addClass('off');
		$('li#contact_toggle').removeClass('on').addClass('off');
		$('li#reviews_toggle').removeClass('on').addClass('off');

		window.location.hash = 'catalogue';
		if (getProfileRecId) {
			$.get('/supplier/log-catalogue-impression-event?getprofilerecid=' + getProfileRecId);
		}
		return false;
	});

	$('a.sidebodge').live('click', function () {
		$(this).css('color', 'black');
	});

	$("#loginForm").keypress(function (e) {
		if (e.which == 13) {
			$("#loginSubmit2").click();
		}
	});

	$('#upgradeListingLink').live('click', function (e) {
		$.get('/supplier/log-upgrade-listing-clicked/format/json/getprofilerecid/' + getProfileRecId, function (data) {
			window.location = $('#upgradeListingLink').attr('href');
		});
		return false;
	});

	$.get('/reviews/supplier-count/s/' + jQuery.url.segment(jQuery.url.segment() - 1), function (data) {
		if (parseInt(data) > 0) {
			$('#reviewsToggleText').html("Reviews (" + data + ")");
		}
	});

	$('.contact_toggle_wrapper').live('click', function (e) {
		var togglElement = $(this).next();
		var toggleClassName = togglElement.attr('class');
		var toggleArrowElement = $(this).children('span').find('i');
		if (toggleArrowElement.hasClass('fa-angle-down')) {
			toggleArrowElement.removeClass('fa-angle-down');
			toggleArrowElement.addClass('fa-angle-up');
			togglElement.slideDown();
			if (getProfileRecId) {
				var logEvent = [];

				switch(toggleClassName) {
					case 'contact_toggle_email_content':
					logEvent.push('CONTACT_EMAIL_IS_VIEWED');
						break;
					case 'contact_toggle_phone_content':
						logEvent.push('CONTACT_IS_VIEWED');
						logEvent.push('BUTTON_TO_VIEW_CONTACT_IS_CLICKED');
						break;
					case 'contact_toggle_tnid_content':
						logEvent.push('TNID_IS_VIEWED');
						logEvent.push('BUTTON_TO_VIEW_TNID_IS_CLICKED');
						break;
				}
	
				for (var i = 0; i < logEvent.length; i++) {
					$.get('/supplier/log-value-event?getprofilerecid=' + getProfileRecId + '&a=' + logEvent[i]);
				}
}
		} else {
			toggleArrowElement.removeClass('fa-angle-up');
			toggleArrowElement.addClass('fa-angle-down');
			togglElement.slideUp();
		}
	});

	$('a.rfq_btn').live('click', function (e) {
		if (getProfileRecId) {
			$.get('/supplier/log-value-event?getprofilerecid=' + getProfileRecId + '&a=BUTTON_TO_SEND_RFQ_IS_CLICKED');
		}
	});

	$('a.website_btn').live('click', function (e) {
		if (getProfileRecId) {
			$.get('/supplier/log-value-event?getprofilerecid=' + getProfileRecId + '&a=BUTTON_TO_WEBSITE_IS_CLICKED');
		}
	});
});
