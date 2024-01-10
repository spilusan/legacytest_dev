define([
	'jquery',
	'underscore',
	'Backbone',
    'handlebars',
	'libs/jquery.uniform',
    '../collections/approvedSuppliers',
    '../views/childRowView',
    '../views/filtersView',
    '../views/paginationButtonView',
    'text!templates/profile/companyApprovedSuppliers/tpl/approvedSuppliers.html'
], function(
	$, 
	_, 
	Backbone,
    Hb,
	Uniform,
    approvedSuppliersCollection,
    childRow,
    filters,
    paginationButton,
    approvedSupplierTpl
){
	var companyApprovedSuppliersView = Backbone.View.extend({
		el: 'body',
        currentPage: 1,
        listOrder: 2,
        isAsc: 1,
        silentAjax: false,
        events: {
           // 'click .showBuyerBranches' : 'getData'
        },
        approvedSupplierTemplate: Handlebars.compile(approvedSupplierTpl),

		initialize: function(){
            var thisView = this;
            filters.parent = this;
            /*
            var html = this.approvedSupplierTemplate();
            $("#generatedContent").html(html);
            */

            this.collection = new approvedSuppliersCollection();
            this.collection.url = "/data/source/supplier/approvedsupplierlist";
            //$('input[type="checkbox"]').uniform();
            $('body').ajaxStart(function(){
                if (thisView.silentAjax == false) {
                    $('#waiting').show();
                }
            });

            $('body').ajaxStop(function(){
                $('#waiting').hide();
            });

            this.getData();
		},
		
        getData: function() {
            var thisView = this;
            var keyword = $('#keyword').val();
                this.collection.reset();

                this.collection.fetch({
                data: $.param({
                    'currentPage': thisView.currentPage,
                    'keyword': keyword,
                    'listOrder': thisView.listOrder,
                    'isAsc': thisView.isAsc,
                }),
                complete: function() {
                    thisView.render();
                }
            });

            
        },

        render: function(){
            var thisView = this;
            if (this.collection.models[0]) {
                var tempData = new Object();
                /* Data for ordering information */
                tempData.arrow1 = (this.listOrder == 1);
                tempData.arrow2 = (this.listOrder== 2);
                tempData.arrow3 = (this.listOrder == 3);
                tempData.arrow4 = (this.listOrder == 4);
                tempData.arrow5 = (this.listOrder == 5);
                tempData.isAsc = (this.isAsc == 1) ? "arrowDown" : "arrowUp";

                var html = this.approvedSupplierTemplate(tempData);
                $("#generatedContent").html(html);
                $(".reorderList").unbind().bind('click', function(e){

                    e.preventDefault;
                    var selectedOrder = parseInt($(this).data('ord'));

                    if (selectedOrder == thisView.listOrder ) {
                        thisView.currentPage = 1;
                        thisView.isAsc = (thisView.isAsc == 1) ? 0 : 1;
                    } else {
                        thisView.listOrder = selectedOrder;
                        thisView.currentPage = 1;
                        thisView.isAsc = 1;
                    }

                    thisView.getData();

                });

                var currentState = $("#allowNotification").is(':checked');
                var newState = this.collection.models[0].attributes.raiseAlert;

                if (currentState != newState) {
                    $('#allowNotification').attr('checked',newState);
                    $.uniform.update($('#allowNotification'));

                }
                
                var pageCount = this.collection.models[0].attributes.pageCount;
                var maxListedPage = 10;

                var fromPage =  Math.floor((this.currentPage-1) / maxListedPage)*maxListedPage +1;
                var toPage = (fromPage +maxListedPage > pageCount) ? pageCount : fromPage + maxListedPage-1;

                /**
                * Create the pagination
                */
                $('#pagination').html('');
                if (pageCount > 1)
                {
                    if (fromPage > 1) {
                        this.addPaginationButton((fromPage-1),false,1);
                    }
                    for (var i = fromPage; i <= toPage ; i++ ) {
                        if (i == this.currentPage) 
                        {
                            this.addPaginationButton(i,true,0);
                        } else {
                            this.addPaginationButton(i,false,0);
                        }
                    }

                    if (toPage < pageCount) {
                        this.addPaginationButton(i,false,2);
                    }
                }
                /**
                * Render the elements
                */
                 _.each(this.collection.models[0].attributes.data, function(item){
                     this.renderItem(item);
               }, this);
                 $(document).scrollTop( 0 );

             }
        },

        renderItem: function(item)
        {

          var ChildRow = new childRow({
                model: item,
            });

          ChildRow.setParent(this);
          $('.dataContainer').append(ChildRow.render().el);
        },

        addPaginationButton: function(pageNr, isSelected, buttonType) 
        {

             var pgModel = new Object();
             pgModel.pageNr = pageNr;
             pgModel.isSelected = isSelected;
             pgModel.buttonType = buttonType;

              var pgButton = new paginationButton({
                    model: pgModel
                });
              pgButton.parent = this;

              $('#pagination').append(pgButton.render().el);
        }

	});

	return new companyApprovedSuppliersView;
});