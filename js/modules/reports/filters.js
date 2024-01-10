/**
 * reports/filters module
 */
define(['jqueryui-datepicker', 'supplier/profile', 'jquery-maskedinput'], function ($, supplierProfile) {
    "use strict";

    var DATE_DISPLAY_FORMAT = 'dd/mm/yy',
        DATE_STORAGE_FORMAT = 'yymmdd',
        allBrands,
        selectedBrands,
        applyCallbacks = [],
        readyCallbacks = [],
        ready = false,

        currentFilterState = {
            tnid: $('input#tnid').val(),
            period: [
				{
					from: "20101001",
					to: "20111001"
				},
				{
					from: "20100901",
					to: "20110901"
				}
			],
            location: [],
            categories: [],
            brands: [],
            products: []
        };
        
    $('div.filters ul.tabs li.tab').each(function () {
        $(this).data('initialised', false);
    });

    /**
     * Some utility functions for date formats
     */
    function changeDateFormat(formatOld, dateString, formatNew) {
        return $.datepicker.formatDate(formatNew, $.datepicker.parseDate(formatOld, dateString));
    }

    function storageDateToDisplayDate(dateString) {
        return changeDateFormat(DATE_STORAGE_FORMAT, dateString, DATE_DISPLAY_FORMAT);
    }

    function storageDateToShortDisplayDate(dateString) {
        var tmp = storageDateToDisplayDate(dateString);
        return tmp.substr(0, tmp.length-4) + tmp.substr(tmp.length - 2, 2);
    }

    function displayDateToStorageDate(dateString) {
        return changeDateFormat(DATE_DISPLAY_FORMAT, dateString, DATE_STORAGE_FORMAT);
    }

    /**
	 * Get the current value of 'period' tab form
	 * E.g. for saving and applying filters
	 */
    function readPeriodForm() {
        var periodOne = {
            from: displayDateToStorageDate($('#fromDate').val()),
            to: displayDateToStorageDate($('#toDate').val())
        };

        return $('#periodToPeriod').attr('checked') ? [periodOne, {
            from: displayDateToStorageDate($('#fromDate2').val()),
            to: displayDateToStorageDate($('#toDate2').val())
        }] : [periodOne];
    }

    function onReady(callback) {
        if (ready) {
            callback.call(currentFilterState, currentFilterState);
        } else {
            readyCallbacks.push(callback);
        }
    }

    /**
	 * 
	 */
    function resetPeriodForm(periods) {
        $('input#fromDate').val(storageDateToDisplayDate(periods[0].from));
        $('input#toDate').val(storageDateToDisplayDate(periods[0].to));

        if (periods[1] !== undefined) {
            $('input#fromDate2').val(storageDateToDisplayDate(periods[1].from));
            $('input#toDate2').val(storageDateToDisplayDate(periods[1].to));
            $('#periodToPeriod').attr('checked', 'checked');
        } else {
            $('#periodToPeriod').removeAttr('checked');
        }

        $('.period input.date').trigger('datechange');
        $('#periodToPeriod').trigger('change');
    }

    function readLocationsForm() {
        var locations = [],
            selected = $('div.selectlist.location').data('selected'),
            l;

        for (l in selected) {
            if (selected.hasOwnProperty(l)) {
                locations.push(selected[l]);
            }
        }
        return locations;
    }
    
    /**
     * Count the number of own (no inherited) properties an object has
     */
    function countObjectOwnProperties(o) {
        var c = 0, 
            i;
        for (i in o) {
            if (o.hasOwnProperty(i)) {
                c++;
            }
        }
        return c;
    }

    function resetLocationForm(location) {
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
        
        if (selectListContainer.is(':visible')) {
            filterLocationSelectList('ul.selectlist.location');
        } else {
            $('li.tab.location').one('open', function () {
                setTimeout(function () {
                    filterLocationSelectList('ul.selectlist.location');
                    $('.tabcontent.location div.waiting').remove();
                },100);
            });
        }
    }

    function readBrandsForm() {
        var selected = $('div.selectlist.brands').data('selected'),
            s,
            result = [];
        for (s in selected) {
			if (selected.hasOwnProperty(s)) {
				result.push(selected[s]);
			}
        }
        return result;
    }
    
    function resetBrandsForm(brands) {
        var selectList = $('div.brands.selectlist'),
            selected = {};
        
        for (var b in brands) {
			if (brands.hasOwnProperty(b)) {
				selected[brands[b]] = brands[b];
			}
        }
        selectList.data('selected', selected);
        
        if (selectList.is(':visible')) {
            selectList.find('input.text.filter').val('').trigger('keyup');
        } else {
            $('li.tab.brands').one('open', function () {
                setTimeout(function () {
                    selectList.find('input.text.filter').val('').trigger('keyup');
                    selectList.closest('.tabcontent').find('div.waiting').remove();
                },100);
            });
        }
    }

    function readCategoriesForm() {
        var selected = $('div.selectlist.categories').data('selected'),
            s,
            result = [];
        for (s in selected) {
			if (selected.hasOwnProperty(s)) {
				result.push(selected[s]);
			}
        }
        return result;
    }
    
    function resetCategoriesForm(categories) {
        var selectList = $('div.categories.selectlist'),
            selected = {};

        for (var c in categories) {
			if (categories.hasOwnProperty(c)) {
				selected[categories[c]] = categories[c];
			}
        }
        selectList.data('selected', selected);

        if (selectList.is(':visible')) {
            selectList.find('input.text.filter').val('').trigger('keyup');
        } else {
            $('li.tab.categories').one('open', function () {
                setTimeout(function () {
                    selectList.find('input.text.filter').val('').trigger('keyup');
                    selectList.closest('.tabcontent').find('div.waiting').remove();
                },100);
            });
        }
    }

    function readProductsForm() {
        var results = [];
        $('ul.select.products input.product:checked').each(function(){
            results.push($(this).attr('data-product-id'));
        });
        return results;
    }
    
    function resetProductsForm(products) {
        var checkboxes = $('ul.select.products input.product').removeAttr('checked'),
            p;
        for (p in products) {
			if (products.hasOwnProperty(p)) {
				checkboxes.filter('[data-product-id='+products[p]+']').attr('checked', 'checked');
			}
            
        }
    }
    
    function updateTabSelectionMessage(tabName) {
		var found = false;
        switch(tabName) {
            case "period":
                $('li.tab.period span.selection.one').text(storageDateToDisplayDate(currentFilterState.period[0].from) + ' - ' + storageDateToDisplayDate(currentFilterState.period[0].to));
                if (currentFilterState.period.length > 1) {
                    $('li.tab.period span.selection.two').text(storageDateToDisplayDate(currentFilterState.period[1].from) + ' - ' + storageDateToDisplayDate(currentFilterState.period[1].to)).show();
                }else {
                    $('li.tab.period span.selection.two').text('').hide();
                }
                break;

            case "location":
				$('#quickSelectLocation option').each(function () {
					if($(this).data('filter') && compareFilters($(this).data('filter'), currentFilterState.location)) {
						$('li.tab.location span.selection').text($(this).text());
						found = true;
					}
				});
				if (!found) {
					$('li.tab.location span.selection').text(currentFilterState.location.length + " selected");
				}
                break;

            case "brands":
				$('#quickSelectBrands option').each(function () {
					if($(this).data('filter') && compareFilters($(this).data('filter'), currentFilterState.brands)) {
						$('li.tab.brands span.selection').text($(this).text());
						found = true;
					}
				});
				if (!found) {
					$('li.tab.brands span.selection').text(currentFilterState.brands.length + " selected");
				}
                break;

            case "categories":
				$('#quickSelectCategories option').each(function () {
					if($(this).data('filter') && compareFilters($(this).data('filter'), currentFilterState.categories)) {
						$('li.tab.categories span.selection').text($(this).text());
						found = true;
					}
				});
				if (!found) {
					$('li.tab.categories span.selection').text(currentFilterState.categories.length + " selected");
				}
                break;

            case "products":
				if (currentFilterState.products.length == 0) {
					$('li.tab.products span.selection').text("All services");
				} else {
					$('li.tab.products span.selection').text(currentFilterState.products.length + " selected");
				}
                break;
        }
    }

    function saveTabFilterState(tabName) {
        switch(tabName) {
            case "period":
                currentFilterState.period = readPeriodForm();
                break;

            case "location":
                currentFilterState.location = readLocationsForm();
                break;

            case "brands":
                currentFilterState.brands = readBrandsForm();
                break;

            case "categories":
                currentFilterState.categories = readCategoriesForm();
                break;

            case "products":
                currentFilterState.products = readProductsForm();
                break;
        }
        updateTabSelectionMessage(tabName);
    }

    function resetTab(tabName, filter, selectionName) {
        switch(tabName) {
            case "period":
                resetPeriodForm(filter, selectionName);
                break;

            case "location":
                resetLocationForm(filter, selectionName);
                break;

            case "brands":
                resetBrandsForm(filter, selectionName);
                break;

            case "categories":
                resetCategoriesForm(filter, selectionName);
                break;

            case "products":
                resetProductsForm(filter, selectionName);
                break;
        }
    }


    function resetTabWithCurrentFilters(tabName) {
        switch(tabName) {
            case "period":
                resetPeriodForm(currentFilterState.period);
                break;

            case "location":
                resetLocationForm(currentFilterState.location);
                break;

            case "brands":
                resetBrandsForm(currentFilterState.brands);
                break;

            case "categories":
                resetCategoriesForm(currentFilterState.categories);
                break;

            case "products":
                resetProductsForm(currentFilterState.products);
                break;
        }
    }
	
	function readForm(tabName) {
        switch(tabName) {
            case "period":
                return readPeriodForm();
                break;

            case "location":
			case "locations:":
                return readLocationsForm();
                break;

            case "brands":
                return readBrandsForm();
                break;

            case "categories":
                return readCategoriesForm();
                break;

            case "products":
                return readProductsForm();
                break;
        }
	}
	
	function hasTabChanged(tabName) {
		return !compareFilters(readForm(tabName), currentFilterState[tabName]);
	}

    /**
     * Clone only basic, directly owned properties for object
     */
    function cloneSimpleObject(o) {
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
    }
    
    /**
     * Compare two sets of filters for simalarity.
     * Returns true if both filter sets contain identical values
     */
    function compareFilters(f1, f2, last) {
        var p;

        //Quickly test objects are same type
        if (f1.constructor != f2.constructor) {
            return false;
        }

        for(p in f1) {
			if (f1.hasOwnProperty(p)) {
	            if (!f2.hasOwnProperty(p)) {
	                return false;
	            }
            
	            if (typeof f1[p] == 'object') {
	                if (!compareFilters(f1[p], f2[p])) {
	                    return false;
	                }
	            } else {
	                if (f2[p] != f1[p]) {
	                    return false;
	                }
	            }
			}
        }

        return last ? true : compareFilters(f2, f1, true); //Perform property test in reverse
    }

    /**
     * Close a given filter tab
     * @param string tabName
     * @param boolean reset default true 
     */
    function closeTab(tabName, reset, ask) {
        reset = reset === undefined ? true : reset;
		ask = ask === undefined ? false : ask;
		
		//If the tab has changed and ask is true, present confirmation dialogue
		if (ask && hasTabChanged(tabName) 
				&& !confirm("You've made changes to the filters. If you click ok you will lose these changes. Click cancel to leave the tab open in order to apply them."))
		{
			return false;
		};
		
        var tab = $('li.tab[data-tabname="' + tabName + '"]'),
        tabBody = $('div.tabcontent[data-tabname="' + tabName + '"]');
        if(tab.is('.open')) {
            /*reset tab to stored value*/
            if(reset) {
                resetTabWithCurrentFilters(tabName);
            }
            tab.removeClass('open');
            $('div.filters div.tabbody').removeClass('open');
            tabBody.hide();
        }
		return true;
    }

    /**
     * Open a given filter tab 
     * @param string tabName
     */
    function openTab(tabName) {
        var tab = $('li.tab[data-tabname="' + tabName + '"]'),
        	tabBody = $('div.tabcontent[data-tabname="' + tabName + '"]'),
			canOpen = true;

        //Close open tab if necessary
        $('div.filters li.tab.open').each(function() {
			//Attempt to close open tab - can return false and prevent this operation
            if (!closeTab($(this).attr('data-tabname'), true, true)) {
				canOpen = false;
			}
        });
		if (!canOpen) return false;
		
        tab.addClass('open');
        $('div.filters div.tabbody').addClass('open');
        tabBody.show();
		return true;
    }

    function highlightSubstr(haystack, needle, insensitive, hclass) {
        var regex = new RegExp("(<[^>]*>)|(" + needle.replace(/([-.*+?^${}()|[\]\/\\])/i, "\\$1") + ")", insensitive ? "i": "g");
        return haystack.replace(regex,
        function(a, b, c) {
            return (a.charAt(0) == "<") ? a: "<span class=\"" + hclass + "\">" + c + "</span>";
        });
    }

    function filterSet(arr, string) {
        var resultFirst = [],
        resultSecond = [],
        i,
        tempObj;

        if (string == '') return arr;
        //No filter needed
        for (i in arr) {
			if (arr.hasOwnProperty(i)) {
	            switch (arr[i].name.toUpperCase().indexOf(string.toUpperCase())) {
	            case - 1:
	                //Do nothing
	                break;
	            case 0:
	                tempObj = cloneSimpleObject(arr[i]);
	                tempObj.name = highlightSubstr(tempObj.name, string, true, 'strong');
	                resultFirst.push(tempObj);
	                break;
	            default:
	                tempObj = cloneSimpleObject(arr[i]);
	                tempObj.name = highlightSubstr(tempObj.name, string, true, 'strong');
	                resultSecond.push(tempObj);
	                break;
	            }
			}
        }
        return resultFirst.concat(resultSecond);
    }

    function filterLocationSet(countries, string) {

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
                        var tempObj = cloneSimpleObject(countries[c].ports[p]);
                        tempObj.name = highlightSubstr(tempObj.name, string, true, 'strong');
                        tempObj.visible = (startsWith(countries[c].ports[p].name, string));
                        countryResults.push(tempObj);
                    }
                }

                var tempObj = cloneSimpleObject(countries[c].country);
                delete tempObj.ports;
                tempObj.name = highlightSubstr(tempObj.name, string, true, 'strong');
                tempObj.visible = (countryResults.length || startsWith(countries[c].country.name, string));
                countryResults.unshift(tempObj);

                results = results.concat(countryResults);
            }
        }
        return results;
    }

    function filterSelectList(selectLists) {
        selectLists.each(function() {
            var selectListContainer = $(this).closest('div.selectlist'),
            all = selectListContainer.data('all'),
            selected = selectListContainer.data('selected'),
            selectLists = selectListContainer.find('ul.selectlist'),
            selectList = $(this),
            selectFilter = $('input[data-selectlist="' + selectList.attr('id') + '"]').val(),
            filtered = filterSet(all, selectFilter),
            html = "";

            selectList.empty();

            for (var i in filtered) {
                if (filtered.hasOwnProperty(i)) {
                    var member = filtered[i];
                    html += "<li data-id='" + member.id + "' " + (selected[member.id] ? 'class="selected"': '') + "><div class='selectbutton'></div>" + member.name + "</li>";
                }
            }
            selectList.get()[0].innerHTML = (html);
            
            if( selectList.is('.selected') && countObjectOwnProperties(selectListContainer.data('selected')) == 0) {
                selectList.prepend('<span class="default">All ' + selectList.attr('data-what') + '</span>');
            }
        });
    }
    
    /**
    * Basic object property iterative filter
    */
    function objectFilter(obj, callback) {
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
    }

    function filterLocationSelectList(selectLists) {
        $(selectLists).each(function() {
            var selectListContainer = $(this).closest('div.selectlist'),

            allByCountry = $(this).is('.available') ? objectFilter(selectListContainer.data('allByCountry'), function () { return this.country.continent === $('#continent').val() }) : selectListContainer.data('allByCountry'),
            selected = selectListContainer.data('selected'),
            selectLists = selectListContainer.find('ul.selectlist'),
            selectList = $(this),
            selectFilter = $('input[data-selectlist="' + selectList.attr('id') + '"]').val(),
            filtered = filterLocationSet(allByCountry, selectFilter),
            html = [],
            lastCountryIndex;

            selectList.empty();

            var isAvailableList = selectList.is('.available');
            for (var i in filtered) {
                if (filtered.hasOwnProperty(i)) {
                    var member = filtered[i];
                    if (member.visible) {
                        html.push("<li id='" + member.type + "-" + member.id + "' data-country='" + ((member.type == 'country') ? member.id: member.country) + "' data-id='" + member.id + "' class='" + (selected[member.id] ? 'selected ': '') + member.type + (member.type == 'country' ? ' nochildren': '') + "' ><div class='selectbutton'></div>" + member.name + "</li>");
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
            selectList.get()[0].innerHTML = (html.join(''));
            if(selectList.is('.selected') && countObjectOwnProperties(selectListContainer.data('selected')) == 0) {
                selectList.prepend('<span class="default">Globally</span>');
            }
        });
    }
    
    function validateTab(tabName) {
    	if( tabName == 'period'){
    		var period = readPeriodForm();
    		if( period[0].from == "" || period[0].to == "" ||( period[1] && (period[1].from == "" || period[1].to == "" )) ) {
    			alert("Please check your dates");
    	        
    			return false;
    		}
            if(period[0].from > period[0].to || ( period[1] && ( period[1].from > period[1].to ) ) ) {
                alert("The start date needs to be before the end date.");
                
                return false;
            }
    	}
    	return true;
    }

    /* On jQuery ready, initialise module */
    function init() {
        //Store current TNID
        currentFilterState.tnid = $('input#tnid').val();
        
        $('div.filters div.comparison>span.label>span').live('click', function () {
            $(this).replaceWith($(this).html()); //Remove one-time wrapper to remove this functionality after use
            $('div.filters div.comparison img.up').remove();
            $('div.filters div.comparison').animate({"top" : 0}, 'fast');
        });
        
        //Set up jQueryUI Datepickers
        $('input.date').datepicker({
            dateFormat: DATE_DISPLAY_FORMAT
        });
                
        $('input.date').bind('datechange', function(){
            //$('.datepicker[data-input="' + $(this).attr('id') + '"]').datepicker('setDate', displayDateToStorageDate($(this).val()));
        });
        
        $('input.date').inputmask("dd/mm/yyyy", {
            oncomplete: function() {
                this.trigger('datechange');
            }
        });
        
        $('#continent').live('change', function () {
            filterLocationSelectList('#location-available-selectlist');
        });

        $('div.button.apply').click(function() {
			
            var openTabName = $('div.filters li.tab.open').attr('data-tabname'),
                c;
            
            if(validateTab(openTabName)) {
            	saveTabFilterState(openTabName);

                closeTab(openTabName);

                for (c in applyCallbacks) {
                    if (applyCallbacks.hasOwnProperty(c)) {
                        applyCallbacks[c].call(currentFilterState, currentFilterState);
                    }
                }
            }
        });

        $('#periodToPeriod').change(function() {
            if ($(this).attr('checked')) {
                $('div.period.two').removeClass('disabled');
            }else {
                $('div.period.two').addClass('disabled');
            }
        });


        $('div.filters li.tab').click(function() {
            var tab = $(this),
            tabName = $(this).attr('data-tabname');
			
            if (tab.is('.open')) {
				if (!closeTab(tabName, true, true)) return false;
            } else {
                if (openTab(tabName)) {
                	$(this).trigger('open');
                };
            }
        });

        $('input.text.filter').keyup(function() {
            var selectList = $('ul#' + $(this).attr('data-selectlist'));
            if ($(this).is('.location')) {
                filterLocationSelectList(selectList);
				selectList.scrollTop($('#location-available-selectlist').find('.port').first().position().top);
            } else {
                filterSelectList(selectList);
            }
        });

        //Lazily populate brands selector
        $.ajax({
            url: '/reports/api/brand/format/json',
            type: 'GET',
            success: function(response) {
                if (response.status == 200) {
                    var selectListContainer = $('div.selectlist.brands');
                    selectListContainer.data('all', response.data);
                    selectListContainer.data('selected', {});
                }
            }
        });

        //Lazily populate category selector
        $.ajax({
            url: '/reports/api/category/format/json',
            type: 'GET',
            success: function(response) {
                if (response.status == 200) {
                    var selectListContainer = $('div.selectlist.categories');
                    selectListContainer.data('all', response.data);
                    selectListContainer.data('selected', {});
                }
            }
        });

        $.ajax({
            url: '/reports/api/port/format/json',
            type: 'GET',
            success: function(response) {
                if (response.status == 200) {
                    var selectListContainer = $('div.selectlist.location'),
                    allByCountry = [],
                    p,
                    lastCountryId,
                    numSelectedByCountry = {};

                    selectListContainer.data('all', response.data);
                    selectListContainer.data('selected', []);

                    for (p in response.data) {
                        if (response.data.hasOwnProperty(p)) {
                            if ('country' == response.data[p].type) {
                                allByCountry[response.data[p].id] = {
                                    country: response.data[p],
                                    ports: []
                                };
                                lastCountryId = response.data[p].id;
                                numSelectedByCountry[response.data[p].id] = 0;
                            } else {
                                allByCountry[lastCountryId].ports.push(response.data[p]);
                            }
                        }
                    }
                    selectListContainer.data('allByCountry', allByCountry);
                    selectListContainer.data('numSelectedInCountry', numSelectedByCountry);
                }
            }
        });
        
        /**
         * Called after all reference data is loaded into filter tabs
         */
        function readyUp() {

            var today = new Date(),
                lastWeek = new Date(),
                lastMonth = new Date(),
                lastQuater = new Date(),
                lastYear = new Date(),
                c;//for counter

            ready = true; //Module-wide readiness flag

            /*Quick selections*/
            $('select.quickselect').change(function () {
                if ($(this).val() !== "0") {
                    var tabName = $(this).closest('div.tabcontent').attr('data-tabname');
                    resetTab(tabName, $(this).children(':selected').data('filter'), $(this).children(':selected').text());
                    $(this).val("0");
                }
            });

            //Period
            lastWeek.setDate(lastWeek.getDate() - 7);
            lastMonth.setMonth(lastMonth.getMonth() - 1);
            lastQuater.setMonth(lastQuater.getMonth() - 3);
            lastYear.setFullYear(lastYear.getFullYear() - 1);
            $('select#quickSelectPeriodOne').empty().append(
                $('<option value="0" selected>Quick select...</option>'),
                $('<option value="1">Last week</option>').data('filter', [{ from: $.datepicker.formatDate(DATE_STORAGE_FORMAT, lastWeek), to: $.datepicker.formatDate(DATE_STORAGE_FORMAT, today) }]),
                $('<option value="2">Last month</option>').data('filter', [{ from: $.datepicker.formatDate(DATE_STORAGE_FORMAT, lastMonth), to: $.datepicker.formatDate(DATE_STORAGE_FORMAT, today) }]),
                $('<option value="3">Last quarter</option>').data('filter', [{ from: $.datepicker.formatDate(DATE_STORAGE_FORMAT, lastQuater), to: $.datepicker.formatDate(DATE_STORAGE_FORMAT, today) }]),
                $('<option value="4">Last year</option>').data('filter', [{ from: $.datepicker.formatDate(DATE_STORAGE_FORMAT, lastYear), to: $.datepicker.formatDate(DATE_STORAGE_FORMAT, today) }])
            );
            //Default
            currentFilterState.period = $('select#quickSelectPeriodOne option[value="4"]').data('filter');
            resetPeriodForm(currentFilterState.period);
            updateTabSelectionMessage('period');

            //Location
            $('select#quickSelectLocation').empty().append(
                $('<option value="0" selected>Quick select...</option>'),
                $('<option value="1">Global</option>').data('filter', []),
                $('<option value="2">My country</option>').data('filter', $($('div.location.selectlist').data('allByCountry')[supplierProfile.countryCode].ports).map(function() { return this.id }))
            );
			
			if (supplierProfile.ports.length > 0) {
				$('select#quickSelectLocation').append($('<option value="3">My ports</option>').data('filter', $(supplierProfile.ports).map(function () { return this.code }).get()));
			}
				
            //currentFilterState.location = $('select#quickSelectLocation option[value="3"]').data('filter');
			currentFilterState.location = [];
            resetLocationForm(currentFilterState.location);
            updateTabSelectionMessage('location');

            //Brands
            $('select#quickSelectBrands').empty().append(
                $('<option value="0" selected>Quick select...</option>'),
                $('<option value="1">All brands</option>').data('filter', [])
            );
			
			if (supplierProfile.brands.length > 0) {
				$('select#quickSelectBrands').append($('<option value="2">My brands</option>').data('filter', $.unique($(supplierProfile.brands.concat(supplierProfile.ownedBrands)).map(function() { return this.id; })).get()));
			}
			
            //currentFilterState.brands = $('select#quickSelectBrands option[value="2"]').data('filter');
			currentFilterState.brands = [];
            resetBrandsForm(currentFilterState.brands);
            updateTabSelectionMessage('brands');

            //Categories
            $('select#quickSelectCategories').empty().append(
                $('<option value="0" selected>Quick select...</option>'),
                $('<option value="1">All categories</option>').data('filter', [])
            );
			
			if (supplierProfile.categories.length > 0) {
				$('select#quickSelectCategories').append($('<option value="2">My categories</option>').data('filter', $.unique($(supplierProfile.categories).map(function() { return this.id; })).get()));
			}
			
            //currentFilterState.categories = $('select#quickSelectCategories option[value="2"]').data('filter');
			currentFilterState.categories = [];
            resetCategoriesForm(currentFilterState.categories);
            updateTabSelectionMessage('categories');

            
            //Products
            currentFilterState.products = [];
            // Change the default view for Basic Lister to show Premium Lister stats for the market value
            if( supplierProfile.premiumListing == "0" )
            {
            	currentFilterState.products = ['premium'];	
            }
            
            resetProductsForm(currentFilterState.products);            
            updateTabSelectionMessage('products');

            /*Fire ready callbacks*/
            for (c in readyCallbacks) {
                if (readyCallbacks.hasOwnProperty(c)) {
                    readyCallbacks[c].call(currentFilterState, currentFilterState);
                }
            }
        }


        $('body').one('ajaxStop',
        function() {
            readyUp();            
        });

        $('li:not(.country,.port) div.selectbutton').live('click',
        function() {
            var container = $(this).closest('div.selectlist'),
            thisList = $(this).closest('ul.selectlist'),
            counterpartList = thisList.closest('div.list').siblings().find('ul.selectlist'),
            thisLi = $(this).closest('li'),
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
                if(countObjectOwnProperties(container.data('selected')) == 0) {
                    thisList.prepend('<span class="default">All ' + thisList.attr('data-what') + '</span>');
                }
            }
            
        });

        $('li.country div.selectbutton').live('click',
        function() {
            var container = $(this).closest('div.selectlist'),
            thisList = $(this).closest('ul.selectlist'),
            counterpartList = thisList.closest('div.list').siblings().find('ul.selectlist'),
            thisLi = $(this).closest('li'),
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

                if(countObjectOwnProperties(container.data('selected')) == 0) {
                    thisList.prepend('<span class="default">Globally</span>');
                }
            }
        });

        $('li.port div.selectbutton').live('click',
        function() {
            var container = $(this).closest('div.selectlist'),
            thisList = $(this).closest('ul.selectlist'),
            counterpartList = thisList.closest('div.list').siblings().find('ul.selectlist'),
            thisLi = $(this).closest('li'),
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
                if(countObjectOwnProperties(container.data('selected')) == 0) {
                    thisList.prepend('<span class="default">Globally</span>');
                }
            }

            if (numSelectedInCountry[countryId] == 0) {
                thisLi.prevAll('.country:first').addClass('nochildren');
            }
        });

    }
    //End init
    $(init);

    /*Start of public module definition*/
    return {
        /**
		 * Return object containing filters specified by form fields
		 */
        getCurrentFilters: function() {
          
            return currentFilterState;
        },

        onApply: function(callback) {
            applyCallbacks.push(callback);
        },
        
        storageDateToShortDisplayDate: storageDateToShortDisplayDate,
        
        onReady: onReady,

        init: init
    };

});
