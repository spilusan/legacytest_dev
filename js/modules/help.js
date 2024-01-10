define(['jquery'], function($) {
	"use strict";
	
	var maskTemplate = '<div class="fsmask"></div>',
		panelTemplate = '<div class="help panel"><div class="zz page header"><h1>Help</h1></div><p class="message"></p> <div class="close button zz white medium"><button class="close">Close</button></div>';
	
	/**
	 * Module will execute on ready
	 * Sets up click handlers for contextual help divs embedded on a page
	 * Displays the associated help message
	 * Example tag:
	 * <div class="contextual help" data-message="Clicking this button will end the world"></div>
	 */
	$(function() {
		$('div.contextual.help').live('click', function () {
			var mask = $(maskTemplate),
				panel = $(panelTemplate);
			
			if( $(this).attr('data-message-div') !== undefined ){
				panel.find('p.message').html($('#' + $(this).attr('data-message-div')).html());
			}else{
				panel.find('p.message').html($(this).attr('data-message'));
			}
			
			
			panel.find('h1').text($(this).attr('data-title'));
			$('body').prepend(mask.append(panel));
			
			/*Position*/
			panel.css({
			    left: Math.round($(window).width() / 2) - Math.round(panel.width() / 2),
			    top: Math.round($(window).height() / 2) - Math.round(panel.height() / 2)
			});
			
			panel.find('button.close').one('click', function () {
				panel.remove();
				mask.remove();
			});
		});
	});
});