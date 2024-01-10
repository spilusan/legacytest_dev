<?php

/**
 * Wrapper class for Shipserv_Adapters_Analytics which provides retrospective
 * updating of log items. This is used e.g. to apply a user id to actions
 * carried out before a user logs in.
 *
 * The implementation uses session-based stacks
 * to keep tabs on logging calls per user. These stacks may then be flushed,
 * triggering an udpate to the back-end analytics service.
 * 
 * @author Anthony Powell <apowell@shipserv.com>
 */
class Myshipserv_Controller_Action_Helper_Analytics extends Zend_Controller_Action_Helper_Abstract 
{
    // Methods that are proxied to underlying analytics adapter
    private static $proxiedMethods = array(
        'logSearch',
        'logGetProfile',
        'logContactInfoViewed',
        'logContactInfoDeclined',
        'logUpgradeListingClicked'
    );
    
    // Session namespace to store stacks
    private $sessionNamespace;
    
    public function __construct ()
    {
        // Get hold of session namespace
        $this->sessionNamespace = Myshipserv_Helper_Session::getNamespaceSafely('Myshipserv_Controller_Action_Helper_Analytics');
        
        // Initialize if not in use
        if (! is_array($this->sessionNamespace->methodStack)) {
            $this->initSession();
        }
    }
    
    /**
     * Implements proxy methods via magic method mechanism.
     */
    public function __call ($name, $args)
    {
        // Throw exception if requested method not supported
        if (! in_array($name, self::$proxiedMethods)) {
            throw new Exception("Unsupported method: $name");
        }
        
        // Proxy to underlying analytics adapter
        $res = call_user_func_array(
            array($this->getAdapter(), $name),
            $args
        );
        
        // Store method, args and result on stack
        $this->postProxy($name, $args, $res);
        
        return $res;
    }
    
    /**
     * Retrospectively update log items generated in user session
     * and flush stack.
     */
    public function flushAnalLog ()
    {
        // Pick up logged-in user, or throw exception
        $user = Shipserv_User::isLoggedIn();
        if (! is_object($user)) {
            throw new Exception("Unable to update logs: no logged-in user.");
        }
        
        // Form parameter arrays
        $methodStack = $this->sessionNamespace->methodStack;
        
        $searchRecIds = array();
        foreach ($methodStack['logSearch'] as $methodItem) {
            $logId = (int) @$methodItem[2];
            if ($logId > 0) {
                $searchRecIds[] = $logId;
            }
        }
        
        $getProfileRecIds = array();
        foreach ($methodStack['logGetProfile'] as $methodItem) {
            $logId = (int) @$methodItem[2];
            if ($logId > 0) {
                $getProfileRecIds[] = $logId;
            }
        }
        
        $adapter = $this->getAdapter();
        
        // Call method to update log retrospectively
        $adapter->updateSearchAndGetProfileRecs($user->username, $searchRecIds, $getProfileRecIds);
        
        // If there have been "logContactInfoDeclined" stacked, loop through those and update them so they're not 'declined'
        foreach ($methodStack['logContactInfoDeclined'] as $methodItem)
        {
            $logId = (string) @$methodItem[1][0]; // xml-rpc method expects id as string (!?)
            if ($logId != '')
            {
                $adapter->logContactInfoViewed($logId, $user->username);
            }
        }
        
        // Flush stacks
        $this->initSession();
    }
    
    /**
     * Provide string representation of stacks. Useful for debugging.
     */
    public function __toString ()
    {
        $methodStack = $this->sessionNamespace->methodStack;
        return print_r($methodStack, true);
    }
    
    /**
     * Flushes stacks
     */
    private function initSession ()
    {
        $methodStack = array();
        foreach (self::$proxiedMethods as $method) {
            $methodStack[$method] = array();
        }
        $this->sessionNamespace->methodStack = $methodStack;        
    }
    
    /**
     * Provides analytics adapter
     */
    private function getAdapter ()
    {
        static $adapter;
        if (! $adapter) {
            $adapter = new Shipserv_Adapters_Analytics();
        }
        return $adapter;
    }
    
    /**
     * Store method call on stack (only if the user is not logged in)
     *
     * @access private
     */
    private function postProxy ($methodName, $methodArgs, $methodResult)
    {
        $user = Shipserv_User::isLoggedIn();
        if (! is_object($user)) {
            $methodStack = $this->sessionNamespace->methodStack;
            $methodStack[$methodName][] = array($methodName, $methodArgs, $methodResult);
            $this->sessionNamespace->methodStack = $methodStack;
        }
    }
}
