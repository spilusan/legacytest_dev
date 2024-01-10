define([
	'jquery',
	'underscore',
	'Backbone',
	'backbone/user/login/router'
], function(
	$, 
	_, 
	Backbone, 
	Router
){
	var initialize = function(){
		var AppRouter = new Router();

		Backbone.history.start({
			pushState: true,
			root: '/user/register-login/'
		});
	}

	initialize();
});
