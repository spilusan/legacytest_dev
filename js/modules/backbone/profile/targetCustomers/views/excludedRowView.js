define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/shared/hbh/general',
	'libs/jquery.tools.overlay.modified',
	'text!templates/profile/targetCustomers/tpl/excludedRow.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	HbhGen,
	Modal,
	excludedRowTpl
){
	var excludedRowView = Backbone.View.extend({
		tagName: 'tr',

		template: Handlebars.compile(excludedRowTpl),

		events: {
			'click input[name="target"]' : 'showTargetPopup',
			'click input[name="doTarget"]' : 'target'
		},
		
		initialize: function(){
			_.bindAll(this, 'render');
			this.model.view = this;
		},

	    render: function() {
			var data = this.model.attributes;

			if(data.OrderValue === null){
				data.OrderValue = 0;
			}

			data.vessels = [];
			var vesselTypeCount = data.vessel.vesselTypeList.length;
			var count = 0;

			_.each(data.vessel.vesselTypeList, function(item){
				count++;
				if(count <= 3){
					if(count != vesselTypeCount && count < 3){
						item = item + ", ";
					}
					data.vessels.push(item);
				}
				else if(count === 4){
					if(vesselTypeCount === 4){
						var typeCopy = "type";
					}
					else {
						var typeCopy = "types";
					}
					data.vessels.push('and ' + (vesselTypeCount - 3) + ' other vessel ' + typeCopy);
				}
			}, this);

			var html = this.template(data);

			$(this.el).html(html);

	        return this;
	    },

	    showTargetPopup: function(e){
	    	var thisView = this;
	    	$('.promotePopup .custName').html(this.model.attributes.name);
	    	this.openDialog();
	    	this.doTargetButton = $(e.target).next('input[name="doTarget"]');
	    	$('input[name="pmote"]').unbind().bind('click', function(e){
	    		$(thisView.doTargetButton).click();
	    	});
	    },

	    target: function(e){
	    	e.preventDefault();
	    	var thisView = this;
	    	$('#modal.promotePopup').overlay().close();

	    	$.ajax({
				method: "GET",
				url: "/profile/target-customers-request",
				data: { 
					type: "add",
					buyerId: thisView.model.attributes.buyerId
				}
			})
			.done(function( msg ) {
				thisView.deleteRow();
				thisView.parent.getTargeted();
			})
			.fail(function(msg){
				alert('An error occurred.');
			});
	    },

	    cancelModal: function() {
	    	$('#modal.promotePopup').overlay().close();
	    },

	    deleteRow: function(){
	    	this.model.destroy();
	    	this.remove();
	    },

	    openDialog: function() {
	    	$("#modal").overlay({
		        mask: 'black',
		        left: 'center',
		        fixed: 'true',
		 
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

		        		$('#modal').css('left', posLeft);
		        	});
		        }
			});

			$('#modal.promotePopup').overlay().load();
	    }
	});

	return excludedRowView;
});