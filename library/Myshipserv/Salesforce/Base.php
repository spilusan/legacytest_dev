<?php
/**
 * Base class for SF - Pages communication
 */
// commented out by Yuriy Akopov on 2016-06-08, S16162 as Salesforce library is moved to composer
// require_once ('/var/www/libraries/SalesForce/SforceEnterpriseClient.php');

/**
 * Initially created by Elvir
 * Extended and somewhat refactored by Yuriy Akopov on 2016-02-03 when working on S15735
 * to separate sandbox and real account credentials in the config
 */
class Myshipserv_Salesforce_Base extends Shipserv_Object
{
	const
        QUERY_ROWS_AT_ONCE = 1000,  // changed from 2000 by Yuriy Akopov on 2017-12-12, DEV-1798
        // time in seconds to wait between requests, added by Yuriy Akopov on 2017-12-13, DEV-1798
        // was hardcoded as 5 in many places prior to change, reset to 10 when the constant introduced on 2017-12-13
        REQUEST_PAUSE = 10
    ;


	/**
	 * Initial null value means that SalesForce environment will be defined by Pages environment
	 *
	 * @var bool
	 */
	protected $sandbox = null;

	/**
	 * @var SforceEnterpriseClient
	 */
	protected $sfConnection = null;

	/**
	 * @var ProxySettings
	 */
	protected $sfProxy = null;

	/**
	 * @var SoapClient
	 */
	protected $soapClient = null;

	/**
	 * @var LoginResult
	 */
	protected $loginObj = null;

	/**
	 * when this method is called, this would mean, this variable is set
	 * it depends of what test transaction mean on the sub class (the logic
	 * should be written there
	 * 
	 * @return void
	 */
	public function runAsTestTransaction()
	{
		$this->test = true;
	}

    /**
     * @return mixed
     */
	public function isTestTransaction()
	{
		return $this->test;
	}
	
	/**
	 * running the script on sandbox (this would mean that the 
	 * connection will try to connect to sandbox environment ie using 
	 * sandbox wsdl 
	 */
	public function runInSandbox()
	{
		$this->sandbox = true;
	}

	/**
	 * Returns SalesForce connection initialised for the current environment
	 * This is for when a query needs to be run outside of the class (which is again most typically wrong, so needs
	 * to be put into in-class wrapper functions later)
	 * 
	 * @author  Yuriy Akopov
	 * @date    2016-06-08
	 * @story   S16162
	 * 
	 * @return  SforceEnterpriseClient
	 * @throws  Myshipserv_Salesforce_Exception
	 */
	public function getSalesForceConnection()
    {
		if (is_null($this->sfConnection)) {
			throw new Myshipserv_Salesforce_Exception("SalesForce SOAP client was not initialised prior to being requested");
		}
		
		return $this->sfConnection;
	}
	
	/**
	 * Establishing all required stuff when connecting to SF
	 */
	public function initialiseConnection()
	{
		$this->sfConnection = new SforceEnterpriseClient();
		$this->sfProxy = new ProxySettings;

		$credentials = Myshipserv_Config::getSalesForceCredentials($this->sandbox);
		$this->soapClient = $this->sfConnection->createConnection($credentials->wsdl, $this->sfProxy);
		$this->loginObj = $this->sfConnection->login($credentials->username, $credentials->password . $credentials->token);

		$this->db = Shipserv_Helper_Database::getDb();
	}
	
	/**
	 * If there are specific tnid to process
	 * @param array $companies
	 * @throws Exception
	 */
	public function setTnidToProcess(array $companies)
	{
		$suppliers = array();
		foreach ($companies as $id) {
			$supplier = Shipserv_Supplier::getInstanceById($id, "", true);
			if ($supplier->tnid !== null) {
				$suppliers[] = $supplier;
			}
		}
	
		if (count($suppliers) == 0) {
			throw new Exception("Error found, we cannot find a single TNID that you specified.");
		}
	
		// this is the flag to tell other logic so it will only
		// remove records affected by given tnids and given period
		$this->suppliers = $suppliers;
		$this->tnids = $companies;
	}
	
