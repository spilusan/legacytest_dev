define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/shared/hbh/general',
	'libs/jquery.tools.overlay.modified',
	'text!templates/profile/targetCustomers/tpl/pendingRow.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	HbhGen,
	Modal,
	pendingRowTpl
){
	var pendingRowView = Backbone.View.extend({
		tagName: 'tr',

		template: Handlebars.compile(pendingRowTpl),

		events: {
			'click input[name="target"]' : 'showTargetPopup',
			'click input[name="exclude"]' : 'showExcludePopup',
			'click input[name="doTarget"]' : 'target',
			'click input[name="doExclude"]' : 'exclude'
		},
		
		initialize: function(){
			_.bindAll(this, 'render');
			this.model.view = this;
		},

	    render: function() {
	    	var thisView = this;
	    	$('input[name="cancel"]').unbind().bind('click', function(){
	    		thisView.cancelModal();
	    	});
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

	    showExcludePopup: function(e){
	    	var thisView = this;
	    	$('.excludePopup .custName').html(this.model.attributes.name);
	    	this.openXDialog();
	    	this.doExcludeButton = $(e.target).next('input[name="doExclude"]');
	    	$('input[name="xclude"]').unbind().bind('click', function(e){
	    		$(thisView.doExcludeButton).click();
	    	});
	    },

	    target: function(e){
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
	    	$('#exposeMask').click();
	    },

	    exclude: function(e){
	    	e.preventDefault();
	    	$('#modalX.excludePopup').overlay().close();
	    	var thisView = this;

	    	$.ajax({
				method: "GET",
				url: "/profile/target-customers-request",
				data: { 
					type: "exclude",
					buyerId: thisView.model.attributes.buyerId
				}
			})
			.done(function(msg) {
				thisView.deleteRow();
				thisView.parent.getExcluded();
			})
			.fail(function(msg){
				alert('An error occurred.');
			});
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

			$('#modal').overlay().load();
	    },

	    openXDialog: function() {
	    	$("#modalX").overlay({
		        mask: 'black',
		        left: 'center',
		        fixed: 'true',
		 
		        onBeforeLoad: function() {
		            var windowWidth = $(window).width();
		        	var modalWidth = $('#modalX').width();
		        	var posLeft = windowWidth/2 - modalWidth/2;

		        	$('#modalX').css('left', posLeft);
		        },

		        onLoad: function() {
		        	$(window).resize(function(){
		        		var windowWidth = $(window).width();
		        		var modalWidth = $('#modalX').width();
		        		var posLeft = windowWidth/2 - modalWidth/2;

		        		$('#modalX').css('left', posLeft);
		        	});
		        }
			});

			$('#modalX').overlay().load();
	    }
	});

	return pendingRowView;
});