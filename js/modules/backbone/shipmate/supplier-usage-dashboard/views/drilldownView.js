define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/shared/hbh/general',
	'../views/userActivityView'
], function(
	$,
	_, 
	Backbone,
	Hb,
	generalHbh,
	userActivity
){
	var drilldownView = Backbone.View.extend({
		el: $('body'),
		params: require('supplier/params'),
		
		initialize: function() {
			$(document).ajaxStart(function(){
				$('#waiting').show();
			});

			$(document).ajaxStop(function(){
				$('#waiting').hide();
			});
			
			/*
			* Add new reports here
			*/
			switch(this.params.type) {
			   case 'userActivity':
			        this.reportView = new userActivity();
			        break;
				default:
			        alert('Report does not exists');
			        return;
			}

			this.reportView.parent = this;
			this.reportView.getCollection();

			this.render();
		},

		render: function(){		
			this.reportView.render();
		},

        renderAjaxErrorMessage: function( errorMessage )
        {
        	var html = this.ajaxErrorTemplate({message:errorMessage}); 
        	
        	$(".innerContent").html(html);
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

	return new drilldownView();
});
