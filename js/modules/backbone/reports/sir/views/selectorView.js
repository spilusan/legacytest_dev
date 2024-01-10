define([
	'jquery',
	'underscore',
	'Backbone',
	'libs/jquery.uniform',
], function(
	$, 
	_, 
	Backbone,
	Uniform
){
	var selectorView = Backbone.View.extend({
		
		el: $('body'),

		supplierProfile: require('supplier/profile'),
		filterState: {},


		initialize: function () {
			$(function(){

			});
			_.bindAll(this, 'render');
			var thisView = this;

			$('body').one('ajaxStop', function() {
	            thisView.render();            
	        });

	        $.ajax({
	            url: '/data/source/ports',
	            type: 'GET',
	            success: function(response) {
                    var selectListContainer = $('div.selectlist.location'),
                    allByCountry = [],
                    p,
                    lastCountryId,
                    numSelectedByCountry = {};

                    selectListContainer.data('all', response);
                    selectListContainer.data('selected', []);

                    for (p in response) {
                        if (response.hasOwnProperty(p)) {
                            if ('country' == response[p].type) {
                                allByCountry[response[p].id] = {
                                    country: response[p],
                                    ports: []
                                };
                                lastCountryId = response[p].id;
                                numSelectedByCountry[response[p].id] = 0;
                            } else {
                                allByCountry[lastCountryId].ports.push(response[p]);
                            }
                        }
                    }
                    selectListContainer.data('allByCountry', allByCountry);
                    selectListContainer.data('numSelectedInCountry', numSelectedByCountry);
                    thisView.filterLocationSelectList('ul.selectlist.location');
                }
	        });

			$.ajax({
	            url: 'data/source/brands',
	            type: 'GET',
	            success: function(response) {
                    var selectListContainer = $('div.selectlist.brands');
                    selectListContainer.data('all', response);
                    selectListContainer.data('selected', {});
                    thisView.filterSelectList('ul.selectlist.brands');
	            }
	        });

	        $.ajax({
	            url: '/data/source/categories',
	            type: 'GET',
	            success: function(response) {
                    var selectListContainer = $('div.selectlist.categories');
                    selectListContainer.data('all', response);
                    selectListContainer.data('selected', {});
                    thisView.filterSelectList('ul.selectlist.categories');
	            }
	        });
		},

		render: function() {
			$('.itemselector img.spinner').show();
			var thisView = this;
			
			//Location
            $('select#quickSelectLocation').empty().append(
                $('<option value="0" selected>Quick select...</option>'),
                $('<option value="1">Global</option>').data('filter', []),
                $('<option value="2">My country</option>').data('filter', $($('div.location.selectlist').data('allByCountry')[thisView.supplierProfile.countryCode].ports).map(function() { return this.id }))
            );
			
			if (this.supplierProfile.ports.length > 0) {
				$('select#quickSelectLocation').append($('<option value="3">My ports</option>').data('filter', $(thisView.supplierProfile.ports).map(function () { return this.code }).get()));
			}

			//Brands
            $('select#quickSelectBrands').empty().append(
                $('<option value="0" selected>Quick select...</option>'),
                $('<option value="1">All brands</option>').data('filter', [])
            );
			
			if (this.supplierProfile.brands.length > 0) {
				$('select#quickSelectBrands').append($('<option value="2">My brands</option>').data('filter', $.unique($(thisView.supplierProfile.brands.concat(thisView.supplierProfile.ownedBrands)).map(function() { return this.id; })).get()));
			}

			//Categories
            $('select#quickSelectCategories').empty().append(
                $('<option value="0" selected>Quick select...</option>'),
                $('<option value="1">All categories</option>').data('filter', [])
            );
			
			if (this.supplierProfile.categories.length > 0) {
				$('select#quickSelectCategories').append($('<option value="2">My categories</option>').data('filter', $.unique($(thisView.supplierProfile.categories).map(function() { return this.id; })).get()));
			}

			$('select.quickselect').change(function () {
                if ($(this).val() !== "0") {
                    var tabName = $(this).closest('div.tabcontent').attr('data-tabname');
                    thisView.resetTab(tabName, $(this).children(':selected').data('filter'), $(this).children(':selected').text());
                    $(this).val("0");
                }
            });
			
			//THIS FOR FILTERING BY TEXT
	        $('input.text.filter').keyup(function() {
	            var selectList = $('ul#' + $(this).attr('data-selectlist'));
	            if ($(this).is('.location')) {
	                thisView.filterLocationSelectList(selectList);
					selectList.scrollTop(0);
	            } else {
	                thisView.filterSelectList(selectList);
	            }
	        });

	        $('body').delegate('li.item', 'click', function(e){
	        	thisView.moveItem(e);
	        });

	        $('body').delegate('li.country', 'click', function(e){
	        	thisView.moveCountry(e);
	        });
	        $('body').delegate('li.port', 'click', function(e){
	        	thisView.movePort(e);
	        });
	        $('body').delegate('#modal .selectorHolder input.apply', 'click', function(e){
	        	e.preventDefault();
	        	thisView.saveState();
	        });
	        $('body').delegate('#continent', 'change', function () {
            	thisView.filterLocationSelectList('#location-available-selectlist');
        	});

        	$('#modal select').uniform();
		},

		objectFilter: function(obj, callback) {
	        var result = {},
	            p;
	        for (p in obj) {
	            if (obj.hasOwnProperty(p)) {
	                if (callback.call(obj[p], p, obj[p])) {
	                    result[p] = obj[p];
	                }
	            }
	        }
	        return result;
	    },

		countObjectOwnProperties: function(o) {
	        var c = 0, 
	            i;
	        for (i in o) {
	            if (o.hasOwnProperty(i)) {
	                c++;
	            }
	        }
	        return c;
	    },

	    cloneSimpleObject: function(o) {
	        var newObj = {},
	        p;

			if (o instanceof Array) {
				return o.slice(0);
			}

	        for (p in o) {
	            if (o.hasOwnProperty(p)) {
	                newObj[p] = o[p]
	            };
	        }

	        return newObj;
	    },

	    resetTab: function(tabName, filter, selectionName) {
	    	var thisView = this;
	        switch(tabName) {
	            case "location":
	                this.resetLocationForm(filter, selectionName);
	                break;

	            case "brands":
	                this.resetBrandsForm(filter, selectionName);
	                break;

	            case "categories":
	                this.resetCategoriesForm(filter, selectionName);
	                break;
	        }
	    },

	    resetTabWithCurrentFilters: function(tabName) {
	        switch(tabName) {
	            case "location":
	                this.resetLocationForm(this.parent.locations);
	                break;

	            case "brands":
	                this.resetBrandsForm(this.parent.brands);
	                break;

	            case "categories":
	                this.resetCategoriesForm(this.parent.categories);
	                break;
	        }
	    },

	    resetAllTabsWithCurrentFilters: function() {
	        this.resetLocationForm(this.parent.locations);
			this.resetBrandsForm(this.parent.brands);
	        this.resetCategoriesForm(this.parent.categories);
	    },

	    resetLocationForm: function(location) {
	        var selectListContainer = $('div.selectlist.location'),
	            selected = {},
	            numSelectedInCountry = selectListContainer.data('numSelectedInCountry');

	        $(numSelectedInCountry).each(function (key) { numSelectedInCountry[key] = 0; } );

	        $(location).each(function () {
	            var country = this.split('-')[0];

	            selected[this] = this;
	            if (numSelectedInCountry[country] === undefined) {
	                numSelectedInCountry[country] = 1;
	            } else {
	                numSelectedInCountry[country]++;
	            }
	        });
	        
	        selectListContainer.data('selected', selected),
	        selectListContainer.find('input.filter.text').val('');

	        this.filterLocationSelectList('ul.selectlist.location');
	    },

	    resetBrandsForm: function(brands) {
	        var selectList = $('div.brands.selectlist'),
	            selected = {};
	        
	        for (var b in brands) {
				if (brands.hasOwnProperty(b)) {
					selected[brands[b]] = brands[b];
				}
			}

	        selectList.data('selected', selected);
	        selectList.find('input.text.filter').val('');

	        this.filterSelectList('ul.selectlist.brands');
	    },

	    resetCategoriesForm: function(categories) {
	        var selectList = $('div.categories.selectlist'),
	            selected = {};

	        for (var c in categories) {
				if (categories.hasOwnProperty(c)) {
					selected[categories[c]] = categories[c];
				}
	        }
	        selectList.data('selected', selected);
	        selectList.find('input.text.filter').val('');

	        this.filterSelectList('ul.selectlist.categories');
	    },

	    filterSelectList: function(selectLists) {
	        var thisView = this;
	        $(selectLists).each(function() {
	            var selectListContainer = $(this).closest('div.selectlist'),
	            all = selectListContainer.data('all'),
	            selected = selectListContainer.data('selected'),
	            selectLists = selectListContainer.find('ul.selectlist'),
	            selectList = $(this),
	            selectFilter = $('input[data-selectlist="' + selectList.attr('id') + '"]').val(),
	            filtered = thisView.filterSet(all, selectFilter),
	            html = "";

	            selectList.empty();

	            for (var i in filtered) {
	                if (filtered.hasOwnProperty(i)) {
	                    var member = filtered[i];
	                    html += "<li data-id='" + member.ID + "' " + (selected[member.ID] ? 'class="selected item"' : 'class="item"') + ">" + member.NAME + "</li>";
	                }
	            }
	            selectList.html(html);
	            
	            if( selectList.is('.selected') && thisView.countObjectOwnProperties(selectListContainer.data('selected')) == 0) {
	                selectList.prepend('<span class="default">All ' + selectList.attr('data-what') + '</span>');
	            }
	        });
	    },

	    filterSet: function(arr, string) {
	        var resultFirst = [],
	        resultSecond = [],
	        i,
	        tempObj;

	        if (string == '') return arr;
	        //No filter needed
	        for (i in arr) {
				if (arr.hasOwnProperty(i)) {
		            switch (arr[i].NAME.toUpperCase().indexOf(string.toUpperCase())) {
		            case - 1:
		                //Do nothing
		                break;
		            case 0:
		                tempObj = this.cloneSimpleObject(arr[i]);
		                tempObj.NAME = this.highlightSubstr(tempObj.NAME, string, true, 'strong');
		                resultFirst.push(tempObj);
		                break;
		            default:
		                tempObj = this.cloneSimpleObject(arr[i]);
		                tempObj.NAME = this.highlightSubstr(tempObj.NAME, string, true, 'strong');
		                resultSecond.push(tempObj);
		                break;
		            }
				}
	        }
	        return resultFirst.concat(resultSecond);
	    },

		filterLocationSelectList: function(selectLists) {
	        var thisView = this;
	        $(selectLists).each(function() {
	            var selectListContainer = $(this).closest('div.selectlist'),

	            allByCountry = $(this).is('.available') ? thisView.objectFilter(
	            	selectListContainer.data('allByCountry'), function () { 
	            		return this.country.continent === $('#continent').val() 
	            	}) : selectListContainer.data('allByCountry'),
	            selected = selectListContainer.data('selected'),
	            selectList = $(this),
	            selectFilter = $('input[data-selectlist="' + selectList.attr('id') + '"]').val(),
	            filtered = thisView.filterLocationSet(allByCountry, selectFilter),
	            html = [],
	            lastCountryIndex;

	            selectList.empty();

	            var isAvailableList = selectList.is('.available');
	            for (var i in filtered) {
	                if (filtered.hasOwnProperty(i)) {
	                    var member = filtered[i];
	                    if (member.visible) {
	                        html.push(
	                        	"<li id='"
	                        	+ member.type 
	                        	+ "-" 
	                        	+ member.id 
	                        	+ "' data-country='" 
	                        	+ ((member.type == 'country') ? member.id : member.PARENT_ID) 
	                        	+ "' data-id='" 
	                        	+ member.id 
	                        	+ "' class='" 
	                        	+ (selected[member.id] ? 'selected ': '') 
	                        	+ member.type 
	                        	+ (member.type == 'country' ? ' nochildren': '') 
	                        	+ "' >" 
	                        	+ member.name 
	                        	+ "</li>"
	                        );
	                    }
	                    if (member.type == 'country') {
	                        lastCountryIndex = html.length - 1;
	                    } else {
	                        if (!selected[member.id] == isAvailableList /*if this is the 'available' list and the item is not selected OR if this is the 'selected' list and the item is selected */) {
	                            html[lastCountryIndex] = html[lastCountryIndex].replace('nochildren', '');
	                        }
	                    }
	                }
	            }
	            //End for-in
	            selectList.html(html);
	            if(selectList.is('.selected') && thisView.countObjectOwnProperties(selectListContainer.data('selected')) == 0) {
	                selectList.prepend('<span class="default">Globally</span>');
	            }
	        });
	    },

	    filterLocationSet: function(countries, string) {
	        var results = [],
	        countryResults = [],
	        c,
	        p,
	        tempObj;
	        
	        //Case-insensitive match string at start of string
	        function startsWith(haystack, needle) {
	            return haystack.slice(0, needle.length).toUpperCase() == needle.toUpperCase();
	        }

	        for (c in countries) {
	            if (countries.hasOwnProperty(c)) {
	                countryResults = [];
	                for (p in countries[c].ports) {
	                    if (countries[c].ports.hasOwnProperty(p)) {
	                        var tempObj = this.cloneSimpleObject(countries[c].ports[p]);
	                        tempObj.name = this.highlightSubstr(tempObj.name, string, true, 'strong');
	                        tempObj.visible = (startsWith(countries[c].ports[p].name, string));
	                        countryResults.push(tempObj);
	                    }
	                }

	                var tempObj = this.cloneSimpleObject(countries[c].country);
	                delete tempObj.ports;
	                tempObj.name = this.highlightSubstr(tempObj.name, string, true, 'strong');
	                tempObj.visible = (countryResults.length || startsWith(countries[c].country.name, string));
	                countryResults.unshift(tempObj);

	                results = results.concat(countryResults);
	            }
	        }
	        return results;
	    },

	    highlightSubstr: function(haystack, needle, insensitive, hclass) {
	        var regex = new RegExp("(<[^>]*>)|(" + needle.replace(/([-.*+?^${}()|[\]\/\\])/i, "\\$1") + ")", insensitive ? "i": "g");
	        return haystack.replace(regex,
	        function(a, b, c) {
	            return (a.charAt(0) == "<") ? a: "<span class=\"" + hclass + "\">" + c + "</span>";
	        });
	    },

	    moveItem: function(e) {
            var container = $(e.target).closest('div.selectlist'),
            thisList = $(e.target).closest('ul.selectlist'),
            counterpartList = thisList.closest('div.list').siblings().find('ul.selectlist'),
            thisLi = $(e.target).closest('li'),
            counterpartLi = counterpartList.find('li[data-id="' + thisLi.attr('data-id') + '"]');
            thisList.find('span.default').remove();
            counterpartList.find('span.default').remove();
            if (thisList.is('.available')) {
                container.data('selected')[thisLi.attr('data-id')] = thisLi.attr('data-id');
                thisLi.addClass('selected');
                counterpartLi.addClass('selected');
            } else {
                delete container.data('selected')[thisLi.attr('data-id')];
                thisLi.removeClass('selected');
                counterpartLi.removeClass('selected');
                if(this.countObjectOwnProperties(container.data('selected')) == 0) {
                    thisList.prepend('<span class="default">All ' + thisList.attr('data-what') + '</span>');
                }
            }
        },

	    moveCountry: function(e) {
            var container = $(e.target).closest('div.selectlist'),
            thisList = $(e.target).closest('ul.selectlist'),
            counterpartList = thisList.closest('div.list').siblings().find('ul.selectlist'),
            thisLi = $(e.target),
            counterpartLi = counterpartList.find('li[data-id="' + thisLi.attr('data-id') + '"]'),
            portLis = thisLi.nextUntil('.country'),
            counterpartPortLis = counterpartLi.nextUntil('.country'),
            countryId = thisLi.attr('data-country'),
            childPorts = container.data('allByCountry')[countryId].ports,
            numSelectedInCountry = container.data('numSelectedInCountry'),
            p;

            thisLi.addClass('nochildren');
            counterpartLi.removeClass('nochildren');

            portLis.addClass('selected');
            counterpartPortLis.addClass('selected');
            
            thisList.find('span.default').remove();
            counterpartList.find('span.default').remove();

            /*Select all ports (move left to right)*/
            if (thisList.is('.available')) {
                for (p in childPorts) {
					if(childPorts.hasOwnProperty(p)) {
						container.data('selected')[childPorts[p].id] = childPorts[p].id;
					}   
                }

                numSelectedInCountry[countryId] = childPorts.length;

                portLis.each(function(li) {
                    var li = $(this);
                    li.addClass('selected');
                });
                counterpartPortLis.addClass('selected');

            } else {
                /*Deselect all ports (move right to left)*/
                for (p in childPorts) {
					if (childPorts.hasOwnProperty(p)) {
						delete container.data('selected')[childPorts[p].id];
					}
                }

                numSelectedInCountry[countryId] = 0;

                portLis.each(function() {
                    var li = $(this);
                    li.removeClass('selected');
                });
                counterpartPortLis.removeClass('selected');

                if(this.countObjectOwnProperties(container.data('selected')) == 0) {
                    thisList.prepend('<span class="default">Globally</span>');
                }
            }
        },

        movePort: function(e) {
            var container = $(e.target).closest('div.selectlist'),
            thisList = $(e.target).closest('ul.selectlist'),
            counterpartList = thisList.closest('div.list').siblings().find('ul.selectlist'),
            thisLi = $(e.target),
            counterpartLi = counterpartList.find('li[data-id="' + thisLi.attr('data-id') + '"]'),
            countryId = thisLi.attr('data-country'),
            counterpartCountryLi = counterpartList.find('#country-' + countryId),
            numSelectedInCountry = container.data('numSelectedInCountry');

            counterpartCountryLi.removeClass('nochildren');
            thisList.find('span.default').remove();
            counterpartList.find('span.default').remove();

            /*Select (move left to right)*/
            if (thisList.is('.available')) {
                numSelectedInCountry[countryId]++;
                container.data('selected')[thisLi.attr('data-id')] = thisLi.attr('data-id');
                thisLi.addClass('selected');
                counterpartLi.addClass('selected');
            } else {
                /*Deselect (move right to left)*/
                numSelectedInCountry[countryId]--;
                delete container.data('selected')[thisLi.attr('data-id')];
                thisLi.removeClass('selected');
                counterpartLi.removeClass('selected');
                if(this.countObjectOwnProperties(container.data('selected')) == 0) {
                    thisList.prepend('<span class="default">Globally</span>');
                }
            }

            if (numSelectedInCountry[countryId] == 0) {
                thisLi.prevAll('.country:first').addClass('nochildren');
            }
        },

        saveState: function(){
        	this.parent.saving = true;
        	$('#modal .close').click();
        	this.parent.locations = this.saveTab('location');
        	this.parent.brands = this.saveTab('brands');
        	this.parent.categories = this.saveTab('categories');
        	this.parent.applyProducts();
        	this.setTabTitles();
	        $('.filters form .apply').click();
        },

        saveTab: function(tabName) {
	        var data = [],
	            selected = $('div.selectlist.' + tabName).data('selected'),
	            l;
	        for (l in selected) {
	            if (selected.hasOwnProperty(l)) {
	                data.push(selected[l]);
	            }
	        }
	        return data;
	    },

	    setTabTitles: function(){
	    	if(this.parent.locations.length > 0) {
				$('.locTab span').html(this.parent.locations.length + " selected");
			}
			else {
				$('.locTab span').html("Global");
			}
			if(this.parent.brands.length > 0) {
				$('.brandTab span').html(this.parent.brands.length + " selected");
			}
			else {
				$('.brandTab span').html("All brands");
			}
			if(this.parent.categories.length > 0) {
				$('.catsTab span').html(this.parent.categories.length + " selected");
			}
			else {
				$('.catsTab span').html("All categories");
			}
	    }
	});

	return selectorView;
});
