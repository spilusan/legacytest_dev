define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/shared/hbh/general',
	'../views/usageDashboardView'
], function(
	$,
	_, 
	Backbone,
	Hb,
	generalHbh,
	usageDashboard
){
	var mainView = Backbone.View.extend({
		el: $('body'),
		
		initialize: function() {
			$(document).ajaxStart(function(){
				$('#waiting').show();
			});

			$(document).ajaxStop(function(){
				$('#waiting').hide();
			});

			this.usageDashboardView = new usageDashboard();
			this.usageDashboardView.parent = this;
		},

        openDialog: function(dialog) { 
            $(dialog).overlay({
                mask: 'black',
                left: 'center',
                fixed: false,

                onBeforeLoad: function() {
                    var windowWidth = $(window).width();
                    var modalWidth = $(dialog).width();
                    var posLeft = windowWidth/2 - modalWidth/2;

                    $(dialog).css('left', posLeft);
                }
            });

            $(dialog).overlay().load();
        },

        renderAjaxErrorMessage: function( errorMessage )
        {
        	var html = this.ajaxErrorTemplate({message:errorMessage}); 
        	
        	$(".innerContent").html(html);
        },

        /* 
			This function converts date from 20150101 to 01-JAN-2014, 
			this part may be removed later, if backend will except other formats too
		*/
        dateConvertToShortMonthFormat: function( dateString ) 
		{

			var ds = dateString+''; // Just to make sure it is a text
			var monthNames = new Array('JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC');
			return ds.substring(6)+'-'+monthNames[parseInt(ds.substring(4,6))-1]+'-'+ds.substring(0,4);

		},

		onError: function(errorMsg, url, lineNumber)
		{
			//var errorMessage = '<div class="ajaxError">'+errorMsg+" line("+lineNumber+")  in "+url+'</div>'
			var errorM = "We're sorry, there is a problem. Please try again later.";
			var errorMessage = '<div class="ajaxError">'+errorM+'</div>';
        	$(".dataContainer").html(errorMessage);
        	$('#waiting').hide();
		}
	});

	return new mainView;
});
