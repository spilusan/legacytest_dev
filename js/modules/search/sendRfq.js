/**
 * Send RFQ
 */
define([], function () {
	//Private 
	function init () {

		$(document).ready(function(){
			$('.enquiry_send').click(sendRfqWindowOpen);
			$('.enquiry_send_bottom').click(sendRfqWindowOpen);
			$('a.enquiry_send_white').click(sendRfqWindowOpen);
		});

	} $(init); //Exec on document ready

	function sendRfqWindowOpen(e)
	{
		e.preventDefault();
		var cookiePars = require('shipserv/basketCookie');
		var d = new Date();
		var o = $.JSONCookie(cookiePars.name);

		if (!o.suppliers) {
			o = {
				'suppliers': []
			};
		} 

		$('.sendEnquiryCheckbox').each(function() {
			if($(this).attr('checked')) {
				if (o.suppliers.indexOf($(this).attr('id')) === -1) {
					o.suppliers.push($(this).attr('id'));
				}
			} 
		});
	
		if(o.suppliers.length > cookiePars.maxSelectedSuppliers) {
			maxSuppliersPopUp(cookiePars.maxSelectedSuppliers);
			return false;
		}

		/* Store Checkbox statuses in json cookie */
		$.JSONCookie(cookiePars.name, o, {
			domain: cookiePars.domain,
			path: cookiePars.path 
		});

		window.open('/enquiry?ts=' + d.getTime(),'_blank');
	}

	return {};
});