<?php
/**
 * When you need your JSON controller to rely on CAS 
 */   
trait Myshipserv_Controller_AuthExport
{
    use Myshipserv_Controller_Export
    {
        Myshipserv_Controller_Export::init as initExport;
    }

    /**
    * Set the header to save the output as a file, possible open with excel
    * @return unkonwn
    */
    public function init()
    {
        $this->initExport();
        $exporFileName = ($this->exportFileName) ? $this->exportFileName : 'report.csv';

		header("Content-Type: text/html");

		//header("Content-Disposition: attachment; filename=buyer-usage-report.csv");
		header("Content-Disposition: attachment; filename=" . $exporFileName);

		// Disable caching
		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1

		header("Pragma: no-cache"); // HTTP 1.0
		header("Expires: 0"); // Proxies
        //Delete the cookie for spinner managament, A cookie can be created with javascript, then in a timer check if the cookie still exists, if not remove spinner from the screen
		header("Set-Cookie: showSpinner=deleted; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT");

    }
}
