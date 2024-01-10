<?php

class Shipserv_Corporate_WpApiClient extends Shipserv_Object
{
    
    const FIND_POST_BY_SEARCH = 'search';
    const FIND_POST_BY_YEAR = 'year';
    const FIND_POST_BY_CATEGORY = 'category';
    const FIND_POST_BY_INDEX = 'index';    
    const HOME_PAGE_ID = 23670;
    const HOME_PAGE_SLUG = 'shipserv-home-page-components';
    
    /**
     * Make an http request to WP
     * 
     * @param String $url
     * @param string $httpMethod
     * @param array $getParams
     * @param array $postParams
     * @param bool $httpAuth
     * @param bool $noCache 
     * @throws Shipserv_Corporate_WpApiException
     * @return String|null  return the http body as string, or null if the call failed 
     */
    private static function _wpHttpRequest($url, $httpMethod = 'GET', $getParams = array(), $postParams = array(), $httpAuth = false, $noCache = false)
    {
        //retireve from memcache
        $memcacheKey = __CLASS__ . '__' . __FUNCTION__ .'__' . md5(serialize(func_get_args()));
        if (!$noCache) {
            $memcache = self::getMemcache();
            if ($memcache) {
                $obj = $memcache->get($memcacheKey);
                if ($obj) {
                    return $obj;
                }
            }
        }
        
        //do api call
        try {
            $client = new Zend_Http_Client($url);    
        } catch(Zend_Uri_Exception $e) {
            throw new Myshipserv_Exception_MessagedException("Page Not Found, " . $e->getMessage(), 404);
        }
        
        $client->setMethod($httpMethod);
        $client->setParameterGet($getParams);
        $client->setParameterPost($postParams);
        if ($httpAuth) {
            $config = self::getConfig();
            $client->setAuth($config->wordpress->user, $config->wordpress->password);
        }
        try
        {
            $response = $client->request();
            if (!$response->isSuccessful()) {
                if ($response->getStatus() == 404) {
                    return null;
                }
                throw new Shipserv_Corporate_WpApiException('Zend_Http_Client call returned not successful response: ' . $response->__toString());
            }
            $body = $response->getBody();
            //save in memcache
            if (!$noCache && $body) {
                $memcache = self::getMemcache();
                if ($memcache) {
                    $memcache->set($memcacheKey, $body, null, 60 * 10);
                }
            } 
            return $body;
        }
        catch (Exception $e) {
            trigger_error(sprintf('%s::%s: call to %s failed with exception %s', __CLASS__, __FUNCTION__, $url, (String) $e), E_USER_WARNING);
            return null;
        }        
    }


    /**
     * Make an http api request to WP and convert the body to json
     * This is meant to be used for http calls using wp api plugin
     *
     * @param String $url
     * @param string $httpMethod
     * @param array $getParams
     * @param array $postParams
     * @param bool $httpAuth
     * @param bool $noCache
     * @throws Shipserv_Corporate_WpApiException
     * @return Array|null  return the json response converted to Array, or null if the call failed
     */    
    private static function _wpApiRequest($url, $httpMethod = 'GET', $getParams = array(), $postParams = array(), $httpAuth = false, $noCache = false)
    { 
        $body = self::_wpHttpRequest($url, $httpMethod, $getParams, $postParams, $httpAuth, $noCache);
        return Zend_Json::decode($body);
    }

    
    /**
     * Get a wordpress page or post by slug and return it as Shipserv_Corporate_WpPage 
     *  
     * @param String $slug
     * @param String $expectedUrlPath
     * @param Bool $isPage
     * @param Bool $noCache
     * @return Shipserv_Corporate_WpPage|null
     */
    private static function _getPageOrPostBySlug($slug, $expectedUrlPath, $isPage = true, $noCache = false)
    {
        $slug = trim($slug);
        //api call to WP
        $wpPageUrl = (
            trim(self::getConfig()->wordpress->baseurl->internal, ' /') 
            . '/wp-json/wp/v2/' 
            . ($isPage? 'pages' : 'posts') 
            . '?slug=' 
            . $slug
        );
        $pages = self::_wpApiRequest($wpPageUrl, 'GET', array(), array(), false, $noCache);

        /*
        * /wp-json/wp/v2/pages?slug=my-slug returns an arry. Indeed there might be more than 1 pages with the same slug.
        * the following code block is aimed to:
        * 1) retun the first matching page in case of multiple occurrencies
        * 2) return null if slug is correct but expected url is not (to avoid duplicated pages which would otherwise exist)
        */
        $expectedUrlPath = str_replace('/', '\/', $expectedUrlPath);
        $wpPage = null;
        foreach ((Array) $pages as $page) {
            if (preg_match("/$expectedUrlPath/i", $page['link'])) {
                $wpPage = $page;
                break;
            }
        }
        if (!$wpPage) {
            return null;
        }

        //Get the full html content calling the page itself
        $fullPageLink = preg_replace('/(https?:\/\/[a-zA-Z0-1\._-]*)(\/.*)/i', self::getConfig()->wordpress->baseurl->internal.'$2', $wpPage['link']);
        $fullHtml = self::_wpHttpRequest($fullPageLink, 'GET', array(), array(), false, $noCache);
        
        //convert from array to our internal WpPage object
        return new Shipserv_Corporate_WpPage($wpPage, $fullHtml);  
    }
    

