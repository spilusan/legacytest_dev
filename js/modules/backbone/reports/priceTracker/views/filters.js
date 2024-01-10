define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'libs/jquery.uniform',
	'libs/jquery-ui-1.10.3/datepicker',
	'libs/jquery.autocomplete',
	'../collections/impaData',
	'text!templates/reports/priceTracker/tpl/filters.html',
	'../views/impaItem'

], function(
	$, 
	_, 
	Backbone, 
	Hb,
	Uniform,
	Datpicker,
	autocomplete,
	impaList,
	filtersTpl,
	impaItemView
){
	var filtersView = Backbone.View.extend({
		el: $('form.filters'),

        autoCompletePars: {
            onlyMine: 1,
        },

        impaCollection : null,
		events: {
            'change select[name="dateRange"]' : 'onSelectTimePeriod',
            'change select[name="productCount"]' : 'onSelectProductCount',
            'click input.show' : 'onShowButtonClick',
		},

		filtersTemplate: Handlebars.compile(filtersTpl),

		initialize: function() {
			this.impaCollection = new impaList();
            this.getData();
			/*
            //This is not auto complete any more, leave it here, if have to be redone
            this.fieldAutoCompelte();
            */
            /*
            $('.show').addClass('disabled');
            $('.show').attr('disabled','disabled');
            */
		},

		getData: function() {
			this.render();
		},

		render: function() {	
            var data = {
                    defaultDayRange: window.defaultDayRange,
            };

			var html = this.filtersTemplate(data);
			$(this.el).html(html);
            $('select').uniform();
		},

		renderImpaItems: function(){
            $('ul.impaItemDisplay').html('');
            this.parent.keywordList = [];
            _.each(this.impaCollection.models, function(item){
                this.renderImpaItem(item);
                this.parent.keywordList.push(item.id);
            }, this);
            if (this.impaCollection.models[0]) {
                 $('ul.impaItemDisplay').show();
            } else {
                 $('ul.impaItemDisplay').hide();
            }
                       
            /*
            if (this.impaCollection.models[0]) {
                $('.show').removeClass('disabled');
                $('.show').removeAttr('disabled');
            } else {
                $('.show').addClass('disabled');
                $('.show').attr('disabled','disabled');
            }
            */
            $('.dataBox').html('');
            
        },

		renderImpaItem: function(item){
            var impaItem = new impaItemView({
                model: item
            });

            impaItem.parent = this;

            var elem = "ul.impaItemDisplay";
            $(elem).append(impaItem.render().el);
        },

        fieldAutoCompelte: function() 
        {

        	var thisView = this;

        	 $('input[name="keywordselect"]').autocomplete({
 				paramName: 'filterStr', 
 				serviceUrl: '/pricebenchmark/input/product-autocomplete',
 				params: thisView.autoCompletePars, 
                width:665,
                zIndex: 9999,
                minChars: 3,
                noCache: true,
                onSearchStart: function() {
                	thisView.parent.endless = true;
                },
                onStart: function(){
                    $('.keywordsAutocomplete').css('display', 'inline-block');

                },
                onFinish: function(){
                	thisView.parent.endless = false;
                    $('.keywordsAutocomplete').hide();
                },
                onSelect: function(response) {
   
                    var unitList  = (response.units)  ? response.units : new Array();
                    var unitExists = (response.units)  ? true : false;

                	thisView.impaCollection.add({
                        id: response.data,
                        name: response.value,
                        units: unitList,
                        unitExists: unitExists,
                    });

	                $('input[name="keywordselect"]').val('');
                    thisView.renderImpaItems();
                    thisView.parent.fixHeight();
                },

                onSearchError: function(query, jqXHR, textStatus, errorThrown)
                {
                    if (jqXHR.responseText) {
                        var response=jQuery.parseJSON(jqXHR.responseText);
                        if(typeof response =='object')
                        {
                          //It is JSON
                          if (response.error === true) {
                            thisView.parent.endless = false;
                            $('.keywordsAutocomplete').hide();
                            $('input[name="keywordselect"]').val('');
                            alert(response.message);
                          }
                        }
                    }
                }
			});
        },

        onSelectTimePeriod: function(e)
        {
            /*
               // Put back this line ,if IMPA list selector will be used
               $('input[name="keywordselect"]').autocomplete().clear();
            */

            var dateSub = parseInt($('select[name="dateRange"]').val());
            if (dateSub == 0) {
                delete this.autoCompletePars.dateFrom;
            } else {
                var d = new Date();
                d.setDate(d.getDate()-dateSub);
                this.autoCompletePars.dateFrom = d.toISOString().substr(0,10);
            }

            this.enableDisableButton();
        },
        
        onShowButtonClick: function(e) 
        {
            e.preventDefault();
            if (!$('input.show').hasClass('disabled')) {
                this.parent.getData();
            }
         },

        onSelectProductCount: function(e)
        {
            this.enableDisableButton();
        },

        enableDisableButton: function()
        {
            if ($('select[name="dateRange"]').val() && $('select[name="productCount"]').val()) {
                $('input.show').removeClass('disabled');
            } else {
                $('input.show').addClass('disabled');
            }
        }


	});

	return filtersView;
});
