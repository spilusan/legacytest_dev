
<?php

class Myshipserv_Helper_ApplicationProtocol
{
    private static $_instance;
    
    /**
    * Get the appliction instance
    */
    public static function getInstance()
    {
        if (null === static::$_instance) {
            static::$_instance = new static();
        }

        return static::$_instance;
    }

	/**
	* Protected classes to prevent creating a new instance 
	* @return object
	*/
    protected function __construct()
    {
    }

	/**
	* Protected classes to prevent creating a new instance 
	* @return object
	*/
    private function __clone()
    {

    }

    /**
    * Get the application Protocol 
    * @return string
    */
    public function getProtocol()
    {
        return  Myshipserv_Config::getApplicationProtocol();
    }
    
    /**
    * Get the application progocol full URL
    */
    public function getProtocolURL()
    {
        return $this->getProtocol() . '://';
    }
}