    /**
     * Get a list of wordpress posts searching by category, search string or year
     * This is returned not as a list, but as Shipserv_Corporate_WpPage as usual. 
     * We don't need indeed to fecth through the list but just to display the html returned by WP
     *
     * @param self::FIND_POST_BY_SEARCH|self::FIND_POST_BY_CATEGORY|self::FIND_POST_BY_VALUE|self::FIND_POST_BY_INDEX $paramName
     * @param String $paramValue
     * @param Int $page  the pagination page number 
     * @param Bool $noCache
     * @return Shipserv_Corporate_WpPage
     */
    public static function findPostsBy($paramName, $paramValue, $page = 1, $noCache = false)
    {
        //We do not need to call the api for the moment. Getting the html is enoguh
        /*
        $query = $paramName . '=' . $paramValue; 
        if ($paramName === 'year') {
            $paramValue = (Int) $paramValue;
            $query = (
                'filter[date_query][after]=' 
                . ($paramValue - 1) //previous year 
                . '-12-31T00:00:00&filter[date_query][before]='
                . ($paramValue + 1) //next year
                . '-01-01T00:00:00'
            );
        }
        //api call to WP
        $wpPageUrl = (
            trim(self::getConfig()->wordpress->baseurl->internal, ' /')
            . '/wp-json/wp/v2/posts?' 
            . $query
        );
        $posts = self::_wpApiRequest($wpPageUrl, 'GET', array(), array(), false, $noCache);        
        */

        if ($paramName === self::FIND_POST_BY_CATEGORY) {
            $publicLink = '/info/news-feed/category/' . $paramValue;
            $path = '/news-feed/category/' . $paramValue;
            $query = '';
            if ($page > 1) {
                $path .= '/page/' . $page;
                $publicLink .= '/page/' . $page;
            }
        } elseif ($paramName === self::FIND_POST_BY_SEARCH) {
            $publicLink = '/info/news-feed/search?query=' . $paramValue;
            $path = '';
            $query = '?post_type=post&s=' . urlencode($paramValue);
            if ($page > 1) {
                $path = '/page/' . $page;
            }   
        } elseif ($paramName === self::FIND_POST_BY_YEAR) {
            $publicLink = '/info/news-feed/' . $paramValue;
            $path = '/news-feed/' . $paramValue;
            $query = '';
            if ($page > 1) {
                $path .= '/page/' . $page;
                $publicLink .= '/page/' . $page;
            }
        } elseif ($paramName === self::FIND_POST_BY_INDEX) {
            $publicLink = '/info/news-feed';
            $path = '/news-feed/';
            $query = '';
            if ($page > 1) {
                $path .= '/page/' . $page;
                $publicLink .= '/page/' . $page;
            }            
        } else {
            return array();
        }
        //Get the full html content calling the page itself
        $wpPageUrl = trim(self::getConfig()->wordpress->baseurl->internal, ' /') . $path . $query;
        $fullHtml = self::_wpHttpRequest($wpPageUrl, 'GET', array(), array(), false, $noCache);
        if (!$fullHtml) {
            return $fullHtml;
        }
        
        //Shipserv_Corporate_WpPage constructor is expecting an array as first param. 
        //This is normally thw wp-json api response typically the search by slug. 
        //Here we are not calling the api, so we need to create the array manually
        $wpPage = array(
            'id' => null,
            'slug' => null,
            'link' => $publicLink,
            'date_gmt' => null,
            'modified_gmt' => null,
            'title' => array('rendered' => "News feed $paramName $paramValue"),
            'content' => array('rendered' => ''),            
        );
        
        //convert from array to our internal WpPage object
        return new Shipserv_Corporate_WpPage($wpPage, $fullHtml);
    }
    
    
    /**
     * Get a wordpress page (Shipserv_Corporate_WpPage) by slug
     *
     * @param String $slug
     * @param String $expectedUrlPath
     * @param Bool $noCache
     * @return Shipserv_Corporate_WpPage|null
     */
    public static function getPageBySlug($slug, $expectedUrlPath, $noCache = false)
    {
        return self::_getPageOrPostBySlug($slug, $expectedUrlPath, true, $noCache);
    }
    
    
    /**
     * Get a wordpress post (which is however returned as a Shipserv_Corporate_WpPage) by slug
     *
     * @param String $slug
     * @param String $expectedUrlPath
     * @param Bool $noCache
     * @return Shipserv_Corporate_WpPage|null
     */
    public static function getPostBySlug($slug, $expectedUrlPath, $noCache = false)
    {
        return self::_getPageOrPostBySlug($slug, $expectedUrlPath, false, $noCache);
    }
    
    
    /**
     * Get a wordpress page (Shipserv_Corporate_WpPage) by id
     *
     * @param Int $id
     * @param Bool $noCache 
     * @return Shipserv_Corporate_WpPage|null
     */    
    public static function getPageById($id, $noCache = false)
    {
        return self::_getPageOrPostById($id, true, $noCache);
    }
    
    
    /**
     * Get a wordpress post (still a Shipserv_Corporate_WpPage object) by id
     *
     * @param Int $id
     * @param Bool $noCache
     * @return Shipserv_Corporate_WpPage|null
     */
    public static function getPostById($id, $noCache = false)
    {
        return self::_getPageOrPostById($id, false, $noCache);
    }
    
    
    /**
     * Get a wordpress page or post (Shipserv_Corporate_WpPage in both cases) by id
     *
     * @param Int $id
     * @param Bool $isPage
     * @param Bool $noCache
     * @return Shipserv_Corporate_WpPage|null
     */
    private static function _getPageOrPostById($id, $isPage = true, $noCache = false)
    {
        $httpAuth = ((self::getUser() && self::getUser()->isShipservUser()) || (substr($_SERVER['REQUEST_URI'], 0, 19) === '/info/private-page/' || substr($_SERVER['REQUEST_URI'], 0, 19) === '/info/private-post/'));
        
        if ($isPage) {
            $wpPageUrl = (trim(self::getConfig()->wordpress->baseurl->internal, ' /') . '/wp-json/wp/v2/pages/' . $id);
        } else {
            $wpPageUrl = (trim(self::getConfig()->wordpress->baseurl->internal, ' /') . '/wp-json/wp/v2/posts/' . $id);
        }
        
        $wpPage = self::_wpApiRequest($wpPageUrl, 'GET', array(), array(), $httpAuth, $noCache);
        if (!$wpPage) {
            return null;
        }
    
        //Get the full html content calling the page itself
        $fullPageLink = preg_replace('/(https?:\/\/[a-zA-Z0-1\._-]*)(\/.*)/i', self::getConfig()->wordpress->baseurl->internal.'$2', $wpPage['link']);
        $fullHtml = self::_wpHttpRequest($fullPageLink, 'GET', array(), array(), $httpAuth, $noCache);
    
        return new Shipserv_Corporate_WpPage($wpPage, $fullHtml);
    }
    
    
    /**
     * Get the html that we need to insert into the HP
     * 
     * @param string $noCache
     * @return String (html)
     */
    public static function getHomePageHtml($noCache = false)
    {
        $expectedUrlPath = '/' . self::HOME_PAGE_SLUG;
        $expectedUrlPath = preg_replace('/\/+/', '/', $expectedUrlPath);
        $wpPage = Shipserv_Corporate_WpApiClient::getPageBySlug(self::HOME_PAGE_SLUG, $expectedUrlPath, $noCache);
        
        return array(
            'title' => $wpPage->htmlTitle,
            'description' => $wpPage->htmlDescription,
            'body' =>  Shipserv_Corporate_WpPage::transformHtml(
                $wpPage->htmlFullBody
            )
        );
    }

