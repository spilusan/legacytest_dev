define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/shared/hbh/general',
	'libs/highlight.pack',
	'text!templates/shipmate/design-guidelines/tpl/buttonsHtml.html',
	'text!templates/shipmate/design-guidelines/tpl/buttonsScss.html',
	'text!templates/shipmate/design-guidelines/tpl/tablesHtml.html',
	'text!templates/shipmate/design-guidelines/tpl/tablesScss.html',
	'text!templates/shipmate/design-guidelines/tpl/coloursHtml.html',
	'text!templates/shipmate/design-guidelines/tpl/coloursScss.html',
	'text!templates/shipmate/design-guidelines/tpl/formsHtml.html',
	'text!templates/shipmate/design-guidelines/tpl/formsScss.html',
	'text!templates/shipmate/design-guidelines/tpl/typoHtml.html',
	'text!templates/shipmate/design-guidelines/tpl/typoScss.html',
	'text!templates/shipmate/design-guidelines/tpl/tabContentUse.html',
	'text!templates/shipmate/design-guidelines/tpl/tabContentUseImprove.html'
], function(
	$,
	_, 
	Backbone,
	Hb,
	generalHbh,
	hljs,
	buttonsHtmlTpl,
	buttonsScssTpl,
	tablesHtmlTpl,
	tablesScssTpl,
	coloursHtmlTpl,
	coloursScssTpl,
	formsHtmlTpl,
	formsScssTpl,
	typoHtmlTpl,
	typoScssTpl,
	tabContentUseTpl,
	tabContentUseImpTpl
){
	String.prototype.escape = function() {
	    var tagsToReplace = {
	        '&': '&amp;',
	        '<': '&lt;',
	        '>': '&gt;'
	    };
	    
	    return this.replace(/[&<>]/g, function(tag) {
	        return tagsToReplace[tag] || tag;
	    });
	};

	var mainView = Backbone.View.extend({
		el: $('body'),

		buttonsHtmlTemplate: Handlebars.compile(buttonsHtmlTpl),
		buttonsScssTemplate: Handlebars.compile(buttonsScssTpl),
		tablesHtmlTemplate: Handlebars.compile(tablesHtmlTpl),
		tablesScssTemplate: Handlebars.compile(tablesScssTpl),
		coloursHtmlTemplate: Handlebars.compile(coloursHtmlTpl),
		coloursScssTemplate: Handlebars.compile(coloursScssTpl),
		formsHtmlTemplate: Handlebars.compile(formsHtmlTpl),
		formsScssTemplate: Handlebars.compile(formsScssTpl),
		typoHtmlTemplate: Handlebars.compile(typoHtmlTpl),
		typoScssTemplate: Handlebars.compile(typoScssTpl),
		tabContentUseTemplate: Handlebars.compile(tabContentUseTpl),
		tabContentUseImpTemplate: Handlebars.compile(tabContentUseImpTpl),

		snipet: "colours",

		events: {
			'click ul#nav li a' : 'renderContent',
			'click .tabs a' 	: 'toggleCode',
			//'click .toggleMenu'	: 'toggleMenu',
			//'click #corporateNav ul.top > li > a' : 'toggleSub',
			//'click .tabBlock-tab' : 'onTabClicked',
			//'click nav.secondLevel ul li.selected a' : 'toggleWpSecondNav'
		},
		
		initialize: function() {
			this.renderContent();
		},

		render: function(){
			$('pre code').each(function(){
				$(this).html($(this).html().escape());
			});

			$('pre code').each(function(i, block) {
			    hljs.highlightBlock(block);
			});

			var thisView = this;

			$('.tabBlock-content').html(this.tabContentUseTemplate());

			$('body').delegate('.company ul li:first-child a', 'click', function(e){
				thisView.toggleCompDrop(e);
			});
		},

		renderContent: function(e){
			if(e){
				e.preventDefault();
				this.snipet = $(e.target).attr('href');
				$('ul#nav li').removeClass('selected');
				$(e.target).parent('li').addClass('selected');
			}

			switch(this.snipet) {
				case "colours":
			        $('.display').html(this.coloursHtmlTemplate());
			        $('pre.html code').html(this.coloursHtmlTemplate());
			        $('pre.scss code').html(this.coloursScssTemplate());
			        break;
			    case "typo":
			        $('.display').html(this.typoHtmlTemplate());
			        $('pre.html code').html(this.typoHtmlTemplate());
			        $('pre.scss code').html(this.typoScssTemplate());
			        break;
			    case "buttons":
			        $('.display').html(this.buttonsHtmlTemplate());
			        $('pre.html code').html(this.buttonsHtmlTemplate());
			        $('pre.scss code').html(this.buttonsScssTemplate());
			        break;
			    case "tables":
			        $('.display').html(this.tablesHtmlTemplate());
			        $('pre.html code').html(this.tablesHtmlTemplate());
			        $('pre.scss code').html(this.tablesScssTemplate());
			        break;
			     case "forms":
			        $('.display').html(this.formsHtmlTemplate());
			        $('pre.html code').html(this.formsHtmlTemplate());
			        $('pre.scss code').html(this.formsScssTemplate());
			        break;
			}

			this.render();
		},

		toggleCode: function(e){
			e.preventDefault();
			$('pre').hide();
			$('.tabs a').removeClass('active');
			$(e.target).addClass('active');
			var toShow = 'pre.' + $(e.target).attr('href');
			$(toShow).show();

		},

		toggleMenu: function(e) {
			e.preventDefault();
			var elem;
			/*
			 * parseInt was added to fix isue on IE11<, as it returns as an integer 
			 */
			if(parseInt($('#corporateNav').css('z-index')) === 900){
				elem = '#corporateNav';
			}
			else {
				elem = '.navigations';
			}

			if($(elem).hasClass('open')){
				$(elem).addClass('closed');
				$(elem).removeClass('open');
			}
			else {
				$(elem).removeClass('closed');
				$(elem).addClass('open');
			}
		},

		toggleSub: function(e){
			e.preventDefault();
			if($(e.target).parent().hasClass('open')){
				$(e.target).parent().removeClass('open');
			}
			else {
				$(e.target).parent().parent().find('li').each(function(){
					$(this).removeClass('open');
				});
				$(e.target).parent().addClass('open');
			}
		},

		toggleWpSecondNav: function(e) {
			e.preventDefault();
			$(e.target).parent().parent().parent().toggleClass('open');
		},

		toggleCompDrop: function(e) {
			e.preventDefault();
			$(e.target).parent().parent().parent().toggleClass('open');
		},

		removeMenuClasses: function(){
			$('#corporateNav').removeClass('closed');
			$('#corporateNav').removeClass('open');

			$('.navigations').removeClass('closed');
			$('.navigations').removeClass('open');

			$('nav.secondLevel').removeClass('open');
		},

		onTabClicked: function(e){
			e.preventDefault();
			var content = $(e.target).attr('href');

			switch(content) {
				case "use":
			        $('.tabBlock-content').html(this.tabContentUseTemplate());
			        break;
			    case "imp":
			        $('.tabBlock-content').html(this.tabContentUseImpTemplate());
			        break;
			}

			$('.tabBlock-tab').removeClass('is-active');
			$(e.target).parent().addClass('is-active');
		}
	});

	return new mainView();
});
