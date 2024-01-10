<?php
/**
* Returns JSON with buyer branches available to currently logged in user and their active buyer company
*/

class Shipserv_Report_Buyer_Match_BuyerBranches
{
    
    //TODO: Shipserv_Report_Buyer_Match_BuyerBranches is not really the best place for this code! Move that in a more generic class! (Claudio didn't have any more time in the huge trading accoutns refactoring)

    /**
     * @var Singleton The reference to *Singleton* instance of this class
     */
    private static $_instance;
    
    /**
     * Returns the *Singleton* instance of this class.
     *
     * @return Shipserv_Report_Buyer_Match_BuyerBranches   Singleton The *Singleton* instance.
     */
    public static function getInstance()
    {
        if (null === static::$_instance) {
            static::$_instance = new static();
        }
        return static::$_instance;
    }

    
     /**
     * Get a list of buyer branches available to CURRENTLY LOGGED IN USER AND HIS SELECTED ORG ID
     *
     * @param String $filterType   should be one of the constant declared in Shipserv_User as BRANCH_FILTER_*. Default is BRANCH_FILTER_ANY
     * @param Bool $excludeIna = false  exlude inatcive branches or not?
     * @author Yuriy Akopov, refactored by Attila O and Claudio
     * @date   2013-12-04
     * @return Array
     */
    public function getBuyerBranches($filterType = Shipserv_User::BRANCH_FILTER_ANY, $excludeIna = false) 
    {
        $user = Shipserv_User::isLoggedIn();
        $buyerOrgCompany = $this->getUserBuyerOrg();
        if (!in_array($filterType, array(Shipserv_User::BRANCH_FILTER_MATCH, Shipserv_User::BRANCH_FILTER_BUY, Shipserv_User::BRANCH_FILTER_WEBREPORTER, Shipserv_User::BRANCH_FILTER_TXNMON, Shipserv_User::BRANCH_FILTER_AUTOREMINDER))) {
            $filterType = Shipserv_User::BRANCH_FILTER_ANY;
        }
        
        $canDoLazyLoading = false;
        $allBranchesCount = 0;
        $allowedBranches = array();
        foreach (Shipserv_Oracle_PagesUserCompany::getInstance()->fetchCompaniesForUser($user->userId, $buyerOrgCompany->id, $excludeIna) as $branch) {
            if ($branch['PUC_COMPANY_TYPE'] === Myshipserv_UserCompany_Company::TYPE_BYB /*&& $branch['BYB_STS'] === Shipserv_Buyer_Branch::STATUS_ACTIVE*/) {
                $allBranchesCount += 1;
                if (
                        ($filterType === Shipserv_User::BRANCH_FILTER_ANY || $branch[$filterType]) //2nd condtion should never bug because of prev lines check + short circuit
                        && in_array($branch['PUC_STATUS'], array(Myshipserv_UserCompany_Company::STATUS_ACTIVE, Myshipserv_UserCompany_Company::STATUS_PENDING))
                ) {
                    $allowedBranches[] = array(
                        'id'   => $branch['PUC_COMPANY_ID'],
                        'name' => $branch['PUC_COMPANY_ID'] . (strlen($branch['BYB_NAME'])? ' - ' . $branch['BYB_NAME'] : ''),
                        'default' => (Int) $branch['PUC_IS_DEFAULT'] //better to use int than boolean cause handlebar and even js sometimes don't undertsand, and this method is used by handlebar and js indeed!
                    );
                }
            //If the user has permission (needed for security!)
            } elseif ($branch['PUC_COMPANY_TYPE'] === Myshipserv_UserCompany_Company::TYPE_BYO && $branch['PUC_COMPANY_ID'] == $buyerOrgCompany->id) {
                $canDoLazyLoading = true;
            }            
        }
        
        //Trading accounts form was never saved and/or populated => do the lazy population the "old way" (before DE6718) 
        if ($allBranchesCount === 0 && count($allowedBranches) === 0 && ($canDoLazyLoading || $user->isShipservUser())) {
            foreach ((array) Shipserv_Buyer::getInstanceById($buyerOrgCompany->id)->getBranchesTnid() as $id) {
                $branch = Shipserv_Buyer::getBuyerBranchInstanceById($id);
                $allowedBranches[] = array(
                    'id'   => (int) $branch->bybBranchCode,
                    'name' => $branch->bybBranchCode . (strlen($branch->bybName) ? (' - ' . $branch->bybName) : ''),
                    'default' => 0,
                	'inactive' => $branch->bybSts === 'INA'
                );
            }
        //Reading accounts was already saved and populated but the current user is not allowed to access any branch
        } elseif ($allBranchesCount > 0 && count($allowedBranches) === 0) {
            return array();
        }
        
        return $allowedBranches;
    }

        /**
     * Returns buyer organisation current user belongs to
     *
     * @return  Shipserv_Buyer
     * @throws  Exception
     */
    protected function getUserBuyerOrg() 
    {
        $testingEnv = in_array($_SERVER['APPLICATION_ENV'], array('development', 'testing'));

        $user = Shipserv_User::isLoggedIn();
        if ($user === false) {
            if (!$testingEnv) {
                throw new Myshipserv_Exception_MessagedException("You need to be logged in to access buyer-related functionality", 403);
            }
        } else {
            $buyerOrgIds = $user->fetchCompanies()->getBuyerIds();
            $message = "This page is only accessible to buyers.";
            if (!empty($buyerOrgIds)) {
                $message .= " Buyer organisations accessible to you are: " . implode(', ', $buyerOrgIds) . ", so your can switch to any of them.";
            }
        }

        $activeCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
        if (strlen($activeCompany->id) === 0) {
            if (in_array($_SERVER['APPLICATION_ENV'], array('testing', 'ukdev'))) {
                $buyerOrgId = 23404;    //@todo: backdoor for load testing
            } else {
                throw new Myshipserv_Exception_MessagedException("There is no active buyer company selected", 403);
            }
        } else {
            $buyerOrgId = $activeCompany->id;
        }

        try {
            $buyerOrgCompany = Shipserv_Buyer::getInstanceById($buyerOrgId);
        } catch (Exception $e) {
            throw new Myshipserv_Exception_MessagedException(
                "Your selected organisation " . $buyerOrgId . " does not appear to be a buyer one. " . $message,
                403
            );
        }

        return $buyerOrgCompany;
    }
    /**
     * Protected constructor to prevent creating a new instance of the
     * *Singleton* via the `new` operator from outside of this class.
     */
    protected function __construct()
    {
    }

    /**
     * Private clone method to prevent cloning of the instance of the
     * *Singleton* instance.
     *
     * @return void
     */
    private function __clone()
    {
    }
}