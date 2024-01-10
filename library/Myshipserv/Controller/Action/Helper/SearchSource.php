<?php

/**
 * Action helper used to aid tracking of from where on the site a search
 * is triggered.
 *
 * @author Anthony Powell <apowell@shipserv.com>
 */
class Myshipserv_Controller_Action_Helper_SearchSource extends Myshipserv_Controller_Action_Helper_AnalyticsSource
{
    const PARAM_NAME = 'ssrc';
    
    /**
     * Override parent method to provide default parameter name.
     */
    public function getPlainKeyFromRequest($paramName = null)
    {
        $res = parent::getPlainKeyFromRequest (self::PARAM_NAME);
        return $res;
    }
    
    /**
     * Implements abstract method: defines 'labels' allowed
     * for source logging.
     */
    public function getKeyObscurer ()
    {
        static $o;
        if (! $o) {
            $o = new Myshipserv_KeyObscurer();
            
            // Primary search box
            $o->addKey('KEYWORD');
            
            // From browse suppliers by category
            $o->addKey('BROWSE_CATEGORY');
            
            // From browse suppliers by brand
            $o->addKey('BROWSE_BRAND');
            
            // From browse suppliers by country
            $o->addKey('BROWSE_COUNTRY');
            
			// From home page top searches by category
            $o->addKey('HP_TOP_CATEGORY');
            
            // From home page top searches by brand
            $o->addKey('HP_TOP_BRAND');
            
            // From home page top searches by country
            $o->addKey('HP_TOP_COUNTRY');
			
            // From browse suppliers by country
            $o->addKey('BROWSE_PORT');
            
			// Related search, from search results page
			$o->addKey('RELATED_FROM_SEARCH');
			
			// Related search, from search results page
			$o->addKey('REFINED_FROM_SEARCH');
			
            // Find similar suppliers from supplier profile page
            $o->addKey('RELATED_FROM_PROFILE');
			
            // Find similar suppliers from supplier profile page
            $o->addKey('ZONE_INVITE_FROM_SEARCH');
			
            // Find similar suppliers from supplier profile page
            $o->addKey('ZONE_EXIT_INVITE');
			
			// Searches shown on the event map
			$o->addKey('EVENT_MAP');
			
			// Searches linked from the profile page
			$o->addKey('PROFILE_PAGE');

        	// Searches linked from the highlight banner page
			$o->addKey('HIGHLIGHT_BANNER');
        }
        return $o;
    }
}
