define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'libs/jquery.tools.overlay.modified',
	'libs/jquery.uniform',
	'libs/jquery-ui-1.10.3/datepicker',
	'backbone/shared/itemselector/views/selectorView',
	'../collections/filters',
	'text!templates/reports/priceBench/tpl/filters.html',
	 'libs/jquery.autocomplete',
	 '../collections/impaData',
	 '../views/impaItem'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	Modal,
	Uniform,
	Datpicker,
	ItemSelector,
	filtersCollection,
	filtersTpl,
	autocomplete,
	impaList,
	impaItemView
){
	var filtersView = Backbone.View.extend({
		el: $('form.filters'),
		warmedUp: false,
		impaTempId: 0,
		itemsRendered: false,
		defalutFilterText: '',
		fromTracker: false,
		autoCompletePars : {
			onlyMine: 1,
		},
		events: {
			'click input.show' : 'onShowClicked',
			'click input[name="addLoc"]' : 'showLocSel',
			'focus input.date' : 'focusDate',
			'blur input.date' : 'blurDate',
			'click input[name="keywordselect"]' : 'clickKeywordselect',
			'click input[name="bought"]' : 'onBoughtClick'
		},

		filtersTemplate: Handlebars.compile(filtersTpl),

		initialize: function() {
			var thisView = this;
			this.vesselCollection = new filtersCollection();
			this.vesselCollection.url = '/data/source/vessels';
			this.impaCollection = new impaList();
			
			/*
			this.locationSelector = new ItemSelector({itemType : 'locations'});
			this.locationSelector.bind('apply', function(){
            	thisView.setLocationFilter();
            });
			*/

            // added by Yuriy Akopov - set the default "from" date which is two years back from now
            var now = new Date();
            var defaultFromDate = new Date();
            defaultFromDate.setFullYear(now.getFullYear() - 2); // this is a bit clumsy - keep this in sync with the backend default!

            var formattedDate = [
                defaultFromDate.getDate(),
                defaultFromDate.getMonth() + 1,
                defaultFromDate.getFullYear().toString().substr(2)
            ];

            for (var index = 0; index < formattedDate.length; ++index ) {
                if (formattedDate[index] < 10) {
                    formattedDate[index] = '0' + formattedDate[index];
                }
            }

            //this.defaultFromDate = formattedDate.join('/');
            this.defaultFromDate  = window.defaultFromDate;
            this.defaultToDate  = window.defaultToDate;
           $('body').delegate('a.help', 'click', function(e){
				e.preventDefault();
				thisView.displayHelp(e);
			});

			this.getData();
            $(document).ready(function(){
                if (window.priceTrackerParams) {
                	thisView.preRenderImpaItems(window.priceTrackerParams);
                }
            });
		},

		getData: function() {
			var thisView = this;

			this.vesselCollection.fetch({
				complete: function() {
					thisView.render();
				}
			});
		},

		showLocSel: function(e) {
			e.preventDefault();
		
			var thisView = this;
			if(!this.locationSelector){
				this.locationSelector = new ItemSelector({itemType : 'locations'});
			}
			this.locationSelector.bind('apply', function(){
            	thisView.setLocationFilter();
            });			
            
			this.locationSelector.show();
		},

		setLocationFilter: function() {
			this.location = [];
			var locDisp = "",
				count = 0;

			_.each(this.locationSelector.selectedItems, function(item){
				count++;
				locDisp += item.name;
				if(count < this.locationSelector.selectedItems.length) {
					locDisp += ", ";
				}
				this.location.push(item.id);
			}, this);
			
			if(locDisp === ""){
				locDisp = "Globally";
			}

			$('.locationDisp').text(locDisp);
		},

		render: function() {
			// this.warmupData();
			this.renderAfterWarmup();
		},

		renderAfterWarmup: function() {	
			
			var thisView = this;
						
			var data = {};
			data.vessel = this.vesselCollection;

			$(this.el).html('');
			var html = this.filtersTemplate(data);

			$(this.el).html(html);

			$(this.el).find('select').uniform();
			$(this.el).find('input[type="checkbox"]').uniform();
			$(this.el).find('input[type="radio"]').uniform();

			$('input.date').datepicker({ 
				autoSize: false,
				dateFormat: 'dd/mm/y'
			});

            $('input[name="dateFrom"]').val(this.defaultFromDate);
            $('input[name="dateTo"]').val(this.defaultToDate);

			/* Auto complete keywords search added by Attila O  10/11/2014*/

 			$('input[name="keywordselect"]').autocomplete({
				paramName: 'filterStr', 
 				/*
 				serviceUrl: '/pricebenchmark/service/buyer-impa-list', 
 				serviceUrl: '/pricebenchmark/input/product-autocomplete',
 				*/
 				serviceUrl: '/pricebenchmark/input/autocomplete-ordered-impa-product',
 				params: thisView.autoCompletePars, 
                width:665,
                zIndex: 9999,
                minChars: 3,
                noCache: true,
                deferRequestBy: 500,
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
                transformResult: function(response) {
			        return {
			            suggestions: $.map(JSON.parse(response).products, function(dataItem) {
			                return { value: dataItem.data, data: ''+dataItem.value };
			            })
			        };
			    },

                onSelect: function(response) {

                	thisView.onAutoCompleteSelect({
                       /* id: response.data, */
                        id: thisView.impaTempId,
                        name: response.value,
                        itemid: response.data,
						/* units: response.units, */
                    });
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
						  	alert(response.message);
						  }
						}
					}
                }
			});

       		this.renderImpaItems();
			
			//if (this.autoShow) {
			//	this.autoShow = false;
			//	this.onShowClicked( null );

			//}
			thisView.parent.fixHeight();

		},

		onAutoCompleteSelect: function( obj )
		{
			var thisView = this;
			obj.mine = (this.autoCompletePars.onlyMine) ? 1 : 0;
			obj.units = false;
			thisView.impaTempId ++;
	        thisView.impaCollection.add(obj);
			$('input[name="keywordselect"]').val('');
			thisView.renderImpaItems();
	        thisView.parent.fixHeight();
		},

		focusDate: function(e){
			if($(e.target).val() === "dd/mm/yy"){
				$(e.target).val('');
			}
		},

		blurDate: function(e){
			if($(e.target).val() === "") {
                var date = 'dd/mm/yy';
                if ($(e.target).attr('name') == 'dateFrom') {
                    date = this.defaultFromDate;
                }

                $(e.target).val(date);
            }
        },

		onShowClicked: function(e) {
			this.itemsRendered = false;
			if (e !== null) {
				e.preventDefault();
			}
			
			var keywords = $('input[name="keywordselect"]').val();
			var vessel = $('select[name="vessel"]').val();

            var getDateParam = function (dateStr) {
                if (dateStr === 'dd/mm/yy') {
                    return '';
                }

                var bits = dateStr.split('/');
                if (bits.length !== 3) {
                    throw 'Invalid date';
                }

                for (var index = 0; index < bits.length; ++index) {
                    if (bits[index].length !== 2) {
                        throw 'Invalid date';
                    }

                    if (index == 2) {
                        bits[index] = '20' + bits[index];
                    }
                }

                return bits.reverse().join('/');
            }

            var dateFromSend,
            	dateToSend;

            try {
                dateFromSend = getDateParam($('input[name="dateFrom"]').val());
                dateToSend   = getDateParam($('input[name="dateTo"]').val());

                if ((dateFromSend !== '') && (dateToSend !== '') && (dateFromSend > dateToSend)) {
                    throw 'The start date must be before the end date.';
                }

            } catch (err) {
                alert(err);
                return;
            }

            /*
			if(dateFrom !== ''){
				var splitDate = dateFrom.split("/"),
					year = "20"+splitDate[2],
					dateFromSend = year + '-' + splitDate[1] + '-' + splitDate[0],

					FromDate = new Date(splitDate[2],splitDate[1]-1,splitDate[0]),

					fromDay = splitDate[0],
					fromMonth = splitDate[1];

				splitDate = dateTo.split("/");

				var year = "20"+splitDate[2],
					dateToSend = year + '-' + splitDate[1] + '-' + splitDate[0],

					ToDate = new Date(splitDate[2],splitDate[1]-1,splitDate[0]),

					toDay = splitDate[0],
					toMonth = splitDate[1]-1;

				if(ToDate < FromDate){
					alert('The start date must be before the end date.');
					return;
				}
				else if((dateFrom && dateFrom != "" && dateFrom != null) && (!dateTo || dateTo =="" || dateTo == null)){
					alert('Please enter an end date.');
					return;
				}
				else if((dateTo && dateTo != "" && dateTo != null) && (!dateFrom || dateFrom =="" || dateFrom == null)){
					alert('Please enter a start date.');
					return;
				}
				else if(toMonth > 11 || toDay > 31 || fromMonth > 12 || fromDay > 31){
					alert('Please enter a valid date (dd/mm/yy)');
					return;
				}
			}
			else {
				var dateFromSend = "",
					dateToSend = ""
			}
			*/
            
            if (this.impaCollection.length === 0) {
				alert('Please enter your keywords.');
			} else {
				this.parent.yCollection.reset();
				this.parent.mCollection.reset();

				$('.leftData .dataContainer .data table tbody').html('');
				$('.rightData .dataContainer .data table tbody').html('');

				this.parent.excludeLeft = [];
				this.parent.excludeRight = [];

				this.parent.refineLeftQuery = '';
				this.parent.refineRightQuery = '';

				this.parent.leftPageNo = 1;
				this.parent.rightPageNo = 1;

				this.parent.keywords = keywords;
				this.parent.vessel = vessel;
				this.parent.dateFrom = dateFromSend;
				this.parent.dateTo = dateToSend;
				this.parent.location = this.location;
				if (this.fromTracker === true) {
					this.fromTracker = false;
					/*
					this.parent.refineLeftQuery = this.defalutFilterText;
					this.parent.refineRightQuery = this.defalutFilterText;
					*/
				}
				
				this.parent.getMarketData();
			}			
		},
		
		renderImpaItems: function(){
            $('ul.impaItemDisplay').html('');
            this.parent.keywordList = [];
            _.each(this.impaCollection.models, function(item){
                this.renderImpaItem(item);
                this.parent.keywordList.push(item.id);
            }, this);
        },
        

        renderImpaItem: function(item){
            var impaItem = new impaItemView({
                model: item
            });

            impaItem.parent = this;

            var elem = "ul.impaItemDisplay";
            $(elem).append(impaItem.render().el);
        },

		clickKeywordselect: function( e ) {
				$(e.target).val('');
		},

		onBoughtClick: function(e)
        {
            $('input[name="keywordselect"]').autocomplete().clear();
            if ($(e.currentTarget).val() == 'ordered') {
            	this.autoCompletePars.onlyMine = 1;
            } else {
            	delete this.autoCompletePars.onlyMine;
            }
            $('input[name="keywordselect"]').val('');
            $('input[name="keywordselect"]').focus();
        },
		
		warmupData: function() {

			if (this.warmedUp === false) {
				this.warmedUp = true;
				var thisView = this;
				var thisyear = ''+new Date().getFullYear();

				var newDateFrom = thisyear.substr(0,2)+this.defaultFromDate.substr(6.2)+'/'+this.defaultFromDate.substr(3,2)+'/'+this.defaultFromDate.substr(0,2);
				var newDateTo = thisyear.substr(0,2)+this.defaultToDate.substr(6.2)+'/'+this.defaultToDate.substr(3,2)+'/'+this.defaultToDate.substr(0,2);

				//turn off ajax wait for warmup



				var url = '/pricebenchmark/service/quoted/?impa%5B%5D=190101&pageNo=1&pageSize=50&filter%5BdateFrom%5D='+encodeURIComponent(newDateFrom)+'&filter%5BdateTo%5D='+encodeURIComponent(newDateTo)+'&filter%5Bvessel%5D=&filter%5Blocation%5D=&filter%5BrefineQuery%5D=&sortBy=&sortDir=';	
				
				//warm up the backend
				$.ajax(url, {
					success: function(data) {
					//this is just a warmup call, do not process the result
						thisView.parent.endless = false;
						thisView.renderAfterWarmup();

					},
					error: function() {
						thisView.parent.endless = false;
						$('#waiting').hide();
					}
				});
			} else {
				this.renderAfterWarmup();
			}
		},
		
		displayHelp: function(e){
			var html;
			var tpl = $(e.target).attr('href'),
				thisView = this;
			switch(tpl) {
			    case "xxx":
			        html = thisView.unactionedTemplate(); 
			        break;
			   	default:
			   		html = 'Under development';
			   		break;
			}

			$('#modalInfo .modalBody').html(html);
			this.openDialog('#modalInfo');
		},

		 openDialog: function(dialog) { 
            $(dialog).overlay({
                mask: 'black',
                left: 'center',
                fixed: false,

                onBeforeLoad: function() {
                    var windowWidth = $(window).width();
                    var modalWidth = $(dialog).width();
                    var posLeft = windowWidth/2 - modalWidth/2;

                    $(dialog).css('left', posLeft);
                }
            });

            $(dialog).overlay().load();
        },

        preRenderImpaItems: function( data )
        {
			//	"impa":"150118","unit":"ST","date":"2014-12-04","refine":null
			if (data.date) {
				var dateParts = data.date.split('-');
				this.defaultFromDate = dateParts[2]+'/'+dateParts[1]+'/'+(parseInt(dateParts[0])-2000);
			}

			if (data.refine) {
				this.defalutFilterText = data.refine;
			}

			this.impaTempId ++;
       		 this.impaCollection.add({
                    /* id: data.impa, */
                    id: this.impaTempId,
                    itemid: data.impa,
                    name: data.impa+' - '+data.desc,
					units: [data.unit],
                   });
       		this.autoShow = true;
       		this.fromTracker = true;
        }

	});

	return filtersView;
});
