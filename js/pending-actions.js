/**
 * Event Map - jquery.eventmap.js
 * 
 * Creates a Google map on the specified element and adds events
 * 
 * Dependencies: jQuery, Google Maps API
 * 
 * @project ShipServ Pages
 * @author Dave Starling dstarling@shipserv.com
 * @version 0.1
 */
(function($) {
	$.fn.pendingActions = function(options) {
		var config = { countUrl: '/profile/fetch-pending-actions/format/json',
					   maxCount: 99 };
		
		if (options) $.extend(config, options);
		
		this.each(function() {
			var self = this;
			
			$.get(config.countUrl, function(data) {
				if (data.pendingActions > 99) {
					data.pendingActions = 99;
				}
				
				$(self).html(function () {
					return data.pendingActions;
				});
				
				/*
				if (data.pendingActions < 10) {
					$(self).css('padding', '4px 8px');
					$(self).css('width', '13px');
				} else {
					$(self).css('padding', '4px 5px');
					$(self).css('width', '19px');
				}
				*/
				
				if (data.pendingActions == 0) {
					$(self).fadeOut('fast');
				}
			});
		});
		
		return this;
	}
})(jQuery);
