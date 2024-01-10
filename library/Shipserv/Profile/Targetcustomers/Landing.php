<?php
/**
* Managing Landing Pages for Active Promotion (Originallly named as Targeting)
*/

class Shipserv_Profile_Targetcustomers_Landing
{

	/**
    * @var Singleton The reference to *Singleton* instance of this class
    */
    private static
    	  $instance
    	  ;
	private 
		  $params
		, $spbBranchCode
		, $bybBranchCode
		, $result
		, $activeCompanyId
		, $activeCompanyType
		, $buyer
		, $buyerBranch
		;


    /**
    * Returns the *Singleton* instance of this class.
    *
    * @return Singleton The *Singleton* instance.
    */
    public static function getInstance( $params = null )
    {
        if (null === static::$instance) {
            static::$instance = new static();
            static::$instance->params = $params;
        }
        
        return static::$instance;
    }

    /**
    * Protected what we have to hide
    */
    protected function __construct() {
    	//$this->db = Shipserv_Helper_Database::getDb();
		$this->result = array();
		$this->config  = Zend_Registry::get('config');
		$company = Myshipserv_Helper_Session::getActiveCompanyNamespace();
		$this->activeCompanyId = $company->id;
		$this->activeCompanyType = $company->type;

    }
    private function __clone()  {}

	public function getData()
	{
		if (!$this->validateInput()) {
			throw new Myshipserv_Exception_MessagedException("Invalid URL", 403);
		}
		if (!$this->user) {
			throw new Myshipserv_Exception_MessagedException("You are not logged in", 403);
		}

		$supplierBuyerRateObj = new Shipserv_Supplier_Rate_Buyer($this->spbBranchCode);
		$rates = $supplierBuyerRateObj->getRateObj()->getRate();

		$supplierStandardRate =  ($rates['SBR_RATE_STANDARD'] === null) ? 0 : $rates['SBR_RATE_STANDARD'];
		$supplierTargetRate = ($rates['SBR_RATE_TARGET'] === null) ? 0 : $rates['SBR_RATE_TARGET'];

		$this->result['currentRate'] = array(
				  'supplierStandardRate' => $supplierStandardRate
				, 'supplierTargetRate' => $supplierTargetRate
				, 'supplierLockPeriod' => $rates['SBR_LOCK_TARGET']
			);

		if ($rates['SBR_LOCK_TARGET']) {
			$years = round((int)$rates['SBR_LOCK_TARGET'] / 365, 1);

			$this->result['lockPeriod'] = 'for ' . $years . ' years';
			
		} else {
			$this->result['lockPeriod'] = 'permanently';
		}
		
		$this->buyer = Shipserv_Buyer::getBuyerBranchInstanceById( $this->bybBranchCode );
		$this->buyerBranch = Shipserv_Buyer_Branch::getInstanceById( $this->bybBranchCode );
		$showChildren = ($this->buyerBranch->isTopLevelBranch() && $this->buyer->bybPromoteChildBranches == 0);

		$shipInfo = Shipserv_Oracle_Targetcustomers_Vessel::getInstance()->getVesselInfo($this->bybBranchCode, $this->spbBranchCode, false, $showChildren);

		$this->result['buyer'] = $this->buyer;
		$this->result['shipInfo'] = $shipInfo;
		//@todo Last parameter of getByyerInfo says, if it has to display child branch data or not
		$this->result['buyerInfo'] = Shipserv_Oracle_Targetcustomers_Buyerinfo::getInstance()->getBuyerInfo( $this->bybBranchCode, $this->spbBranchCode, true, null, $showChildren);
		$this->result['firstOrderDate'] = date('d M Y',strtotime($this->result['buyerInfo']['FIRST_ORDER']));

		$vesselTypeList = '';
		$vesselTypeCount = count($shipInfo['vesselTypeList']);

		for ($i = 0 ; $i<$vesselTypeCount; $i++) {
			if ($i<5) {
			$vesselTypeList .= ($vesselTypeList == '') ?  $shipInfo['vesselTypeList'][$i] : ', '.$shipInfo['vesselTypeList'][$i];
			}
		}


		if ($i>=5) {
			$vesselTypeList .='... and '.($vesselTypeCount - 5).' more';
		}	

	  	$userName = $this->user->firstName . ' ' . $this->user->lastName;
		$userName = ($userName == ' ')? explode('@',$this->user->email)[0] : $userName;

		$this->result['vesselTypeList'] = $vesselTypeList;
		$this->result['user'] = $this->user;
		$this->result['userName'] = $userName;
		$this->result['validFrom'] = $this->validFrom;
		$this->result['validFromTime'] = $this->validFromTime;
		$this->result['spbBranchCode'] = $this->spbBranchCode;
		$this->result['bybBranchCode'] = $this->bybBranchCode;
		
		$logoUrl = $this->config->shipserv->images->buyerLogo->urlPrefix.$this->bybBranchCode.'.gif';


		//Get the image, and it's file size, as when it is an empty 1x1 pixel image, we do not dispaly it
		
		$headers = get_headers($logoUrl);
		$responseCode = (int)explode(' ',$headers[0])[1];

		$imageFileSize = 0;
		foreach ($headers as  $header) {
			$headerParts = explode(":",$header);
			if ($headerParts[0] == 'Content-Length') {
				$imageFileSize = (int)$headerParts[1];
			}
		}
		
		$this->result['logoUrlIsValid'] = (($responseCode == 200) && ($imageFileSize > 500));
		$this->result['logoUrl'] = $logoUrl;
		$this->result['domain'] = $this->config->shipserv->application->hostname;

		return static::$instance;
	}

	public function getResult()
	{

		return $this->result;
	}

	private function validateInput()
	{
		if (!is_array($this->params)) {
			return false;
		}

		if (!(array_key_exists('buyerid', $this->params) && array_key_exists('supplierid', $this->params))) {
			return false;	
		}

        if ($this->activeCompanyType == 'v') {
        	if ($this->activeCompanyId != $this->params['supplierid']) {
        		throw new Myshipserv_Exception_MessagedException("Your selected company does not match the company referred by the email", 500);
        	}
        } else {
        	throw new Myshipserv_Exception_MessagedException("You need to be logged in as a Supplier to access buyer services", 500);
        }

		$this->user = Shipserv_User::isLoggedIn();
		$this->bybBranchCode = $this->params['buyerid'];
		$this->spbBranchCode = $this->params['supplierid'];
		
		return true;
	}
}


