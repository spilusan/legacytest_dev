<?php
/**
* This class is created to avoind an error exception during the implementation of REST report API
* if any exception was reised before the exception a CAS client alrady been called exception was shown,
* which was uncathabe due to the poor PHPCas library, as PHPCas::client could be called twice, (due to poor pages implementation) 
* so this class is temporaryly will solve the problem by storing the status of PHPcas:client initalisation. 
* We can get rid of this class as soos as we will deploy the new cas REST login
*/
class Myshipserv_CasControl
{

    private static $_instance;
    protected $casClientCalled;
    
    /**
    * Create a (single) instance of the class, or if exists, retun the existing class
    * @return object The class (single) instance
    */
    public static function getInstance()
    {
        if (null === static::$_instance) {
            static::$_instance = new static();
        }
        
        return static::$_instance;
    }

    /**
     * Protected classes to prevent creating a new instance, and initialize variables 
     */
    protected function __construct()
    {
    	$this->casClientCalled = false;
    }

    /**
    * Prevent clone class
    * @return unkonwn
    */
    private function __clone()
    {

    }

    /**
    * Called after PHPCAS:client, and flag as called
    * @return unkonwn
    */
    public function setCasClientCalled()
    {
    	$this->casClientCalled = true;
    }

    /**
    * Return the value we stored, if PHPCas::client was called
    * @return bool if PHPCas::client was called it is true
    */
    public function getCasClientCalled()
    {
    	return $this->casClientCalled;
    }

}