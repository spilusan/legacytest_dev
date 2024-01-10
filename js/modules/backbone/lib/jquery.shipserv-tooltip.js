/*
	Arrowed tooltip
	Dev: Attila Olbrich 12 May 2016
	Requires/css/tooltip.css
	
	Usage $(element).shTooltip({
		displayType : 'bottom'
	})

	optional parameter: onClick : true
	in that case the tooltip will show on click
	
	Text will be displayed from the element data-tooltip attribute like data-tooltip="Hello world"
	where datatype can be top, bottom, left (default top, or in case of wrong datatype set, also defaulted back to top)
*/
define('shipservTooltip', ['jquery'], function (jQuery) {
	(function ($) {
		$.shTooltip = function (el, options) {
			var base = this;

			base.$el = $(el);
			base.el = el;

			base.$el.data("shTooltip", base);
			base.init = function () {
				base.options = $.extend({}, $.shTooltip.defaultOptions, options);

				// Put other initialization code here
			};

			// Sample Function, Uncomment to use
			// base.functionName = function(paramaters){
			// 
			// };

			base.init();
		};

		$.shTooltip.defaultOptions = {
			displayType: "top"
		};

		$.fn.shTooltip = function (options) {
			options = options || {};
			
			//ADD elements to DOM if not exist for the proper type
			switch (options.displayType) {
				case 'top':
					if ($('#ttip-top').length === 0) {
						var element = $('<div>');
						element.attr('id', 'ttip-top');
						element.addClass('sh-tooltip');
						var innerElement = $('<div>');
						innerElement.addClass('arrow');
						innerElement.addClass('top');
						var captionElement = $('<span>');
						captionElement.attr('id', 'toolText-top');
						element.append(innerElement);
						element.append(captionElement);
						$('body').append(element);
					}

					break;
				case 'bottom':
					if ($('#ttip-bottom').length === 0) {
						var element = $('<div>');
						element.attr('id', 'ttip-bottom');
						element.addClass('sh-tooltip');
						var innerElement = $('<div>');
						innerElement.addClass('arrow');
						var captionElement = $('<span>');
						captionElement.attr('id', 'toolText-bottom');
						element.append(innerElement);
						element.append(captionElement);
						$('body').append(element);
					}
					break;
				case 'left':
					if ($('#ttip-left').length === 0) {
						var element = $('<div>');
						element.attr('id', 'ttip-left');
						element.addClass('sh-tooltip');
						var innerElement = $('<div>');
						innerElement.addClass('h-arrow left');
						innerElement.addClass('left');
						var captionElement = $('<span>');
						captionElement.attr('id', 'toolText-left');
						element.append(innerElement);
						element.append(captionElement);
						$('body').append(element);
					}

					break;
				case 'right':
					if ($('#ttip-right').length === 0) {
						var element = $('<div>');
						element.attr('id', 'ttip-right');
						element.addClass('sh-tooltip');
						var innerElement = $('<div>');
						innerElement.addClass('h-arrow right');
						innerElement.addClass('right');
						var captionElement = $('<span>');
						captionElement.attr('id', 'toolText-right');
						element.append(innerElement);
						element.append(captionElement);
						$('body').append(element);
					}

					break;
				default:
					/* The default is top */
					if ($('#ttip-top').length === 0) {
						var element = $('<div>');
						element.attr('id', 'ttip-top');
						element.addClass('sh-tooltip');
						var innerElement = $('<div>');
						innerElement.addClass('arrow');
						innerElement.addClass('top');
						var captionElement = $('<span>');
						captionElement.attr('id', 'toolText-top');
						element.append(innerElement);
						element.append(captionElement);
						$('body').append(element);
					}

					break;
			}


			return this.each(function () {
				(new $.shTooltip(this, options));


                var tootlipShow = function () {
                    var tText = '';

                    if (options.tooltipelement) {
                        tText = $('#' + options.tooltipelement).html();
                    } else if (options.tooltip) {
                        tText = options.tooltip;
                    } else if ($(this).data('tooltipelement')) {
                        tText = $('#' + $(this).data('tooltipelement')).html();
                    } else {
                        tText = $(this).data('tooltip');
                    }

                    tText = tText.replace(/\[br\]/g, "<br>");
                    switch (options.displayType) {
                        case 'top':
                            $('#toolText-top').html(tText);
                            var position = $(this).offset();
                            $('#ttip-top').css('left', (position.left - Math.round($('#ttip-top').width() / 2)) + 'px');
                            $('#ttip-top').css('top', (position.top + 30) + 'px');
                            $('#ttip-top').stop(true, true).fadeIn(400);
                            break;
                        case 'bottom':
                            $('#toolText-bottom').html(tText);
                            var position = $(this).offset();
                            $('#ttip-bottom').css('left', (position.left - Math.round($('#ttip-bottom').width() / 2)) + 'px');
                            $('#ttip-bottom').css('top', (position.top - $('#ttip-bottom').height() - 22) + 'px');
                            $('#ttip-bottom').stop(true, true).fadeIn(400);
                            break;
                        case 'left':
                            $('#toolText-left').html(tText);
                            var position = $(this).offset();
                            $('#ttip-left').css('left', (position.left + 30) + 'px');
                            $('#ttip-left').css('top', (position.top - Math.round($('#ttip-left').height() / 2)) + 'px');
                            $('#ttip-left').stop(true, true).fadeIn(400);
                            break;
                        case 'right':
                            $('#toolText-right').html(tText);
                            var position = $(this).offset();
                            $('#ttip-right').css('left', (position.left - Math.round($('#ttip-right').width()) - 35) + 'px');
                            $('#ttip-right').css('top', (position.top - Math.round($('#ttip-right').height() / 2)) + 'px');
                            $('#ttip-right').stop(true, true).fadeIn(400);
                            break;
                        default:
                            /* The default is top */
                            $('#toolText-top').html(tText);
                            var position = $(this).offset();
                            $('#ttip-top').css('left', (position.left - Math.round($('#ttip-top').width() / 2)) + 'px');
                            $('#ttip-top').css('top', (position.top + 30) + 'px');
                            $('#ttip-top').stop(true, true).fadeIn(400);
                            break;
                    }
                };

                if (options.onClick) {
                    $(this).click(tootlipShow);
				} else {
                    $(this).mouseover(tootlipShow);
				}

                $(this).mouseout(function () {
					switch (options.displayType) {
						case 'top':
							$('#ttip-top').stop(true, true).delay(100).fadeOut();
							break;
						case 'bottom':
							$('#ttip-bottom').stop(true, true).delay(100).fadeOut();
							break;
						case 'left':
							$('#ttip-left').stop(true, true).delay(100).fadeOut();
							break;
						case 'left':
							$('#ttip-right').stop(true, true).delay(100).fadeOut();
							break;
						default:
							$('#ttip-top').stop(true, true).delay(100).fadeOut();
							break;
					}
				});
			});
		};

	})(jQuery);
});