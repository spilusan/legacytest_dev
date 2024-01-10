<?php
// commented out by Yuriy Akopov on 2016-06-08, S16162 as Salesforce library is moved to composer
// require_once ('/var/www/libraries/SalesForce/SforceEnterpriseClient.php');

/**
 * Initially created by Elvir
 * Extended and somewhat refactored by Yuriy Akopov on 2016-02-03 when working on S15735
 * to separate sandbox and real account credentials in the config
 */
class Myshipserv_Salesforce extends Shipserv_Object
{
    private $sfConnection;
    private $soapClient;
    private $loginObj;

    //Object containing details about who their account manager is.
    private $accountManager;
    private $isPremium;

    //Date they upgraded to premium
    private $premiumDate;
    private $tnid;
    public $accountDetails;
    private $db;

    protected $useSandbox = false;

    public function __construct($tnid = null) {
        $credentials = Myshipserv_Config::getSalesForceCredentials($useSandbox);

    	// $this->config = Zend_Registry::get('config');

        $this->sfConnection = new SforceEnterpriseClient();

        $this->soapClient = $this->sfConnection->createConnection($credentials->wsdl);
        $this->loginObj = $this->sfConnection->login($credentials->username, $credentials->password . $credentials->token);
        $this->db = Shipserv_Helper_Database::getDb();

        if ($tnid != null) {
            $this->tnid = (int) $tnid;
            $query = "SELECT
                        Id, Name, TNID__c, Trading_TNID__c, Pages_Listing_Level__c,
						Owner.Name, Owner.Email, Owner.Phone, Owner.Title
					  FROM
					    Account where TNID__c = " . $this->tnid . " LIMIT 1";

            $queryResult = $this->sfConnection->query($query);
        }
    }
}
	