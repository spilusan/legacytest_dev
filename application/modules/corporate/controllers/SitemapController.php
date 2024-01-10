<?php

class Corporate_SitemapController extends Myshipserv_Controller_Action
{
    /**
     * Inherit Zend init function 
     * 
     * @see Myshipserv_Controller_Action::init()
     */
    public function init()
    {
        parent::init();
        $this->_helper->viewRenderer->setNoRender(true);
        $this->_helper->layout->disableLayout();
        $this->_helper->getHelper('contextSwitch')
            ->addActionContext('child', 'xml')
            ->addActionContext('index', 'xml')
            ->initContext('xml');
    }
    
    
    /**
     * Get the sitemap from Wp using Shipserv_Corporate_WpApiClient
     * 
     * @param String $sitemapName
     */
    private function _getSitemap($sitemapName)
    {   
        if (!$sitemapName || !preg_match('/.*\.xml$/', $sitemapName)) {
            throw new Zend_Controller_Action_Exception('No correct sitemap name provided', 404);
        }
        $siteMap = Shipserv_Corporate_WpApiClient::getSitemap($sitemapName);
        if (!$siteMap) {
            throw new Zend_Controller_Action_Exception('No sitemap with this name', 404);
        }
        echo $siteMap; 
    }
    
    
    
    /**
     * Dynamic route to proxy wp content tog et child sitemaps
     */
    public function childAction()
    {
        return $this->_getSitemap($this->getRequest()->getParam('sitemap-name'));
    }


    /**
     * Dynamic route to proxy wp content to get main sitemap
     */
    public function indexAction()
    {
        return $this->_getSitemap('sitemap.xml');
    }    
}
