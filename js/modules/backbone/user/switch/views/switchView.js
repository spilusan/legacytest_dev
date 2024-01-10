define([
	'jquery',
	'underscore',
	'Backbone'
], function(
	$,
	_, 
	Backbone
){
	var switchView = Backbone.View.extend({
		el: $('body'),
		
		companyId: require('alert/tnid'),
		isShipServ: require('alert/isShipmate'),
		switcherUser: require('alert/switcherUser'),

		initialize: function() {
			if(!this.switcherUser)
			{
				this.isShipServ = false;
			}
			this.render();
		},

		render: function() {
			var thisView = this;
				
			$(function(){
				if(thisView.isShipServ){
					thisView.setInputWidth();
					thisView.hideDropDown();
					thisView.bindEvents();				
				}
				else {
					thisView.setDropDownWidth();
					thisView.hideDropDown();
					thisView.bindEvents();
				}
			});
		},

		bindEvents: function(){
			var thisView = this;

			$('div#switchContainer form a#selectedSwitchCompany').bind('click', function(e){
				e.preventDefault();
				thisView.toggleDropDown();
			});

			$('#switchContainer form a#compSelArrow').bind('click', function(e){
				e.preventDefault();
				thisView.toggleDropDown();
			});

			$('body').bind('click', function(e){
				if((!$(e.target).is('#selectedSwitchCompany') === true) && (!$(e.target).is('#compSelArrow') === true) && (!$(e.target).is('#header .companySwitchList li') === true) && (!$(e.target).is('#header .companySwitchList input') === true)) {
					$('#switchContainer .companySwitchList').hide();
				}
			});

			$('#header .companySwitchList li').bind('click', function(e){
				e.preventDefault();
				thisView.switchCompany(e);
			});

			$('#header .companySwitchList input[type="button"]').bind('click', function(e){
				e.preventDefault();
				thisView.switchCompany(e);
			});

			$('#header .companySwitchList input[type="text"]').bind('keyup', function(e){
				var code = (e.keyCode ? e.keyCode : e.which);
				if(code == 13) {
					e.preventDefault();
					thisView.switchCompany(e);
				}
			});
		},

		setDropDownWidth: function(){
			var maxWidth = 350,
				itemWidth = 0,
				widest = 0;

			$('.companySwitchList li').each(function(index){
				itemWidth = $(this).width();
				if (itemWidth > widest) {
					widest = itemWidth;
				}
			});

			if(widest > maxWidth) {
				widest = 350;
			}

			$('#switchContainer form').width(widest + 10);

			$('#switchContainer form #selectedSwitchCompany').width(widest - 25);

			$('#switchContainer .companySwitchList').width(widest + 10);
			$('#switchContainer .companySwitchList li').width(widest);
		},

		setInputWidth: function(){
			var width = $('#switchContainer').width() - 20;
			if(width > 340) {
				width = 340;
			}

			$('#switchContainer .companySwitchList').width(width);
		},

		hideDropDown: function() {
			$('#switchContainer .companySwitchList').hide();

			$('#switchContainer .companySwitchList').css('opacity', 1);
			$('#switchContainer .companySwitchList').css('filter', 'alpha(opacity=100)');

			$('#switchContainer .companySwitchList li').css('opacity', 1);
			$('#switchContainer .companySwitchList li').css('filter', 'alpha(opacity=100)');
		},

		toggleDropDown: function(){
			if($('#switchContainer .companySwitchList').css('display') === "none"){
				$('#switchContainer .companySwitchList').show();
				if(this.isShipServ){
					$('#header .companySwitchList input[type="text"]').focus();
				}
			}
			else {
				$('#switchContainer .companySwitchList').hide();
			}
		},

		switchCompany: function(e){
			$('#userCompanySwitch #selectedSwitchCompany').html('Loading...');
			$('#switchContainer .companySwitchList').hide();

			if(this.isShipServ){
				$('#userCompanySwitch input').val($('#switchContainer .companySwitchList input[type="text"]').val());
				$('#userCompanySwitch').submit();
			}
			else {
				if($(e.target).find('input').val() !== this.companyId){
					$('#userCompanySwitch input').val($(e.target).find('input').val());
					$('#userCompanySwitch').submit();
				}
			}
		}
	});

	return new switchView;
});
