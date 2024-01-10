define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'/js/jquery.jsoncookie.js',
	'/js/jquery.cookie.min.js',
	'../collections/recSuppliers',
	'text!templates/reports/priceBench/tpl/recomendedSuppliers.html',
	 '../views/recItem'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	jsonCookie,
	cookie,
	recSuppliersCollection,
	recomendedSupplierTpl,
	recItemView
){

	var recomendedSupplierView = Backbone.View.extend({
		el: $('.dataBox'),
		basketCookieName: null,
		maxSelectedSupplier: null,
		basketCookieDomain: null,
		basketCookiePath: null,
		events: {

		},

		recomendedSuppliersTemplate: Handlebars.compile(recomendedSupplierTpl),

		initialize: function() {
			var thisView = this;
			this.recCollection = new recSuppliersCollection();

			//Adding handlebar helper to createUrl
			Handlebars.registerHelper('createUrl', function(pname, tnid, psection) {
				var name = pname.replace(/[^a-zA-Z\d\s:]/g, "");
				if (psection == null || psection == 'profile' )
				{
					var section = 'supplier/profile';
				} else if (psection == 'review') {
					var section = 'reviews/supplier';
				}
				name = name.toLowerCase();
				name = name.replace(/(\W){1,}/g, '-');
				return '/'+section+'/s/'+name+'-'+tnid;
			});

			this.basketCookieName = window.basketCookieName;
			this.maxSelectedSupplier = window.maxSelectedSupplier;
			this.basketCookieDomain = window.basketCookieDomain;
			this.basketCookiePath = window.basketCookiePath;

			$("body").delegate('.searchResult input:checkbox','click', function() {
			var o = $.JSONCookie(thisView.basketCookieName);

		
				if (this.checked) {
					if (!o.suppliers) {
						o.suppliers = [];
					}

					var tnid = this.id;
					var count = thisView.countProperties(o.suppliers);

					if( count > thisView.maxSelectedSupplier-1 ){
						alert("Sorry, you can only select up to " + thisView.maxSelectedSupplier + " Suppliers.\n\nIf you send the same RFQ to more than \n" + thisView.maxSelectedSupplier + " suppliers, in 1 or more batches, the \nsystem will permanently disable your email address.");

						//maxSuppliersPopUp(maxSelectedSupplier);
						
						return false;
					}
					
					o.suppliers.push(tnid);
				} else {
					var tnid = this.id;
					for(var i=0;i<o.suppliers.length;i++){
						if( tnid == o.suppliers[i] ){
							o.suppliers.splice(i,1);
						}
					}
				}
				$.JSONCookie(thisView.basketCookieName, o, { domain: thisView.basketCookieDomain,	path: thisView.basketCookiePath });
			});


		},

		render: function() {

			var thisView = this;
			/* var data = this.model.attributes; 
			var html = this.priceTemplate(data);*/
			var html = this.recomendedSuppliersTemplate();
			$(this.el).html(html);

			this.renderItems();
			this.parent.fixHeight();

			return this;
		},
		getData : function() {
			var thisView = this;

			this.recCollection.reset();
			if (this.parent.hasImpaCodeList()) {
				this.recCollection.fetch({
					add: true,
					remove: false,
					data: $.param({
						products: this.parent.getImpaCodeList(),
						//query: this.keywords,
						pageNo: this.parent.rightPageNo,
						pageSize: this.parent.pageSize,
						filter: {
							dateFrom: this.parent.dateFrom,
							dateTo: this.parent.dateTo,
							vessel: this.parent.vessel,
							location: this.parent.location,
							excludeRight: this.parent.excludeRight,
							refineQuery: this.parent.refineRightQuery
						},
						sortBy: this.parent.sortRight,
						sortDir: this.parent.sortOrderRight
					}),
					complete: function() {
						thisView.render();
					}
				});
			} 
		},
		renderItems: function() {
			if (this.recCollection.models[0]) {

		    	_.each(this.recCollection.models[0].attributes.suppliers.documents, function(item) {
			        this.renderItem(item);
			    }, this);
			 	if (this.recCollection.models[0].attributes.userMessage) {
					alert(this.recCollection.models[0].attributes.userMessage);
				} 
	    	}
	    	/* Items are rendered, set Traderanks */
	    	$('.ratingImg').each(function(){
	    		var offset = 67-(Math.round(67*(parseInt($(this).data('rank'))/20)));
	    		$(this).css("backgroundPosition", "-"+offset+"px", "0px");
	    	});

	    	//items are rendered,

			// new addition: Check all selected suppliers 
			var b = $.JSONCookie(this.basketCookieName);
			if( typeof b.suppliers != 'undefined' )
			{
				for(var i=0;i<b.suppliers.length;i++){
					$(".searchResult input:checkbox[id='"+b.suppliers[i]+"']").attr('checked','checked');
				}
			}

	    },
	    renderItem: function(item) {

	       var recItem = new recItemView({
                model: item
            });

            recItem.parent = this;

            var elem = "#recItemList";
            $(elem).append(recItem.render().el);
	    },

	    countProperties: function( obj )
		{
			var keys = [];
	        for (k in obj) {
	            if (Object.prototype.hasOwnProperty.call(obj, k)) {
	                keys.push(k);
	            }
	        }
	        return keys.length;
		},

	});

	return recomendedSupplierView;
});