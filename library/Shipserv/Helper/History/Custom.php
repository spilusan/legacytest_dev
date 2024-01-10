<?php
/**
 * Provides session-based storage of a stack of url's representing user's browsing history.
 */
class Shipserv_Helper_History_Custom
{
	/**
	 * @var string
	 */
    private $_historyKey = null;

	/**
	 * @var int
	 */
    private $_trackAmount = null;

	/**
	 * @var Zend_Session_Namespace
	 */
    private $_sessionStorage = null;

	/**
	 * @return string
	 */
	protected function _getSessionNamespaceStr()
	{
		return __CLASS__ . '_' . $this->_historyKey;
	}

    /**
     * @param   string  $historyKey unique key identifying browsing history stack (provides multiple stacks)
     * @param   int $trackAmount
     */
    public function __construct($historyKey, $trackAmount = 5)
    {
        $this->_historyKey = $historyKey;
        $this->_trackAmount = $trackAmount;

	    $this->_sessionStorage = Myshipserv_Helper_Session::getNamespaceSafely($this->_getSessionNamespaceStr());
    }

	/**
	 * Returns an instance set up for search URL history
	 *
	 * @return Shipserv_Helper_History_Custom
	 */
    public static function getSearchHistory()
    {
    	return new self('customHistorySearch'); // used to be 'backToSearch' in the old factory Shipserv_Helper_History
    }

	/**
	 * Returns a list of recorded search URLs
	 *
	 * @author  Yuriy Akopov
	 * @date    2016-07-21
	 * @story   DE6822
	 *
	 * @return  array
	 */
    protected function _getUrlHistory()
    {
	    if (!isset($this->_sessionStorage->history)) {
	    	$this->_sessionStorage->history = array();
	    }

	    return $this->_sessionStorage->history;
    }

	/**
	 * Replaces history as a whole maintaining the allowed maximal length
	 *
	 * @author  Yuriy Akopov
	 * @date    2016-07-21
	 * @story   DE6822
	 *
	 * @param   array   $history
	 */
    protected function _setUrlHistory(array $history = array())
    {
	    array_splice($history, $this->_trackAmount);
	    $this->_sessionStorage->history = $history;
    }

	/**
	 * Inserts a URL in the beginning of the list maintaining the allowed maximal length
	 *
	 * @param   string  $url
	 *
	 * @return  bool
	 */
    protected function _addUrlToHistory($url)
	{
    	$urls = $this->_getUrlHistory();

	    if (!empty($urls) and ($urls[0] == $url)) {
		    return false;   // the URL about to be recorded is already the most recent in the list
	    }

        array_unshift($urls, $url);
	    $this->_setUrlHistory($urls);

	    return true;
    }

    /**
     * Add URL to browsing history, unless identical to most recent URL already in stack
     *
     * @param string $url URL to add, or null to add current URL
     *
     * @return  bool
     */
    public function addUrl($url = null)
    {
        if (strlen($url) === 0) {
            $url = Zend_Controller_Front::getInstance()->getRequest()->getRequestUri();
        }

        return $this->_addUrlToHistory($url);
    }
    
    /**
    * Returns a URL from recorded history
    *
    * @return string
    */
    public function getMostRecentUrl()
    {
    	$urls = $this->_getUrlHistory();
        if (empty($urls)) {
        	return null; // could be some default standard URL maybe?
        }

        return $urls[0];
    }
}
