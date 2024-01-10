// Filename: router.js
define([
	'jquery',
	'underscore',
	'Backbone',
	'backbone/help/views/registerView',
	'backbone/help/views/updateView',
	'backbone/help/views/maximiseView',
	'backbone/help/views/brandSupplierView',
	'backbone/help/views//brandOwnerView',
	'backbone/help/views/brandReviewsView',
	'backbone/help/views/rfqStartView',
	'backbone/help/views/rfqSmartView',
	'backbone/help/views/rfqIntView',
	'backbone/help/views/pagesHelpView',
	'backbone/help/views/helpHomeView'
], 

function(
	$, 
	_, 
	Backbone, 
	registerView, 
	updateView,
	maximiseView,
	brandSupplierView,
	brandOwnerView,
	brandReviewsView,
	rfqStartView,
	rfqSmartView,
	rfqIntView,
	pagesHelpView,
	helpHomeView
){
	
	var AppRouter = Backbone.Router.extend({
	
		routes: {
			'register'			: 'showRegister',
			'update'			: 'showUpdate',
			'maximise'			: 'showMaximise',
			'brandSupplier' 	: 'showBrandSupplier',
			'brandOwner'		: 'showBrandOwner',
			'brandReviews'		: 'showBrandReviews',
			'rfqStart'			: 'showRfqStart',
			'rfqSmart'			: 'showRfqSmart',
			'rfqIntegrated'		: 'showRfqInt',
			'pagesHelp'			: 'showPagesHelp',

			'*actions'			: 'defaultAction'
		},

		showRegister: function(){
			registerView.render();
		},

		showUpdate: function(){
			updateView.render();
		},

		showMaximise: function(){
			maximiseView.render();
		},

		showBrandSupplier: function(){
			brandSupplierView.render();
		},

		showBrandOwner: function(){
			brandOwnerView.render();
		},

		showBrandReviews: function(){
			brandReviewsView.render();
		},

		showRfqStart: function(){
			rfqStartView.render();
		},

		showRfqSmart: function(){
			rfqSmartView.render();
		},

		showRfqInt: function(){
			rfqIntView.render();
		},

		showPagesHelp: function(){
			pagesHelpView.render();
		},

		defaultAction: function(){
			helpHomeView.render();
		}
	});

	return AppRouter;
});