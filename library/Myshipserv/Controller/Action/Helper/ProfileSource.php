<?php

/**
 * Action helper used to aid tracking of from where on the site a supplier
 * profile view is triggered.
 *
 * @author Anthony Powell <apowell@shipserv.com>
 */
class Myshipserv_Controller_Action_Helper_ProfileSource extends Myshipserv_Controller_Action_Helper_AnalyticsSource
{
    const PARAM_NAME = 'spsrc';
    
    /**
     * Override parent method to provide default parameter name.
     */
    public function getPlainKeyFromRequest ($paramName)
    {
        $res = parent::getPlainKeyFromRequest (self::PARAM_NAME);
        return $res;
    }
    
    /**
     * Implements abstract method: defines 'labels' allowed
     * for source logging.
     */
    protected function getKeyObscurer ()
    {
        static $o;
        if (! $o) {
            $o = new Myshipserv_KeyObscurer();
            
            // From a competitor 'ad' displayed on supplier profile
            $o->addKey('COMPETITOR');
            
            // From A-Z supplier directory
            $o->addKey('BROWSE_AZ');
            
            // From search result
            $o->addKey('SEARCH');
            
            // From highlight banner
            $o->addKey('HIGHLIGHT_BANNER');
        }
        return $o;
    }
}