    /**
     * Get Sitemap xml replacing wp urls with our public Pages urls
     * 
     * @param String $sitemapName
     * @return String (XML)
     */
    public static function getSitemap($sitemapName)
    {
        $wpBaseUrl = self::getConfig()->wordpress->baseurl->external;
        $pagesBaseUrl = 'https://' . self::getConfig()->shipserv->application->hostname;
        $sitemapName = $wpBaseUrl . '/' . $sitemapName;
        $siteMap = self::_wpHttpRequest($sitemapName, 'GET', array(), array(), false, 1);
        
        //correct link for sitemap childs
        $siteMap = str_replace($wpBaseUrl . '/sitemap', $pagesBaseUrl . '/info/sitemap', $siteMap); 
        $siteMap = str_replace('https://wordpress.shipserv.com' . '/sitemap', $pagesBaseUrl . '/info/sitemap', $siteMap); 
        
        //correct link for pages urls
        $siteMap = str_replace($wpBaseUrl, $pagesBaseUrl . '/info', $siteMap);
        $siteMap = str_replace('https://wordpress.shipserv.com', $pagesBaseUrl . '/info', $siteMap);
        
        return $siteMap;
    }
    
    
    /**
     * Get the primary navigation of corporate website
     * 
     * @param Bool $noCache
     * @return Shipserv_Corporate_WpMenuItem[]|null
     */
    public static function getMenu($noCache = false)
    {
        $wpMenuUrl = trim(self::getConfig()->wordpress->baseurl->internal, ' /') . '/wp-json/wp-api-menus/v2/menus/2';
        $wpMenu = self::_wpApiRequest($wpMenuUrl, 'GET', array(), array(), false, $noCache);
        if (!$wpMenu || !isset($wpMenu['items'])) {
            return null;
        }
        return Shipserv_Corporate_WpMenuItem::wpApiResponse2WpPageList($wpMenu);
    }      
}
