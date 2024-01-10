/*
* This is a fix for IE10, 9 ,(8?) to support flexboxes
*/
$(function() {
	 var myNav = navigator.userAgent.toLowerCase();
	 var isVersion = (myNav.indexOf('msie') != -1) ? parseInt(myNav.split('msie')[1]) : false;
	 if (isVersion !== false) {
		 if (isVersion <= 10) {
			/**
			 * New HACK for gallery as flexbox is broken in IE10< regardless of the used JS library as it is a bit buggy
			 */
			 switch(window.location.pathname) {
			    case '/info/contact-us':
			    	var footers = document.getElementsByClassName('mainFooter');
					flexibility(footers[0]);
			        break;
			    case '/info/pages-for-suppliers/membership-price/basic-vs-premium':
			    	var footers = document.getElementsByClassName('mainFooter');
					var wpFooters = document.getElementsByClassName('wp-footer');
					flexibility(footers[0]);
					flexibility(wpFooters[0]);
			        break;
			    case '/info/save-time-and-money-with-tradenet':
			    case '/info/pages-for-suppliers':
			    	$('.gallery-container').css('display',"block");
					$('.gallery-container').css('float',"left");
					$('.image-block').css('display',"block");
					$('.image-block').css('float',"left");
					$('.image-block').css('margin',"0px 6px 40px 6px");
			    	break;
			    default:
			    	flexibility(document.body);	
			}
		 }
	 }
});
