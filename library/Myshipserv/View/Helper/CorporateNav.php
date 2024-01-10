<?php

/**
 * Navigation of the corporate (wp based website)
 * @author claudio
 *
 */
class Myshipserv_View_Helper_CorporateNav extends Zend_View_Helper_Abstract
{

    const MAX_CORPORATE_PARENT_ITEMS = 6;
    
    /**
     * @var Myshipserv_View_Helper_Navigation_WpTab
     */
    private static $_instance = null;
    
    /**
     * @var Array $fullNav
     */
    protected $fullNav = null;
    
    /**
     * @var Bool
     */
    protected $noCache = false;

    /**
     * @var Zend_Controller_Request_Abstract
     */
    protected $req = null;
    
    
    /**
     * Singleton method
     */
    public static function getInstance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new static();
            $config = Myshipserv_Config::getIni();
            self::$_instance->req = Zend_Controller_Front::getInstance()->getRequest();
            self::$_instance->noCache = false;
            if (!$config->wordpress->cache->enabled || self::$_instance->req->getParam('nocache') == 'true') {
                self::$_instance->noCache = true;
            }            
        }
        return self::$_instance;
    }
    
    
    /**
     * Method needed for Zend View helper
     * 
     * @return Myshipserv_View_Helper_Navigation_WpTab
     */
    public function corporateNav()
    {
        return $this->getInstance();    
    }

    
    /**
     * Get the full navigation of the corporate website (WP) 
     * 
     * @return Array
     */
    public function getFullNav()
    {
        if ($this->fullNav) {
            return $this->fullNav;
        }
        //Get the menu through eventually-cached api call, or fallback on an hardcoded one
        $menuArr = (Array) Shipserv_Corporate_WpApiClient::getMenu($this->noCache);
        if (!count($menuArr)) {
            //trigger_error('Shipserv_Corporate_WpApiClient::getMenu() returned empty or non valid object. Falling back on static menu');
            return self::_getFullNavFallback();
        }

        //If the menu has more than 6 levels, we'll cut it out. Having more than 4 first level menu items would break the responsive design css rules
        $menuArr = array_slice($menuArr, 0, self::MAX_CORPORATE_PARENT_ITEMS);

        //Iter on parent items
        $isCorporateSite = ($this->req->getModuleName() === 'corporate' && $this->req->getControllerName() === 'index'); 
        $fullNav = array();
        foreach ($menuArr as $curParentMenuItem) {
            $isParentSelected = false;
            $parentSlug = trim(str_replace('/info/', '', parse_url($curParentMenuItem->url, PHP_URL_PATH)), '/#');
            //Should be this 1st level menu item formatted as selected?
            if (
                $isCorporateSite //Of course we need to be in corporate website
                && !$isParentSelected //No other second lev item already selected
                && (
                    $parentSlug && in_array($parentSlug, array($this->req->getParam('slug'), $this->req->getParam('parentSlug'), $this->req->getParam('parentOfParentSlug')))
                    || ($parentSlug === 'about-us' && preg_match('/^\/info\/news-feed/', $_SERVER['REQUEST_URI'])) //We are on blog page or a blog post
                )
            ) {
                $isParentSelected = true;
            }

            //Iter on children items
            $children = array();
            if (count((Array) $curParentMenuItem->children)) {
                foreach ($curParentMenuItem->children as $curChildMenuItem) {
                    $isChildSelected = false;
                    $isProductPage = false;
                    //Should be this 2nd level menu item formatted as selected?
                    if ($isCorporateSite && !$isChildSelected) {
                        //We are on a page of second nav level
                        if (rtrim($curChildMenuItem->url, '/') === rtrim($this->req->getRequestUri(), '/')) {
                            $isChildSelected = true;
                        //If we are on a blog (news-feed) page, we should highlight the news and events menu tab
                        } elseif (rtrim($curChildMenuItem->url, '/') === '/info/about-us/news-and-events' && preg_match('/^\/info\/news-feed/', $this->req->getRequestUri())) {
                            $isChildSelected = true;                            
                        //We are on a child page of a second nav level item
                        } elseif (strpos($this->req->getRequestUri(), $curChildMenuItem->url) !== false) {
                            $isProductPage = true;
                            $isChildSelected = true;                            
                        }
                    }                 
                    
                    $idstr = 'wp-id-' . $curChildMenuItem->id;
                    $children[$idstr] = self::_menuItem($idstr, $curChildMenuItem->title, $isChildSelected, $isProductPage, $curChildMenuItem->url);
                }
            }
            
            $idstr = 'wp-id-' . $curParentMenuItem->id;
            $fullNav[$idstr] = self::_menuItem($idstr, $curParentMenuItem->title, $isParentSelected, false, $curParentMenuItem->url, $children);
        }
        //echo '<pre>'; echo json_encode($fullNav, JSON_PRETTY_PRINT); echo '</pre>'; die;
        //return $this->fullNav = $fullNav;
        return array();
    }

    
    /**
     * Get the second level of the currently selected 1st level navigation menu item
     *
     * @return Array
     */
    public function getNavCurrentSecondLevel()
    {
        foreach ($this->getFullNav() as $navItem) {
            if ($navItem['isSelected']) {
                if (!is_array($navItem['children']) || !count($navItem['children'])) {
                    return array();
                }
                //The parent should be added also to second level navigation, and should appear selected if no other child is selected                
                $isParentPage = true;
                foreach ($navItem['children'] as $child) {
                    if ($child['isSelected']) {
                        $isParentPage = false;
                        break;
                    }
                }
                return array_merge(
                    array($navItem['idstr'] => self::_menuItem($navItem['idstr'], $navItem['title'], $isParentPage, false, $navItem['href'])),
                    $navItem['children']
                );

            }
        }
        //if no children found, return empty array
        return array();
    }    
    
    
    /**
     * If there are some problems retrieving WP menu, the code will fallback on this method, with an hardcoded "better than nothign" menu
     * @return Array
     */
    private static function _getFullNavFallback()
    {
        /*return array(
            self::_menuItem('wp-id-16', 'Supplier Solutions', false, false, '/info/pages-for-suppliers'),
            self::_menuItem('wp-id-91', 'Buyer Solutions', false, false, '/info/save-time-and-money-with-tradenet'),
            self::_menuItem('wp-id-901', 'Our Customers', false, false, '/info/the-community'),
            self::_menuItem('wp-id-770', 'About', false, false, '/info/about-us'),
            self::_menuItem('wp-id-23868', 'Contact us', false, false, '/info/contact-us'),
            self::_menuItem('wp-id-28018', 'Help', false, false, '/info/help')
        );*/
        return array();
    }
    
    
    /**
     * Generate standard menu item array filling it in with input params
     *  
     * @param String $idstr  a string identifier. not very important actually. this is just an id that can be used for instance for css or js reference 
     * @param String $title
     * @param Boolean $isSelected
     * @param Boolean $isProductPage
     * @param String $href
     * @param Array|Null $children
     * @return Array
     */
    private static function _menuItem($idstr, $title, $isSelected, $isProductPage, $href, array $children = array())
    {
        return array(
            'idstr' => (String) $idstr, 
            'title' => (String) $title, 
            'isSelected' => (Boolean) $isSelected, 
            'href' => (String) $href,
            'children' => $children,
            'isProductPage' => $isProductPage
        );
    }
}
