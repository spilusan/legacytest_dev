<?php
/*
* HTTP Layer for CAS client
*/
class Myshipserv_CAS_CasHTTP
{
    /**
     * Application.ini configs
     * @var StdObj
     */
	protected $config;
	
	
	/**
	 * Zend_Http_Client instance 
	 * @var Zend_Http_Client
	 */
	protected $httpClient;
	
	/**
	 * case error message
	 * @var String
	 */
	protected $casErrorMessage;
	protected $logger;
	protected $bodyTest;

	
	/**
	* Myshipserv_CAS_CasHTTP constructor
	*/
	protected function __construct()
    {
    	$this->config = Myshipserv_Config::getIni();
		$this->httpClient = new Zend_Http_Client();
		$this->httpClient->setConfig(array('maxredirects' => 2, 'timeout' => 20));
		$this->casErrorMessage = null;
		$this->logger = new Myshipserv_Logger_File('cas-rest');
    }

    
    /**
     * Sending DELETE REST request
     * @param string $url
     * @return Zend_Http_Response
     */    
	protected function _casCurlDelete($url)
	{

		$this->httpClient->setUri($url);
        $this->httpClient->setMethod(Zend_Http_Client::DELETE);

        try {
            $output = $this->httpClient->request();
        } catch (Exception $e) {
        	$this->setErrorMessage('CAS login exception: ' . (String) $e);
            return false;
        }  
        
        $this->setBody($output->getBody());

        if ($output->isError()) {
        	$this->setErrorMessage(
        		sprintf(
	                "\nhttp call did not succeed. Http status code: %s\n status msg: %s\n URL: %s \nBody: %s",
	                $output->getStatus(),
	                $output->getMessage(),
	                $url,
	                trim(strip_tags($output->getBody()))
	            )
        	);
            return null;
        }

		return $output;
	}
	
	/**
	* Helper function to perform a post and handle errors
	* 
	* @param array  $fields          HTTP POST Params 
	* @param string $url             Where to send POST command
	* @param boolean $sendAsRawData  Send as raw data in body
	* @return Zend_Http_Response
	*/
	protected function _casCurl($fields, $url, $sendAsRawData = false)
	{
		$this->httpClient->setUri($url);
        $this->httpClient->setMethod(Zend_Http_Client::POST);
        
        if ($sendAsRawData === true) {
            $this->httpClient->setRawData(http_build_query($fields));
        } else {
            $this->httpClient->setParameterPost($fields, 'application/json');
        }
        
        try {
            $output = $this->httpClient->request();
        } catch (Exception $e) {
            $this->setErrorMessage('_casCurl exception: ' . (String) $e);
            return null;
        }  

        $this->setBody($output->getBody());
        
        if ($output->isError()) {
            $this->setErrorMessage(
            	sprintf(
                    "\nhttp call did not succeed. Http status code: %s\n status msg: %s\n URL: %s \nBody: %s",
                    $output->getStatus(),
                    $output->getMessage(),
                    $url,
                    trim(strip_tags($output->getBody()))
                )
            );
            return null;
        }
        //Logging successful workflow (Uncomment for testing)
       	//$this->setErrorMessage("Success \n$url\n".print_r($fields, true)."\n".$output->getBody());
		return $output;
	}

	/**
	* Get the base requested URL
	* @return string
	*/
	protected function _getBaseRequestedUrl()
	{	
		/*
		* This one does not work, as our proxy does not forward the original X_FORWARDED_PROTOCOL in any way
		* but we can say we use https only
		* $url = array_key_exists('HTTPS', $_SERVER) ? 'https://' : 'http://';
		*/
		//$url = ($_SERVER['APPLICATION_ENV'] == 'development') ? 'http://' : 'https://';
		//Chenge to HTTPS only
		$url =  'https://' . $this->getApplicationHostName();
		return $url;
	}

	/**
	* Store message in Error Log
	* @param string $message to store in the log
	* @return unknown
	*/
	public function setErrorMessage($message)
	{
		$this->casErrorMessage = $message;
		$this->logger->log('----------------------------------------------------------');
		$this->logger->log($message);
		$this->logger->log('----------------------------------------------------------');
	}
	
	/**
	 * Setting last body text
	 * 
	 * @param string $bodyTest
	 */
	public function setBody($bodyTest)
	{
	    $this->bodyTest = $bodyTest;
	}
	
	/**
	 * Get the last sent out body text
	 * 
	 * @return string
	 */
	public function getBody()
	{
	    return $this->bodyTest;
	}

	/**
	* Return the text representation of the error (last occurence)
	* 
	* @return string
	*/
	public function getErrorMessage()
	{
		return $this->casErrorMessage;
	}

	/**
	* Get the hostname of the application
	* @return string
	*/
	public function getApplicationHostName()
	{
		return $this->config->shipserv->application->hostname;
	}
}
