define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'text!templates/spr/tpl/tabs.html'
], function (
	$,
	_,
	Backbone,
	Hb,
	tabsTpl
) {
		var view = Backbone.View.extend({
			events: {
				/* 'click a' : 'render' */
			},
			tabsTemplate: Handlebars.compile(tabsTpl),
			parent: null,
			rendered: false,
			hideFilter: require('spr/hideFilter'),
			initialize: function () {
				var thisView = this;

				var Tabs = {
					parent: thisView,
					lastHash: null,

					init: function () {
						this.lastHash = '';
						this.bindUIfunctions();
					},

					bindUIfunctions: function () {
						// Delegation
						$(document)
							.on("click", ".spr-tabs a[href^='#']:not('.active')", function (event) {
								event.preventDefault();
								Tabs.changeTab($(event.target).attr('href'), true);
							})
							.on("click", ".spr-tabs a.active", function (event) {
								Tabs.toggleMobileMenu(event, this);
								event.preventDefault();
							});

					},

					changeTab: function (hash, reload) {
						var anchor = $("[href=" + hash + "]");
						var div = $(hash);

						// activate correct anchor (visually)
						anchor.addClass("active").parent().siblings().find("a").removeClass("active");

						// activate correct div (visually)
						div.addClass("active").siblings().removeClass("active");

						// update URL, no history addition
						// You'd have this active in a real situation, but it causes issues in an <iframe> (like here on CodePen) in Firefox. So commenting out.
						// window.history.replaceState("", "", hash);

						// Close menu, in case mobile
						anchor.closest("ul").removeClass("open");

						if (Tabs.parent.parent && this.lastHash !== hash) {
							this.lastHash = hash;
							// Run the report again in case of tab switch to rerender the report
							if (reload) {
								$.ajaxQ.abortAll();
							}

                            Tabs.parent.parent.runReport(true);
						}

					},

					toggleMobileMenu: function (event, el) {
						$(el).closest("ul").toggleClass("open");
					}

				};

				this.Tabs = Tabs;
			},

			render: function () {
				if (this.rendered === false) {
					var tabsHtml = this.tabsTemplate();

					$('#tabs').empty();
					$('#tabs').html(tabsHtml);
					this.Tabs.init();

					if (parseInt(this.hideFilter) === 1) {
						$('.spr-tabs').hide();
					}

					this.rendered = true;
				}
			},

			isRendered: function () {
				return this.rendered;
			}
		});
		return new view();
	});