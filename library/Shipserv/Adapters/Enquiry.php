<?php

/**
 * Adapter class for Enquiry Service
 * 
 * @package ShipServ
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
class Shipserv_Adapters_Enquiry extends Shipserv_Adapter
{
	/**
	 * The XML-RPC Client
	 * 
	 * @var object
	 * @access protected
	 */
	protected $client;
	
	protected $applicationversion;
	
	/**
	 * Set up the XML-RPC interface
	 * 
	 * @access public
	 */
	public function __construct ()
	{
		$config  = Zend_Registry::get('config');
		
		$this->client = new Zend_XmlRpc_Client($config->shipserv->services->enquiry->url);
		$this->applicationversion = Myshipserv_Config::getApplicationReleaseVersion();
		
		$this->geodata    = new Shipserv_Geodata();
		
		parent::__construct($config->memcache->server->host,
							$config->memcache->server->port,
							$config->memcache->client->keyPrefix,
							$config->memcache->client->keySuffix);
	}
	
	/**
	 * Adapter for fetching a supplier profile. Will optionally cache the result
	 * if a memcache adapter is passed
	 * 
	 * Request Format:
	 * <struct>
	 *		username (string) � Username used to login. Must exist in the oracle database.
	 *		senderName (string)
	 *		senderCompany (string)
	 *		senderEmail (string)
	 *  	senderPhone (string)
	 *		enquiryText (string)
	 *		subject (string)
	 *		vesselName (string)
	 *		imo (string)
	 *		deliveryLocation (string)
	 *		deliveryDate (string)
	 *		attachments (array)
	 *			<struct>
	 *				filename (string) e.g. whitepaper.pdf
	 *				url (string)
	 *				size (int) � file size
	 *		recipients (array) � An array of TNIDs
	 *			<string>
	 * 
	 * Response:
	 *		<string> -- The internal database ID for the saved enquiry
	 * 
	 * @access public
	 * @param string $username
	 * @param string $senderName
	 * @param string $senderCompany
	 * @param string $senderEmail
	 * @param string $senderPhone
	 * @param string $senderCountry
	 * @param string $enquiryText
	 * @param string $subject
	 * @param string $vesselName
	 * @param string $imo The vessel's IMO number
	 * @param string $deliveryLocation
	 * @param string $deliveryDate
	 * @param string $searchRecId
	 * @param string $getProfileId
	 * @param array $attachments
	 * @param array $recipients an array of TNIDs to which the enquiry should be sent
	 */
	public function send ($username, $senderName, $senderCompany, $senderEmail,
						  $senderPhone, $senderCountry, $enquiryText, $subject, $vesselName, $imo,
						  $deliveryLocation, $deliveryDate, $searchRecId, $getProfileId,
						  $attachments, $recipients, $mtml, $companyId, $companyType)
	{
		// make sure the recipients are strings, not ints
		$tnids = array();
		foreach ($recipients as $tnid)
		{
			$tnids[] = (string) $tnid;
		}
		
		if ($deliveryDate)
		{
			$deliveryDate = date('Ymd', strtotime($deliveryDate));
		}
		else 
		{
			$deliveryDate = null;
		}
		
		if( strtolower($companyType) == 'b' ) $companyType = 'BYO';
		else if( strtolower($companyType) == 'v' ) $companyType = 'SPB';
		
		$parameters = array(array('username'      => $username,
								  'senderName'    => $senderName,
								  'senderCompany' => $senderCompany,
								  'senderEmail'   => $senderEmail,
								  'senderPhone'   => $senderPhone,
								  'senderCountry' => $senderCountry,
								  'enquiryText'   => $enquiryText,
								  'subject'       => $subject,
								  'vesselName'    => $vesselName,
								  'imo'           => $imo,
								  'deliveryLocation' => $deliveryLocation,
								  'deliveryDate'     => $deliveryDate,
								  'searchRecId'   => $searchRecId,
								  'getProfileId'  => $getProfileId,
								  'attachments'   => $attachments,
								  'recipients'    => $tnids,
								  'mtml'   		  => $mtml,
								  'companyId'	  => $companyId,
								  'companyType'	  => $companyType,
								  'geoData'       => addslashes(serialize($this->geodata)),
								  'appVersion'    => $this->applicationversion));
		try
		{
			$this->client->call('Supplier.sendEnquiry', $parameters);
			return true;
		}
		catch (Zend_XmlRpc_Client_HttpException $e)
		{
			throw $e;
		}
		catch (Zend_XmlRpc_Client_FaultException $e)
		{
			$request = $this->client->getLastRequest();
			
			throw $e;
		}
		
		echo '<!--';
		var_dump($parameters);
		echo '//-->';
		return false;
	}
	
	
	/**
	 * Method to fetch an enquiry from the database
	 *
	 * @access public
	 * @param int $enquiryId The enquiry ID
	 * @param int $tnid The TNID of the supplier
	 * @return array
	 */
	public function fetch ($enquiryId, $tnid, $hash)
	{
		$parameters = array(array('enquiryId'        => $enquiryId,
								  'supplierBranchId' => $tnid,
								  'hashKey'          => $hash));
		
		try
		{
			$enquiry = $this->client->call('Supplier.getEnquiry', $parameters);
			
			return $enquiry;
		}
		catch (Zend_XmlRpc_Client_HttpException $e)
		{
			throw $e;
		}
		catch (Zend_XmlRpc_Client_FaultException $e)
		{
			$request = $this->client->getLastRequest();
			
			throw $e;
		}
		
		echo '<!--';
		var_dump($parameters);
		echo '//-->';
		return false;
	}
	
	/**
	 * Marks an enquiry as 'declined to quote'
	 *
	 * @access public
	 * @param int $enquiryId The enquiry ID
	 * @return array
	 */
	public function decline ($enquiryId, $tnid, $hash, $declineSource = 'EMAIL')
	{
		$parameters = array(array('enquiryId'        => $enquiryId,
								  'supplierBranchId' => $tnid,
								  'hashKey'          => $hash,
								  'declineSource'    => $declineSource));
		
		try
		{
			$this->client->call('Supplier.declineEnquiry', $parameters);
			
			return true;
		}
		catch (Zend_XmlRpc_Client_HttpException $e)
		{
			throw $e;
		}
		catch (Zend_XmlRpc_Client_FaultException $e)
		{
			$request = $this->client->getLastRequest();
			
			throw $e;
		}
		
		echo '<!--';
		var_dump($parameters);
		echo '//-->';
		return false;
	}
}
