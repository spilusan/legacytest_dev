$(function () {
	var menuView = {
		toggleMenu: function (e) {
			bodyClick();

			if (e) {
				e.preventDefault();
				e.stopPropagation();
			}

			$('input').blur();
			var elem;
			/*
			 * parseInt was added to fix isue on IE11<, as it returns as an integer 
			 */
			if (parseInt($('#corporateNav').css('z-index')) === 900) {
				elem = '#corporateNav';
			} else {
				elem = '.navigations';
				if ($('body').hasClass('noscroll')) {
					$('body').removeClass('noscroll');
				} else {
					$('body').addClass('noscroll');
				}
			}

			if ($(elem).hasClass('open')) {
				$(elem).addClass('closed');
				$(elem).removeClass('open');
			} else {
				$(elem).removeClass('closed');
				$(elem).addClass('open');

				// close menun on document body click
				setTimeout(function () {
					$(document)
						.one('click.sideMenuClose', function (e) {
							var isCorporateNavEvent = $(e.srcElement).closest('header').length > 0;

							if (!isCorporateNavEvent) {
								menuView.closeAllMenus();
							}
						});
				});

				if ($('.current').closest('.show-submenu').length === 0 && $(window).width() < 1045) {
					// expand menu to current page
					setTimeout(function () {
						//$('.current').closest('.menu').siblings('.menu-label').click().closest('.menu').siblings('.menu-label').click();
						$('.current').parents('.menu').siblings('.menu-label').click();
					}, 0);
				}
			}
		},

		toggleWpSecondNav: function (e) {
			//note: window.matchMedia is not support IE, but needed only for mobile
			if (window.matchMedia && window.matchMedia('(max-width : 1044px)').matches) {
				e.preventDefault();
				$('input').blur();
				$(e.target).closest('nav.secondLevel').toggleClass('open');
			}
		},

		toggleCompDrop: function (e) {
			e.preventDefault();
			if ($(e.target).is('div')) {
				el = $(e.target).parent().parent().parent().parent().parent();
			} else {
				el = $(e.target).parent().parent().parent().parent();
			}

			el.toggleClass('open');
		},

		closeAllMenus: function (e) {
			$(document).off('click.sideMenuClose');

			$(".company").removeClass('open');

			if ($("#corporateNav").hasClass('open')) {
				$("#corporateNav").addClass('closed');
				$("#corporateNav").removeClass('open');
			} else {
				$("#corporateNav").removeClass('open');
				$("#corporateNav").removeClass('closed');
			}

			if ($(".navigations").hasClass('open')) {
				$(".navigations").addClass('closed');
				$(".navigations").removeClass('open');
			} else {
				$(".navigations").removeClass('open');
				$(".navigations").removeClass('closed');
			}

			$(".secondLevel").removeClass('open');
		},

		closeAllMenusResize: function (e) {
			$(".company").removeClass('open');
			$("#corporateNav").removeClass('open');
			$("#corporateNav").removeClass('closed');
			$(".navigations").removeClass('open');
			$(".navigations").removeClass('closed');
			$(".secondLevel").removeClass('open');
		}
	};

	var tabNav = {
		navigateTab: function (el) {
			$('li.wpTab').removeClass('selected');
			$(el).addClass('selected');
			var active = $(el).index();
			$('div.contentWpTab').removeClass('active');
			$('div.contentWpTab').eq(active).addClass('active');
		}
	};

	$('li.wpTab').on('click', function () {
		tabNav.navigateTab(this);
	});

	$('.toggleMenu').on('click', function (e) {
		menuView.toggleMenu(e);
	});

	$('.toggleMenu').on('touchend', function (e) {
		menuView.toggleMenu(e);
	});

	$('nav.secondLevel ul li.selected a').on('click', function (e) {
		menuView.toggleWpSecondNav(e);
	});

	$('.company ul li:first-child a').on('click', function (e) {
		e.stopPropagation();

		menuView.toggleCompDrop(e);
	});

	$('.navigations').click(function (e) {
		e.stopPropagation();
	});

	// If there is no second level menu, hide the content pusher so there is no white space at top of page
	if (!$('.secondLevel').length) {
		$('.content-pusher').hide();
	}

	$(window).resize(function () {
		menuView.closeAllMenusResize();
	});

	$(window).on("orientationchange", function () {
		menuView.closeAllMenusResize();
	});

	/* Check if it is an IOS, and add the CSS fix for the page to be scrollable on IOS and the scrollbar is on window level on desktop */
	var fixOs = {
		fixIfIos: function () {
			var ios = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
			if (ios === false) {
				if ($('.scrollWrapper').length > 0) {
					if ($('.scrollWrapper').css('overflow-x') === 'scroll') {
						$('.scrollWrapper').css('overflow-x', 'auto'); // 'auto' aviods x scrollbar in chrome 
					}
				}
			}
		}
	};

	fixOs.fixIfIos();

	function bodyClick() {
		$('header .menu-item.focus').removeClass('focus');
		$('#pagesNav').removeClass('submenu-open');
	}

	// App menu
	$('#pagesNav')
		.find('.menu-item')
		.each(function (e) {
			// add class to menu items that have children
			var hasChildren = $(this).children('.menu').length > 0;

			if (hasChildren) {
				$(this).addClass('has-children');
			}
		})
		.click(function (e) {
			e.stopPropagation();

			if (parseInt($('.navigations').css('z-index')) !== 900) {
				$(document.body).trigger('click.sideMenuClose');
			}

			var href = $(this).children('a').attr('href'),
				menuItem = $(this),
				submenusVisible = $('#pagesNav .show-submenu').length > 0;

			if (href) {
				e.preventDefault();
				var target = $(this).children('a').attr('target');
				if (target) {
					window.open(href, target);
				} else {
					location.href = href;
				}

				$('#pagesNav .show-submenu').removeClass('show-submenu');

				if (location.href.indexOf('#') !== -1 && $(window).width() < 1045) {
					menuView.toggleMenu();
				}

			} else {
				if (parseInt($('.navigations').css('z-index')) !== 900) {
					menuView.closeAllMenus();
				}

				$('main').one('click', bodyClick);
				$('header').one('click', bodyClick);

				// close any sibling submenus that are open
				$(this)
					.siblings()
					.removeClass('focus')
					.removeClass('show-submenu');

				$('#pagesNav .menu').attr('style', null);

				$(this).addClass('focus');

				var submenuVisible = $(this).hasClass('show-submenu');

				// toggle submenu when menu item clicked
				if (submenuVisible) {
					$(this).removeClass('show-submenu');
					$(this).removeClass('focus');

					$('#pagesNav').removeClass('submenu-open');
				} else {
					var menu0 = $(this).closest('.menu-0 > menu-item');

					$('.focus', this).removeClass('focus');

					// bind document click to close any open submenus
					$(document)
						.off('click.appNavClose')
						.one('click.appNavClose', function () {
							$('#pagesNav .show-submenu').removeClass('show-submenu');

							$(document).off('click.appNav');
						});

					// close any other submenus off the root menu
					$('#pagesNav .menu-0 > .menu-item')
						.not(menu0)
						.find('.show-submenu')
						.removeClass('show-submenu');

					//setTimeout(function () {
					menuItem.addClass('show-submenu');
					//}, submenusVisible ? 500 : 0);

					var parent = menuItem.parent();

					if (parent.is('.menu-1') && $(window).width() > 1044) {
						parent.css('overflow', 'scroll');
						parent.height(parent.prop('scrollHeight'));
						parent.css('overflow', 'visible');
						parent.width(440);
					}

					$('#pagesNav').addClass('submenu-open');
				}
			}
		});


	/*
	 * This is a hack for SPR, as the selected option cannot be managed properly on backend
	 * as #pageId not accessible there, and navigation is managed by Javascript 
	 */
    $("a[href^='/reports/']").each(function () {
        if (location.href.indexOf($(this).attr('href')) === -1) {
            $(this).removeClass('current').parent().removeClass('current');
        } else {
            $(this).addClass('current').parent().addClass('current');
        }
    });

	$('html').attr("data-useragent", navigator.userAgent);
	$('html').attr("data-platform", navigator.platform);
});