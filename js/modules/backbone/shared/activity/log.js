define([
	"jquery"
], function(
	$ 
){
	return {
		logActivity: function( activity, info )
		{
			$.ajax({
				  type: "POST",
				  url: '/reports/log-js-event',
				  data: {
				  	event: activity,
				  	info: info
				  },
				  success: function( response )
				  {

				  },
				  error: function( error )
				  {

				  }
				});

		}
	}

});