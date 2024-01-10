<?php
/**
 * Base exception for everything related to Consortia billing and synchronisation workflows
 *
 * @author  Yuriy Akopov
 * @date    2017-11-30
 * @story   DEV-1170
 */
class Myshipserv_Consortia_Exception extends Exception
{
    /**
     * @var string
     */
    protected $salesforceId = null;

    /**
     * @return string
     */
    public function getSalesforceId()
    {
        return $this->salesforceId;
    }

    /**
     * @param   string  $salesforceId
     *
     * @throws  Exception
     */
    public function setSalesforceId($salesforceId)
    {
        if (is_null($this->salesforceId)) {
            $this->salesforceId = $salesforceId;
        } else {
            throw new Exception(
                "Attempt to re-assign erroneous Salesforce ID " . $this->salesforceId . " with " . $salesforceId
            );
        }
    }

    /**
     * @param   string          $message
     * @param   null            $salesforceId
     * @param   int             $code
     * @param   Throwable|null  $previous
     */
    public function __construct($message = "", $salesforceId = null, $code = 0, Throwable $previous = null)
    {
        $this->salesforceId = $salesforceId;
        parent::__construct($message, $code, $previous);
    }
}