	/**
	 * Converting SF date to PHP DateTime 
	 * @param DateTime $date
	 * @return DateTime
	 */
	public function convertSFDateToPHPDateTime($date)
	{
		$tmp = explode("-", $date);
		$date = new DateTime();
		$date->setDate($tmp[0], $tmp[1], $tmp[2]);
		return $date;
	}

	/**
	 * Querying SF and have option to retry when fails
	 *
	 * @param   string  $soql
	 * @param   bool    $retryWhenFails
	 *
	 * @return  QueryResult
     * @throws  Exception
	 */
	public function querySalesforce($soql, $retryWhenFails = false)
	{
		$this->sfConnection->setQueryOptions(new QueryOptions(self::QUERY_ROWS_AT_ONCE));

		if ($retryWhenFails === false) {
			$response = $this->sfConnection->query($soql);
		} else {
			$response = $this->querySalesforceRetryWhenFails($soql);
		}

		return $response;
	}
	
	/**
	 * Function to query SF, it'll keep trying it if throws error
	 * Modified by Yuriy Akopov on 2016-02-11
	 *
	 * @param   string  $soql
	 * @param   int     $numberOfAttempts
	 * @param   int     $pauseFor
	 *
	 * @return  QueryResult
	 * @throws  Exception
	 */
	public function querySalesforceRetryWhenFails($soql, $numberOfAttempts = 10, $pauseFor = self::REQUEST_PAUSE)
    {
		$this->sfConnection->setQueryOptions(new QueryOptions(self::QUERY_ROWS_AT_ONCE));

		$attemptNo = 0;

		while (true) {
			$attemptNo++;

			try {
				return $this->sfConnection->query($soql);

			} catch (SoapFault $e) {
				if ($e->getMessage() == "Could not connect to host") { // @todo: by Yuriy Akopov: is this really 'not responding when querying?

					if ($attemptNo <= $numberOfAttempts) {
						$this->logger->log("\tSF not responding when querying; " . $e->getMessage() . " - pausing for " . $pauseFor . " and retry");
						sleep($pauseFor);
					} else {
						$this->logger->log("\tSF not responding when querying; " . $e->getMessage() . " aborting after " . $numberOfAttempts . " attempts");
						throw $e;
					}

				} else {
					$this->logger->log("An error happened while querying SalesForce: " . get_class($e) . ", " . $e->getMessage());
					throw $e;
				}

			} catch (Exception $e) {
				$this->logger->log("An error happened while querying SalesForce: " . get_class($e) . ", " . $e->getMessage());
				throw $e;
			}
		}
	}

    /**
     * Returns all possible spellings for the supplier contract status
     *
     * @date    2016-12-06
     * @story   S18756
     *
     * @return array
     */
	public static function getContractStatusSpellings()
    {
	    return array(
            'Membership - Value based (web)',
            'Membership - Value based (integration)',
            'Membership- Value based',
            'Membership - Value based'
        );
    }

	/**
	 * Builds a constraint for SalesForce queries that considers all the spelling variations of value based pricing status
	 *
	 * @author  Yuriy Akopov
	 * @date    2016-08-05
	 * @story   S17177
	 *
	 * @param   string  $field
	 *
	 * @return  string
	 */
	protected static function _getValueBasedStatusConstraint($field)
    {
		$statusVariations = self::getContractStatusSpellings();

		$expressions = array();
		foreach ($statusVariations as $value) {
			$expressions[] = $field . " = '" . $value . "'";
		}

		$constraint = '(' . implode(' OR ', $expressions) . ')';

		return $constraint;
	}

	/**
	 * Returns true if the parameter may be a valid SalesForce ID
     * Modified by Yuriy Akopov on 2017-12-05, DEV-1170
	 *
	 * @author  Yuriy Akopov
	 * @date    2016-08-09
	 * @story   S17177
	 *
	 * @param   string  $id
     * @param   bool    $checkLength
	 *
	 * @return  bool
	 */
	public static function validateSalesForceId($id, $checkLength = false)
	{
        $syntaxCheckResult = preg_match('/^[A-Z0-9]+$/i', $id);

		if (!$checkLength) {
		    return $syntaxCheckResult;
        }

        if ($syntaxCheckResult) {
            return in_array(strlen($id), array(15, 18));
        }

        return false;
	}
}