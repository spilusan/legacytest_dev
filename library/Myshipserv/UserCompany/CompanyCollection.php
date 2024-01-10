<?php

// Ajwp_CompanyCollection
class Myshipserv_UserCompany_CompanyCollection
{
	private $companyArr = array();
	
	public function __construct (array $companies)
	{
		// todo: avoid duplicate companies incoming
		foreach ($companies as $c)
		{
			if ($c instanceof Myshipserv_UserCompany_Company)
			{
				$this->companyArr[] = $c;
			}
			else
			{
				throw new Exception("Expected array of Ajwp_Company instances");
			}
		}
	}
	
	/**
	 * Active supplier IDs
	 */
	public function getSupplierIds ()
	{
		$res = array();
		foreach ($this->companyArr as $c)
		{
			if ($c->getStatus() == Myshipserv_UserCompany_Company::STATUS_ACTIVE
				&& $c->getType() == Myshipserv_UserCompany_Company::TYPE_SPB)
			{
				$res[] = $c->getId();
			}
		}
		return $res;
	}
	
	/**
	 * Active buyer IDs
	 */
	public function getBuyerIds()
	{
		$res = array();
		foreach ($this->companyArr as $c) {
			if ($c->getStatus() == Myshipserv_UserCompany_Company::STATUS_ACTIVE
				&& $c->getType() == Myshipserv_UserCompany_Company::TYPE_BYO)
			{
				$res[] = $c->getId();
			}
			
		}
		return $res;
	}
	

	public function getBuyerBranchIds()
	{
		$res = array();
		foreach ($this->companyArr as $c) {
			if ($c->getStatus() == Myshipserv_UserCompany_Company::STATUS_ACTIVE
			&& $c->getType() == Myshipserv_UserCompany_Company::TYPE_BYB)
			{
				$res[] = $c->getId();
			}
		}
		return $res;
	}

	public function getBuyerSubBranchIds()
	{
	    $db = $GLOBALS['application']->getBootstrap()->getResource('db');
	    $res = array();
	    foreach ($this->companyArr as $c) {
	        if ($c->getStatus() == Myshipserv_UserCompany_Company::STATUS_ACTIVE) {
	            $sql = "
                    SELECT bb_parent.byb_byo_org_code AS ORG_ID
                    FROM buyer_branch bb_parent JOIN buyer_branch bb_child ON bb_parent.byb_under_contract=bb_child.byb_branch_code
                    WHERE bb_child.byb_byo_org_code = :byb_byo_org_code
	            ";           
	            $rows = $db->fetchAll($sql, array('byb_byo_org_code' => $c->getId()));
	            foreach ($rows as $row) {
	                $res[] = $row['ORG_ID'];
	            }
	        }
	    }
	    return $res;
	}
	
	
	public function getDefaultBranchId()
	{
		$res = array();
		foreach ($this->companyArr as $c) {
			if ($c->getStatus() == Myshipserv_UserCompany_Company::STATUS_ACTIVE
			&& $c->getType() == Myshipserv_UserCompany_Company::TYPE_BYB
			&& $c->isDefault() )
			{
				$res[] = $c->getId();
			}
		}
		return $res;
		
	}
	
	/**
	 * Active Admin IDs
	 */
	public function getAdminIds(&$buyers, &$suppliers, &$consortia = null)
	{
		$buyers = $suppliers = $consortia = array();
		foreach ($this->companyArr as $c) {
			if ($c->getStatus() == Myshipserv_UserCompany_Company::STATUS_ACTIVE
				&& $c->getLevel() == Myshipserv_UserCompany_Company::LEVEL_ADMIN)
			{
				if ($c->getType() == Myshipserv_UserCompany_Company::TYPE_SPB)
				{
					$suppliers[] = $c->getId();
				}
				elseif ($c->getType() == Myshipserv_UserCompany_Company::TYPE_BYO)
				{
					$buyers[] = $c->getId();
				}
                elseif ($c->getType() == Myshipserv_UserCompany_Company::TYPE_CON)
                {
                    $consortia[] = $c->getId();
                }
			}
		}
	}

    /**
     * Active Consortia Company IDs
     */
    public function getConsortiaIds()
    {
        $res = array();
        foreach ($this->companyArr as $c) {
            if ($c->getStatus() == Myshipserv_UserCompany_Company::STATUS_ACTIVE && $c->getType() == Myshipserv_UserCompany_Company::TYPE_CON) {
                $res[] = $c->getId();
            }
        }
        return $res;
    }
	
	/**
	 * Return company collection as a list or dictinary, depending on $associative param
	 * @param Bool $associative   If true, returns a company collection as associative array with company id as index and company name as object. If false returns a non associative array (list) 
	 * @return Array
	 */	
	public function toArr($associative = false)
	{
		if (!$associative) {
		    return $this->companyArr;
		}
		$associativeArraty = array();
		foreach ($this->companyArr as $company) {
		    $associativeArraty[$company->getId()] = $company;
		}
		return $associativeArraty;		
	}
}
