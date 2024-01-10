<?php
/*
* Class to retreive the shipmate status, if we are not logged in
* Author: Attila O 11/06/2015
*/

class Shipserv_Report_Supplier_Insight_Smart extends Shipserv_Object
{
	protected $userId;

	/**
	* To initalize the singleton class, pass the user ID here
	* @param integer $userid
	* @return class instance
	*/
    public static function getInstance( $userId )
    {
        static $instance = null;
        if (null === $instance) {
            $instance = new self((int)$userId);
        }

        return $instance;
    }

    /**
    * Get if shipmate or not
    * @return boolean
    */
	public function isShipservUserByUserId ( )
    {
        $config = parent::getConfig();

        $key = $this->userId . '-isShipServUser';
        $db = $this->getDb();
        
        $isShipservUser = $this->memcacheGet(get_class(), 'isShipservUser', $key);
        $companyId = $config->shipserv->company->tnid;
        
        if( $isShipservUser !== false )
        {
            return ($isShipservUser == "Y");
        }
        else
        {
            // new implementation
         	if( $this->isPartOfSupplier($companyId))
            {
                $this->memcacheSet(get_class(), 'isShipservUser', $key, "Y");
                return true;
            }
            else
            {
                $this->memcacheSet(get_class(), 'isShipservUser', $key, "N");
                return false;
            }
        }
    }

    /**
    * Check if is part of supplier
    * @return boolean
    */

    public function isPartOfSupplier( $tnid = null )
	{
		if( $tnid === null )
		{
			$collection = $this->fetchCompanies();
			$supplierIds = $collection->getSupplierIds();
			$ucDom = new Myshipserv_UserCompany_Domain($this->getDb());

			foreach ($supplierIds as $tnid)
			{
				$uColl = $ucDom->fetchUsersForCompany('SPB', $tnid);
				
				$supplier = Shipserv_Supplier::getInstanceById($tnid, null, true);
				
				if( $supplier->isPublished() === false )
				{
					return false;
				}
				
				foreach ($uColl->getActiveUsers() as $u)
				{

					if( $u->userId == $this->userId && $u->status != 'DEL' )
					{
						return true;
					}
				}
			}
			return false;
		}
		else
		{
			$ucDom = new Myshipserv_UserCompany_Domain($this->getDb());
			
			$uColl = $ucDom->fetchUsersForCompany('SPB', $tnid);
	
			foreach ($uColl->getActiveUsers() as $u)
			{
				if( $u->userId == $this->userId && $u->status != 'DEL' )
				{
					return true;
					
				}
			}
			return false;
		}		
	}

	/**
	* Fetch buyer and supplier companies
	* @return array  or object of companies
	*/
	public function fetchCompanies ( $asArray = false)
	{
		if( $asArray )
		{
			foreach( $this->fetchCompanies()->getSupplierIds() as $r )
			{
				$supplier = Shipserv_Supplier::fetch( $r );
				$myCompanies[] = array("type" => "v", "name" => $supplier->name, "id" => $supplier->tnid, "value" => "v" . $supplier->tnid );	
			}
			
			foreach( $this->fetchCompanies()->getBuyerIds() as $r )
			{
				$buyer = Shipserv_Buyer::getInstanceById( $r );
				$myCompanies[] = array("type" => "b", "name" => $buyer->name, "id" => $buyer->id, "value" => "b" . $buyer->id );	
			}

			foreach( $this->fetchCompanies()->getBuyerBranchIds() as $r )
			{
				$buyer = Shipserv_Buyer::getBuyerBranchInstanceById( $r );
				$myCompanies[] = array("type" => "byb", "name" => $buyer->bybName, "id" => $buyer->bybBranchCode, "value" => "byb" . $buyer->bybBranchCode );	
			}
			
			return $myCompanies;

		}
		else 
		{
			$ucActions = new Myshipserv_UserCompany_Actions(self::getDb(), $this->userId);
			return $ucActions->fetchMyCompanies();
		}
	}

	/**
	* Called once
	* @param integer $userId
	*/
    protected function __construct( $userId )
    {
    	$this->userId = $userId;
    }

    /**
    * Protect the class to be clones, as it is singleton
    */
    private function __clone()
    {
    }
}



