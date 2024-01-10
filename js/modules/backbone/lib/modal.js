/**
 * ShipServModal is based on SimpleModal by Eric Martin http://SimpleModal.com/
 * Tweaks include: simplification, is a requireJS module, requires its own CSS 
 * via CSSP, custom ShipServ wrapping according to our style guidelines
 *
 * The purpose is to create a modal dialogue on a page, populated with
 * either DOM elements or HTML directly. The wrapper and behaviour
 * will conform to ShipServ style guidelines.
 *
 * There are two ways to call ShipServModal:
 * 1) As a chained function on a jQuery object, like $('#myDiv').ssmodal();.
 * This call would place the DOM object, #myDiv, inside a modal dialog.
 * Chaining requires a jQuery object. An optional options object can be
 * passed as a parameter.
 *
 * @example $('<div>my data</div>').ssmodal({options});
 * @example $('#myDiv').ssmodal({options});
 * @example jQueryObject.ssmodal({options});
 *
 * 2) As a stand-alone function, like $.ssmodal(data). The data parameter
 * is required and an optional options object can be passed as a second
 * parameter. This method provides more flexibility in the types of data
 * that are allowed. The data could be a DOM object, a jQuery object, HTML
 * or a string.
 *
 * @example $.ssmodal('<div>my data</div>', {options});
 * @example $.ssmodal('my data', {options});
 * @example $.ssmodal($('#myDiv'), {options});
 * @example $.ssmodal(jQueryObject, {options});
 * @example $.ssmodal(document.getElementById('myDiv'), {options});
 *
 * A ShipServModal call can contain multiple elements, but only one modal
 * dialog can be created at a time. Which means that all of the matched
 * elements will be displayed within the modal container.
 *
 *
 * ShipServModal has been tested in the following browsers:
 * - IE 7+
 * - Firefox 2+
 * - Opera 9+
 * - Safari 3+
 * - Chrome 1+
 *
 * @name ShipServModal
 * @type jQuery
 * @requires jQuery v1.4.2
 * @cat Plugins/Windows and Overlays
 * @author Kevin Bennett (kbennett@shipserv.com)
 * @version 1.0
 */

