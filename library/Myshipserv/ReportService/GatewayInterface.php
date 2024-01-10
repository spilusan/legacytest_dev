<?php 
/**
 * Interface class for report service gateway, so we can be sure when we have the dependency injection
 * on SPR data warming, this class is used
 * 
 * @author attilaolbrich
 *
 */

interface Myshipserv_ReportService_GatewayInterface
{
    /**
     * Forward a call to report service
     *
     * @param string $reportType 	The report type we would like to fetch
     * @param array $params      	The URL params
     * @param boolean $memcacheTtl	If null, default 1 hour, else the value set
     * @param boolean $rewriteCache	force to rewrite cache, always read from database
     * @return array
     */
    public function forward($reportType, $params, $memcacheTtl = null, $rewriteCache = false);
    
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
    public function forwardAs($serviceType, $reportType, $params, $memcacheTtl = null, $rewriteCache = false);
    
    /**
     * Getter for the status of the last call
     *
     * @ret
     */
    public function getStatus();
    
    /**
     * @param int $serviceType Constant of report type
     * Set the service URL by implemented types
     *
     * @return unknown
     */
    public function setServiceUrl($serviceType);
}

