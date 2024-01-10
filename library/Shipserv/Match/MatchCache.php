<?php
/**
 * A special version of the common application cache used by Match engine
 *
 * Refactored by Yuriy Akopov on 2013-08-01
 */
class Shipserv_Match_MatchCache extends Shipserv_Oracle {
    // default cache time out is one week
    const DEFAULT_TIMEOUT = 604800;

    /**
     * Expiration time out for cached data
     * If zero, cache is bypassed with all the data retrieved directly from db
     *
     * @author  Yuriy Akopov
     * @date    2013-08-01
     *
     * @var int
     */
    protected $timeOut = self::DEFAULT_TIMEOUT;

    /**
     * @param   object  $db
     * @param   int     $timeOut
     */
    public function __construct(&$db, $timeOut = self::DEFAULT_TIMEOUT) {
        $this->timeOut = $timeOut;

        parent::__construct($db);
    }

    /**
     * @todo: wouldn't it be better to override parent::fetchCachedQuery() instead of introducing a new method?
     *
     * @param   string                  $key
     * @param   string|Zend_Db_Select   $sql
     * @param   array                   $params
     *
     * @return  array
     */
    public function fetchData($key, $sql, $params = array()) {
        if ($this->timeOut > 0) {
            // cache is enabled so proceed with first checking for the data in cache
            $this->wrapKey($key);
            $result = $this->fetchCachedQuery($sql, $params, $key, $this->timeOut);
        } else {
            // cache is disabled so running a query straight away and don't cache the results
            $result = $this->db->fetchAll($sql, $params);
        }

        return $result;
    }
}