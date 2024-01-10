define([
	'jquery',
	'underscore',
	'Backbone',
	'libs/ajaxfileupload'
], function(
	$, 
	_, 
	Backbone,
	Upload
){
	var fileUploadView = Backbone.View.extend({
		el: $('body'),

		events: {
			'change input#scopeWhiteFile' : 'uploadWhiteFile',
			'change input#scopeBlackFile' : 'uploadBlackFile',
			'change input#scopeBlackSbFile' : 'uploadBlackSbFile'
		},

	    uploadWhiteFile: function(){
	    	var thisView = this;
	    	$.ajaxFileUpload(
				{
					url:'/buyer/blacklist/add',
					secureuri: false,
					fileElementId: 'scopeWhiteFile',
					dataType: 'json',
					data:{
						type:'whitelist'
					},
					success: function (data, status)
					{
						thisView.afterUpload('whitelist');
					},
					error: function (data, status, e)
					{
						alert(e);
					}
				}
			)

			return false;
	    },

	    uploadBlackFile: function(){
	    	var thisView = this;
	    	$.ajaxFileUpload(
				{
					url:'/buyer/blacklist/add',
					secureuri: false,
					fileElementId: 'scopeBlackFile',
					dataType: 'json',
					data:{
						type:'blacklist'
					},
					success: function (data, status)
					{
						thisView.afterUpload('blacklist');
					},
					error: function (data, status, e)
					{
						alert(e);
					}
				}
			)

			return false;
	    },

	    uploadBlackSbFile: function(){
	    	var thisView = this;
	    	$.ajaxFileUpload(
				{
					url:'/buyer/blacklist/add',
					secureuri: false,
					fileElementId: 'scopeBlackSbFile',
					dataType: 'json',
					data:{
						type:'blacksb'
					},
					success: function (data, status)
					{
						thisView.afterUpload('blacksb');
					},
					error: function (data, status, e)
					{
						alert(e);
					}
				}
			)

			return false;
	    }, 

	    afterUpload: function( status )
	    {
	    	var thisView = this;
	    	/* After uplolad, we need to clear the memcache for Spend Benchmarking */
			$.ajax({
                type: "GET",
                url: "/buyer/blacklist/clear-spend-benchmark-cache",
                success: function(){
                    thisView.parent.getData(status, true);
                },
                error: function(e) {
                    alert('Something went wrong, Please try it later');
                }
            });
	    }


	    
	});

	return fileUploadView;
});