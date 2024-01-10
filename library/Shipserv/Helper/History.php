<?php
/**
* This helper tracks the user's browsing history
*
* @copyright 2008 Jani Hartikainen <www.codeutopia.net>
* @author Jani Hartikainen <firstname at codeutopia net>
* @author Chris Antoine <chris@spinweb.net>(Modifications)
 *
 * Changed by Yuriy Akopov on 2016-08 following DE6822 (removed factory functionality for Shipserv_Helper_History_Custom)
*/
class Shipserv_Helper_History extends Zend_Controller_Action_Helper_Abstract
{
    /**
    * @var Zend_Session_Namespace
    */
    private $_namespace;
    
    /**
    * How many history URLs to track?
    *
    * @var int
    */
    private $_trackAmount = 0;
    
    /**
    * Track Ajax Requests?
    *
    * @var int
    */
    private $_ajax = 0;
    
    /**
    * Current URL
    *
    * @var string
    */
    private $_currentUrl = null;

    /**
     * @param int $trackAmount [optional] How many history URLs to track
     */
    public function __construct($trackAmount = 5)
    {
        $this->setTrackAmount($trackAmount);
        $this->_initSession();
    }

    /**
     * Initialize the history from session
     */
    private function _initSession()
    {
        $this->_namespace = Myshipserv_Helper_Session::getNamespaceSafely('Zend_Controller_Action_Helper_History');
        
        // Break out if we don't want to track the request
        if ($this->_trackableRequest() == false) {
            return;
        }
    
        if (!is_array($this->_namespace->history)) {
            $this->_namespace->history = array();
            die("A");
            if (!empty($_SERVER['HTTP_REFERER'])) {
                array_unshift($this->_namespace->history, $_SERVER['HTTP_REFERER']);
            }
        } else {
        	array_splice($this->_namespace->history, $this->_trackAmount);
        }
    }

    public function preDispatch()
    {
        $urlHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Url');
        $this->_currentUrl = $urlHelper->url();
        
        // Break out if we don't want to track the request
        if ($this->_trackableRequest() == false) {
            return;
        }
        
        if (!is_array($this->_namespace->history)) {
            $this->_namespace->history = array();
        }
        
        array_unshift($this->_namespace->history, $this->_currentUrl);
    }
    
    /**
     * Set how many history URLs to track
     *
     * @param int $trackAmount
     */
    public function setTrackAmount($trackAmount)
    {
        $this->_trackAmount = $trackAmount;
    }
    
    /**
     * Redirects the browser back in history
     *
     * @param int $amount How many URLs to go back
     */
    public function goBack($amount = 1)
    {
	    /** @var Zend_Controller_Action_Helper_Redirector $redirector */
        $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('Redirector');
	    $redirector
            ->setPrependBase(false)
            ->gotoUrl($this->_namespace->history[$amount])
        ;
    }
    
    /**
     * Returns an URL from history
     *
     * @param int $amount How many URLs to go back
     *
     * @return string
     */
    public function getPreviousUrl($amount = 1)
    {
        return $this->_namespace->history[$amount];
    }
    
    /**
     * Return all previous URLs
     *
     * @return array
     */
    public function getArray()
    {
        return $this->_namespace->history;
    }

	/**
	 * @return string
	 */
    public function getName()
    {
        return 'History';
    }
    
    /**
    * Validate that we want to track the request
    *
    * @return Boolean
    */
    private function _trackableRequest()
    {
        // Decide whether or not we should track Ajax Requests
        if ($this->_ajax == 0) {
            Zend_Loader::loadClass('Zend_Controller_Request_Http');
            $request = new Zend_Controller_Request_Http();
            
            if ($request->isXmlHttpRequest()) {
                return false;
            }
        }
        
        // Now we are going to verify the user didn't just reload the page or click the same link.
        // No reason to track page reloads.
        if ($this->_currentUrl == ($this->_namespace->history[0] ?? '')) {
            return false;
        }
        
        // Need to come up with a way to check for a 404 error so we don't track bad requests.
        
        return true;
    }
}
