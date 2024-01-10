define([
	'jquery',
	'underscore',
	'Backbone',
	'backbone/user/login/views/mainView'
], 

function(
	$, 
	_, 
	Backbone, 
	mainView
){
	
	var AppRouter = Backbone.Router.extend({
		isLoggedIn: require('/user/isLoggedIn'),
		hasCompleted: require('/user/hasCompleted'),
		redirectUrl: require('/user/redirectUrl'),

		routes: {
			'forgot'			: 'showForgot',
			'register'			: 'showRegister',
			'update'			: 'showUpdate',
			'*actions'			: 'defaultAction'
		},

		showForgot: function(){
			var thisView = this;
			$(function(){
				if(thisView.isLoggedIn === 0){
					mainView.showForgot();
				}
				else {
					if(thisView.hasCompleted === 0){
						mainView.getDataUpdate();
					}
				}
			});
		},

		showRegister: function(){
			var thisView = this;
			$(function(){
				if(thisView.isLoggedIn === 0){
					mainView.getData();
				}
				else {
					if(thisView.hasCompleted === 0){
						mainView.getDataUpdate();
					}
					else {
						if(thisView.redirectUrl == ""){
							window.location.href = "/search";
						}
					}
				}
			});
		},

		showUpdate: function(){
			var thisView = this;
			$(function(){
				if(thisView.isLoggedIn === 0){
					mainView.showLogin();
				}
				else {
					if(thisView.hasCompleted === 0){
						mainView.getDataUpdate();
					}
					else {
						if(thisView.redirectUrl == ""){
							window.location.href = "/search";
						}
					}
				}
			});
		},

		defaultAction: function(){
			var thisView = this;
			$(function(){
				if(thisView.isLoggedIn === 0){
					mainView.showLogin();
				}
				else {
					if(thisView.hasCompleted === 0){
						mainView.getDataUpdate();
					}
				}
			});
		}
	});

	return AppRouter;
});
