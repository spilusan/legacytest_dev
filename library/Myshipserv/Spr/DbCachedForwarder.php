<?php 
/**
 * This class is responsible for forwarding the call to the report-service forward class
 * and if we need to cache the data into the DB, then it will take care of that
 * @author attilaolbrich
 *
 */
class Myshipserv_Spr_DbCachedForwarder
{
    
    protected $reportService;
    protected $db;
    protected $status;
    
    /**
     * @param Myshipserv_ReportService_GatewayInterface $reporService
     * @return object
     */
    public function __construct(Myshipserv_ReportService_GatewayInterface $reporService)
    {
        $this->reportService = $reporService;
        return $this;
    }
    
    /**
     * Forward a call to report service
     *
     * @param string $reportType 	The report type we would like to fetch
     * @param array $params      	The URL params
     * @param boolean $memcacheTtl	If null, default 1 hour, else the value set
     * @param boolean $rewriteCache	force to rewrite cache, always read from database
     * @return array
     */
    public function forward($reportType, $params, $memcacheTtl = null, $rewriteCache = false)
    {
        if ($memcacheTtl === Myshipserv_ReportService_Gateway::SPR_DATABASE_CACHE) {
            //Check if no TNID or BYB parameter is possed, for avoiding wrong implementation, This will run only for averages
            if (!isset($params['tnid']) && !isset($params['byb'])) {
                return $this->fetchFromDbCache($reportType, $params, $memcacheTtl, $rewriteCache, false);
            } else {
                $memcacheTtl = null;
            }
        }
        
        $result = $this->reportService->forward($reportType, $params, $memcacheTtl, $rewriteCache);
        $this->status = $this->reportService->getStatus();
        return $result;
        
    }
    
    /**
     * *** This HACK function is implemented, as after refactoring the report-service, the URL endpoints are not consequent anymore :-(
     * *** and as this class is a singleton, I cannot use two instances at the same time, so I have to be able to call the forward with an individual URL setting
     *
     * @param integer $serviceType   The service type constant definied above
     * @param string $reportType 	The report type we would like to fetch
     * @param array $params      	The URL params
     * @param boolean $memcacheTtl	If null, default 1 hour, else the value set
     * @param boolean $rewriteCache	force to rewrite cache, always read from database
     * @return array
     */
    public function forwardAs($serviceType, $reportType, $params, $memcacheTtl = null, $rewriteCache = false)
    {
        if ($memcacheTtl === Myshipserv_ReportService_Gateway::SPR_DATABASE_CACHE) {
            //Check if no TNID or BYB parameter is possed, for avoiding wrong implementation, This will run only for averages
            if (!isset($params['tnid']) && !isset($params['byb'])) {
                return $this->fetchFromDbCache($reportType, $params, $memcacheTtl, $rewriteCache, true);
            } else {
                $memcacheTtl = null;
            }
        }
        
        $result = $this->reportService->forwardAs($serviceType, $reportType, $params, $memcacheTtl, $rewriteCache);
        $this->status = $this->reportService->getStatus();
        return $result;
    }
    
    /**
     * Getter for the status of the last call
     *
     * @ret
     */
    public function getStatus()
    {
        return $this->status;
    }
    
    /**
     * @param int $serviceType Constant of report type
     * Set the service URL by implemented types
     *
     * @return unknown
     */
    public function setServiceUrl($serviceType)
    {
        $this->reportService->setServiceUrl($serviceType);
    }
    
