<?php

/**
 * Get the vessel list for Transaction Monitor vessel filter
 *
 * Class Application_Model_Vessel
 */
class Application_Model_Vessel
{
    public static $memcacheExpiration = 86400; //300

    /**
     * Set the expiration TTL for memcache
     *
     * @param integer $exp
     */
    public static function setMemcacheExpiration($exp)
    {
        self::$memcacheExpiration = $exp;
    }

    /**
     * @param Shipserv_User $user
     * @param string $documentType
     * @param integer $buyerBranch
     * @param string $fromDate
     * @param string $toDate
     * @param bool $includeChildren
     * @return array|string
     * @throws Zend_Exception
     */
    public function fetchAllVesselNames($user, $documentType, $buyerBranch, $fromDate, $toDate, $includeChildren = false)
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
        $query =$transactionQueries->getVesselQuery();
        $preparedParams = array_intersect_key($params, array_flip($requiredParams));

        // Connect to memcache
        $config   = Zend_Registry::get('config');
        $memcache = new Memcache();
        $memcache->connect($config->memcache->server->host, $config->memcache->server->port);

        $memcacheKey = 'txn_vessel:' . md5($query . '_' . serialize($preparedParams));
        // Check if result is already in memcache - return it
        $result = $memcache->get($memcacheKey);
        if ($result === false) {
            $result = $db->fetchAll($query, $preparedParams);
            $memcache->set($memcacheKey, $result, 0, self::$memcacheExpiration);
        }

        return $result;

    }
}
