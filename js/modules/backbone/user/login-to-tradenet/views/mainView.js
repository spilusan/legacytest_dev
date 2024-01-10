define([
	'jquery',
	'Backbone'
], function(
	$,
	Backbone
){
	var mainView = Backbone.View.extend({
		initialize: function() {
			var thisView = this;
			$(document).ready(function(){
				window.document_onkeypress();
				thisView.startup();
				$('#Image1').mouseover(function(){
					thisView.MM_swapImage('Image1','','/img/tnet/visit-tradenet_over.jpg',1);
				});

				$('#Image1').mouseout(function(){
					thisView.MM_swapImgRestore();

				});
			});
		},

		MM_preloadImages: function()
		{ //v3.0
			var d=document;
			if(d.images) {
				if(!d.MM_p) d.MM_p=new Array();
				var i,j=d.MM_p.length,a=this.MM_preloadImages.arguments; 
				for(i=0; i<a.length; i++) if (a[i].indexOf("#")!=0) {
					d.MM_p[j]=new Image; d.MM_p[j++].src=a[i];
				}
			}
		},

		MM_swapImgRestore: function()
		{ //v3.0
			var i,x,a=document.MM_sr;
			for(i=0;a&&i<a.length&&(x=a[i])&&x.oSrc;i++) x.src=x.oSrc;
		},

		MM_findObj: function(n, d)
		{ //v4.01
			var p,i,x;
			if(!d) d=document;
			if((p=n.indexOf("?"))>0&&parent.frames.length) {
				d=parent.frames[n.substring(p+1)].document;
				n=n.substring(0,p);
			}
			if(!(x=d[n])&&d.all) x=d.all[n];
			for (i=0;!x&&i<d.forms.length;i++) x=d.forms[i][n];
			for(i=0;!x&&d.layers&&i<d.layers.length;i++) x=this.MM_findObj(n,d.layers[i].document);
			if(!x && d.getElementById) x=d.getElementById(n);
			return x;
		},

		MM_swapImage: function()
		{ //v3.0
			var i,j=0,x,a=this.MM_swapImage.arguments;
			document.MM_sr=new Array;
			for(i=0;i<(a.length-2);i+=3) if ((x=this.MM_findObj(a[i]))!=null) {
				document.MM_sr[j++]=x;
				if(!x.oSrc) x.oSrc=x.src; x.src=a[i+2];
			}
		},

		login: function()
		{
			document.loginForm.submit();
		},

		startup: function()
		{
		    document.loginForm.username.focus(); 
			bname=navigator.appName;
			
			//Set up the object reference depending on presence of Netscape's DOM & from this the browser type
			if(document.layers) {
				objRef = document.controlCentre.document.loginForm;
				objRef.BrowserName.value = "Netscape";
			} else {
				objRef = document.loginForm;
				objRef.BrowserName.value = "IE";
			}

			return;
		}

	});

	return new mainView();
});