    /**
     * Tries fetching the data from SSREPORT2, if not exists it will forward to report service and store to the database
     * 
     * @param string $reportType
     * @param array $params
     * @param integer $memcacheTtl
     * @param boolean $rewriteCache
     * @param boolean $forwardAs
     * @return array
     */
    protected function fetchFromDbCache($reportType, $params, $memcacheTtl, $rewriteCache, $forwardAs)
    {
        //We set the DB object here, as we do not want to create this object, if there is no DB caching required, If it was set previously, just ignore
        if (!isset($this->db)) {
            $this->db = Shipserv_Helper_Database::getSsreport2Db();
        }
        
        if ($rewriteCache === true) {
            $result = ($forwardAs === true) ? $this->reportService->forwardAs($reportType, $params, $memcacheTtl, $rewriteCache) : $this->reportService->forward($reportType, $params, $memcacheTtl, $rewriteCache);
            $this->status = $this->reportService->getStatus();
            if ($this->status) {
                $this->storeInDb($reportType, $params, $result);
            }
            return $result;
        }
        
        $result = $this->getFromDb($reportType, $params);
        
        if ($result) {
            $this->status = true;
            return json_decode($result, true);
        } else {
            $result = ($forwardAs === true) ? $this->reportService->forwardAs($reportType, $params, $memcacheTtl, $rewriteCache) : $this->reportService->forward($reportType, $params, $memcacheTtl, $rewriteCache);
            $this->status = $this->reportService->getStatus();
            if ($this->status) {
                $this->storeInDb($reportType, $params, $result);
            }
            return $result;
        }
    }
    
    /**
     * Generates a specific key for the storing
     * Generation must comply with the requirement that
     * next day before cache warming was executed, it must return the data for previous day
     * therefore we identify the item with the endpoint name, and the time period between the lowerdate and upperdate
     * as period parameter is not guranteed here
     * Please note, this function is designed for SPR only
     * 
     * @param string $reportType
     * @param array $params
     * @return string
     */
    protected function getKey($reportType, $params)
    {
        return $reportType . '_' . (strtotime($params['upperdate'])  - strtotime($params['lowerdate']));
    }
    
    /**
     * The actual store in the database, If this item alredy exist for this specific day, we will just update the record avoiding multiple same keys
     * This could happen only via the cron job, as we force the storage here, Normal browser operatnion will always insert, as there is a check boefore 
     * that and if data already exist in the table (within the previous 2 days) it will be served instad
     * 
     * @param string $reportType
     * @param array $params     We store it only for debug, and tracking purposes
     * @param string $result    The JSON string we store
     */
    protected function storeInDb($reportType, $params, $result)
    {
        //Check if this key is stored today, If yes, we just have to update, else we should insert it
        $sql = "
            MERGE INTO spr_json_cache sjc
            USING (SELECT
                        count(*) cache_count
                    FROM
                        spr_json_cache
                    WHERE
                        sjc_key = :key
                        and sjc_submitted_date = trunc(SYSDATE)
                  ) hassjc
            ON (hassjc.cache_count > 0)
            WHEN MATCHED THEN UPDATE 
                SET sjc.sjc_key = :key,
                sjc.sjc_submitted_date = trunc(SYSDATE),
                sjc.sjc_valid_till = SYSDATE + 2,
                sjc.sjc_pars = :pars,
                sjc.sjc_json = :json
                WHERE sjc.sjc_key = :key and sjc.sjc_submitted_date = trunc(SYSDATE)
            WHEN NOT MATCHED THEN INSERT (sjc.sjc_key, sjc.sjc_submitted_date, sjc.sjc_valid_till, sjc.sjc_pars, sjc.sjc_json) values (:key, trunc(SYSDATE), SYSDATE + 2, :pars, :json)";
        
        $this->db->query(
            $sql,
            array(
                'key' => $this->getKey($reportType, $params),
                'pars' => serialize($params),
                'json' => json_encode($result, true)
                )
        );
    }
    
    /**
     * Load the data from the database(cache) if not yet expired
     * 
     * @param string $reportType
     * @param array $params
     * @return string|false
     */
    protected function getFromDb($reportType, $params)
    {
        $sql = "
            SELECT
                sjc_json
            FROM
                spr_json_cache
            where
                sjc_key = :key
                and sjc_valid_till > SYSDATE
                order by sjc_valid_till desc";
        
        $result = $this->db->fetchOne($sql, array('key' => $this->getKey($reportType, $params)));
        return $result;
    }
    
}