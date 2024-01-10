<?php

/**
 * Get the list of suppliers for Transaction Monitor Supplier Drop Down list
 *
 * Class Application_Model_Supplier
 */
class Application_Model_Supplier
{
    public static $memcacheExpiration = 86400; //300

    /**
     * Memcache expiration
     *
     * @param integer $exp
     */
    public static function setMemcacheExpiration($exp)
    {
        self::$memcacheExpiration = $exp;
    }

    /**
     * Fetch list of supplier names and code according to filters
     *
     * @param Shipserv_User $user
     * @param string $documentType
     * @param integer $buyerBranch
     * @param string $fromDate
     * @param string $toDate
     * @param bool $includeChildren
     * @return array|string
     * @throws Zend_Exception
     */
    public function fetchSupplierNamesForFilter($user, $documentType, $buyerBranch, $fromDate, $toDate, $includeChildren = false)
    {

        $params = array();
        $buyerbranch = null;

        $resource = $GLOBALS["application"]->getBootstrap()->getPluginResource('multidb');
        $db = $resource->getDb('ssreport2');
        $qotdeadline = (array_key_exists('qotdeadline', $_REQUEST) && $_REQUEST['qotdeadline'] != '') ? new DateTime($_REQUEST['qotdeadline']) : null;
        extract(Application_Model_Transaction::processParams($buyerBranch, $fromDate, $toDate, $qotdeadline, $_REQUEST, $user, null, null), EXTR_OVERWRITE);

        $embeddedQueryParams = array(
            'includeChildren' => $includeChildren,
            'buyerbranch' => $buyerbranch,
        );

        $transactionQueries = new Application_Model_TransactionQueries($documentType, $embeddedQueryParams);

        $requiredParams = $transactionQueries->getRequiredCountParams();
        $query = $transactionQueries->getSupplierQuery();
        $preparedParams = array_intersect_key($params, array_flip($requiredParams));

        // Connect to memcache
        $config = Zend_Registry::get('config');
        $memcache = new Memcache();
        $memcache->connect($config->memcache->server->host, $config->memcache->server->port);

        $memcacheKey = 'txn_supplier:' . md5($query . '_' . serialize($preparedParams));

        // Check if result is already in memcache - return it
        $result = $memcache->get($memcacheKey);
        if ($result === false) {
            $result = $db->fetchAll($query, $preparedParams);
            $memcache->set($memcacheKey, $result, 0, self::$memcacheExpiration);
        }

        return $result;
    }

}
