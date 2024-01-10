define([
	'jquery',
	'underscore',
	'Backbone',
	'jqueryui/widget',
	'libs/tmpl.min',
	'libs/load-image.min',
	'libs/canvas-to-blob.min',
	'libs/jquery.iframe-transport',
	'libs/jquery.fileupload',
	'libs/jquery.fileupload-ui',
	'libs/jquery.fileupload-fp',
], function(
	$, 
	_, 
	Backbone,
	uiWidget,
	tmplJS,
	loadImage,
	canvasToBlob,
	iframeTransport,
	fileUpload,
	fileUploadUi,
	fileUploadFp
){
	var fileUploadView = Backbone.View.extend({
		el: $('form#fileupload'),

		rfqData: require("rfq/rfqData"),

		initialize: function(){
			_.bindAll(this, 'render');
		},

	    render: function() {
	    	// Initialize the jQuery File Upload widget:
		    $('#standard').fileupload({
		        // Uncomment the following to send cross-domain cookies:
		        //xhrFields: {withCredentials: true},
		        url: '/enquiry/upload/hash/'+this.rfqData.hash
		    });
		    

	        $('#standard').fileupload('option', {
	            url: '/enquiry/upload/hash/'+this.rfqData.hash,
	            maxFileSize: 1000000, // 1MB
	            maxNumberOfFiles: 3,
	            acceptFileTypes: /(\.|\/)(gif|jpe?g|png|bmp|pdf|doc|docx|rtf|xls|xlsx|csv|txt)$/i,
	            autoUpload: true,	            
	            process: [
	                {
	                    action: 'load',
	                    fileTypes: /^image\/(gif|jpe?g|png|bmp|pdf|doc|docx|rtf|xls|xlsx|csv|txt)$/,
	                    maxFileSize: 1000000, // 1MB
	                    maxNumberOfFiles: 3
	                },
	                {
	                    action: 'save'
	                }
	            ]
	        });
	    }
	});

	return new fileUploadView;
});