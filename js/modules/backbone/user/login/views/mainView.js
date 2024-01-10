define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	
	'backbone/shared/hbh/general',
	
	'libs/jquery.cookie.min',
	'libs/jquery.uniform',
	'libs/jquery.validity.min',
    'libs/jquery.validity.custom.output.login',
    'libs/jquery.tools.overlay.modified',
	'../collections/dataCollection',
	'text!templates/user/login/tpl/step1.html',
	'text!templates/user/login/tpl/step2.html',
	'text!templates/user/login/tpl/updateForm.html'
], function(
	$,
	_, 
	Backbone, 
	Hb,
	generalHbH,
	Cookie,
	Uniform,
	validity,
    validityCustom,
    Modal,
	dataCollection,
	userTpl,
	companyTpl,
	updateFormTpl
){
	var loginView = Backbone.View.extend({
		
		el: $('body'),

		events: {
			'click input.next' : 'nextStep',
			'click input[name="register"]' : 'showRegistration',
			'click a.goLogin' : 'showLogClicked',
			'click a.forgotPwd' : 'showForgotOnClick'
		},

		userTemplate: Handlebars.compile(userTpl),
		companyTemplate: Handlebars.compile(companyTpl),
		updateFormTemplate: Handlebars.compile(updateFormTpl),
		
		redirectUrl: require('/user/redirectUrl'),
		showVesselType: require('/user/showVesselType'),
		pwdSent: require('/user/passwordHasBeenSent'),

		jobType: require('/user/jobType'),
		companyName: require('/user/companyName'),

		initialize: function () {			
			this.compTypeCollection = new dataCollection();
			this.compTypeCollection.url = '/user/list-company-type';
			this.jobTypeCollection = new dataCollection();
			this.jobTypeCollection.url = '/user/list-job-type';
			this.countryCollection = new dataCollection();
			this.countryCollection.url = '/data/source/locations';
			this.annualBudgetCollection = new dataCollection();
			this.annualBudgetCollection.url = '/user/list-company-annual-budget';
			this.vesselCollection = new dataCollection();
			this.vesselCollection.url = '/user/list-vessel-type';

			var thisView = this;

			$('body').delegate('input[name="decInfo"]', 'click', function(e){
				e.preventDefault();
				thisView.openInfo('decisionInfo');
			});

			$('body').delegate('input[name="spendInfo"]', 'click', function(e){
				e.preventDefault();
				thisView.openInfo('spendInfo');
			});

			$('body').delegate('input[name="signIn"]' , 'click', function(e){
				e.preventDefault();
				thisView.loginSubmit();
			});

			$('body').delegate('input[name="sendPass"]' , 'click', function(e){
				e.preventDefault();
				thisView.sendPassSubmit();
			});

			$('body').delegate('select[name="companyType"]', 'change', function(e){
				if($(e.target).val() == 9){
					$('label[for="companyOtherType"]').css('display', 'inline-block');
					$('input[name="companyOtherType"]').show();
				}
				else {
					$('label[for="companyOtherType"]').hide();
					$('input[name="companyOtherType"]').hide();
				}
			});

			$('body').delegate('select[name="jobType"]', 'change', function(e){
				if($(e.target).val() == 16){
					$('label[for="jobOtherType"]').css('display', 'inline-block');
					$('input[name="jobOtherType"]').show();
					$('#uniform-jobType').css('margin-bottom', '3px');
				}
				else {
					$('label[for="jobOtherType"]').hide();
					$('input[name="jobOtherType"]').hide();
					$('#uniform-jobType').css('margin-bottom', '12px');
				}
			});
		},

		getData: function(){
			var thisView = this;
			this.compTypeCollection.fetch({
				complete: function(){
					thisView.jobTypeCollection.fetch({
						complete: function(){
							thisView.countryCollection.fetch({
								complete: function(){
									thisView.annualBudgetCollection.fetch({
										complete: function(){
											thisView.vesselCollection.fetch({
												complete: function(){
													thisView.render();
												}
											});
										}
									});
								}
							});
						}
					});
				}
			});
		},

		getDataUpdate: function(){
			var thisView = this;
			this.compTypeCollection.fetch({
				complete: function(){
					thisView.jobTypeCollection.fetch({
						complete: function(){
							thisView.countryCollection.fetch({
								complete: function(){
									thisView.annualBudgetCollection.fetch({
										complete: function(){
											thisView.vesselCollection.fetch({
												complete: function(){
													thisView.renderUpdate();
												}
											});
										}
									});
								}
							});
						}
					});
				}
			});
		},

		render: function() {
			var data = this.compTypeCollection;
			var html = this.userTemplate(data);
			$('.registerBox .formContainer form .fields').html(html);
			$('select').uniform();
			this.showRegistration();
		},

		showForgotOnClick: function(e){
			e.preventDefault();
			this.showForgot();
		},

		showForgot: function(){
			$('.loginBox.login').hide();
			$('.loginBox.forgot').show();
			if($('.loginBox.forgot .left') - 15 !== $('.loginBox.forgot .right').height()){
				$('.loginBox.forgot .right').height($('.loginBox.forgot .left').height() - 15);
			}
			if(this.pwdSent == 1){
				this.showLogin();
			}
		},

		showRegistration: function() {
			var thisView = this;
			if($('.step1').length === 0){
				this.getData();
			}
			else {
				$('.loginBox').hide();
				$('.registerBox').show();
			}
			$('#waiting').hide();
			$('p.infoText').hide();
		},

		showUpdate: function() {
			$('.loginBox').hide();
			this.renderUpdate();
			$('#waiting').hide();
		},

		showLogin: function(cache) {
			if(!this.loaded){
				$('input[name="loginRememberMe"]').uniform();
				this.loaded = true;
			}

			$('.registerBox').hide();
			$('.loginBox.forgot').hide();
			$('.loginBox.login').show();
			$('input').removeClass('invalid');
			$('input[name="loginPassword"]').val('');
			$('#waiting').hide();
			if(!cache){
				$('.loginBox.login .right').height($('.loginBox.login .left').height() - 15);
			}
			else {
				$('.loginBox.login .right').height(334);
			}
			if(this.pwdSent == 1){
				$('form.pwdSent').show();
				$('.loginBox.login .right').height($('.loginBox.login .left').height() - 15);
				$('input[name="loginPassword"]').focus();
			}
			else {
				$('form.pwdSent').hide();
			}
		},

		showLogClicked: function(e){
			e.preventDefault();
			/*
			$('.error').remove();
			$('#form_error').remove();
			this.showLogin(true);*/
			var url = "https://";
				url += window.location.host;
				url += "/auth/cas/login?pageLayout=new&service=https://";
				url += window.location.host;
				url += "/user/cas?redirect=";
			window.location.href = url;
		},

		nextStep: function(e) {
			e.preventDefault();
			if(this.validateFirstStep()){
				var thisView = this;

				$('.steps').addClass('company');
				$('div.step1').hide();

				if($('div.step2').length === 0){
					this.showNextStep();
				}
				else {
					$('div.step2').show();
					if($('select[name="companyType"]').val() !== "1" && $('select[name="companyType"]').val() !== "2"){
						$('.vesselContainer').hide();
					}
					else {
						$('.vesselContainer').show();
					}
					$('#waiting').hide();
				}
			}
		},

		showNextStep: function(){
			var thisView = this,
				data = {};
			data.jobTypes = this.jobTypeCollection.models;
			data.countries = this.countryCollection.models;
			data.annualBudget = this.annualBudgetCollection.models;
			data.vessels = this.vesselCollection.models;

			var html = this.companyTemplate(data);

			$('.registerBox .formContainer form').append(html);

			if($('select[name="companyType"]').val() !== "1" && $('select[name="companyType"]').val() !== "2"){
				$('.vesselContainer').hide();
			}

			$('select[name="jobType"]').uniform();
			$('select[name="country"]').uniform();
			$('select[name="spend"]').uniform();

			$('input[name="vesselType[]"]').uniform();
			$('input[name="agree"]').uniform();
			$('input[name="news"]').uniform();
			$('input[name="keepLoggedIn"]').uniform();

			$('input[type="radio"]').uniform();

			$('input.back').bind('click', function(e){
				e.preventDefault();
				$('div.step2').hide();
				$('div.step1').show();
			});

			$('input.register').bind('click', function(e){
				e.preventDefault();
				thisView.postForm();
			});

			$('#waiting').hide();
		},

		renderUpdate: function(){
			var thisView = this,
				data = {};
			data.jobTypes = this.jobTypeCollection.models;
			data.countries = this.countryCollection.models;
			data.annualBudget = this.annualBudgetCollection.models;
			data.vessels = this.vesselCollection.models;
			data.compType = this.compTypeCollection.models;
			data.jobType = this.jobType;
			data.companyName = this.companyName;
			var html = this.updateFormTemplate(data);

			$('.registerBox .formContainer form').append(html);

			if($('select[name="companyType"]').val() !== "1" && $('select[name="companyType"]').val() !== "2"){
				$('.vesselContainer').hide();
			}

			$('select[name="companyType"]').uniform();
			$('select[name="jobType"]').uniform();
			$('select[name="country"]').uniform();
			$('select[name="spend"]').uniform();

			$('input[name="vesselType[]"]').uniform();
			$('input[name="agree"]').uniform();
			$('input[name="news"]').uniform();
			$('input[name="keepLoggedIn"]').uniform();

			$('input[type="radio"]').uniform();

			$('div.steps').hide();
			$('p.infoText').show();

			$('.registerBox h1.styled').html('Please update your profile information');

			$('.registerBox').show();

			$('input.update').bind('click', function(e){
				e.preventDefault();
				thisView.postUpdate();
			});

			if(this.showVesselType == 1) {
				$('.vesselContainer').show();
			}

			$('#waiting').hide();
		},

		validateFirstStep: function(){
			$.validity.setup({ outputMode:"custom" });

			$.validity.start();

			$('input[name="firstName"]').require('Please enter your First Name.');
			$('input[name="lastName"]').require('Please enter your Last Name.');
			$('input.eml').require('Please enter your Email Address.')
				.match(/@/, 'Please enter a valid Email Address');
			$('input.pwd.first').require('Please enter a password.')
				.minLength(6, 'The password has to be at least 6 characters.');
			$('input[name="passwordConf"]').require('Please confirm your password.')
			if($('input[name="passwordConf"]').val() !== "" && $('input.pwd.first').val() !== ""){
				$('input.pwd').equal('The passwords do not match.');
			}
			
			$('select[name="companyType"]').require('Please choose a Company Type');

			var result = $.validity.end();

			return result.valid;
		},

		validateSecondStep: function(){
			$.validity.setup({ outputMode:"custom" });

			$.validity.start();

			$('select[name="jobType"]').require('Please select your Job Type.');
			$('input[name="companyName"]').require('Please enter a Company Name.');
			$('input[name="address1"]').require('Please enter an Address.');
			$('input[name="zip"]').require('Please enter a Zip/Post Code.');
			$('select[name="country"]').require('Please select a Country.');
			$('select[name="companyType"]').require('Please choose a Company Type');

			var result = $.validity.end();

			if(!$('input[name="agree"]').is(':checked')){
				result.valid = false;
				if($('.error.terms').length === 0){
					$('input[name="agree"]').parent().parent().next('label').after("<div class='error terms'>You have to accept our terms and conditions.</div><div class='clear err terms'></div>");
				}
			}
			else {
				$('.error.terms').remove();
				$('.err.terms').remove();
			}

			return result.valid;
		},

		validateUpdate: function(){
			$.validity.setup({ outputMode:"custom" });

			$.validity.start();

			$('select[name="jobType"]').require('Please select your Job Type.');
			$('input[name="companyName"]').require('Please enter your Company Name.');
			$('input[name="address1"]').require('Please enter an Address.');
			$('input[name="zip"]').require('Please enter a Zip/Post Code.');
			$('select[name="country"]').require('Please select a Country.');

			var result = $.validity.end();

			if(!$('input[name="agree"]').is(':checked')){
				result.valid = false;
				if($('.error.terms').length === 0){
					$('input[name="agree"]').parent().parent().next('label').after("<div class='error terms'>You have to accept our terms and conditions.</div><div class='clear err terms'></div>");
				}
			}
			else {
				$('.error.terms').remove();
				$('.err.terms').remove();
			}

			return result.valid;
		},

		validateLogin: function(){
			$.validity.setup({ outputMode:"custom" });

			$.validity.start();

			$('input[name="loginUsername"]').require('Please enter your Email.')
				.match(/@/, 'Please enter a valid Email Address');
			$('input[name="loginPassword"]').require('Please enter your Password.');

			var result = $.validity.end();

			if($('.loginBox.login .left') - 15 !== $('.loginBox.login .right').height()){
				$('.loginBox.login .right').height($('.loginBox.login .left').height() - 15);
			}

			return result.valid;
		},

		validateSendPass: function(){
			$.validity.setup({ outputMode:"custom" });

			$.validity.start();

			$('input[name="forgotUsername"]').require('Please enter your Email.')
				.match(/@/, 'Please enter a valid Email Address');

			var result = $.validity.end();

			if($('.loginBox.forgot .left') - 15 !== $('.loginBox.forgot .right').height()){
				$('.loginBox.forgot .right').height($('.loginBox.forgot .left').height() - 15);
			}

			return result.valid;
		},

		postForm: function(){
			if(this.validateSecondStep()){
				var thisView = this;
				$.post('/user/register', $('form.regForm').serialize(), function(data){

					if (data.status == true) {
						if (data.redirect != "") {
							location.href = data.redirect;
						} else {
							location.href = '/search';
						}
					} else {
						var html = '<form class="new">';
						
						_.each(data.errors.top, function(item){
							html += '<div class="error" style="margin-left: 0; margin-bottom: 20px;">' + item + '</div>';
						});
						
						html += '</form>';
						
						$('div.steps').after(html);
						$(window).scrollTop(0);						
					}			
				});
			}
		},

		postUpdate: function(){
			if(this.validateUpdate()){
				var thisView = this;
				$.post('/user/complete-profile', $('form.regForm').serialize(), function(data){
					if( data.status == true ){
						if( data.redirect != "" ){
							location.href = data.redirect;
						}else{
							location.href = '/search';
						}
					}else{
						var html = '<form class="new">';
						
						_.each(data.errors.top, function(item){
							html += '<div class="error" style="margin-left: 0; margin-bottom: 20px;">' + item + '</div>';
						});
						
						html += '</form>';
						
						$('div.steps').after(html);
						$(window).scrollTop(0);
					}
				});
			}
		},
		
		loginSubmit: function(e){
			$.cookie('loggedIn', true, { path : '/' });
			if(this.validateLogin()){
				$('#login-form').submit();
			}
		},

		sendPassSubmit: function(e){
			if(this.validateSendPass()){
				$('#forgot-form').submit();
			}
		},

		openDialog: function(){
			$("#modal").overlay({
                mask: 'black',
                left: 'center',
                fixed: false,

                onBeforeLoad: function() {
                    var windowWidth = $(window).width();
                    var modalWidth = $('#modal').width();
                    var posLeft = windowWidth/2 - modalWidth/2;

                    $('#modal').css('left', posLeft);
                },

                onLoad: function() {
                    $(window).resize(function(){
                        var windowWidth = $(window).width();
                        var modalWidth = $('#modal').width();
                        var posLeft = windowWidth/2 - modalWidth/2;

                        $('#modal').css('left', posLeft);
                    });
                }
            });

            $('#modal').overlay().load();
		},

		openInfo: function(infoType){
			$('.modal.' + infoType).overlay({
                mask: 'black',
                left: 'center',
                fixed: true,

                onBeforeLoad: function() {
                    var windowWidth = $(window).width();
                    var modalWidth = $('.modal.' + infoType).width();
                    var posLeft = windowWidth/2 - modalWidth/2;

                    $('.modal.' + infoType).css('left', posLeft);
                },

                onLoad: function() {
                    $(window).resize(function(){
                        var windowWidth = $(window).width();
                        var modalWidth = $('.modal.' + infoType).width();
                        var posLeft = windowWidth/2 - modalWidth/2;

                        $('.modal.' + infoType).css('left', posLeft);
                    });
                }
            });

            $('.modal.' + infoType).overlay().load();
		}		
	});

	return new loginView;
});
