/*
 * Profile View
 * reports
 */
define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'components/map/views/mainView',
	'../collections/collection',
	'text!templates/spr/tpl/profileView.html'
], function (
	$,
	_,
	Backbone,
	Hb,
	GoogleMap,
	Collection,
	profileTpl
) {
	var view = Backbone.View.extend({
		events: {
			/* 'click a' : 'render' */
		},
		renderId: 0,
		selectedSupplier: 0,
		params: [],
		anonim: 0,
		showMap: require('spr/showMap'),
		profileTemplate: Handlebars.compile(profileTpl),
		initialize: function () {
			var thisView = this;
			this.collection = new Collection();
			this.gmvCollection = new Collection();
			this.tradingSinceCollection = new Collection();

			$(document).click(function () {
				// all dropdowns
				$('.wrapper-dropdown').removeClass('active');
			});

			this.isDataLoaded = false;
			this.completedRequests = 0;

			this.on('complete', function (event, request, settings) {
				++this.completedRequests;

				if (this.completedRequests === 3) {
					this.isDataLoaded = true;
				}
			});
		},

		getData: function (params, selectedName, reload) {
			var thisView = this;

			if (!reload && this.isDataLoaded) return;

			$('#tab-1').empty();
			$('#tab-1').append($('<div class="spinner"></div>'));

			if (params) {
				this.params = params;
			}
			/* TODO select proper params.suppliers[X] according to the selected Supplier */
			this.collection.url = '/reports/data/supplier-performance-profile/' + this.params.suppliers[this.selectedSupplier];
			this.collection.fetch({
				complete: function (result) {
					thisView.render(selectedName);
				}
			}).done(function () {
				thisView.trigger('complete');
			}).done(function () {
				thisView.trigger('complete');
			});
		},

		getGmvData: function () {
			var thisView = this;
			var today = new Date();
			var fromDate = new Date(this.collection.models[0].attributes.joinedDate);

			var lowerDate = "" + fromDate.getFullYear() + this.trailingZeros(fromDate.getMonth() + 1, 2) + this.trailingZeros(fromDate.getDate(), 2);
			var upperDate = "" + today.getFullYear() + this.trailingZeros(today.getMonth() + 1, 2) + this.trailingZeros(today.getDate(), 2);

			this.gmvCollection.url = '/reports/data/supplier-performance/gmv';
			this.gmvCollection.fetch({
				data: {
					tnid: this.params.suppliers[thisView.selectedSupplier],
					lowerdate: lowerDate,
					upperdate: upperDate
				},
				complete: function (result) {
					thisView.renderGmv();
				}
			}).done(function () {
				thisView.trigger('complete');
			}).done(function () {
				thisView.trigger('complete');
			});
		},

		getTradedSince: function () {
			var thisView = this;
			var bybList = this.params.buyers.join(',');
			this.tradingSinceCollection.url = '/reports/data/supplier-performance/started-trading';

			this.tradingSinceCollection.fetch({
				data: {
					tnid: this.params.suppliers[thisView.selectedSupplier],
					byb: bybList
				},
				complete: function (result) {
					thisView.renderTradedSince();
				}
			}).done(function () {
				thisView.trigger('complete');
			});
		},

		trailingZeros: function (n, len) {
			return (new Array(len + 1).join('0') + n).slice(-len);
		},

		render: function (selectedName) {
			this.renderId++;

			var thisView = this;
			var data = JSON.parse(JSON.stringify(this.collection.models[0].attributes));

			/*
			 * Brand names are not unique, as there are different other, currently not important parameters, We have to make a unique list 
			 */
			var brandNames = [];
			var brandName;
			for (var key in data.brands) {
				brandName = data.brands[key].name;
				if (brandNames.indexOf(brandName) === -1) {
					brandNames.push(brandName);
				}
			}

			data.uniqueBrandNames = brandNames;
			//categorie split
			data.defaultCategories = data.categories.splice(0, 3);

			//brands split
			data.defaultBrandNames = data.uniqueBrandNames.splice(0, 3);

			//ports split
			data.defaultPorts = data.ports.splice(0, 3);

			//traded with remove duplicates and split
			data.defaultTradedWith = _.uniq(data.tradedWith.names);
			data.defaultTradedWith = data.defaultTradedWith.splice(0, 3);

			data.supplierBranches = this.params.supplierList;
			data.displaySupplierBranches = (this.params.supplierList.length > 1);
			data.displayScope = (data.defaultCategories.length + data.uniqueBrandNames.length + data.ports.length > 0);

			if (selectedName && this.anonim === 1) {
				data.name = selectedName;
			}
			data.showMap = this.showMap;
			var html = this.profileTemplate(data);
			$('#tab-1').empty();
			$('#tab-1').html(html);

			$('.office-selector').click(function (e) {
				e.preventDefault();
				var selectedSupplier = parseInt($(this).data('id'));
				var i = 0;
				while (i < thisView.params.suppliers.length && parseInt(thisView.params.suppliers[i]) !== selectedSupplier) {
					i++;
				}
				thisView.selectedSupplier = i;
				thisView.getData(null, $(this).html(), true);

			});

			$('.see-more').click(function (e) {
				e.preventDefault();
				var className = '.' + $(this).attr('href').substr(1);
				if ($(className).is(':visible')) {
					$(className).hide();
					$($(this).find('span')[0]).removeClass('hide-toggler');
					$($(this).find('span')[1]).addClass('hide-toggler');
				} else {
					$(className).show();
					$($(this).find('span')[0]).addClass('hide-toggler');
					$($(this).find('span')[1]).removeClass('hide-toggler');
				}
			});

			var dd = new DropDown($('#dd'));

			/*
			 * Disabling loading of GMV data, as it was removed from the template
			 * but let's save it for later, if we have to put it back
			 * this.getGmvData();
			 */
			this.getTradedSince();

			/*
			 * Render the google map
			 * by geo position if we have, else by address. 
			 * Hide container if we do not have any
			 */

			if (parseInt(this.showMap) === 1) {
                if (data.latitude && data.longitude) {
                    GoogleMap.showByLatLong('supplierAddressMap', data.latitude, data.longitude, data.name);
                } else if (data.fullAddress) {
                    GoogleMap.show('supplierAddressMap', data.fullAddress, data.name);
                } else {
                    $('#supplierAddressMap').hide();
                }
            }

		},

		renderGmv: function () {
			if (this.gmvCollection.models[0]) {
				if (this.gmvCollection.models[0].attributes.gmv) {
					//Convert number to currency
					var str = this.gmvCollection.models[0].attributes.gmv;
					if (typeof str !== 'number') {
						str = parseFloat(str);
					}

					str = str.toFixed(0);
					str = str.replace(/\B(?=(\d{3})+(?!\d))/g, ",");

					$('#gmv-value').html('$' + str);
				} else {
					$('#gmv-value').html('$0');
				}
			}
		},

		renderTradedSince: function () {
			if (this.tradingSinceCollection.models[0]) {
				var tradedSince = this.tradingSinceCollection.models[0].attributes['traded-since'];
				if (tradedSince === "0") {
					$('#traded-since').html('No trading activity');
				} else {
					var shortMonthsInYear = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
					var dateParts = tradedSince.split(' ')[0].split('-');
					var formattedDate = dateParts[2] + '-' + shortMonthsInYear[parseInt(dateParts[1]) - 1] + '-' + dateParts[0];
					$('#traded-since').html(formattedDate.toUpperCase());
				}
			}
		}
	});

	function DropDown(el) {
		this.dd = el;
		this.placeholder = this.dd.children('span');
		this.opts = this.dd.find('ul.dropdown > li');
		this.val = '';
		this.index = -1;
		this.initEvents();
	}
	DropDown.prototype = {
		initEvents: function () {
			var obj = this;

			obj.dd.on('click', function (event) {
				$(this).toggleClass('active');
				return false;
			});

			obj.opts.on('click', function () {
				var opt = $(this);
				obj.val = opt.text();
				obj.index = opt.index();
				obj.placeholder.text(obj.val);
			});
		},
		getValue: function () {
			return this.val;
		},
		getIndex: function () {
			return this.index;
		}
	};

	return new view();
});