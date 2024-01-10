<?php

class Search_IndexController extends Myshipserv_Controller_Action
{

    /**
     * ShipServ Home Page (/search)
     */
    public function indexAction()
    {
        $config = Zend_Registry::get('config');
        $this->_helper->layout->setLayout('default');   
        $this->getResponse()->setHeader('Expires', '', true);
        $this->getResponse()->setHeader('Cache-Control', 'public', true);

        $this->view->user = Shipserv_User::isLoggedIn();
        $this->view->googleMapsApiKey = $config->google->services->maps->apiKey;
        
        //Search Form
        $form = new Zend_Form();
        $form->setAction('/search/results')->setMethod('post')->setAttrib('id', 'anonSearch');
        $form->addElement('text', 'search-what');
        $form->addElement('text', 'search-where');
        
        // fetch the top brands, categories, and countries
        $categoriesAdapter = new Shipserv_Oracle_Categories($config->resources->db);
        $brandAdapter = new Shipserv_Oracle_Brands($config->resources->db);
        $countryAdapter = new Shipserv_Oracle_Countries($config->resources->db);
        $this->view->categories = $categoriesAdapter->fetchTopCategories();
        $this->view->brands = $brandAdapter->fetchTopBrands();
        $this->view->countries = $countryAdapter->fetchTopCountries();

        $this->view->stats = array();

        $noCache = false;
        if ((int)$config->wordpress->cache->enabled === 0 || $this->getRequest()->getParam('nocache') == 'true' || $this->getRequest()->getParam('preview') == 'true') {
            $noCache = true;
        }
        
        $wpHomePage = Shipserv_Corporate_WpApiClient::getHomePageHtml($noCache);
        $this->view->wpTitle = $wpHomePage['title'];
        $this->view->wpContent = $wpHomePage['body'];
    }
    
    /**
     * What's this??
     */
    public function headerAction()
    {
        $user = Shipserv_User::isLoggedIn();
        $this->view->user = $user;
    }

}
