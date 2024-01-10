define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/shared/hbh/general',
	'libs/jquery.tools.overlay.modified',
	'libs/jquery.uniform',
	'jqueryui/datepicker',
	'libs/jquery.dateFormat',
	'../views/itemView',
	'../views/selectorView',
	'../collections/marketSizingData',
	'../collections/marketSizingVesselData',
	'text!templates/reports/marketSizing/tpl/totalsView.html'
], function(
	$,
	_, 
	Backbone,
	Hb,
	generalHbh,
	Modal,
	Uniform,
	dateFormat,
	datePicker,
	itemView,
	selectorView,
	marketSizingData,
	marketSizingVesselData,
	totalsViewTpl
){
	var marketSizingView = Backbone.View.extend({
		el: $('body'),

		totalsTemplate: Handlebars.compile(totalsViewTpl),

		incKeywords: null,	
		excKeywords: null,	
		dateFrom: null,
		dateTo: null,
		location: [],
		vesselGMV: null,
		vesselOrderValue: null,
		vesselType: null,
		vesselImo: null,
		keywordEdit: null,
		editAction: null,
		ordering: false,
		level: 0,
		childCount: 0,

		events: {
			// 'click input#show' : 'getData'
		},

		initialize: function() {
			var thisView = this;

			this.selectorView = new selectorView();
			this.selectorView.parent = this;

			this.collection = new marketSizingData();

			this.vesselCollection = new marketSizingVesselData();

			$('body').ajaxStart(function(){
				$('#waiting').show();
			});

			$('body').ajaxStop(function(){
				$('#waiting').hide();
			});

			$('input.date').datepicker({ 
				autoSize: false,
				dateFormat: 'dd/mm/yy'
			});

			this.dateTo = new Date();
			this.dateFrom = new Date();
			this.dateFrom.setFullYear(this.dateFrom.getFullYear() - 1);

			dateFromDisplay = $.format.date(this.dateFrom, "dd/MM/yyyy");
			dateToDisplay = $.format.date(this.dateTo, "dd/MM/yyyy");

			$('#fromDate').val(dateFromDisplay);
			$('#toDate').val(dateToDisplay);

			$(this.el).delegate('.editLocations', 'click', function(e){
		    	e.preventDefault();

	    		thisView.openDialog();
		    });

		    this.vesselCollection.fetch({
		    	data:{},
		    	complete: function(){
		    		thisView.renderVessels(thisView.vesselCollection.models[0].attributes);
		    		$('select#vesselType').uniform();
		    	}
		    });
		},

		renderVessels: function(data){
			_.each(data, function(item){
				var indent = "";
				var count = 1;
				var level = item.level;

				if(level > 0){
					for(i = 1; i <= level; i++){
						indent+="--";
					}
				}
				$('select#vesselType').append('<option value="'+item.id+'">'+indent+item.name+'</option>');

				if(item.children && item.children != null && item.children != ""){
					this.renderVessels(item.children);
				}
			}, this);
			$('select#vesselType option').first().attr('selected', 'selected');
		},

		getData: function(e){
			if(e){
				e.preventDefault();
			}

			var thisView = this;

			this.incKeywords = $('#incKeywords').val();

			if(!this.ordering){
				this.keywordEdit = null;
				this.editAction = null;
			}
			this.excKeywords = $('#excKeywords').val();

			//this.locations = $('#location').val();
			this.dateFrom = $('#fromDate').val();
			this.dateTo = $('#toDate').val();
			this.vesselGMV = $('#vesselGMV').val();
			this.vesselOrderValue = $('#vesselOrdVal').val();
			this.vesselType = $('#vesselType').val();
			this.vesselImo = $('#vesselImo').val();

			var df = this.dateFrom.split('/');
			var dt = this.dateTo.split('/');

			this.dateFrom = df[2] + '-' + df[1] + '-' + df[0];
			this.dateTo = dt[2] + '-' + dt[1] + '-' + dt[0];

			this.collection.fetch({
				data: $.param({
					keywordsInclude: this.incKeywords,
					keywordsExclude: this.excKeywords,
					filter: {
						locations: this.location,
						dateFrom: this.dateFrom,
						dateTo: this.dateTo,
						vesselType: this.vesselType,
						vesselImo: this.vesselImo,
						vesselGmv: this.vesselGMV,
						orderValue: this.vesselOrderValue
					},
					keywordEdit: this.keywordEdit,
					editAction: this.editAction
				}),
				complete: function(){
					thisView.render();
				}
			});
		},

		getIndex: function(keyword){
			var keywords = [];

			_.each(this.collection.models, function(item){
				keywords.push(item.attributes.keyword);
			}, this);

			return keywords.indexOf(keyword);
		},

		render: function(){
			this.ordering = false;

			var html = "";

			_.each(this.collection.models, function(item){
				html += item.attributes.keyword;
				if(this.collection.indexOf(item) + 1 < this.collection.length) {
			        html += ", ";
			    }
			}, this);

			$('#incKeywords').val(html);

			$('div.data table tbody').html('');
			$('div.data table tfoot').html('');

			var thisView = this;
			var count = 0;
			var length = this.collection.length;

			// totals are going to be calculated from every row loaded separately
			var totals = {};
			totals.pages = [];
			totals.pages.searches = 0;
			totals.pages.events = 0;

			totals.tradenet = [];
			totals.tradenet.rfqNo = 0;
			totals.tradenet.rfqBranchNo = 0;
			// totals.tradenet.rfqOrgNo = 0;
			// totals.tradenet.rfqVesNo = 0;
			totals.tradenet.lineItemsNoRfq = 0;

			totals.tradenet.posNo = 0;
			totals.tradenet.posBranchNo = 0;
			totals.tradenet.posSpbBranchNo = 0;
			// totals.tradenet.posOrgNo = 0;
			// totals.tradenet.posVesNo = 0;
			totals.tradenet.lineItemsNoPo = 0;
			totals.tradenet.lineItemsQty = 0;
			totals.tradenet.lineItemCost = 0;
			totals.tradenet.totalCost = 0;
			totals.tradenet.totalAvgUntCost = 0;
			totals.tradenet.unitCount = 0;
			totals.tradenet.mostCommonUnt = "";
			totals.tradenet.mostCommonUnitShare = 0;
			totals.tradenet.totalUnitCount = 0;

			var thisView = this;
			var marketData = [];

			_.each(this.collection.models, function(item){
				$.ajax({
					url: item.attributes.uri,
				}).always(function(data){
					// here data contains the output of MarketSizing ServiceController::getRowAction()
					count++;

                    // this used to loop through order unit/quantity stats which are now supplied as pre-calculated scalars
					/*
					data.orders.totalLineItemQuantity = 0;
					data.orders.totalLineItemCost = 0;
					data.orders.avgUnitCost = 0;
					data.orders.unitCount = 0;
					data.orders.mostCommonUnt = "";
					data.orders.mostCommonShare = 0;
					data.orders.avgUnitCostTotal = 0;
					data.orders.unitsCount = 0;

					$.each(data.orders.quantity, function(key, item) {
						data.orders.unitsCount++;

						if(data.orders.unitCount < item.lineItemCount){
							data.orders.unitCount = item.lineItemCount;
							data.orders.mostCommonUnt = key;
						}

						data.orders.avgUnitCost = data.orders.avgUnitCost + item.averageUnitCost;
						data.orders.totalLineItemQuantity = data.orders.totalLineItemQuantity + item.totalQuantity;
					});

					totals.tradenet.totalUnitCount = totals.tradenet.totalUnitCount + data.orders.unitsCount;
					data.orders.mostCommonPct = (data.orders.unitCount / data.orders.lineItemCount) * 100;
					data.orders.avgUnitCostTotal = data.orders.avgUnitCostTotal + data.orders.avgUnitCost;
					data.orders.avgUnitCost = data.orders.avgUnitCost / data.orders.unitsCount;
					data.orders.avgUnitCost = data.orders.avgUnitCost.toFixed(2);

					data.orders.totalLineItemQuantity = parseFloat(data.orders.totalLineItemQuantity.toFixed(2));
					data.orders.totalLineItemCost = parseFloat(data.orders.totalCost.toFixed(2));
					data.orders.totalCost = parseFloat(data.orders.totalCost.toFixed(2));
					*/

					data.count = count;
					data.length = length;

					marketData[thisView.getIndex(data.keywords)] = data;

					totals.pages.searches = totals.pages.searches + data.pagesSearchCount;
					totals.pages.events = totals.pages.events + data.rfq.eventCount;

					totals.tradenet.rfqNo		   = totals.tradenet.rfqNo + data.rfq.count;
					totals.tradenet.rfqBranchNo    = totals.tradenet.rfqBranchNo + data.rfq.buyerBranchCount;
					// totals.tradenet.rfqOrgNo 	   = totals.tradenet.rfqOrgNo + data.rfq.buyerOrgCount;
					// totals.tradenet.rfqVesNo 	   = totals.tradenet.rfqVesNo + data.rfq.vesselCount;
					totals.tradenet.lineItemsNoRfq = totals.tradenet.lineItemsNoRfq + data.rfq.lineItemCount;

					totals.tradenet.posNo = totals.tradenet.posNo + data.orders.count;
					totals.tradenet.posBranchNo = totals.tradenet.posBranchNo + data.orders.buyerBranchCount;
					totals.tradenet.posSpbBranchNo = totals.tradenet.posSpbBranchNo + data.orders.supplierBranchCount;
					// totals.tradenet.posOrgNo = totals.tradenet.posOrgNo + data.orders.buyerOrgCount;
					// totals.tradenet.posVesNo = totals.tradenet.posVesNo + data.orders.vesselCount;
					totals.tradenet.lineItemsNoPo = totals.tradenet.lineItemsNoPo + data.orders.lineItemCount;
					totals.tradenet.lineItemsQty = totals.tradenet.lineItemsQty + data.orders.totalLineItemQuantity;
					totals.tradenet.lineItemCost = totals.tradenet.lineItemCost + data.orders.totalLineItemCost;
					totals.tradenet.totalCost = totals.tradenet.totalCost + data.orders.totalCost;

					totals.tradenet.totalAvgUntCost = totals.tradenet.totalAvgUntCost + data.orders.avgUnitCostTotal;
					if (totals.tradenet.unitCount < data.orders.unitCount) {
						totals.tradenet.unitCount = data.orders.unitCount;
						totals.tradenet.mostCommonUnt = data.orders.mostCommonUnt;
					}

					if (length == count) {
						// if that was the last row loaded, we can display the totals
						totals.tradenet.lineItemsQty = totals.tradenet.lineItemsQty.toFixed(2);
						totals.tradenet.lineItemCost = totals.tradenet.lineItemCost.toFixed(2);
						totals.tradenet.totalCost = totals.tradenet.totalCost.toFixed(2);

						totals.tradenet.mostCommonPct = (totals.tradenet.unitCount / parseFloat(totals.tradenet.lineItemsNoPo)) * 100;

						totals.tradenet.totalAvgUntCost = totals.tradenet.totalAvgUntCost / totals.tradenet.totalUnitCount;
						totals.tradenet.totalAvgUntCost = totals.tradenet.totalAvgUntCost.toFixed(2);

						var html = thisView.totalsTemplate(totals);
						$('div.data table tfoot').html(html);

						thisView.renderItems(marketData);
						$('div.data').show();
					}
				});
			}, this);
		},

		renderItems: function(data){
			var count = 0;
			_.each(data, function(item){
				count++;
				item.count = count;
				this.renderItem(item);
			}, this);
		},

		renderItem: function(item){
			var rowView = new itemView({
				model: item
			});
			
			if(item.count == 1){
				rowView.first = true;
			}
			else {
				rowView.first = false;
			}
			if(item.count == item.length){
				rowView.last = true;
			}
			else {
				rowView.last = false;
			}

			rowView.parent = this;

			$('div.data table tbody').append(rowView.render().el);
		},

		openDialog: function() {
        	var thisView = this;
            $("#modal").overlay({
                mask: 'black',
                left: 'center',
                fixed: false,

                onBeforeLoad: function() {
                	$('.itemselector.selectlist.locations').data('oldHtml', $('.itemselector.selectlist.locations').html());
                	thisView.locTemp = $.extend({}, $('.itemselector.selectlist.location').data('selected'));

                	var windowWidth = $(window).width();
                    var modalWidth = $('#modal').width();
                    var posLeft = windowWidth/2 - modalWidth/2;
                    $('#modal').css('left', posLeft);
                },

                onLoad: function() {
                    $(window).resize(function(){
                        var windowWidth = $(window).width();
                        var modalWidth = $('#modal').width();
                        var posLeft = windowWidth/2 - modalWidth/2;

                        $('#modalContact').css('left', posLeft);
                    });
                    $('.tabcontent.locations').show();

                },
                onClose: function(){
                	if(thisView.saving){
                		thisView.saving = false;
                	}
                	else {
                		$('.itemselector.selectlist.locations').html($('.itemselector.selectlist.locations').data('oldHtml'));
                	}
                }
            });

            $('#modal').overlay().load();
        }
	});

	return new marketSizingView;
});