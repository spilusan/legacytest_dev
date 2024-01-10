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

		filterState: {},

		initialize: function () {
			_.bindAll(this, 'render');
			var thisView = this;

			$('body').one('ajaxStop', function() {
	            thisView.render();            
	        });

			$.ajax({
	            url: '/data/source/locations',
	            type: 'GET',
	            success: function(response) {
                    var selectListContainer = $('div.selectlist.locations');

                    selectListContainer.data('all', response);
                    selectListContainer.data('selected', {});
                    thisView.filterSelectList('ul.selectlist.locations');
	            }
	        });
		},

		render: function() {
			$('.itemselector img.spinner').show();
			var thisView = this;
			
			//THIS FOR FILTERING BY TEXT
	        $('input.text.filter').keyup(function() {
	            var selectList = $('ul#' + $(this).attr('data-selectlist'));
	            thisView.filterSelectList(selectList);
	        });

	        $('body').delegate('li.item', 'click', function(e){
	        	thisView.moveItem(e);
	        });

	        $('body').delegate('#modal .selectorHolder input.apply', 'click', function(e){
	        	e.preventDefault();
	        	thisView.saveState();
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

	    resetTab: function() {
			this.resetLocationsForm(this.parent.location);
	    },


	    resetLocationsForm: function(locations) {
	        var selectList = $('div.locations.selectlist'),
	            selected = {};
	        
	        for (var l in locations) {
				if (locations.hasOwnProperty(l)) {
					selected[locations[l]] = locations[l];
				}
			}

	        selectList.data('selected', selected);
	        selectList.find('input.text.filter').val('');

	        this.filterSelectList('ul.selectlist.locations');
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
	                    html += "<li data-id='" + member.id + "' " + (selected[member.id] ? 'class="selected item"' : 'class="item"') + ">" + member.name + "</li>";
	                }
	            }
	            selectList.html(html);
	            
	            if( selectList.is('.selected') && thisView.countObjectOwnProperties(selectListContainer.data('selected')) == 0) {
	                selectList.prepend('<span class="default">Globally</span>');
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
		            switch (arr[i].name.toUpperCase().indexOf(string.toUpperCase())) {
		            case - 1:
		                //Do nothing
		                break;
		            case 0:
		                tempObj = this.cloneSimpleObject(arr[i]);
		                tempObj.name = this.highlightSubstr(tempObj.name, string, true, 'strong');
		                resultFirst.push(tempObj);
		                break;
		            default:
		                tempObj = this.cloneSimpleObject(arr[i]);
		                tempObj.name = this.highlightSubstr(tempObj.name, string, true, 'strong');
		                resultSecond.push(tempObj);
		                break;
		            }
				}
	        }
	        return resultFirst.concat(resultSecond);
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
                    thisList.prepend('<span class="default">Globally</span>');
                }
            }
        },

        saveState: function(){
        	this.parent.saving = true;
        	$('#modal .close').click();
        	this.parent.location = this.saveTab('locations');
        	this.setTabTitles();
        },

        saveTab: function(tabName) {
        	$('input#location').val('');

	        var data = [],
	            selected = $('div.selectlist.' + tabName).data('selected'),
	            l,
	            text = '';

	        for (l in selected) {
	            if (selected.hasOwnProperty(l)) {
	                data.push(selected[l]);
	                text += selected[l] + ", ";
	            }
	        }

	        if(data.length == 0){
	        	text = "Globally";
	        }
	        $('input#location').val(text);
	        return data;
	    },

	    setTabTitles: function(){
			if(this.parent.location.length > 0) {
				$('.locationTab span').html(this.parent.location.length + " selected");
			}
			else {
				$('.locationTab span').html("Globally");
			}
	    }
	});

	return selectorView;
});
