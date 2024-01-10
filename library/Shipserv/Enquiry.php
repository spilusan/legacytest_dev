<?php

/**
 * @package ShipServ
 * @author Elvir <eleonard@shipserv.com>
 * @copyright Copyright (c) 2011, ShipServ
 */
class Shipserv_Enquiry extends Shipserv_Object
{
	public $id;
	public $name;
	
	const IS_READ = 'read';
	const IS_DECLINED = 'declined';
	const IS_IGNORED = 'ignored';
	const IS_REPLIED = 'replied';
	
	const IS_READ_AS_WORD 		= 'Details viewed';
	const IS_DECLINED_AS_WORD 	= 'Not interested';
	const IS_IGNORED_AS_WORD 	= 'Not clicked';
	const IS_REPLIED_AS_WORD 	= 'Replied';
	
	
	public function __construct ($data)
	{
		// populate the supplier object
		if (is_array($data))
		{
			foreach ($data as $name => $value)
			{
				$this->{$name} = $value;
			}
		}
		
		if( $this->pirCreationDateFull != null )
		$this->pirCreationDate = Shipserv_Oracle_Util_DbTime::parseDbTime($this->pirCreationDateFull)->format("n/d/y H:i");
	}
	
	private function createObjectFromDb( $data )
	{
		$object = new self($data);
		$object->pinStatus = $object->getStatus(true);
		return $object;
	}
	
	public static function getInstanceById( $id )
	{
		$row = self::getDao()->fetchById( $id );
		$data = parent::camelCase($row[0]);
		return self::createObjectFromDb($data);
	}
	
	public static function getInstanceByIdAndTnid( $id, $tnid)
	{
		$row = self::getDao()->fetchByEnquiryIdAndTnid( $id, $tnid );
		$data = parent::camelCase($row[0]);
		return self::createObjectFromDb($data);
	}
	
	public static function getInstanceByEnquiryRecipientId( $id )
	{
		$row = self::getDao()->fetchByEnquiryRecipientId( $id, $tnid );
		$data = parent::camelCase($row[0]);
		return self::createObjectFromDb($data);
	}
	
	public function getAttachments()
	{
		return self::_getAttachments($this->pinId);
	}
	
	public static function _getAttachments( $enquiryId )
	{
        $hostname = self::getHostname($_SERVER['HTTP_HOST']);
		$url = "http://" . $hostname . "/ShipServ/pages/enquiry/";

		$rows = self::getDao()->getAttachments($enquiryId);
        $attachments = self::mapAttachmentFields($rows, $url);

		return $attachments;
	}

    /**
     * As attachments can be returned by two classes - Shipserv_Enquiry and Shipserv_Rfq, table field mapping has been
     * isolated into a separate function
     *
     * @author  Yuriy Akopov
     * @date    2013-11-01
     *
     * @param   array   $rows
     * @param   string  $url
     *
     * @return  array
     */
    public static function mapAttachmentFields(array $rows, $url) {
        $attachments = array();

        foreach ($rows as $row) {
            $attachments[] = array(
                'urlFilename'   => $row['ATF_FILENAME'],
                'filename'      => $row['ATF_ORIG_FILENAME'],
                'type'          => $row['ATF_FILETYPE'],
                'url'           => $url . $row['ATF_CREATED_BY'] . '/' . $row['ATF_FILENAME'],
                'size'          => $row['ATF_FILESIZE']
            );
        }

        return $attachments;
    }
	
	public static function getMyshipservEnquiryInstanceById( $id, $tnid, $securityHash, $skipService = false )
	{
		if( $skipService === false )
		{
			$enquiry = Myshipserv_Enquiry::fetch ($id, $tnid, $securityHash);
			return $enquiry;
		}
		else 
		{
			$row = self::getDao()->fetchByEnquiryIdAndTnid( $id, $tnid );
			$data = parent::camelCase($row[0]);
			$object = self::createObjectFromDb($data);
			
			$user = Shipserv_User::getInstanceById( $object->pinUsrUserCode);
			/*
$username, $userId, $senderName, $senderCompany,
								 $senderEmail, $senderPhone, $senderCountry, $enquiryText, $subject,
								 $vesselName, $imo, $deliveryLocation, $deliveryDate,
								 $searchRecId, $getProfileId, array $attachments,
								 array $recipients
								 */			
			$enquiry = new Myshipserv_Enquiry ($user->email,
								 $user->userId,
								 $object->pinName,
								 $object->pinCompany,
								 $object->pinEmail,
								 $object->pinPhone,
								 $object->pinCountry,
								 $object->pinInquiryText,
								 $object->pinSubject,
								 $object->pinVesselName,
								 $object->pinImo,
								 $object->pinDeliveryLocation,
								 $object->pinDeliveryDate,
								 null,
								 null,
								 (array)self::_getAttachments($object->pinId),
								 array());
			return $enquiry;					 
				
		}					 
	}
	/**
	public static function getMyshipservEnquiryInstanceById( $id, $tnid )
	{
		$row = self::getDao()->fetchByEnquiryIdAndTnid( $id, $tnid );
		$data = parent::camelCase($row[0]);
		$object = self::createObjectFromDb($data);
		
		$user = Shipserv_User::getInstanceById( $object->pinUsrUserCode);
		$enquiry = new Myshipserv_Enquiry ($user->email,
							 $user->userId,
							 $object->pinName,
							 $object->pinCompany,
							 $object->pinEmail,
							 $object->pinPhone,
							 $object->pinCountry,
							 $object->pinInquiryText,
							 $object->pinSubject,
							 $object->pinVesselName,
							 $object->pinImo,
							 $object->pinDeliveryLocation,
							 $object->pinDeliveryDate,
							 null,
							 null,
							 array(),
							 array());
		return $enquiry;					 
	}

	 */
	public function getInstanceByTnid( $tnid, $start = null, $total = null, &$totalFound = null, $period = null)
	{
		$rows = self::getDao()->fetchByTnid( $tnid, $start, $total, $totalFound, $period );
		foreach($rows as $row )
		{
			$row = parent::camelCase($row);
			$enquiries[] = self::createObjectFromDb($row);
		}
		return $enquiries;
	}
	
