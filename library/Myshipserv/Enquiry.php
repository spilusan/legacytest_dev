<?php

/**
 * Wrapper for Enquiry Service. Handles validation and attachment uploads
 * 
 * @package ShipServ
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
class Myshipserv_Enquiry
{
	/**
	 * Constructor for an enquiry
	 *
	 * @access public
	 * @param string $username
	 * @param int $userId
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
	public function __construct ($username, $userId, $senderName, $senderCompany,
								 $senderEmail, $senderPhone, $senderCountry, $enquiryText, $subject,
								 $vesselName, $imo, $deliveryLocation, $deliveryDate,
								 $searchRecId, $getProfileId, array $attachments,
								 array $recipients, $mtml = "", $companyId = "", $companyType = "")
	{
		$this->username      = $username;
		$this->userId        = $userId;
		$this->senderName    = $senderName;
		$this->senderCompany = $senderCompany;
		$this->senderEmail   = $senderEmail;
		$this->senderPhone   = ($senderPhone) ? $senderPhone : ''; // phone is not mandatory, but should not be 'null'
		$this->senderCountry = ($senderCountry) ? $senderCountry : ''; // country is not mandatory, but should not be 'null'
		$this->enquiryText   = $enquiryText;
		$this->subject       = $subject;
		$this->vesselName    = $vesselName;
		$this->imo           = $imo;
		$this->deliveryLocation = $deliveryLocation;
		$this->deliveryDate     = $deliveryDate;
		$this->searchRecId   = ($searchRecId) ? $searchRecId : '';
		$this->getProfileId  = ($getProfileId) ? $getProfileId : '';
		$this->attachments   = $attachments;
		$this->recipients    = $recipients;
		$this->mtml			 = $mtml;
		$this->companyId	 = $companyId;
		$this->companyType	 = $companyType;
	}
	
	public function __set ($key, $value)
	{
		$this->{$key} = $value;
	}
	
	public function __get ($key)
	{
		return $this->{$key};
	}
	
	public static function getBannedUserBySupplierId( $supplierId )
	{
		
	}
	
	public function sendSupplierFeedbackToBuyer( $response, $supplierId )
	{
		$db = $GLOBALS['application']->getBootstrap()->getResource('db');
		
		$nm = new Myshipserv_NotificationManager( $db );
		$nm->sendBuyerFeedbackWhenSupplierDeclineRFQ( $this, $response, $supplierId);
	}
	
	/**
	 * Validates the current enquiry instance.
	 * 
	 * @access public
	 * @return boolean
	 */
	private function validate ()
	{
		return true;
	}
	
	/**
	 * Attempts to send an enquiry. Validates first, then uploads attachments,
	 * then sends to the Enquiry Service.
	 * 
	 * Will throw exceptions if validation or attachment uploads fail
	 * 
	 * @access public
	 * @return boolean TRUE if enquiry was successfully sent, FALSE if otherwise
	 */
	public function send ()
	{
		try
		{
			if (!$this->validate())
			{
				throw new Myshipserv_Enquiry_Exception('');
			}
			
			$attachments = self::uploadAttachments($this->attachments, $this->userId);
			
			$enquiryAdapter = new Shipserv_Adapters_Enquiry();
			
			$return = $enquiryAdapter->send($this->username, $this->senderName,
										    $this->senderCompany, $this->senderEmail,
										    $this->senderPhone, $this->senderCountry, $this->enquiryText,
											$this->subject, $this->vesselName, $this->imo, 
											$this->deliveryLocation, $this->deliveryDate,
										    $this->searchRecId, $this->getProfileId,
										    $attachments, $this->recipients, $this->mtml, $this->companyId, $this->companyType);
			
			echo '<!--';
			var_dump($return);
			echo '//-->';
			
			return $return;
		}
		catch (Myshipserv_Enquiry_Exception $e)
		{
			throw $e;
		}
	}
	
	public static function fetch ($enquiryId, $supplierBranchId, $securityHash)
	{
		try
		{
			$enquiryAdapter = new Shipserv_Adapters_Enquiry();
			
			$data = $enquiryAdapter->fetch($enquiryId, $supplierBranchId, $securityHash);
			return new self ($data['username'],
							 $data['userId'],
							 $data['senderName'],
							 $data['senderCompany'],
							 $data['senderEmail'],
							 $data['senderPhone'],
							 $data['senderCountry'],
							 $data['enquiryText'],
							 $data['subject'],
							 $data['vesselName'],
							 $data['imo'],
							 $data['deliveryLocation'],
							 $data['deliveryDate'],
							 $data['searchRecId'],
							 $data['getProfileId'],
							 (array)$data['attachments'],
							 (array)$data['recipients'],
							 $data['mtml'],
							 $data['companyId'],
							 $data['companyType']
			);
		}
		catch (Exception $e)
		{
			throw $e;
		}
	}
	
	public static function reject ($enquiryId, $supplierBranchId, $securityHash, $declineSource)
	{
		try
		{
			$enquiryAdapter = new Shipserv_Adapters_Enquiry();
			
			$data = $enquiryAdapter->decline($enquiryId, $supplierBranchId, $securityHash, $declineSource);
		}
		catch (Exception $e)
		{
			throw $e;
		}
		
		return true;
	}
	
	/**
	 * Uploads any attachments to Amazon's S3 storage service
	 *
	 * @access private
	 * @static
	 * @param array $attachments
	 * @param int $userId
	 * @return array An array of uploaded attachments with their full URL
	 */
	private static function uploadAttachments (array $attachments, $userId)
	{
		if (count($attachments) == 0)
		{
			return array();
		}
		
		$config  = Zend_Registry::get('config');
		
		switch ($config->shipserv->enquiryBasket->attachments->uploadLocation)
		{
			case 'S3':
				$s3 = new Zend_Service_Amazon_S3($config->services->amazon->S3->accessKey,
												 $config->services->amazon->S3->secretKey);
				
				$uploadedAttachments = array();
				foreach ($attachments as $file)
				{
					$filenameArray = array_reverse(explode('/', $file));
					$filename      = $userId.'-'.$filenameArray[0];
					
					$fileUrl = $config->shipserv->services->enquiry->S3bucket.'/'.$filename;
					
					$s3->putFile($file, $fileUrl,
								 array(Zend_Service_Amazon_S3::S3_ACL_HEADER =>
									   Zend_Service_Amazon_S3::S3_ACL_PUBLIC_READ));
					
					$uploadedAttachments[] = array('filename' => $filename,
												   'url'      => $config->shipserv->services->enquiry->S3url.'/'.$filename,
												   'size'     => filesize($file));
				}
				
			break;
			
			case 'ftp':
				// create an FTP connection
				try
				{
					$ftpConfig = $config->services->ftp;
					
					if (!$ftpConn = ftp_connect($ftpConfig->host))
					{
						throw new Myshipserv_Enquiry_Exception("Could not connect to FTP server");
					}
					
					if (!ftp_login($ftpConn, $ftpConfig->username, $ftpConfig->password))
					{
						throw new Myshipserv_Enquiry_Exception("Could not login to FTP server");
					}
					
					$ftpDir = $ftpConfig->directory . $userId;
					
					// check the user directory exists
					if (!@ftp_chdir($ftpConn, $ftpDir))
					{
						// it doesn't exist, so change to the main directory
						if (!ftp_chdir($ftpConn, $ftpConfig->directory))
						{
							throw new Myshipserv_Enquiry_Exception("Unable to change to attachments directory");
						}
						
						// and create the user directory
						if (!ftp_mkdir($ftpConn, $userId))
						{
							throw new Myshipserv_Enquiry_Exception("Unable to make user directory");
						}
						
						ftp_chmod($ftpConn, 0777, $userId);
						
						// try changing directory again, and throw an exception if it still doesn't work
						if (!ftp_chdir($ftpConn, $userId))
						{
							throw new Myshipserv_Enquiry_Exception("Still unable to change to attachments directory");
						}
					}
					
					ftp_pasv($ftpConn, true);
					
					// now try putting the files
					foreach ($attachments as $file)
					{
						$filenameArray = array_reverse(explode('/', $file));
						$filename      = $filenameArray[0];
						
						$special_chars = array("?", "%", "[", "]", "/", "\\", "=", "<", ">", ":", ";", ",", "'", "\"", "&", "$", "#", "*", "(", ")", "|", "~", "`", "!", "{", "}");
						$filename = str_replace($special_chars, '', $filename);
						
						$url = 'ftp://'.$ftpConfig->host.'/'.$ftpDir.'/'.$filename;
						
						if (!ftp_put($ftpConn, $filename, $file, FTP_BINARY))
						{
							throw new Myshipserv_Enquiry_Exception("Unable to upload file");
						}
						
						$uploadedAttachments[] = array('filename' => $filename,
													   'url'      => $url,
													   'size'     => filesize($file));
					}
				}
				catch (Exception $e)
				{
					throw $e; // rethrow the exception for the controller to handle
				}
				
			break;
		}
		
		return $uploadedAttachments;
	}
}

class Myshipserv_Enquiry_Exception extends Exception { }