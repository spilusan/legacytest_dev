<?php

/**
 * 
 * 
 * @package ShipServ
 * @author Dave Starling <dstarling@shipserv.com>
 */
class Shipserv_Services_Auth_Storage_Interface implements Zend_Auth_Storage_Interface
{
	/**
     * Returns true if and only if storage is empty
     *
     * @throws Zend_Auth_Storage_Exception If it is impossible to
     *                                     determine whether storage
     *                                     is empty
     * @return boolean
     */
    public function isEmpty ()
    {
        /**
         * @todo implementation
         */
    }

    /**
     * Returns the contents of storage
     *
     * Behavior is undefined when storage is empty.
     *
     * @throws Zend_Auth_Storage_Exception If reading contents from
     *                                     storage is impossible
     * @return mixed
     */
    public function read ()
    {
        /**
         * @todo implementation
         */
    }

    /**
     * Writes $contents to storage
     *
     * @param  mixed $contents
     * @throws Zend_Auth_Storage_Exception If writing $contents to
     *                                     storage is impossible
     * @return void
     */
    public function write ($contents)
    {
        /**
         * @todo implementation
         */
    }

    /**
     * Clears contents from storage
     *
     * @throws Zend_Auth_Storage_Exception If clearing contents from
     *                                     storage is impossible
     * @return void
     */
    public function clear ()
    {
        /**
         * @todo implementation
         */
    }
}