	/**
	 * Get the database access object
     *
	 * @return Shipserv_Oracle_Enquiry
	 */
	private function getDao( $what = null )
	{
		if( $what == null )
		{
			return new Shipserv_Oracle_Enquiry( self::getDb() );
		}
	}	
	
	public function getStatus( $asWord = false )
	{
		if( $this->pirIsDeclined == 1  ) 		return ($asWord)?self::IS_DECLINED_AS_WORD : self::IS_DECLINED;
		else if( $this->pirIsReplied == 1  ) 	return ($asWord)?self::IS_REPLIED_AS_WORD : self::IS_REPLIED;
		else if( $this->pirIsRead == 1 && $this->pirIsDeclined == "" ) 	return ($asWord)?self::IS_READ_AS_WORD : self::IS_READ;
		else if( $this->pirIsRead == "" && $this->pirIsDeclined == "" ) return ($asWord)?self::IS_IGNORED_AS_WORD : self::IS_IGNORED;
	}
	
	public function getUrl( $shipServUser = false )
	{
		if( $shipServUser === true )
		{
			$url = '/enquiry/internal-view/enquiryId/' . $this->pinId . '/supplierBranchId/' . $this->pirSpbBranchCode . '/hash/' . $this->pinHashKey;
		}
		else 
		{
			$url = '/enquiry/view/enquiryId/' . $this->pinId . '/supplierBranchId/' . $this->pirSpbBranchCode . '/hash/' . $this->pinHashKey;
		}
		return $url;
	}
	
	public function getAge()
	{
		
	}
	
	public function setRepliedDate()
	{
		return $this->getDao()->setRepliedDate($this->pirPinId, $this->pirSpbBranchCode, $this::getUser()->userId);
	}
	
	public function setDeclinedDate()
	{
		return $this->getDao()->setDeclinedDate($this->pirPinId, $this->pirSpbBranchCode, $this::getUser()->userId);
	}
	
	public function setViewedDate()
	{
		return $this->getDao()->setViewedDate($this->pirPinId, $this->pirSpbBranchCode, $this::getUser()->userId);
	}
	
	public function getUserWritingResponse()
	{
		if( $this->getStatus() == self::IS_REPLIED && $this->pirRepliedBy != "" )
		{
			// issue came up if user who reply isn't a pages user
			try
			{
				$user = Shipserv_User::getInstanceById( $this->pirRepliedBy );			
			}
			catch(Exception $e)
			{
				$user = Shipserv_User::getInstanceByTnUserId( $this->pirRepliedBy );
			}
			return $user;
		}
		else
		{
			return false;
		}
	}
	
	public function getRecipient()
	{
		return $this->getDao()->getRecipient( $this->pinId );
	}
	
	public function toArray( $requiredData = array() )
	{
		$thisObjectAsArray = get_object_vars($this);
		
		if( count($requiredData) == 0 )
		{
			return $thisObjectAsArray;
		}
		else
		{
			foreach($requiredData as $field)
			{
				$data[$field] = $thisObjectAsArray[$field];
			}
			return $data;;
		}
	}
	
	public function getRfqIdBySupplierTnid($tnid)
	{
		$sql = "SELECT PIR_RFQ_INTERNAL_REF_NO FROM pages_inquiry_recipient WHERE pir_pin_id=:enquiryId AND pir_spb_branch_code=:tnid";
		$result = $this->getDb()->fetchAll($sql, array("tnid" => $tnid, "enquiryId" => $this->pirPinId));
		return $result[0]['PIR_RFQ_INTERNAL_REF_NO'];
	}
	
	public function getUrlToReplyOnStartSupplier()
	{
		return Myshipserv_Config::getApplicationProtocol() . '://' . Myshipserv_Config::getApplicationHostName() . "/viewrfq?login=" . $this->pirSpbBranchCode . '&rfqrefno=' . $this->pirRfqInternalRefNo;
	}

    /**
     * Returns Pages user who has created an enquiry
     *
     * @author  Yuriy Akopov
     *
     * @return Shipserv_User
     * @throws Exception
     */
    public function getSenderUser() {
        if (strlen($this->pinUsrUserCode) === 0) {
            throw new Exception("No user recorded for this enquiry");
        }

        $user = Shipserv_User::getInstanceById($this->pinUsrUserCode);
        return $user;
    }
}
