<?php
/**
 * Supplier Object
 * 
 * @package ShipServ
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
class Shipserv_Supplier extends Shipserv_Object
{
    const
        TABLE_NAME  = 'SUPPLIER_BRANCH',

        COL_ID          = 'SPB_BRANCH_CODE',
        COL_NAME        = 'SPB_NAME',
        COL_ORG_ID      = 'SPB_SUP_ORG_CODE',
        COL_STATUS      = 'SPB_STS',
        COL_DIR_ENTRY   = 'DIRECTORY_ENTRY_STATUS',
        COL_DELETED     = 'SPB_ACCOUNT_DELETED',
        COL_TEST        = 'SPB_TEST_ACCOUNT',
        COL_MATCH_EXCLUDE = 'SPB_MATCH_EXCLUDE',
	    COL_POM_BUYER   = 'SPB_BYB_BRANCH_CODE',
	    COL_COUNTRY     = 'SPB_COUNTRY',

	    COL_PROMOTION_CHECK_QUOTES = 'SPB_PROMOTION_CHECK_QUOTES',
		COL_PROMOTION_MAX_QUOTES   = 'SPB_PROMOTION_MAX_QUOTES',
		COL_PROMOTION_ONLY_TARGETS = 'SPB_PROMOTION_ONLY_TARGETS'
    ;

    const
        DIR_STATUS_PUBLISHED = 'PUBLISHED',
        DIR_STATUS_WITHDRAWN = 'WITHDRAWN',
        DIR_STATUS_PENDING   = 'PENDING',
        DIR_STATUS_REQUESTED = 'REQUESTED',
        DIR_STATUS_HIDDEN    = 'HIDDEN',
        DIR_STATUS_APPROVED  = 'APPROVED'
    ;

    const
        // cache keys used by get*Count() calculations
        CACHE_KEY_COUNT_ALL     = 'all.',
        CACHE_KEY_COUNT_PROXY   = 'proxy.',
        CACHE_KEY_COUNT_NOPROXY = 'noProxy.',
        CACHE_KEY_COUNT_DATE_QUOTE  = 'dateQuote.',
        CACHE_KEY_COUNT_DATE_RFQ    = 'dateRfq.'
    ;

	const
		PRODUCT_START_SUPPLIER = 'STARTSUPPLIER',
		PRODUCT_SMART_SUPPLIER = 'SmartSupplier'
	;

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
	}

	public static function getInstanceById($id, $db = "", $skipCheck = false, $useMemcache = true)
	{
		return self::fetch($id, $db, $skipCheck, $useMemcache);
	}
	
	public static function getInstanceByIdWithNormalisationCheck($tnid, $db ="", $skipCheck = false)
	{
		$db = parent::getDb();
		$sql = "SELECT PSN_NORM_SPB_BRANCH_CODE FROM PAGES_SPB_NORM WHERE PSN_SPB_BRANCH_CODE=:tnid";
		$r = $db->fetchAll($sql, array('tnid' => $tnid));
		$normaliseToTnid = $r[0]['PSN_NORM_SPB_BRANCH_CODE'];
		
		if ($normaliseToTnid === null )
		{
			$object =  self::getInstanceById($tnid, $db, $skipCheck);
			return $object;
		}
		else
		{
			return self::getInstanceByIdWithNormalisationCheck($normaliseToTnid, $db, $skipCheck);
		}
		
	}
	
	public static function getTestSuppliersOnValueBasedPricing()
	{
		$ids = array( 217686, 217109, 217595, 80347, 87829, 80467, 51612,
					  213351, 69331, 52159, 51060, 52715, 67046, 59980, 68854, 59960, 66824, 61116,
					  73360, 58341, 72619);
		
		foreach($ids as $id)
		{
			$supplier = self::getInstanceById($id, "", true);
			if( $supplier->tnid != "" )
				$suppliers[] = $supplier;
		}
		
		return $suppliers;
	}
	
	public static function getSuppliersOnValueBasedPricing()
	{
		$db = parent::getDb();
		$sql = "SELECT DISTINCT vst_spb_branch_code TNID FROM VBP_TRANSITION_DATE";
		$rows = $db->fetchAll($sql);
		foreach((array)$rows as $row )
		{
			$supplier = self::getInstanceById($row['TNID'], "", true);
			if( $supplier->tnid != "" )
			$suppliers[] = $supplier;
		}
		return $suppliers;
	}
	
	/**
	 * Get report for monthly billing report
	 *
	 * @param string    $month
	 * @param string    $year
	 *
	 * @return  array
	 */
	public function getValueBasedEventForBillingReport($month, $year) {
		$report = Shipserv_Report_MonthlyBillingReport::getInstance($this, $month, $year, "summary");
		return $report->toArray();
	}
	
	public function getGMVBreakdownForBillingReport($month, $year)
	{
		$report = Shipserv_Report_MonthlyBillingReport::getInstance($this, $month, $year, "gmv");
		return $report->toArray();
	}
    
	public function getGMVReport($dateFrom, $dateTo)
	{
		$report = Shipserv_Report_GmvReport::getInstance($this, $dateFrom, $dateTo);
		return $report;
	}
	
	
    /**
	 * Static method to create and fetch a supplier
	 * 
	 * @access public
	 * @static
	 * @param int $tnid The TradeNet ID of the supplier to fetch
	 * @param object $db A database resource object
	 * @param boolean $skipCheck will skip checking the status of the listing (published or not)
	 * @return Shipserv_Supplier
	 */
	public static function fetch ($tnid, $db = "", $skipCheck = false, $useMemcache = true)
	{
		if( $db == "" ) {
            $tmp = new self(null);
            $db = $tmp->getDb();
        }
		
		// create a profile adapter
        $profile = new Shipserv_Oracle_Profile($db);

        // fetch the supplier profile
        $data = $profile->fetch($tnid, $useMemcache, 86400, $skipCheck);

        $data = self::camelCaseData($data);

		$supplier = new self($data);
		
		$supplier->db = $db;
		
		return $supplier;
	}
	
	/**
	 * Get address of a supplier
     *
     * Refactored by Yuriy Akopov on 2014-06-11, S10311
     *
	 * @param   bool    $singleLine
     * @param   bool    $addressOnly
     * @param   bool    $htmlEncode
     *
     * @returns array|string
	 */
	public function getAddress($singleLine = true, $addressOnly = false, $forHtml = true)
	{
        $address = array(
            'Line 1'    => $this->address1,
            'Line 2'    => $this->address2,
            'City'      => $this->city,
            'State'     => $this->state,
            'Postcode'  => $this->zipCode,
            'Country'   => $this->countryName
        );

        if (!$addressOnly) {
            $address = array_merge($address, array(
                'Phone' => $this->phoneNo,
                'Fax'   => $this->faxNo,
                'Email' => $this->email,
                'URL'   => $this->homePageUrl
            ));
        }

        foreach ($address as $label => $value) {
            if (strlen(trim($value)) === 0) {
                unset($address[$label]);
            }

            // labels are added to two fields for legacy behaviour
            if (in_array($label, array('Phone', 'Fax'))) {
                $address[$label] = $label . ': ' . $value;
            }

            if ($forHtml) {
                switch ($label) {
                    case 'Email':
                        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $address[$label] = '<a href="mailto:' . $value . '">' . $value . '</a>';
                        } else {
                            $address[$label] = htmlentities($value);
                        }
                        break;

                    case 'URL':
                        if (filter_var($value, FILTER_VALIDATE_URL)) {
                            $address[$label] = '<a href="' . $value . '" target="_blank">' . $value . '</a>';
                        } else {
                            $address[$label] = htmlentities($value);
                        }
                        break;

                    default:
                        $address[$label] = htmlentities($address[$label] ?? '');
                }
            }
        }
		
		if ($singleLine) {
            return implode(', ', $address);
		}

        return $address;
	}
	
	/**
	 * Static method to fetch all unverified suppliers (suppliers that haven't verified their listing in 12 months)
	 * 
	 * @param object $db database resource object
	 * @param bool $asObject if object needed as the returned data
	 * @param int $total number of supplier will be fetched
	 */
	public static function fetchUnverified( $db, $asObject = true, $total = "" )
	{
		$i = 1;
		$adapter = new Shipserv_Oracle_Suppliers( $db );	
		foreach( $adapter->fetchUnverifiedSupplierIds( $total ) as $row )
		{
			if( $asObject == true )
				$suppliers[] = self::fetch( $row["SPB_BRANCH_CODE"], $db );
			else 
				$suppliers[] = $row["SPB_BRANCH_CODE"];
		}
		return $suppliers;
	}
	
	/**
	 * Fetches the competitors for the current supplier
	 * 
	 * @access public
	 * @param int $count The number of competitors to fetch
	 * @param boolean $instantiate Set to TRUE to return an array of Shipserv_Supplier objects, rather than just TNIDs
	 * @return array
	 */
	public function fetchCompetitors ($count = 3, $instantiateSuppliers = false)
	{
		$competitorList = new Shipserv_Supplier_Competitorlist($this->tnid, $this->db);
		$this->competitors = $competitorList->fetchOrdered($count);
		
		if ($instantiateSuppliers)
		{
			$objArray = array();
			foreach ($this->competitors as $tnid)
			{
				$objArray[] = self::fetch($tnid, $this->db);
			}
			
			return $objArray;
		}
		else
		{
			return $this->competitors;
		}
	}

	private static function camelCaseData($data)
	{
        $camelCaseRules = array(
            "orgCode"	                => "ORGCODE",
            "tnid"	                    => "TNID",
            "name"	                    => "NAME",
            "description"	            => "DESCRIPTION",
            "address1"	                => "ADDRESS1",
            "address2"	                => "ADDRESS2",
            "city"	                    => "CITY",
            "state"	                    => "STATE",
            "zipCode"	                => "ZIPCODE",
            "countryCode"	            => "COUNTRYCODE",
            "countryName"	            => "COUNTRYNAME",
            "countryRestricted"         => "COUNTRYRESTRICTED",
        	"accountRegion"	            => "ACCOUNTREGION",
            "phoneNo"	                => "PHONENO",
            "faxNo"	                    => "FAXNO",
            "afterHoursNo"	            => "AFTERHOURSNO",
            "email"	                    => "EMAIL",
            "publicEmail"	            => "PUBLICEMAIL",
            "logoUrl"	                => "LOGOURL",
            "homePageUrl"	            => "HOMEPAGEURL",
            "publicTnid"	            => "PUBLICTNID",
            "lastTranDate"	            => "LASTTRANDATE",
            "smartSupplier"         	=> "SMARTSUPPLIER",
            "expertSupplier"	        => "EXPERTSUPPLIER",
            "premiumListing"	        => "PREMIUMLISTING",
            "impaMember"            	=> "IMPAMEMBER",
            "issaMember"	            => "ISSAMEMBER",
            "tradeNetMember"	        => "TRADENETMEMBER",
            "wholeSale"	                => "WHOLESALE",
            "tradeRank"	                => "TRADERANK",
            "onlineCatalogue"	        => "ONLINECATALOGUE",
            "accessCode"	            => "ACCESSCODE",
            "latitude"	                => "LATITUDE",
            "longitude"	                => "LONGITUDE",
            "isVerified"	            => "ISVERIFIED",
            "joinedDate"	            => "JOINEDDATE",
            "svrAccess"	                => "SVRACCESS",
            "integratedOnTradeNet"	    => "INTEGRATEDONTRADENET",
            "directoryStatus"	        => "DIRECTORYSTATUS",
            "profileCompletionScore"    => "PROFILE_COMPLETION_SCORE",
            "accountManagerName"	    => "ACCOUNTMANAGERNAME",
            "accountManagerEmail"	    => "ACCOUNTMANAGEREMAIL",
        	"accountIsDeleted"			=> "ACCOUNTISDELETED",
            "globalDelivery"	        => "GLOBAL_DELIVERY",
            "accountManagerSupportHrs"  => "ACCOUNTMANAGERSUPPORTHRS",
            "contactPerson"             => 'CONTACTPERSON',
            'monetisationPercent'       => 'MONETISATIONPERCENT',
            "isPublished"		        => "IS_PUBLISHED",
        	"onsiteInspected"		    => "ONSITEINSPECTED",
        	"onsiteVerRptUrl"			=> "ONSITEVERRPTURL"
        );

        $returnArray = array();
        foreach($camelCaseRules as $new => $orig) {
            if (array_key_exists($orig, $data)) {   // to avoid strict standards notice
                $value = $data[$orig];
            } else {
                $value = null;
            }

            $returnArray[$new] = $value;
        }

		$returnArray = array_merge($returnArray, array(
            "tradeNetStatus" => ((isset($data['TRADENETSTATUS']) && $data['TRADENETSTATUS'] == "ACT") ? true : false),
            'matchexclude'   => isset($data['MATCHEXCLUDE']) ? (bool) $data['MATCHEXCLUDE'] : false,
            "brands"         => array (),
			"ownedBrands"    => array (),
			"attachments"    => array (),
			"maAttachments"  => array (),
			"categories"     => array (),
			"contacts"       => array (),
			"ports"          => array (),
			"memberships"    => array (),
			"certifications" => array (),
			"catalogues"     => array (),
			"videos"         => array ()
		));
		
		$brands = $data["brands"] ?? [];
		foreach ($brands as $arr)
		{
			$returnArray["brands"][] = array (
				"id" => $arr["PCB_BRAND_ID"],
				"authLevel" => $arr["PCB_AUTH_LEVEL"],
				"isAuthorised" => $arr["PCB_IS_AUTHORISED"],
				"name" => $arr["NAME"],
				"ownersCount" => $arr["OWNERSCOUNT"],
				"browsePageName" => $arr["BROWSE_PAGE_NAME"],
				"logoFileName" => $arr["LOGO_FILENAME"]
			);
		}
		$ownedBrands = $data["ownedBrands"] ?? [];
		foreach ($ownedBrands as $arr)
		{
			$returnArray["ownedBrands"][] = array (
				"id" => $arr["PCB_BRAND_ID"],
				"authLevel" => $arr["PCB_AUTH_LEVEL"],
				"isAuthorised" => $arr["PCB_IS_AUTHORISED"],
				"name" => $arr["NAME"],
				"ownersCount" => $arr["OWNERSCOUNT"],
				"browsePageName" => $arr["BROWSE_PAGE_NAME"],
				"logoFileName" => $arr["LOGO_FILENAME"]
			);
		}

		$attachments = $data["attachments"] ?? [];
		foreach ($attachments as $arr)
		{
			$returnArray["attachments"][] = array (
				"id" => $arr["ID"],
				"name" => $arr["NAME"],
				"description" => $arr["DESCRIPTION"],
				"url" => $arr["URL"]
			);
		}

		$maAttachments = $data["maAttachments"] ?? [];
		foreach ($maAttachments as $arr)
		{
			$returnArray["maAttachments"][] = array (
				"id" => $arr["ID"],
				"name" => $arr["NAME"],
				"description" => $arr["DESCRIPTION"],
				"refNo" => $arr["REFNO"],
				"url" => $arr["URL"],
				"thumbnailUrl" => $arr["THUMBNAILURL"]
			);
		}

		$categories = $data["categories"] ?? [];
		foreach ($categories as $arr)
		{
			$returnArray["categories"][] = array (
				"id" => $arr["ID"],
				"name" => $arr["NAME"],
				"primary" => $arr["PRIMARY"],
				"ownersCount" => $arr["OWNERSCOUNT"],
				"browsePageName" => $arr['BROWSE_PAGE_NAME']
			);
		}

		$contacts = $data["contacts"] ?? [];
		foreach ($contacts as $arr)
		{
			$returnArray["contacts"][] = array (
				"id" => $arr["ID"],
				"firstName" => $arr["FIRSTNAME"],
				"middleName" => $arr["MIDDLENAME"],
				"lastName" => $arr["LASTNAME"],
				"nameTitle" => $arr["NAMETITLE"],
				"jobTitle" => $arr["JOBTITLE"],
				"phoneNo" => $arr["PHONENO"],
				"mobileNo" => $arr["MOBILENO"],
				"skypeName" => $arr["SKYPENAME"],
				"status" => $arr["STATUS"],
				"emailAddress" => $arr["EMAILADDRESS"],
				"department" => $arr["DEPARTMENT"]
			);
		}

		$ports = $data["ports"] ?? [];
		foreach ($ports as $arr)
		{
			$returnArray["ports"][] = array (
				"code" => $arr["CODE"],
				"name" => $arr["NAME"],
				"countryCode" => $arr["COUNTRYCODE"],
				"primary"	=> $arr["PRIMARY"],
                "portRestricted" => $arr["PORTRESTRICTED"],
                "countryRestricted" => $arr["COUNTRYRESTRICTED"],
                "isRestricted"  => $arr["ISRESTRICTED"]
			);
		}

		$memberships = $data["memberships"] ?? [];
		foreach ($memberships as $arr)
		{
			$returnArray["memberships"][] = array (
				"id" => $arr["ID"],
				"name" => $arr["NAME"],
				"is_authorised"=> $arr["SM_IS_AUTHORISED"],
				"ownersCount" => $arr["OWNERSCOUNT"],
				"logoFileName" => $arr["QO_LOGO_PATH"]
			);
		}
		$certifications = $data["certifications"] ?? [];
		foreach ($certifications as $arr)
		{
			$returnArray["certifications"][] = array (
				"id" => $arr["ID"],
				"name" => $arr["NAME"],
				"is_authorised"=> $arr["SC_IS_AUTHORISED"],
				"logoFileName" => $arr["CO_LOGO_PATH"]
			);
		}

		$catalogues = $data["catalogues"] ?? [];
		foreach ($catalogues as $arr)
		{
			$returnArray["catalogues"][] = array (
				"id" => $arr["ID"],
				"name" => $arr["NAME"],
				"subfoldersFound"	=> $arr["SUBFOLDERSFOUND"]
			);
		}
		$videos = $data["videos"] ?? [];
		foreach ($videos as $arr)
		{
			$returnArray["videos"][] = array (
				"id" => $arr["ID"],
				"name" => $arr["NAME"],
				"description"	=> $arr["DESCRIPTION"],
				"clipKey"	=> $arr["CLIPKEY"]
			);
		}

		return $returnArray;
	}
	
	/**
	 * Get url of supplier on pages
	 * @return string url
	 */
	public function getUrl($section = null)
	{
		return self::createUrl($this->name, $this->tnid, $section);
	}
	
	public static function createUrl($name, $tnid, $section = null)
	{
		$name = preg_replace('/[^a-zA-Z\d\s:]/', "", $name);
		
		if( $section === null || $section == 'profile')
		{
			$section = 'supplier/profile';
		}
		else if( $section == 'review' )
		{
			$section = 'reviews/supplier';
		}
		
		return 'https://' . $_SERVER['HTTP_HOST'] . '/' . $section . '/s/' . preg_replace('/(\W){1,}/', '-', strtolower($name)) . '-' . $tnid;
	}
	
	/**
	 * Get edit listing url for pages listing
     *
     * Changed by Yuriy Akopov on 2014-10-03 replacing hardcoded URLs with the config values
	 */
	public function getEditUrl() {
        $url = Myshipserv_Config::getPagesAdminListingsUrl();
        $url .= '?' . http_build_query(array(
                'accessCode' => (string) $this->accessCode
            ));

        return $url;
	}
	
	/**
	 * This will make pages listing to be verified
	 */
	public function updateDirectoryListingDate()
	{
		$adapter = new Shipserv_Oracle_Suppliers( $this->db);
		$adapter->updateDirectoryListingDate( $this->tnid );
	}
	
	/**
	 * 
	 */
	public function updateEmailReminderSentDate()
	{
		$adapter = new Shipserv_Oracle_Suppliers( $this->db);
		$adapter->updateEmailReminderSentDate( $this->tnid );
	}
	
	/**
	 * Remove the memcache related for this supplier
     *
     * Rewritten by Yuriy Akopov on 2013-08-07
	 */
	public function purgeMemcache()
	{
        $keys = array(
            // legacy keys existed before decoration rules were refactored and used
            '' => array(
                'PROFILEFOR_',
                'PROFILETNID_',
                'BRANDSFOR_'
            ),
            // added by Yuriy Akopov on 2013-08-07
            'getRfqCount'   => array(
                self::CACHE_KEY_COUNT_ALL,
                self::CACHE_KEY_COUNT_PROXY,
                self::CACHE_KEY_COUNT_NOPROXY,
            ),
            'getDeclineCount' => array(
                self::CACHE_KEY_COUNT_ALL,
                self::CACHE_KEY_COUNT_PROXY,
                self::CACHE_KEY_COUNT_NOPROXY,
            ),
            'getQuoteCount'   => array(
                self::CACHE_KEY_COUNT_ALL,
                self::CACHE_KEY_COUNT_PROXY,
                self::CACHE_KEY_COUNT_NOPROXY,

                self::CACHE_KEY_COUNT_ALL       . self::CACHE_KEY_COUNT_DATE_QUOTE,
                self::CACHE_KEY_COUNT_PROXY     . self::CACHE_KEY_COUNT_DATE_QUOTE,
                self::CACHE_KEY_COUNT_NOPROXY   . self::CACHE_KEY_COUNT_DATE_QUOTE,

                self::CACHE_KEY_COUNT_ALL       . self::CACHE_KEY_COUNT_DATE_RFQ,
                self::CACHE_KEY_COUNT_PROXY     . self::CACHE_KEY_COUNT_DATE_RFQ,
                self::CACHE_KEY_COUNT_NOPROXY   . self::CACHE_KEY_COUNT_DATE_RFQ
            ),
            'getOrderCount' => array(
                ''
            )
        );

        foreach ($keys as $method => $methodKeys) {
            foreach ($methodKeys as $key) {
                $key .= $this->tnid;

                if (strlen($method)) {
                    $this->memcachePurge(__CLASS__, $method, $key);    // key fully decorated
                } else {
                    $this->memcachePurge(null, null, $key); // the only key decoration enforced is INI file defined prefix and suffix
                }
            }
        }
	}
	
	/**
	 * Get supplier's profile complete score
	 * @param int $tnid
	 */
	public function getProfileCompletionScore()
	{
		$profileChecker = new Myshipserv_SupplierListing( $this->tnid, $this->getDb());
		$score = $profileChecker->getCompletenessAsPercentage();
		
		// update db with the latest score if it's different 
		if( (int) $score != (int) $this->profileCompletionScore )
		{
			
			try{
				$sql = "UPDATE supplier_branch SET SPB_PCS_SCORE=:score WHERE spb_branch_code=:tnid";
				$this->getDb()->query( $sql, array('score' => $score, 'tnid' => $this->tnid ) );
			}
			catch(Exception $e)
			{}
			
			
			// purge cache
			$this->purgeMemcache();
		}
		return $score;
	}
	
	/**
	 * Pull account manager of this supplier
	 * @param boolean $detail -- only pull specific info eg: phone, name
	 */
	public function getAccountManager( $detail = null )
	{
		// FIELDSALES' detail 
		$fieldSales['ishikawa@marine-net.com'] 		= array('name' => 'Atsushi Ishikawa', 'phone' => '+81 (3) 5157 8757');
		$fieldSales['sgill@shipserv.com'] 			= array('name' => 'Sharon Gill', 'phone' => '+852 2501 9210');
		$fieldSales['bkwan@shipserv.com'] 			= array('name' => 'Bible Kwan', 'phone' => '+852 2501 9339');
		$fieldSales['afosseng@shipserv.com'] 		= array('name' => 'Ane Fosseng', 'phone' => '+45 3332 3120');
		$fieldSales['lbratshaug@shipserv.com'] 		= array('name' => 'Lars Bratshaug', 'phone' => '+45 3332 3120');
		$fieldSales['cjkerr@marinersannual.com'] 	= array('name' => 'Chris Kerr', 'phone' => '+1 215 862 3353');
		$fieldSales['parstorp@shipserv.com'] 		= array('name' => 'Peder Arstorp', 'phone' => '+1 732 738 6500');
		
		if( !empty($fieldSales[$this->accountManagerEmail]) )
		{
			$data['name'] = $fieldSales[$this->accountManagerEmail]['name'];
			$data['email'] = $this->accountManagerEmail;
			$data['phone'] = $fieldSales[$this->accountManagerEmail]['phone'];
		}
		else 
		{
			$data['name'] = $this->accountManagerName;
			$data['email'] = $this->accountManagerEmail;
			$data['phone'] = '+44 203 111 9700';
		}		
		
		if( $detail == null )
		{
			return $data;
		}
		else
		{
			return $data[$detail];
		}
	}
	
	/**
	 * Pull suppliers enquiry statistic for unread, read, declined, etc
	 * @param array $period with this format Array('start' => new DateTime, 'end' => new DateTime)
	 */
	public function getEnquiriesStatistic($period = null)
	{
		if( $period == null )
		{
			$now = new DateTime();
			$lastYear = new DateTime();
			
			$now->setDate(date('Y'), date('m'), date('d'));
			$lastYear->setDate(date('Y')-1, date('m'), date('d'));

			$period = array('start' => $lastYear, 'end' => $now );			
		}
		
		$adapter = new Shipserv_Oracle_Suppliers($this->getDb());
		$data = $adapter->getEnquiriesStatistic($this->tnid, $period);
		return parent::camelCase($data[0]);
	}
	
	/**
	 * Calculate total transaction in a year
	 * @param unknown_type $numberOfYear
	 */
	public function getTotalTransactionInYear( $period = null )
	{
		if( $period === null )
		{
			$now = new DateTime();
			$lastYear = new DateTime();
			
			$now->setDate(date('Y'), date('m'), date('d'));
			$lastYear->setDate(date('Y')-5, date('m'), date('d'));

			$period = array('start' => $lastYear, 'end' => $now );
		}
		$adapter = new Shipserv_Oracle_Suppliers($this->getDb());
		$data = $adapter->getTotalTransaction($this->tnid, $period);
		
		return $data;
	}
	
	/**
	 * Pull the email of where the enquiry going to get sent
	 * @return String $email
	 */
	public function getEnquiryEmail()
	{
		if( $this->getConfig()->shipserv->enquiry->integrationToTradeNet == 1 && $this->integratedOnTradeNet != 'N' )
		{
			return $this->getRFQRecipient();
		}
		else
		{
			return ($this->publicEmail)?$this->publicEmail:$this->email;
		}
		/*
		// if integrated to TN is on
		if( $this->getConfig()->shipserv->enquiry->integrationToTradeNet == 1 && $this->integratedOnTradeNet != 'N' ) 
		{
			if( $this->publicTnid != $this->tnid )
			{
				$supplier = Shipserv_Supplier::fetch($this->publicTnid, "", true);
				
				if( $supplier->email != "" )
				{
					return $supplier->email;
				}
			}
			return $this->email;
		}
		return ($this->publicEmail)?$this->publicEmail:$this->email;
		
		*/
	}
	
	public function getRFQRecipient()
	{
		$sql = "
			SELECT
			  CASE
			    WHEN (select spb_email from supplier_branch where spb_branch_code =
			          	(select spb_public_branch_code from supplier_branch where spb_branch_code = SPB.spb_branch_code and spb_pgs_tn_int = 'Y' )
			          	and 
			          	spb_sts = 'ACT' 
			          	and spb_pgs_tn_int = 'Y') is not null
			      THEN (select spb_email from supplier_branch where spb_branch_code =
			          (select spb_public_branch_code from supplier_branch where spb_branch_code =  SPB.spb_branch_code))
			    WHEN (SPB_PGS_TN_INT = 'Y' AND SPB.SPB_STS = 'ACT') THEN SPB.SPB_EMAIL
			    ELSE SPB.PUBLIC_CONTACT_EMAIL 
			  END 
			  AS email                   
			FROM                          
				SUPPLIER_BRANCH SPB 
			WHERE 
				SPB.SPB_BRANCH_CODE  = :tnid
		";
		return $this->getDb()->fetchOne($sql, array('tnid' => $this->tnid));
	}
	
	/**
	 * Check if supplier pages profile is published (or not)
	 * @return boolean 
	 */
	public function isPublished()
	{
		$adapter = new Shipserv_Oracle_Suppliers($this->getDb());
		$data = $adapter->getPublishedStatus($this->tnid);
		return ($data['DIRECTORY_ENTRY_STATUS'] == "PUBLISHED") ? true: false;
	}
	
	/**
	* Check if tthe supplier is normalised
	* @return boolean
	*/
	public function isNormalised()
	{
		$db = $this->getDb();
		$sql = "SELECT PSN_NORM_SPB_BRANCH_CODE FROM PAGES_SPB_NORM WHERE PSN_SPB_BRANCH_CODE=:tnid";
		$r = $db->fetchAll($sql, array('tnid' => $this->tnid));
		$normaliseToTnid = $r[0]['PSN_NORM_SPB_BRANCH_CODE'];
		
		return  ($normaliseToTnid === null );

	}
	/**
	 * Static function used on view	 
	 * @param unknown_type $changes
	 * @param unknown_type $lineItemNo
	 * @param unknown_type $column
	 * @param unknown_type $returnBoolean
	 */
	public static function lineItemHasBeenChanged($changes, $lineItemNo, $column, $returnBoolean = false)
	{
		foreach( $changes as $change )
		{
			if( $change['RQLC_LINE_ITEM_NO'] == $lineItemNo )
			{
				if( $column == '' )
				{
					if( $change['RQLC_LINE_ITEM_STATUS'] == 'MOD')
					{
						if( $returnBoolean ) return true;
						return '<td style="font-family: Arial, Helvetica, sans-serif;font-size: 12px; font-weight: normal;border-bottom: 1px solid #c4ccd7;border-right: 1px solid #c4ccd7;padding: 9px;color: #008b00; text-align: center;">changed</td>';
					}
					else if( $change['RQLC_LINE_ITEM_STATUS'] == 'DEC')
					{
						if( $returnBoolean ) return false;
						return '<td style="font-family: Arial, Helvetica, sans-serif;font-size: 12px; font-weight: normal;border-bottom: 1px solid #c4ccd7;border-right: 1px solid #c4ccd7;padding: 9px;text-align: center; color: #e81e24; font-weight:bold;">declined</td>';
					}
					else if( $change['RQLC_LINE_ITEM_STATUS'] == 'NEW')
					{
						if( $returnBoolean ) return false;
						return '<td style="font-family: Arial, Helvetica, sans-serif;font-size: 12px;border-bottom: 1px solid #c4ccd7;border-right: 1px solid #c4ccd7;padding: 9px;text-align: center; color: #00386e; font-weight:bold;">new line</td>';
					}
					else
					{
						if( $returnBoolean ) return false;
						return '<td style="font-family: Arial, Helvetica, sans-serif;font-size: 12px; font-weight: normal;border-bottom: 1px solid #c4ccd7;border-right: 1px solid #c4ccd7;padding: 9px;">&nbsp;</td>';
					}
				}
				else 
				{
					if( $change[ $column ] != null )
					{
						if( $returnBoolean ) return true;
						return 'bgcolor="#83cc4e" style="background: #83cc4e; font-family: Arial, Helvetica, sans-serif;font-size: 12px;border-bottom: 1px solid #c4ccd7;border-right: 1px solid #c4ccd7;font-weight:bold; font-style:italic;color: #2e3235;"';
					}
					else 
					{
						if( $returnBoolean ) return false;
						return '';
					}
				}
			}
		}
	}
	
	public static function aasort (&$array, $key) 
	{
	    $sorter=array();
	    $ret=array();
	    reset($array);
	    foreach ($array as $ii => $va) {
	        $sorter[$ii]=$va[$key];
	    }
	    arsort($sorter);
	    foreach ($sorter as $ii => $va) {
	        $ret[$ii]=$array[$ii];
	    }
	    $array=$ret;
	}
	
	/**
	 * Pull account type of supplier for pages enquiry (eg: Postpay, prepay, premium or basic)
	 */
	public function getEnquiryAccountType()
	{
		$adapter = new Shipserv_Oracle_Suppliers($this->getDb());
		$result = $adapter->getEnquiryAccountType($this->tnid);
		return $result['PEA_ACCOUNT_TYPE'];
	}
	
	public function isPremium()
	{
		return ($this->premiumListing == 1)?true:false;
	}
	
	public function hasBrandVerification()
	{
		$products = $this->getProducts();
		
		return ($products->products['brandVerification']==1);		
	}
	
	public function hasEInvoicing()
	{
		$adapter = new Shipserv_Oracle_Suppliers($this->getDb());
		return $adapter->hasEInvoicing( $this->tnid );
	}
	
	public function getIntegrationType()
	{
		if( $this->hasExpertSupplier() ) return 'EXPERT_SUPPLIER';
		else if( $this->hasSmartSupplier() ) return 'SMART_SUPPLIER';
		else if( $this->hasStartSupplier() ) return 'START_SUPPLIER';
	}
	
	
	public function hasSmartSupplier()
	{
		$sql = "SELECT SPB_SMART_PRODUCT_NAME as DATA FROM supplier_branch WHERE spb_branch_code=:tnid";
		$result = $this->getDb()->fetchAll($sql, array("tnid" => $this->tnid));
		if( strstr($result[0]['DATA'], 'SmartSupplier')!==false ) return true;
		return false;	
	}
	
	public function hasStartSupplier()
	{
		$sql = "SELECT SPB_CONNECT_TYPE as DATA  FROM supplier_branch WHERE spb_branch_code=:tnid AND (SPB_SMART_PRODUCT_NAME!='SmartSupplier' OR SPB_SMART_PRODUCT_NAME IS null)";
		$result = $this->getDb()->fetchAll($sql, array("tnid" => $this->tnid));
		if( strstr($result[0]['DATA'], 'STARTSUPPLIER')!==false ) return true;
		return false;		
	}
	
	public function hasExpertSupplier()
	{
		if( $this->hasStartSupplier() == true ) return false;
		
		$sql = "select * from weblogic.branch_group_hierarchy where group_id = 111 and branch_id=:tnid ";
		$result = $this->getDb()->fetchAll($sql, array("tnid" => $this->tnid));
		if( count($result)>0 ) return true;
		return false;		
	}
	
	public function canAccessSVR()
	{
		// all supplier can now access SIR
		return true;
		
		/*
		if( $this->svrAccess == 'Y' || $this->svrAccess == '1')
		return true;
		else
		return false;
		*/
	}
	
	/*@Todo: finish this*/
	public function registerImpression($adUnitId, $searchKeyword, $searchLocation, $searchId)
	{
		if( $searchId === null ) return false;
		
		$browser = new Shipserv_Browser();
		$geodata = new Shipserv_Geodata();
		
		$sql = "SELECT PAI_ID FROM pages_ppc_impression WHERE pai_pad_id=:adUnitId AND pai_pst_id=:searchId AND pai_ip_address=:ipAddress";
		$data = $this->db->fetchAll($sql, array('adUnitId' => $adUnitId, 'searchId' => $searchId, 'ipAddress' => $browser->getIpAddress()));

		if( $data[0]['PAI_ID'] != ""  ) return $data[0]['PAI_ID'];
		
		$adapter = new Shipserv_Oracle_Suppliers( $db );
		$sql = "INSERT INTO 
					PAGES_PPC_IMPRESSION (PAI_PAD_ID, 	PAI_PST_ID,		PAI_DATE_TIME, 	PAI_IP_ADDRESS, PAI_BROWSER, 	PAI_PSU_ID, PAI_REFERRER, 	PAI_GEODATA, 	PAI_CNT_COUNTRY_CODE, 	PAI_SEARCH_KEYWORD, PAI_SEARCH_LOCATION)
					VALUES				 (:adUnitId, 	:searchId,		SYSDATE, 		:ipAddress, 	:browserName, 		:userId, 	:referrer, 		:geoData, 		:countryCode, 			:searchKeyword, 	:searchLocation)
		";

		
		$user = $this::getUser();
		
		$sqlData = array(
			"adUnitId" => $adUnitId,
			"ipAddress" => $browser->getIpAddress(),
			"browserName" => $browser->fetchName(),
			"userId" => $userId,
			"referrer" => $browser->getReferrer(),
			"geoData" => addslashes(serialize($geodata)),
			"countryCode" => $countryCode,
			"searchId" => $searchId,
			"searchKeyword" => $searchKeyword,
			"searchLocation" => $searchLocation
		);
		
		$this->db->query($sql,$sqlData);
		return $this->db->lastSequenceId('SQ_PAGES_PPC_IMP_ID');
	}
	
	public function getLastUpdatedProfile()
	{
		$adapter = new Shipserv_Oracle_Suppliers( $this->getDb() );
		return $adapter->getLastUpdatedProfile( $this->tnid );
	}
	public function registerClick($impressionId)
	{
		$adapter = new Shipserv_Oracle_Suppliers( $db );
		$sql = "INSERT INTO 
					PAGES_PPC_CLICK (PAC_PAI_ID, 	PAC_DATE_TIME)
					VALUES			(:impressionId, 	SYSDATE)
		";
		
		$user = $this->getUser();
		
		$sqlData = array(
			"impressionId" => $impressionId
		);
		
		$this->db->query($sql,$sqlData);
		
	}
	
	public function getUrlForAdUnit( $adUnitId, $impressionId)
	{
		return "/supplier/ppc-track?au=" . $adUnitId . "&i=" . $impressionId . "&u=" . urlencode( $this->getUrl() ) . "&sig=" . $this->getAdUnitHash($adUnitId, $impressionId);
		
	}
	
	public function getAdUnitHash($adUnitId, $impressionId)
	{
		$data[] = $adUnitId;
		$data[] = $impressionId;
		return md5( implode("-",$data ) );
	}
	
	public function getTradingHistory( $nonAnonymised = null )
	{
		// get endorsement info
		$db = $this->getDb();
		$profileDao = new Shipserv_Oracle_Profile($db);
		$endorsee = $profileDao->getSuppliersByIds(array($this->tnid));
		$endorseeInfo =  $endorsee[0];

		//retrieve list of endorsements for given supplier
		$endorsementsAdapter = new Shipserv_Oracle_Endorsements($db);
		$endorsements = $endorsementsAdapter->fetchEndorsementsByEndorsee($this->tnid,false);
		$endorseeIdsArray = array ();
		foreach ($endorsements as $endorsement)
		{
			$endorseeIdsArray[] = $endorsement["PE_ENDORSER_ID"];
		}

		if( $nonAnonymised === null )
		{
			return $endorsements;
		}
		
		$userEndorsementPrivacy = $endorsementsAdapter->showBuyers($this->tnid, $endorseeIdsArray);

		$output = array();
		
		foreach($endorsements as $e)
		{
			if( $nonAnonymised == true )
			{
				if ($userEndorsementPrivacy[$e["PE_ENDORSER_ID"]]===true )
				{
					$output[] = $e;
				}
			}
		}
		
		return $output;;
	}
	
	public function getProducts()
	{
		return Shipserv_ProductServices::getInstanceBySupplier( $this );
	}
	
	public function getActiveBanner()
	{
		$sql = "SELECT * FROM PAGES_ACTIVE_BANNER WHERE PAB_TNID=:tnid";
		foreach( $this->getDb()->fetchAll( $sql, array("tnid" => $this->tnid)) as $banner)
		{
			$banner['creativeData'] = json_decode( $banner['PAB_CREATIVE_DATA']);
			$data[] = $banner;
		}
		return $data;
	}
	
	public function hasValidPublicBranch()
	{
		if( $this->publicTnid != "" && $this->tnid != $this->publicTnid )
		{
			// please check SPB_PGS_TN_INT = 'Y'
			
			// check if public tnid is trading
			$sql = "SELECT spb_sts, SPB_PGS_TN_INT FROM supplier_branch WHERE spb_branch_code=:tnid";
			$res = $this->getDb()->fetchAll($sql, array('tnid' => $this->publicTnid ));
			if( isset($res[0]) && $res[0]['SPB_STS'] == 'ACT' && $res[0]['SPB_PGS_TN_INT'] == 'Y' )
			{
				return true;
			}
		}	
		return false;
			
	}
	
	public function getPublicTnid()
	{
		return $this->publicTnid;
	}
	
	public function getPublicBranch()
	{
		if( $this->hasValidPublicBranch() === true )
		{
			return Shipserv::fetch($this->publicTnid);
		}	

		return false;
	}
	
	public function toArray( $type = "" )
	{
		if( $type == "contact-detail" )
		{
			$supplier = $this;
			$data['tnid'] = $supplier->tnid;
			$data['name'] = $supplier->name;
			$data['address1'] = $supplier->address1;
			$data['address2'] = $supplier->address2;
			$data['city'] = $supplier->city;
			$data['postcode'] = $supplier->zipCode;
			$data['state'] = $supplier->state;
			$data['country'] = $supplier->countryName;
			$data['contactEmail'] = $supplier->email;
			$data['contactName'] = '';
			$data['contactPhone'] = $supplier->phoneNo;
			
			return $data;
		}
		else if( $type == "gmv" )
		{
			$supplier = $this;
			
			$data = array(
				'name' => $supplier->name,
				'tnid' => $supplier->tnid,
				'publicTnid' => $supplier->publicTnid,
				'countryName' => $supplier->countryName,
				'isPublished' => $supplier->isPublished(),
				'accountManager' => $supplier->accountManagerName,
				'accountManagerEmail' => $supplier->accountManagerEmail,
			);
				
			return $data;
		}
	}
		
	public function getGoogleTargetedSearch()
	{
		$sql = "SELECT * FROM pages_adunit_profile WHERE pap_tnid=:tnid";
		$res = $this->getDb()->fetchAll($sql, array('tnid' => $this->tnid));
		return $res;
	}
	
	public static function getGoogleTargetedSearchBySpotlightedSupplierTnid( $tnid ) 
	{
		$sql = "SELECT * FROM pages_adunit_serp WHERE pas_spotlighted_tnid=:tnid";
		$res = self::getDb()->fetchAll($sql, array('tnid' => $tnid));
		return $res;
	}

	/**
	 * VBP related functin to record spotlight on our db
	 */
	public static function logSpotlightImpression($obj, $tnid)
	{           
        try 
        {
            $username = (is_object($user)) ? $user->username : '';

            $ip = Myshipserv_Config::getUserIp();
			
	        $searchRecId = $analyticsAdapter->logSearch(
	        	$ip, 
	        	$_SERVER['HTTP_REFERER'], 
	        	$obj->_helper->getHelper('SearchSource')->getPlainKeyFromRequest(), 
	        	$username, 
	        	$refinedSearch->what, 
	        	$refinedSearch->type, 
	        	@$result['refinedQuery']['id'], // brandId (when type = brand)
                @$result['refinedQuery']['id'], // categoryId (when type = category)
                $_SERVER['REQUEST_URI'], // fullQuery - path + params
                $zone_param, 
                $refinedSearch->country, 
                $refinedSearch->port, 
                $result['documentsFound'], 
                $result['catalogueMatchesFound'], 
                $result['widenedSearch'],
                null,
                null,
                null,
                null
			);
			
            $cookieManager->setCookie('search', $searchRecId);
        } catch (Exception $e) { }
    }
    
    public function hasVBPContract()
    {
    	$sql = "SELECT COUNT(*) total FROM VBP_TRANSITION_DATE WHERE vst_spb_branch_code=:tnid AND vst_transition_date IS NOT null";
    	$result = self::getDb()->fetchAll($sql, array('tnid' => $this->tnid));
    	return ( $result[0]['TOTAL'] > 0 ); 
    }
    
    public function getVBPTransitionDate()
    {
    	$sql = "
    	    SELECT
    	        TO_CHAR(VST_TRANSITION_DATE, 'DD MM YYYY') VST_TRANSITION_DATE
    	    FROM
    	        VBP_TRANSITION_DATE
    	    WHERE
    	        vst_spb_branch_code=:tnid
    	        AND vst_transition_date IS NOT null";
    	$result = self::getDb()->fetchAll($sql, array('tnid' => $this->tnid));
    	$dateAsString = $result[0]['VST_TRANSITION_DATE'];
        
    	if( $dateAsString != "" )
    	{
    		$dateAsArr = explode(" ", $dateAsString);
    		 
    		$d = new DateTime();
    		$d->setDate($dateAsArr[2], $dateAsArr[1], $dateAsArr[0]);
    		 
    		return $d;
    	}
    	else
    	{
    		return null;
    	}
    }

    public function getContactDetail()
    {
        $data['contactPerson'] = $this->contacts;
        $data['companyCity'] = $this->city;
        $data['companyCountry'] = $this->countryName;
        $data['companyAddress'] = $this->address1;
        if($this->address2){
            $data['companyAddress'] .= " ".$this->address2;
        }
        if($this->state && $this->state != " ") {
            $data['state'] = $this->state;
        }
        $data['zipCode'] = $this->zipCode;
        $data['phone'] = $this->phoneNo;
        $data['fax'] = $this->faxNo;
        $data['url'] = $this->homePageUrl;
        if($this->afterHoursNo && $this->afterHoursNo != " ") {
            $data['afterHoursNo'] = $this->afterHoursNo;
        }

        return $data;
    }

    /**
     * Checks if supplier has an order from a given buyer
     *
     * @author  Yuriy Akopov
     * @date    2013-08-07
     * @story   TA16592
     *
     * @param   int $buyerId
     *
     * @return  bool
     */
    public function hasOrderFrom($buyerId) {
        // this is a quick query so it isn't cached (and caching by supplier won't help much anyway

        $db = Shipserv_Helper_Database::getSsreport2Db();

        $select = new Zend_Db_Select($db);
        $select->from(
            'ord',
            array('previous_orders' => '(1)')   // selecting constant for speed - result would be empty (0 rows) if there weren't previous queries
        )
            ->where('spb_branch_code = :tnid')
            ->where('byb_branch_code = :buyerid')
            ->where('ROWNUM = 1')
        ;

        $result = $db->fetchOne($select, array(
            'tnid'      => $this->tnid,
            'buyerid'   => $buyerId
        ));

        return ($result !== false);
    }

    /**
     * Returns the number of orders received by supplier in response to quotes it sent
     *
     * Number of orders
     *      Orders recieved by the current supplier
     *      Orders based on quotes issed in the specified date range
     *      Original orders only
     *
     * @author  Yuriy Akopov
     * @date    2013-08-07
     * @story   S7286
     *
     * @param   bool    $cacheOnly
     *
     * @return  int|null
     */
    public function getOrderCount($cacheOnly = false) {
        // building cacheKey
        // WARNING: if date limit is changed in Match Settings, cache should be invalidated!
        $key = $this->tnid;

        // check for the cached value and return it if it is available
        if (($count = $this->memcacheGet(__CLASS__, __METHOD__, $key)) !== false) {
            return (int) $count;
        }

        if ($cacheOnly) {
            return null;    // no cached value and we've been asked not to proceed with the real (slow) query
        }

        // this calculation always happens on a secondary database even if the object was created for a live one
        $db = Shipserv_Helper_Database::getSsreport2Db();
        $params = array('tnid'  => $this->tnid);

        $select = new Zend_Db_Select($db);
        $select->from(
            array('o' => 'ord'),
            array('count' => 'COUNT(DISTINCT o.ord_internal_ref_no)')
        )
            ->where('o.spb_branch_code = :tnid')    // order sent to the given supplier
            ->where('o.ord_count = 1')              // original orders only
        ;

        $qotJoinConstraints = array(
            'q.spb_branch_code = :tnid',
            'o.qot_internal_ref_no = q.qot_internal_ref_no'
        );

        $dateLimit = Shipserv_Match_Settings::get(Shipserv_Match_Settings::META_FIGURES_DEPTH);
        if (strlen($dateLimit)) {
            // orders based on quotes in $dateLimit last days
            $params['days'] = $dateLimit;
            $qotJoinConstraints[] ='q.qot_submitted_date >= TO_DATE(sysdate - :days)';
        }

        $select->join(
            array('q' => 'qot'),
            implode(' AND ', $qotJoinConstraints),
            array()
        );

        $count = $db->fetchOne($select, $params);

        // remember the calculated value in cache
        $this->memcacheSet(__CLASS__, __METHOD__, $key, $count);

        return (int) $count;
    }

    /**
     * Returns the number of quotes sent by supplier
     *
     * Number of quotes for quote rate and response rate figures:
     *      Total
     *          Sent by the given supplier
     *          Original quotes only
     *          Based on the RFQs which are:
     *              Sent to the given supplier
     *              Sent in the given date
     *      Match
     *          Same as above, but quotes should be sent to the match engine only
     *
     * Number of quotes for win rate:
     *      Same as for quote rate and response rate, only date range constraint is applied to quote's own range
     *
     * @author  Yuriy Akopov
     * @date    2013-08-07
     * @story   S7286
     *
     * @param   bool    $toMatch            null - all quotes, true - only those to the match engine, false - only non-match engine ones
     * @param   bool    $ownDateConstraint  true - date range is applied to quote's own date, false - to underlying RFQ's date
     * @param   bool    $cacheOnly
     *
     * @return  int|null
     */
    public function getQuoteCount($toMatch = null, $ownDateConstraint = false, $cacheOnly = false) {
        // building cacheKey
        // WARNING: if rules for key composing are changed don't forget to amend purgeCache() function accordingly!
        // WARNING: if date limit is changed in Match Settings, cache should be invalidated!
        if (!is_null($toMatch)) {
            if ($toMatch) {
                $key = self::CACHE_KEY_COUNT_PROXY;
            } else {
                $key = self::CACHE_KEY_COUNT_NOPROXY;
            }
        } else {
            $key = self::CACHE_KEY_COUNT_ALL;
        }

        if ($ownDateConstraint) {
            $key .= self::CACHE_KEY_COUNT_DATE_QUOTE;
        } else {
            $key .= self::CACHE_KEY_COUNT_DATE_RFQ;
        }

        $key .= $this->tnid;

        // check for the cached value and return it if it is available
        if (($count = $this->memcacheGet(__CLASS__, __METHOD__, $key)) !== false) {
            return (int) $count;
        }

        if ($cacheOnly) {
            return null;    // no cached value and we've been asked not to proceed with the real (slow) query
        }

        // this calculation always happens on a secondary database even if the object was created for a live one
        $db = Shipserv_Helper_Database::getSsreport2Db();
        $params = array('tnid' => $this->tnid);

        $select = new Zend_Db_Select($db);
        $select->from(
            array('q' => 'qot'),
            array('count' => 'COUNT(DISTINCT q.qot_internal_ref_no)')
        )
            ->join(
                array('r' => 'rfq'),
                'r.rfq_internal_ref_no = q.rfq_internal_ref_no ' .  // only quotes which base on RFQs
                'AND r.spb_branch_code = :tnid',                    // only RFQs sent to this supplier
                array()
            )
            ->where('q.qot_count = 1')              // original quotes only
            ->where('q.spb_branch_code = :tnid')    // only quotes sent by this supplier
        ;

        if (!is_null($toMatch)) {
            $params['proxyid'] = Shipserv_Match_Settings::get(Shipserv_Match_Settings::BUYER_PROXY_ID);

            if ($toMatch) {
                // only quotes sent to the match engine proxy buyer
                $select->where('q.byb_branch_code = :proxyid');
            } else {
                // only quotes sent to any buyer but to proxy one
                $select->where('q.byb_branch_code <> :proxyid');
            }
        }

        $dateLimit = Shipserv_Match_Settings::get(Shipserv_Match_Settings::META_FIGURES_DEPTH);
        if (strlen($dateLimit)) {
            $params['days'] = $dateLimit;
            if ($ownDateConstraint) {
                $select->where('q.qot_submitted_date >= TO_DATE(sysdate - :days)'); // all the quotes submitted in the date range
            } else {
                $select->where('r.rfq_submitted_date >= TO_DATE(sysdate - :days)'); // all the quotes with RFQs submitted in the date range
            }
        }

        $count = $db->fetchOne($select, $params);

        // remember the calculated value in cache
        $this->memcacheSet(__CLASS__, __METHOD__, $key, $count);

        return (int) $count;
    }

    /**
     * Returns the number of RFQs declined by supplier
     *
     *   Number of declines
     *      Total
     *          RFQ declined sent to the given supplier
     *	        RFQ declined sent in the given date range
     *	        If both original RFQ and its duplicate one were declined, only one decline should be calculated
     *	    Match
     *	        Same as above, only the RFQ declined sent from the match engine only
     *
     * @author  Yuriy Akopov
     * @date    2013-08-14
     * @story   S7286
     *
     * @param   bool    $fromMatch  null - all RFQs, true - only those from the match engine, false - only non-match engine ones
     * @param   bool    $cacheOnly
     *
     * @return  int|null
     */
    public function getDeclineCount($fromMatch = null, $cacheOnly = false) {
        // building cacheKey
        // WARNING: if rules for key composing are changed don't forget to amend purgeCache() function accordingly!
        // WARNING: if date limit is changed in Match Settings, cache should be invalidated!
        if (!is_null($fromMatch)) {
            if ($fromMatch) {
                $key = self::CACHE_KEY_COUNT_NOPROXY;
            } else {
                $key = self::CACHE_KEY_COUNT_PROXY;
            }
        } else {
            $key = self::CACHE_KEY_COUNT_ALL;
        }

        $key .= $this->tnid;

        // check for the cached value and return it if it is available
        if (($count = $this->memcacheGet(__CLASS__, __METHOD__, $key)) !== false) {
            return (int) $count;
        }

        if ($cacheOnly) {
            return null;    // no cached value and we've been asked not to proceed with the real (slow) query
        }

        $db = Shipserv_Helper_Database::getSsreport2Db();
        $params = array(
            'tnid'      => $this->tnid,
            'status'    => 'DEC'
        );

        $select = new Zend_Db_Select($db);

        $select->from(
            array('rr' => 'rfq_resp'),
            // calculating number or declined RFQs, not the number of declines
            // for duplicate RFQs, their original identifiers are counted
            array('count' => 'COUNT(DISTINCT CASE WHEN r.rfq_count = 1 THEN r.rfq_internal_ref_no ELSE r.rfq_original END)')
        )
            ->where('rr.spb_branch_code = :tnid')   // only responses sent by the current supplier
            ->where('rr.rfq_resp_sts = :status')    // "decline" response only
        ;
        $rfqJoinConstraints = array(
            'r.spb_branch_code = :tnid',
            'r.rfq_internal_ref_no = rr.rfq_internal_ref_no'
        );

        if (!is_null($fromMatch)) {
            $params['proxyid'] = Shipserv_Match_Settings::get(Shipserv_Match_Settings::BUYER_PROXY_ID);

            if ($fromMatch) {
                $rfqJoinConstraints[] = 'r.byb_branch_code = :proxyid';     // only RFQs sent by proxy buyer
            } else {
                $rfqJoinConstraints[] = 'r.byb_branch_code <> :proxyid';    // only RFQs sent by any buyer but by proxy one
            }
        }

        $dateLimit = Shipserv_Match_Settings::get(Shipserv_Match_Settings::META_FIGURES_DEPTH);
        if (strlen($dateLimit)) {
            // declined RFQs must be sent in $dateLimit last days
            $params['days'] = $dateLimit;
            $rfqJoinConstraints[] = 'r.rfq_submitted_date >= TO_DATE(sysdate - :days)';
        }

        $select->join(
            array('r' => 'rfq'),
            implode(' AND ', $rfqJoinConstraints),
            array()
        );

        $count = $db->fetchOne($select, $params);

        // remember the calculated value in cache
        $this->memcacheSet(__CLASS__, __METHOD__, $key, $count);

        return (int) $count;
    }

    /**
     * Returns the number of RFQs received by supplier
     *
     * Number of RFQs
     *      Total
     *          Sent to the given supplier
     *          Sent in the given date range
     *          Original RFQs only
     *      Match
     *          Same as above plus sent by the match engine only
     *
     * @author  Yuriy Akopov
     * @date    2013-08-07
     * @story   S7286
     *
     * @param   bool    $fromMatch  null - all RFQs, true - only those from the match engine, false - only non-match engine ones
     * @param   bool    $cacheOnly
     *
     * @return  int
     */
    public function getRfqCount($fromMatch = null, $cacheOnly = false) {
        // building cacheKey
        // WARNING: if rules for key composing are changed don't forget to amend purgeCache() function accordingly!
        // WARNING: if date limit is changed in Match Settings, cache should be invalidated!
        if (!is_null($fromMatch)) {
            if ($fromMatch) {
                $key = self::CACHE_KEY_COUNT_NOPROXY;
            } else {
                $key = self::CACHE_KEY_COUNT_PROXY;
            }
        } else {
            $key = self::CACHE_KEY_COUNT_ALL;
        }

        $key .= $this->tnid;

        // check for the cached value and return it if it is available
        if (($count = $this->memcacheGet(__CLASS__, __METHOD__, $key)) !== false) {
            return (int) $count;
        }

        if ($cacheOnly) {
            return null;    // no cached value and we've been asked not to proceed with the real (slow) query
        }

        // this calculation always happens on a secondary database even if the object was created for a live one
        $db = Shipserv_Helper_Database::getSsreport2Db();
        $params = array('tnid'  => $this->tnid);

        $select = new Zend_Db_Select($db);
        $select->from(
            array('r' => 'rfq'),
            array('count' => 'COUNT(DISTINCT r.rfq_internal_ref_no)')
        )
            ->where('r.spb_branch_code = :tnid')    // RFQs sent to the given supplier only
            ->where('r.rfq_count = 1')              // original RFQs only
        ;

        if (!is_null($fromMatch)) {
            $params['proxyid'] = Shipserv_Match_Settings::get(Shipserv_Match_Settings::BUYER_PROXY_ID);

            if ($fromMatch) {
                $select->where('r.byb_branch_code = :proxyid'); // only RFQs sent by the match engine
            } else {
                $select->where('r.byb_branch_code <> :proxyid');    // all other RFQs
            }
        }

        $dateLimit = Shipserv_Match_Settings::get(Shipserv_Match_Settings::META_FIGURES_DEPTH);
        if (strlen($dateLimit)) {
            // including all the RFQs sent in $dateLimit last days
            $params['days'] = $dateLimit;
            $select->where('r.rfq_submitted_date >= TO_DATE(sysdate - :days)');
        }

        $count = $db->fetchOne($select, $params);

        // remember the calculated value in cache
        $this->memcacheSet(__CLASS__, __METHOD__, $key, $count);

        return (int) $count;
    }

    /**
     * Returns basic fields of all the active supplier branches
     *
     * Warning: this method caches its output, but its data is not purged in purgeMemcache as it's global and not related
     * to a particular supplier.
     *
     * @author  Yuriy Akopov
     * @date    2013-02-18
     * @story   S6152
     *
     * @return array
     */
    public static function getAllBranches() {
        $stub = new self(null);

        if (($data = $stub->memcacheGet(__CLASS__, __METHOD__, null)) !== false) {
            return unserialize($data);
        }

        $select = new Zend_Db_Select(Shipserv_Helper_Database::getDb());
        $select
            ->from(
                array('spb' => Shipserv_Supplier::TABLE_NAME),
                array(
                    'ID'     => 'spb.' . Shipserv_Supplier::COL_ID,
                    'NAME'   => new Zend_Db_Expr('TRIM(spb.' . Shipserv_Supplier::COL_NAME . ')')
                )
            )
            ->where('spb.' . Shipserv_Supplier::COL_STATUS . ' = ?', 'ACT')
            ->order(new Zend_Db_Expr('TRIM(spb.' . Shipserv_Supplier::COL_NAME . ')'))
        ;

        $rows = $select->getAdapter()->fetchAll($select);

        $indexedRows = array();
        foreach ($rows as $row) {
            $indexedRows[$row['ID']] = $row['NAME'];
        }

        $stub->memcacheSet(__CLASS__, __METHOD__, null, serialize($indexedRows));
        return $indexedRows;
    }
    
    public function isInSSO($version=2014)
    {
    	$adapter = new Shipserv_Oracle_Suppliers();
    	return $adapter->isPublishedInShipServOnBoard($this->tnid, $version);
    }

    /**
     * Returns the date of the last order to the given buyer branch or null of no such orders yet
     *
     * @param   Shipserv_Buyer_Branch|int   $buyer
     * @param   int                         $dayDepth
     *
     * @return  DateTime|null
     */
    public function getLastOrderDate($buyer = null, $dayDepth = null) {
        if ($buyer instanceof Shipserv_Buyer_Branch) {
            $buyerId = $buyer->bybBranchCode;
        } else {
            $buyerId = $buyer;
        }

        $select = new Zend_Db_Select($this->getDb());
        $select
            ->from(
                array('ord' => Shipserv_PurchaseOrder::TABLE_NAME),
                new Zend_Db_Expr('TO_CHAR(MAX(ord.' . Shipserv_PurchaseOrder::COL_DATE_SUB . "), 'YYYY-MM-DD HH24:MI:SS')")
            )
            ->where('ord.' . Shipserv_PurchaseOrder::COL_STATUS . ' = ?', Shipserv_PurchaseOrder::STATUS_SUBMITTED)
            ->where('ord.' . Shipserv_PurchaseOrder::COL_SUPPLIER_ID . ' = ?', $this->tnid)
            ->where('ord.' . Shipserv_PurchaseOrder::COL_BUYER_ID . ' = ?', $buyerId)
        ;

        if ($dayDepth) {
            $select->where('ord.' . Shipserv_PurchaseOrder::COL_DATE . ' >= (SYSDATE - ?)', $dayDepth);
        }

        $dateStr = $select->getAdapter()->fetchOne($select);
        if (strlen($dateStr) === 0) {
            return null;
        }

        $date = new DateTime($dateStr);

        return $date;
    }

    /**
    * Retuns true if the supplier has revews
    * @return bool
    */
    public function hasReview()
    {

    	$endorsementsAdapter = new Shipserv_Oracle_Endorsements($this->getDb());
		$endorsements = $endorsementsAdapter->fetchEndorsementsByEndorsee($this->tnid,false);

		return count($endorsements) > 0;

    }

	/**
	 * Returns string constraints defining a supplier as valid for re-using in string SQL queries
	 *
	 * @author  Yuriy Akopov
	 * @modified by Attila O adding possibility to remove unpublished suppliers
	 * @date    2016-10-24
	 * @story   S18410
	 *
	 * @param   string  $prefix
	 * @param   bool    $includeNonPublished
     * @param   bool    $hideTestAccounts       added by Yuriy Akopov on 2017-06-26, S20478
	 *
	 * @return  string
     * @throws  Myshipserv_Search_Exception
	 */
    public static function getValidSupplierConstraints($prefix = 'spb', $includeNonPublished = false, $hideTestAccounts = false, $supplierActiveStatus = false)
    {
    	$db = Shipserv_Helper_Database::getDb();
	    $constraints = array();
	    
	    if ($includeNonPublished === false) {
		    $constraints[] = $db->quoteInto($prefix . '.' . self::COL_DIR_ENTRY . ' = ?', self::DIR_STATUS_PUBLISHED);
		}

		if ($hideTestAccounts) {
	        $constraints[] = $db->quoteInto($prefix . '.' . self::COL_TEST . ' = ?', 'N');
		}

		if ($supplierActiveStatus) {
			$constraints[] = $db->quoteInto($prefix . '.' . self::COL_STATUS . ' = ?', 'ACT');
		}
	    
	    $constraints[] = $db->quoteInto($prefix . '.' . self::COL_DELETED . ' = ?', 'N');
		$constraints[] = $db->quoteInto($prefix . '.' . self::COL_ID . ' < ?', Myshipserv_Config::getProxyMatchSupplier());

	    return implode(' AND ', $constraints);
    }
}
