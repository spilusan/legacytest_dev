define([
	'jquery',
	'underscore',
	'Backbone',
    'handlebars',
	'libs/jquery.tools.overlay.modified',
	'libs/jquery.uniform',
    '../collections/checklist',
    'text!templates/profile/companyPeople/tpl/list.html'
], function(
	$, 
	_, 
	Backbone,
    Hb,
	Modal,
	Uniform,
    checkList,
    checkListTpl
){
	var companyPeopleView = Backbone.View.extend({
		el: 'body',
		titleForModal: '',
        events: {
            'click .showBuyerBranches' : 'getData'
        },
        template: Handlebars.compile(checkListTpl),

		initialize: function(){

            this.collection = new checkList();
            this.collection.url = "/data/source/user/company/list/";
            $('input[type="checkbox"]').uniform();

		},
		
        getData: function(e) {
        	e.preventDefault();
        	
        	if($(e.target).is('a')){
        		var el = $(e.target);
        	}
        	else if($(e.target).is('span')){
        		var el = $(e.target).parent();
        	}
        	
        	var t = $(el).attr('href').split('//');
            var thisView = this;
            this.titleForModal = t[2] + "'s membership";
            this.userId = t[0];
            this.collection.fetch({
                data: $.param({
                	'userId': t[0]
                	, 'byoOrgCode': t[1]
                }),
                complete: function() {
                    thisView.render();
                }
            });
        },

        render: function(){
            var data = this.collection,
                html = this.template(data);

            $('#modal .modalBody').html(html);
            $('#modal input[type="checkbox"]').uniform();
            $('#modal input[type="radio"]').uniform();
            this.openDialog();
            var thisView = this;
            $('input.save').bind('click', function(e){
                e.preventDefault();
                thisView.saveList();
            });
        },

        saveList: function(){
        	if( $(".buyerBranchCode:checked").length == 0 ){
        		alert("Please select at least one buyer branch");
        	}else{
        		
	            var url = "/profile/store-byb-user/"
	            $.ajax({
	                type: "POST",
	                url: url,
	                data: $('form.tradingAccForm').serialize(),
	                success: function(){
	                    $('#modal').overlay().close();
	                },
	            });
        	}
        },

        openDialog: function() {
        	var thisView = this;
            $("#modal").overlay({
                mask: 'black',
                left: 'center',
                fixed: false,

                onBeforeLoad: function() {
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
                }
            });

            $('#modal').overlay().load();
            $('h1.styled').html(thisView.titleForModal);
            $("#tradingAccountUserId").val(this.userId);
        }
	});

	return new companyPeopleView;
});