/**
 * Handles A/B testing for profile page with MIXPANEL
 * @author Elvir <eleonard@shipserv.com>
 */
define(['jquery', 'mixpanel'], function($) {
	// cookie related function
	function Cookie(){};
	Cookie.prototype = {
		set: function(name, value, nDays){
			var today = new Date();
			var expire = new Date();
			if (nDays==null || nDays==0) nDays=1;
			expire.setTime(today.getTime() + 3600000*24*nDays);

			if (document.cookie != document.cookie) {
				index = document.cookie.indexOf(name);
			} else {
				index = -1;
			}
			if (index == -1) {
				document.cookie = name+"="+escape(value) + "; path=/; expires="+expire.toGMTString();
			}
		},
		
		get: function(name){
			var start = document.cookie.indexOf( name + "=" );
			var len = start + name.length + 1;
			if ( ( !start ) && ( name != document.cookie.substring( 0, name.length ) ) ) return null;
			if ( start == -1 ) return null;
			var end = document.cookie.indexOf( ";", len );
			if ( end == -1 ) end = document.cookie.length;
			return unescape( document.cookie.substring( len, end ) );
		},
		
		remove: function(name){
			if ( this.get( name ) ) document.cookie = name + "=" +
			";expires=Thu, 01-Jan-1970 00:00:01 GMT";
		}
	}
	
	/**
	 * Initalise module (executes on document ready)
	 */
	function main() {
		var m,c;
		m = new Mixpanel();
		c = new Cookie();

		// register a unique user based on the PHPSESSID
		m.identify(m.getSessionId());
		
		// give user a name if they're logged in
		if( window.user.id != "" )
		m.nameTag(window.user.id + '_' + window.user.name);

		// if user is not logged, just use their session id
		else
		m.nameTag(m.getSessionId());
		
		// register the route
		m.register({"route": window.ABRoute});
		
		// assign cookie
		if( c.get('ABRoute') == null ) c.set('ABRoute', window.ABRoute);
		
		// track when the supplier page is loaded up whether user is logged in or not
		if( window.user.name != "" )
		{
			// log  when user is logged in
			m.track('User logged in', {}, function(){
				// check the url, if user has the hash tag of contact view, then track it
				if( window.location.href.search("#contact_box") != -1 )
				{
					m.track('Contact detail viewed');
				}
			}); 
			
			// bind the tracking when user clicking the contact button
			$("#contact_toggle a").click(function(){ 
				m.track('Contact detail viewed'); 
			});
			
		}
		// user not logged in
		else
		{
			m.track('User NOT logged in', {}, function(){ 

				m.track('Profile page is viewed'); 
				
				// route1: if contact tab is only available to logged member
				if( window.ABRoute == 'contact-viewable-by-member' )
				{
					// if logged in
					if( window.user.id  == "" )
					{
						// STEP: track when contact tab is clicked
						$("#contact_toggle a").click(function(){ 
							m.track('Contact button is clicked'); 
						});
						
						// check the url, if user has the hash tag of contact view, then track it
						if( window.location.href.search("#contact_box") != -1 )
						{
							m.track('Contact button is clicked');
						}
						
						// STEP: track if user click registration link
						$(".form_inner_body #registrationLink").click(function(){
							var link = $(this); 
							m.track('Register link was clicked',{}, function(){ 
								location.href = link.attr("url"); 
							}); 
						});
	
						// STEP: track if user trying to login
						$(".form_inner_body #loginUsername").focus(function(){ 
							var obj = $(this); 
							if( obj.attr('tracked') != 'true' ) 
							{
								m.track('Entering username',{}, function(){ 
									obj.attr('tracked', 'true') 
								});
							}
						});
	
						// STEP: track if user trying to login
						$(".form_inner_body #loginPassword").focus(function(){ 
							var obj = $(this); 
							if( obj.attr('tracked') != 'true' ) 
							{
								m.track('Entering password',{}, function(){ 
									obj.attr('tracked', 'true') 
								});
							}
						});
						
						// STEP: track if user clicking the login button
						$("#loginForm").submit(
							function()
							{ 
								var obj = $(this); 
								m.track('Clicking login button',{}, 
									function()
									{ 
										obj.attr('tracked', 'true'); 
										obj.get()[0].submit();
									}
								);
								return false;
							}
						);
					}
				}
				
				// when different route is selected by the controller
				else if( window.ABRoute == 'contact-viewable-by-public')
				{
					// when contact button is clicked, register the view impression straight away
					// bind the tracking when user clicking the contact button
					$("#contact_toggle a").click(function(){ 
						m.track('Contact button is clicked'); 
						m.track('Contact detail viewed'); 
					});
					
					// check the url, if user has the hash tag of contact view, then track it
					if( window.location.href.search("#contact_box") != -1 )
					{
						m.track('Contact button is clicked');
						m.track('Contact detail viewed');
					}
				}			
			});
		}
	}$(main);
});