define(['jquery', 'cssp!css/modal.css?cssp-ssModal'], function($) {

	var d = [],
		doc = $(document),
        ua = navigator.userAgent.toLowerCase(),
		wndw = $(window),
		w = [];
        
	var browser = {
    	ieQuirks: null,
    	msie: /msie/.test(ua) && !/opera/.test(ua),
    	opera: /opera/.test(ua)
	};
	browser.ie6 = browser.msie && /msie 6./.test(ua) && typeof window['XMLHttpRequest'] !== 'object';
	browser.ie7 = browser.msie && /msie 7.0/.test(ua);

	/*
	 * Create and display a modal dialog.
	 *
	 * @param {string, object} data A string, jQuery object or DOM object
	 * @param {object} [options] An optional object containing options overrides
	 */
	$.ssmodal = function (data, options) {
		return $.ssmodal.impl.init(data, options);
	};

	/*
	 * Close the modal dialog.
	 */
	$.ssmodal.close = function () {
		$.ssmodal.impl.close();
	};

	/*
	 * Set focus on first or last visible input in the modal dialog. To focus on the last
	 * element, call $.ssmodal.focus('last'). If no input elements are found, focus is placed
	 * on the data wrapper element.
	 */
	$.ssmodal.focus = function (pos) {
		$.ssmodal.impl.focus(pos);
	};

	/*
	 * Determine and set the dimensions of the modal dialog container.
	 * setPosition() is called if the autoPosition option is true.
	 */
	$.ssmodal.setContainerDimensions = function () {
		$.ssmodal.impl.setContainerDimensions();
	};

	/*
	 * Re-position the modal dialog.
	 */
	$.ssmodal.setPosition = function () {
		$.ssmodal.impl.setPosition();
	};

	/*
	 * Update the modal dialog. If new dimensions are passed, they will be used to determine
	 * the dimensions of the container.
	 *
	 * setContainerDimensions() is called, which in turn calls setPosition(), if enabled.
	 * Lastly, focus() is called is the focus option is true.
	 */
	$.ssmodal.update = function (height, width) {
		$.ssmodal.impl.update(height, width);
	};

	/*
	 * Chained function to create a modal dialog.
	 *
	 * @param {object} [options] An optional object containing options overrides
	 */
	$.fn.ssmodal = function (options) {
		return $.ssmodal.impl.init(this, options);
	};

	/*
	 * ShipServModal default options
	 *
	 * appendTo:		(String:'body') The jQuery selector to append the elements to. For .NET, use 'form'.
	 * focus:			(Boolean:true) Focus in the first visible, enabled element?
	 * containerId:		(String:'ShipServModal-container') The DOM element id for the container div
	 * minHeight:		(Number:null) The minimum height for the container
	 * minWidth:		(Number:null) The minimum width for the container
	 * maxHeight:		(Number:null) The maximum height for the container. If not specified, the window height is used.
	 * maxWidth:		(Number:null) The maximum width for the container. If not specified, the window width is used.
	 * autoPosition:	(Boolean:true) Automatically position the container upon creation and on window resize?
	 * close:			(Boolean:true) If true, a close button will be displayed. Additionally, escape key will close.
	 * position:		(Array:null) Position of container [top, left]. Can be number of pixels or percentage
	 * persist:			(Boolean:false) Persist the data across modal calls? Only used for existing
								DOM elements. If true, the data will be maintained across modal calls, if false,
								the data will be reverted to its original state.
	 * modal:			(Boolean:true) User will be unable to interact with the page below the modal or tab away from the dialog.
								If false, the overlay, iframe, and certain events will be disabled allowing the user to interact
								with the page below the dialog.
	 */
	$.ssmodal.defaults = {
		title: '',
		appendTo: 'body',
		focus: true,
		containerId: 'ssModalDialogue',
		contentId: 'ShipServModal-content',
		minHeight: null,
		minWidth: null,
		maxHeight: null,
		maxWidth: null,
		autoPosition: true,
		close: true,
		overlayId: 'ssModalMask',
		overlayClose: false,
		position: null,
		persist: false,
		modal: true,
		onOpen: null,
		onShow: null,
		onClose: null
	};

	/*
	 * Main modal object
	 * o = options
	 */
	$.ssmodal.impl = {
		/*
		 * Contains the modal dialog elements and is the object passed
		 * back to the callback (onOpen, onShow, onClose) functions
		 */
		d: {},
		/*
		 * Initialize the modal dialog
		 */
		init: function (content, options) {
			var s = this;

			// don't allow multiple calls
			if (s.d.content) {
				return false;
			}

			// $.boxModel is undefined if checked earlier
			browser.ieQuirks = browser.msie && !$.support.boxModel;

			// merge defaults and user options
			s.o = $.extend({}, $.ssmodal.defaults, options);

			// get highest zIndex on the page
		    s.o.zIndex = Math.max.apply(null,$.map($('*'), function(e,n){
            	return parseInt($(e).css('z-index'))||1 ;
			}));
			s.o.zIndex++;
            
			// set the onClose callback flag
			s.occb = false;
			
			// determine how to handle the content based on its type
			if (typeof content === 'object') {
				// convert DOM object to a jQuery object
				content = content instanceof jQuery ? content : $(content);
				s.d.placeholder = false;

				// if the object came from the DOM, keep track of its parent
				if (content.parent().parent().size() > 0) {
					content.before($('<span></span>')
						.attr('id', 'ShipServModal-placeholder')
						.css({display: 'none'}));

					s.d.placeholder = true;
					s.display = content.css('display');

					// persist changes? if not, make a clone of the element
					if (!s.o.persist) {
						s.d.orig = content.clone(true);
					}
				}
			}
			else if (typeof content === 'string' || typeof content === 'number') {
				// just insert the content as innerHTML
				content = $('<div></div>').html(content);
			}
			else {
				// unsupported content type!
				if (console && console.log) {
					console.log('ShipServModal Error: Unsupported content type: ' + typeof content);
				}
				return s;
			}

			// create the modal overlay, container and, if necessary, iframe
			s.create(content);
			content = null;

			// display the modal dialog
			s.open();

			// useful for adding events/manipulating content in the modal dialog
			if ($.isFunction(s.o.onShow)) {
				s.o.onShow.apply(s, [s.d]);
			}

			return s;
		},
		/*
		 * Create and add the modal overlay and container to the page
		 */
		create: function (content) {
			var s = this;

			// get the window properties
			s.getDimensions();

			// add an iframe to prevent select options from bleeding through
			if (s.o.ssmodal && browser.ie6) {
				s.d.iframe = $('<iframe src="javascript:false;"></iframe>')
					.css($.extend(s.o.iframeCss, {
						display: 'none',
						opacity: 0,
						position: 'fixed',
						height: w[0],
						width: w[1],
						zIndex: s.o.zIndex,
						top: 0,
						left: 0
					}))
					.appendTo(s.o.appendTo);
			}

			// create the overlay
			s.d.overlay = $('<div></div>')
                .attr('id', s.o.overlayId)
                .addClass('ssmodal-overlay')
				.css({display: 'none', zIndex: s.o.zIndex + 1})
				.appendTo(s.o.appendTo);

			// create the container
			s.d.container = $('<div></div>')
				.attr('id', s.o.containerId)
				.addClass('ShipServModal-container')
				.css($.extend(
					s.o.containerCss,
					{display: 'none', zIndex: s.o.zIndex + 2}
				))
				.append(s.o.close && s.o.closeHTML
					? $(s.o.closeHTML).addClass(s.o.closeClass)
					: '')
				.prepend('<div class="zz page header"><h1>' + s.o.title + '</h1></div>')
				
			if (s.o.close) {
				s.d.container.append('<div class="ssModal close button zz white medium"><button>Close</button></div>');
			}
					
			s.d.container.css({ position: $(window).width() > s.d.container.outerWidth(true) && $(window).height() > s.d.container.outerHeight(true) ? 'fixed' : 'absolute' })
				.appendTo(s.o.appendTo);

			s.d.wrap = $('<div></div>')
				.attr('tabIndex', -1)
				.addClass('ShipServModal-wrap clearfix')
				.css({height: '100%', outline: 0, width: '100%'})
				.appendTo(s.d.container);

			// add styling and attributes to the content
			// append to body to get correct dimensions, then move to wrap
			s.d.content = content
				.attr('id', content.attr('id') || s.o.contentId)
				.addClass('ShipServModal-content')
				.css({display: 'none'})
				.appendTo('body');
			content = null;

			s.setContainerDimensions();
			s.d.content.appendTo(s.d.wrap);
		},
		/*
		 * Bind events
		 */
		bindEvents: function () {
			var s = this;

			// bind the close event to any element with the closeClass class
			if (s.o.close) {
				s.d.container.find('.button.close').bind('click.ShipServModal', function (e) {
					e.preventDefault();
					s.close();
				});
			}

			// bind the overlay click to the close function, if enabled
			if (s.o.ssmodal && s.o.close) {
				s.d.overlay.bind('click.ShipServModal', function (e) {
					e.preventDefault();
					s.close();
				});
			}

			// bind keydown events
			doc.bind('keydown.ShipServModal', function (e) {
				if (s.o.ssmodal && e.keyCode === 9) { // TAB
					s.watchTab(e);
				}
				else if ((s.o.close) && e.keyCode === 27) { // ESC
					e.preventDefault();
					s.close();
				}
			});

			// update window size
			wndw.bind('resize.ShipServModal orientationchange.ShipServModal', function () {
				// redetermine the window width/height
				s.getDimensions();

				// reposition the dialog
				if (s.o.autoPosition) {
					s.setPosition();
				}

				if (s.o.ssmodal) {
					// update the iframe & overlay
					s.d.iframe && s.d.iframe.css({height: w[0], width: w[1]});
					s.d.overlay.css({height: d[0], width: d[1]});
				}
			});
		},
		/*
		 * Unbind events
		 */
		unbindEvents: function () {
			var s = this;
			
			s.d.container.find('button.close').unbind('click.ShipServModal');
			doc.unbind('keydown.ShipServModal');
			wndw.unbind('.ShipServModal');
			this.d.overlay.unbind('click.ShipServModal');
		},

		/*
		 * Place focus on the first or last visible input
		 */
		focus: function (pos) {
			var s = this, p = pos && $.inArray(pos, ['first', 'last']) !== -1 ? pos : 'first';

			// focus on dialog or the first visible/enabled input element
			var input = $(':input:enabled:visible:' + p, s.d.wrap);
			setTimeout(function () {
				input.length > 0 ? input.focus() : s.d.wrap.focus();
			}, 10);
		},
		getDimensions: function () {
			// fix a jQuery/Opera bug with determining the window height
			var s = this,
				h = $.browser.opera && $.browser.version > '9.5' && $.fn.jquery < '1.3'
						|| $.browser.opera && $.browser.version < '9.5' && $.fn.jquery > '1.2.6'
				? wndw[0].innerHeight : wndw.height();

			d = [doc.height(), doc.width()];
			w = [h, wndw.width()];
		},
		getVal: function (v, d) {
			return v ? (typeof v === 'number' ? v
					: v === 'auto' ? 0
					: v.indexOf('%') > 0 ? ((parseInt(v.replace(/%/, '')) / 100) * (d === 'h' ? w[0] : w[1]))
					: parseInt(v.replace(/px/, '')))
				: null;
		},
		/*
		 * Update the container. Set new dimensions, if provided.
		 * Focus, if enabled. Re-bind events.
		 */
		update: function (height, width) {
			var s = this;

			// prevent update if dialog does not exist
			if (!s.d.content) {
				return false;
			}

			// reset orig values
			s.d.origHeight = s.getVal(height, 'h');
			s.d.origWidth = s.getVal(width, 'w');

			// hide content to prevent screen flicker
			s.d.content.hide();
			height && s.d.container.css('height', height);
			width && s.d.container.css('width', width);
			s.setContainerDimensions();
			s.d.content.show();
			s.o.focus && s.focus();

			// rebind events
			s.unbindEvents();
			s.bindEvents();
		},

		setContainerDimensions: function () {
			var s = this,
				badIE = browser.ie6 || browser.ie7;

			// get the dimensions for the container and content
			var ch = s.d.origHeight ? s.d.origHeight : $.browser.opera ? s.d.container.height() : s.getVal(badIE ? s.d.container[0].currentStyle['height'] : s.d.container.css('height'), 'h'),
				cw = s.d.origWidth ? s.d.origWidth : $.browser.opera ? s.d.container.width() : s.getVal(badIE ? s.d.container[0].currentStyle['width'] : s.d.container.css('width'), 'w'),
				dh = s.d.content.outerHeight(true) + 45, dw = s.d.content.outerWidth(true);

			s.d.origHeight = s.d.origHeight || ch;
			s.d.origWidth = s.d.origWidth || cw;

			// mxoh = max option height, mxow = max option width
			var mxoh = s.o.maxHeight ? s.getVal(s.o.maxHeight, 'h') : null,
				mxow = s.o.maxWidth ? s.getVal(s.o.maxWidth, 'w') : null,
				mh = mxoh && mxoh < w[0] ? mxoh : w[0],
				mw = mxow && mxow < w[1] ? mxow : w[1];

			// moh = min option height
			var moh = s.o.minHeight ? s.getVal(s.o.minHeight, 'h') : 'auto';
			if (!ch) {
				if (!dh) {ch = moh;}
				else {
					if (dh > mh) {ch = mh;}
					else if (s.o.minHeight && moh !== 'auto' && dh < moh) {ch = moh;}
					else {ch = dh;}
				}
			}
			else {
				ch = ch < moh ? moh : ch;
			}

			// mow = min option width
			var mow = s.o.minWidth ? s.getVal(s.o.minWidth, 'w') : 'auto';
			if (!cw) {
				if (!dw) {cw = mow;}
				else {
					if (dw > mw) {cw = mw;}
					else if (s.o.minWidth && mow !== 'auto' && dw < mow) {cw = mow;}
					else {cw = dw;}
				}
			}
			else {
				cw = cw < mow ? mow : cw;
			}
			
			s.d.container.css({height: dh, width: dw});
			//s.d.wrap.css({overflow: (dh > ch || dw > cw) ? 'auto' : 'visible'});
            s.d.wrap.css({overflow: 'visible'});
			s.o.autoPosition && s.setPosition();
		},

		setPosition: function () {
			var s = this, top, left,
				hc = (w[0]/2) - (s.d.container.outerHeight(true)/2),
				vc = (w[1]/2) - (s.d.container.outerWidth(true)/2),
				position = $(window).width() > s.d.container.outerWidth(true) && $(window).height() > s.d.container.outerHeight(true) ? 'fixed' : 'absolute',
				st = s.d.container.css('position', position).css('position') !== 'fixed' ? wndw.scrollTop() : 0;

			if (s.o.position && Object.prototype.toString.call(s.o.position) === '[object Array]') {
				top = st + (s.o.position[0] || hc);
				left = s.o.position[1] || vc;
			} else {
				top = position == 'absolute' ? 20 : st + hc; //For absolute positioned dialogues, place at top of page
				left = vc;
			}
			s.d.container.css({left: left, top: top});
		},
		watchTab: function (e) {
			var s = this;

			if ($(e.target).parents('.ShipServModal-container').length > 0) {
				// save the list of inputs
				s.inputs = $(':input:enabled:visible:first, :input:enabled:visible:last', s.d.content[0]);

				// if it's the first or last tabbable element, refocus
				if ((!e.shiftKey && e.target === s.inputs[s.inputs.length -1]) ||
						(e.shiftKey && e.target === s.inputs[0]) ||
						s.inputs.length === 0) {
					e.preventDefault();
					var pos = e.shiftKey ? 'last' : 'first';
					s.focus(pos);
				}
			}
			else {
				// might be necessary when custom onShow callback is used
				e.preventDefault();
				s.focus();
			}
		},
		/*
		 * Open the modal dialog elements
		 * - Note: If you use the onOpen callback, you must "show" the
		 *			overlay and container elements manually
		 *		 (the iframe will be handled by ShipServModal)
		 */
		open: function () {
			var s = this;
			// display the iframe
			s.d.iframe && s.d.iframe.show();

			if ($.isFunction(s.o.onOpen)) {
				// execute the onOpen callback
				s.o.onOpen.apply(s, [s.d]);
			}
			else {
				// display the remaining elements
				s.d.overlay.show();
				s.d.container.show();
				s.d.content.show();
			}

			s.o.focus && s.focus();

			// bind default events
			s.bindEvents();
		},
		/*
		 * Close the modal dialog
		 * - Note: If you use an onClose callback, you must remove the
		 *         overlay, container and iframe elements manually
		 *
		 * @param {boolean} external Indicates whether the call to this
		 *     function was internal or external. If it was external, the
		 *     onClose callback will be ignored
		 */
		close: function () {
			var s = this;

			// prevent close when dialog does not exist
			if (!s.d.content) {
				return false;
			}

			// remove the default events
			s.unbindEvents();

			if ($.isFunction(s.o.onClose) && !s.occb) {
				// set the onClose callback flag
				s.occb = true;

				// execute the onClose callback
				s.o.onClose.apply(s, [s.d]);
			}
            
			// if the content came from the DOM, put it back
			if (s.d.placeholder) {
				var ph = $('#ShipServModal-placeholder');
				// save changes to the content?
				if (s.o.persist) {
					// insert the (possibly) modified content back into the DOM
					ph.replaceWith(s.d.content.removeClass('ShipServModal-content').css('display', s.display));
				}
				else {
					// remove the current and insert the original,
					// unmodified content back into the DOM
					s.d.content.hide().remove();
					ph.replaceWith(s.d.orig);
				}
			}
			else {
				// otherwise, remove it
				s.d.content.hide().remove();
			}

			// remove the remaining elements
			s.d.container.hide().remove();
			s.d.overlay.hide();
			s.d.iframe && s.d.iframe.hide().remove();
			s.d.overlay.remove();

			// reset the dialog object
			s.d = {};
		}
	};
	return $;
});
