var newWindow;
var bname;
var objRef = null;

function launchNewWindow(pagename)
{
	newWindow = window.open(pagename, "sub", "width=545,height=505,scrollbars");
	newWindow.focus();
}

function launchBiggerWindow(pagename)
{
	newWindow = window.open(pagename, "sub", "width=645,height=505,scrollbars,resizable=yes");
	newWindow.focus();
}
function launchWindow(pagename)
{
	newWindow = window.open(pagename, "sub", "width=770,height=600,scrollbars");
	newWindow.focus();
}

// edit fnLogin to prevent logins during maintenance or to put advance warnings a few days before -robin
function fnLogin()
{
 //alert('ADVANCE WARNING : \n\nPlease note that TradeNet will be unavailable for around 4 hours on Sunday 24th April 2011 at 16:00 GMT\nin order to carry out essential maintenance work.\nThis corresponds to : \n \t * 09:00 USA/Pacific time \n \t * 12:00 USA/Eastern time  \n \t * 18:00 Central European \n \t * midnight Asia/HongKong time.');
  // alert('ShipServ TradeNet is down for maintenance on  Sunday 22nd November 2009 from 11:00 GMT until 15:00 GMT.\n ');
  // return;
	if(objRef.j_username.value=="")
	{
		alert('Please Enter Your Member Name');
		objRef.j_username.focus();
		return;
	}

	if (objRef.j_password.value=="")
	{
		alert('Please Enter Your Password');
		objRef.j_password.focus();
		return;
	}
	

	objRef.method="POST";
	// changed RAVI's LOCAL PC to use non-ssl login 14may04
	// wtf are ravi's local pc changes doing on here..?!
	 // objRef.action="/LoginToSystem";
	 
	 
	//objRef.action="https://"+location.host+"/LoginToSystem";
	objRef.action="https://"+location.host+"/j_security_check";
	
	objRef.submit();
}

// permalogin always works!
function fnPermaLogin()
{
	if(objRef.j_username.value=="")
	{
		alert('Please Enter Your Member Name');
		objRef.j_username.focus();
		return;
	}
	if (objRef.j_password.value=="")
	{
		alert('Please Enter Your Password');
		objRef.j_password.focus();
		return;
	}
	objRef.method="POST";
	//objRef.action="/LoginToSystem";
	
	//objRef.action="https://"+location.host+"/LoginToSystem";
	objRef.action="https://"+location.host+"/j_security_check";
	objRef.submit();

}

// don't use fnMaint anymore : edit fnKmecLogin AND fnLogin if down for maintenance.. robin19feb05
function fnMaint()
{
        alert('ShipServ TradeNet is down for maintenance on Saturday February 19th from 15:00 until 21:00 GMT.\n Please try again later.   \n');
        return;
}

function fnKmecLogin()
{
    //alert('ShipServ TradeNet is down for routine maintenance on Saturday 2nd February from 14:00 GMT to 17:00 GMT for scheduled maintenance.\n ');
    //return;
	
	if(document.frmkmec.userid.value=="")
	{
		alert('Please Enter Your Member Name');
		document.frmkmec.userid.focus();
		return false;
	}
	
	if (document.frmkmec.password.value=="")
	{
		alert('Please Enter Your Password');
		document.frmkmec.password.focus();
		return false;
	}
	document.frmkmec.host.value=location.host;

	//document.frmkmec.action="https://www.shipserv.com/LoginToSystem";
	objRef.action="https://"+location.host+"/j_security_check";	
	document.frmkmec.submit();
}

function document_onkeypress()
{
	//Functionality limited to IE on Return keypress(ASCII code 13)
	if(!document.layers && event.keyCode==13)
	{
		fnLogin();
	}
}

function window_onload()
{

    document.aboutshipservform.j_username.focus(); 
	bname=navigator.appName;
	
	//Set up the object reference depending on presence of Netscape's DOM & from this the browser type
	if(document.layers)
	{
		objRef = document.controlCentre.document.aboutshipservform;
		objRef.BrowserName.value = "Netscape";
	}
	else
	{
		objRef = document.aboutshipservform;
		objRef.BrowserName.value = "IE";
	}
	return;
}