define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'libs/jquery.uniform',
    'libs/jquery.tools.overlay.modified'
], function(
	$,
	_, 
	Backbone, 
	Hb,
	Uniform,
    Modal

){
	var myInfoView = Backbone.View.extend({
		
		el: $('body'),

		events: {

		},

		showPwd: require('profile/showPwd'),

		initialize: function () {
			this.render();
		},

		render: function() {
			var thisView = this;

			$(function(){
				$('form.profile-form select').uniform();
				$('form.profile-form input[type="checkbox"]').uniform();
				$('form.profile-form input[type="radio"]').uniform();
				
				$('body').delegate('input[name="decInfo"]', 'click', function(e){
					e.preventDefault();
					thisView.openInfo('decisionInfo');
				});

				$('body').delegate('input[name="spInfo"]', 'click', function(e){
					e.preventDefault();
					thisView.openInfo('spendInfo');
				});

				$('body').delegate('a.chgPwd', 'click', function(e){
					e.preventDefault();
					thisView.openDialog();
				});

				$('body').delegate('select[name="jobFunction"]', 'change', function(e){
					if($(e.target).val() == 16){
						$('label[for="otherJobFunction"]').show();
						$('input[name="otherJobFunction"]').show();
					}
					else {
						$('label[for="otherJobFunction"]').hide();
						$('input[name="otherJobFunction"]').hide();
					}
				});

				$('body').delegate('select[name="companyType"]', 'change', function(e){
					if($(e.target).val() == 9){
						$('label[for="otherCompanyType"]').show();
						$('input[name="otherCompanyType"]').show();
					}
					else {
						$('label[for="otherCompanyType"]').hide();
						$('input[name="otherCompanyType"]').hide();
					}
					
					if($('select[name="companyType"]').val() !== "1" && $('select[name="companyType"]').val() !== "2"){
						$('.vesselInfo').hide();
					}
					else {
						$('.vesselInfo').show();
					}
				});

				if(thisView.showPwd === 1) {
					thisView.openDialog();
				}

				if($('select[name="companyType"]').val() !== "1" && $('select[name="companyType"]').val() !== "2"){
					$('.vesselInfo').hide();
				}
				else {
					$('.vesselInfo').show();
				}
			});
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

	return new myInfoView;
});
