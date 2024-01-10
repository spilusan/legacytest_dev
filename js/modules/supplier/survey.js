// automatically move the focus to the top using a nice movement
function scrollToTop( speed, id, offset ) {
	if( id != undefined && id!=""){
		if( $("#" + id).length > 0 )
		var top = $("#" + id).position().top;
		else
		var top = 0;
	}else{
		var top = 0;	
	}
	
	if( offset != undefined ){
		top = top - ( offset );	
	}
	
	if (!speed) speed = "slow";
	$("html, body").animate( {scrollTop:top+"px"}, speed);
}

/**
 * Modal window for survey
 * Dependency: AB testing route
 * @author Elvir <eleonard@shipserv.com>
 */
define(['cookie', 'modal'], 
	function(cookie, $) {
		SurveyModalWindow = function () {
			var cookie = require('cookie');
			
			this.delay = 5 /*seconds*/ * 1000;
			this.$popup = $('#brand_invitation_form_popup');

			this.init = function() {
				this.addEvent();
			}
	
			// attach all events
			this.addEvent = function(){
				var object = this;
				$("#contact_toggle a").click(function(){
					setTimeout(function(){
						object.show();
					}, object.delay)
				});
	
				if( window.location.href.search("#contact_box") != -1 ){
					setTimeout(function(){
						$("#contact_toggle a").trigger('click');
					}, this.delay)
				}

				// bind cancel to close the modal window
				$('.surveyClose').live('click', function(e){
					object.hide();
				});
				
				// bind esc key to close the popup
				$(document).keyup(function(e) {
					if (e.keyCode == 27) { $('.surveyClose').click(); }   // esc
					else if( e.keyCode == 13 ) return;
				});
			}
			// show the modal window
			this.show = function(){
				if( this.okToShow() === true ){
					scrollToTop();
					$('<iframe src="https://docs.google.com/spreadsheet/embeddedform?formkey=dHVleGpuWHdnVGtOc1JLSGlmWG10Z2c6MQ" width="490" height="340" frameborder="0" marginheight="0" scrolling="no" marginwidth="0" allowTransparency="true">Loading...</iframe>')
						.bind('close', function () {
							cookie.set('survey', '1');
						})
						.ssmodal({title: 'Survey'});
						
				}
			}

			// check before showing the survey; it'll check the route, logged in condition, and whether user has filled the survey in or not.
			this.okToShow = function() {
				if( this._getABRoute() == 'contact-viewable-by-public' ) {
					if( this._isFilled() === true ) {
						return false;
					} else {
						return true;
					}
				} else {
					if( this._isLoggedIn() == true ){
						if( this._isFilled() === false ){
							return true;
						}else{
							return false;
						}
					}
				}
				return false;
			}
			// get the route from the ab testing
			this._getABRoute = function(){
				return window.ABRoute;
			}
			// check if user is logged in
			this._isLoggedIn = function(){
				return (window.user.id != '')?true:false;
			}
			// check if user has filled in the survey
			this._isFilled = function(){
				return ( cookie.get('survey') == 1 ) ? true:false ;
			}
		};

		function init(){
			var smw = new SurveyModalWindow();
			smw.init();
		}$(init); //Exec on document ready
		return {};

});