/**
* Sticky Header Plugin
* Dev: Attila O
* Usage Add the #shipservSticky to the stylesheet of your table
* Currently the plugin supports only one table per page, not tested for more but may work
* usage: $('#tablename').shipservSticky();
* Please note that the table must be loaded (at least the header) when you append it to the table, so you may put in document ready
*/
;(function($, window, document) {
	"use strict";
		var pluginName = "shipservSticky";
		/*
		* The actual plugin constructor
		*/
		function Plugin(element) {
			this.element = element;
			/* Top offset has to be added, as we applied a new fixed header navigation */
			this.topOffset = 60;
			this._name = pluginName;
			this.init();
		}
		/*
		* Avoid Plugin.prototype conflicts
		*/
		$.extend(Plugin.prototype, {
			/*
			* Initalize plugin
			*/	
			init: function() {
				this.createStickyHeader();
				var thisView = this;
				$(window).scroll(function(){
					thisView.onWindowScroll();
				});
			},
			/*
			* On scroll  if the original header would position outside the window 
			* we have to reposition the header, make sure that the hader positioned correctly
			*/
			onWindowScroll: function() {
				var position = $(this.element).offset();
				

				var topPosition = position.top - $(window).scrollTop();
				var leftPosition = position.left - $(window).scrollLeft();
				var headerTop = 0;
				if (topPosition < this.topOffset) {
					$(this.stickyElement).css('left', leftPosition+'px'); 
					//Calculate the table last row position, if we have to push out the header from the screen, otherwise it looks ugly scrolling to the bottom
					headerTop = topPosition + $(this.element).height() - $(this.element).find('tr:last').height() - $(this.stickyElement).height();
					if (headerTop < this.topOffset) {
						$(this.stickyElement).css('top', headerTop+'px'); 
					} else {
						$(this.stickyElement).css('top', this.topOffset+'px'); 
					}
					$(this.stickyHeadContent).html($(this.element).find('thead').html());
					this.fixHeaderWidths();
					$(this.stickyElement).css('display', 'block');
				} else {
					$(this.stickyElement).css('display', 'none');
				}
			},
			/*
			* Create a hidden new table header when assigning to the table, where the content header will be rendered
			*/
			createStickyHeader: function() {
				if ($('#shipservStickyHeader').length > 0) {
					this.stickyElement = $('#shipservStickyHeader');
					this.stickyHeadContent = $(this.stickyElement).find('thead');
				} else {
					var element = $('<table>');
					var thead = $('<thead>');
					$(element).attr('id','shipservStickyHeader');
					$(element).css('display', 'none');
					$(element).css('position', 'fixed');
					$(element).css('top', this.topOffset+'px');
					$(element).css('z-index', '999');
					$(element).css('background-color', 'white');
	 				$(element).append(thead);
					$('body').append(element);
					this.stickyElement = element;
					this.stickyHeadContent = thead;
				}
			},
			/*
			* Fix the header widths according to the original header
			*/
			fixHeaderWidths: function() {
				$(this.stickyElement).width($(this.element).width());
				var tds = $(this.element).find('tbody').find('tr').first().find('td');
				var ths = $(this.stickyHeadContent).find('tr').first().find('th');
				if (tds.length > 0) {
					for (var key=0;key<ths.length;key++) {
						$(ths[key]).width($(tds[key]).width());
					}	
				} 
			}
		});
		
		$.fn[pluginName] = function() {
			return this.each( function() {
				if (!$.data(this, "plugin_" + pluginName)) {
					$.data( this, "plugin_" + pluginName, new Plugin(this));
				}
			});
		};
})(jQuery, window, document);

