<?php
/**
* This hepler will return the protocol, or the protocol URL for tbe view
* Usage: echo $this->getHelper('Protocol')->getProtocolURL().
*/
class Myshipserv_View_Helper_Protocol extends Zend_View_Helper_Abstract
{
	public function protocol()
    {
        return $this;
	}
    
    public function init()
    {
        
    }
    
    public function getProtocol()
    {
        return  Myshipserv_Config::getApplicationProtocol();
    }
    
    public function getProtocolURL()
    {
        return $this->getProtocol() . '://';
    }
}