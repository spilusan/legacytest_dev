define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'libs/jquery.tools.overlay.modified',
	'text!templates/store/membership/tpl/membership.html',
	'text!templates/store/membership/tpl/po.html',
	'text!templates/store/membership/tpl/credits.html',
	'text!templates/store/membership/tpl/listing.html',
	'text!templates/store/membership/tpl/order.html',
	'text!templates/store/membership/tpl/track.html',
	'text!templates/store/membership/tpl/cat.html',
	'text!templates/store/membership/tpl/logo.html',
	'text!templates/store/membership/tpl/salesdoc.html',
	'text!templates/store/membership/tpl/accman.html',
	'text!templates/store/membership/tpl/premiumlisting.html',
	'text!templates/store/membership/tpl/smart.html',
	'text!templates/store/membership/tpl/sir.html'

], function(
	$, 
	_, 
	Backbone,
	Hb,
	Modal,
	memberTpl,
	poTpl,
	creditsTpl,
	listingTpl,
	orderTpl,
	trackTpl,
	catTpl,
	logoTpl,
	salesDocTpl,
	accManTpl,
	premiumTpl,
	smartTpl,
	sirTpl
){
	var helpView = Backbone.View.extend({
		el: $('body'),

		events: {
			'click input[type="image"]' : 'getInfoType',
			'click a.xmpl'				: 'showExample',
			'click a.smart'				: 'showSmart',
			'click a.sir'				: 'showSir'
		},

		template: Handlebars.compile(memberTpl),

		render: function(fixed, el){
			var html = this.template(),
				ele = el + ' .modalBody';
			$(ele).html(html);
			this.openDialog(fixed, el);
		},

		getInfoType: function(e){
			e.preventDefault();

			if($(e.target).is('input')){
				var type = $(e.target).attr('name');
			}
			else {
				var type = $(e.target).attr('class');
			}

			if(type === "memberInfo"){
				this.template = Handlebars.compile(memberTpl);
			}
			else if(type === "poInfo") {
				this.template = Handlebars.compile(poTpl);
			}
			else if(type === "creditsInfo") {
				this.template = Handlebars.compile(creditsTpl);
			}
			else if(type === "listingInfo") {
				this.template = Handlebars.compile(listingTpl);
			}
			else if(type === "orderInfo") {
				this.template = Handlebars.compile(orderTpl);
			}
			else if(type === "trackInfo") {
				this.template = Handlebars.compile(trackTpl);
			}
			else if(type === "catInfo") {
				this.template = Handlebars.compile(catTpl);
			}
			else if(type === "logoInfo") {
				this.template = Handlebars.compile(logoTpl);
			}
			else if(type === "salesDocInfo") {
				this.template = Handlebars.compile(salesDocTpl);
			}
			else if(type === "accManInfo") {
				this.template = Handlebars.compile(accManTpl);
			}

			this.render(true, '#modal');
		},

		showExample: function(e) {
			e.preventDefault();
			this.template = Handlebars.compile(premiumTpl);
			this.render(false, '#modalBig');
		},

		showSmart: function(e) {
			e.preventDefault();
			this.template = Handlebars.compile(smartTpl);
			this.render(false, '#modalBig');
		},

		showSir: function(e) {
			e.preventDefault();
			this.template = Handlebars.compile(sirTpl);
			this.render(false, '#modalBig');
		},

        openDialog: function(fxpar, el) {
            $(el).overlay({
                mask: 'black',
                left: 'center',
                fixed: fxpar,

                onBeforeLoad: function() {
                    var windowWidth = $(window).width();
                    var modalWidth = $(el).width();
                    var posLeft = windowWidth/2 - modalWidth/2;

                    $(el).css('left', posLeft);
                },

                onLoad: function() {
                    $(window).resize(function(){
                        var windowWidth = $(window).width();
                        var modalWidth = $(el).width();
                        var posLeft = windowWidth/2 - modalWidth/2;

                        $(el).css('left', posLeft);
                    });
                },

                onClose: function() {
                	var ele = el + ' .modalBody';
                    $(ele).html('');
                }
            });

            $(el).overlay().load();
        }
	});

	return new helpView;
});
