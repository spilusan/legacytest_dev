<?php

/**
 * Controller for ontology requests - fetch ports, countries, categories, brands, etc.
 *
 * @package Myshipserv
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
class OntologyController extends Myshipserv_Controller_Action {

    /**
     * Initialise the controller - set up the context helpers for AJAX calls
     * 
     * @access public
     */
    public function init() {
        parent::init();
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('country-browse', array('html', 'json'))
                ->addActionContext('category-parse', array('html', 'json'))
                ->addActionContext('port-browse', array('html', 'json'))
                ->initContext();
    }

    //Interface to output Categories for found 
    public function categoryParseAction() {
        $params = $this->params;

        // fetch the DB resource
        $db = Shipserv_Helper_Database::getDb();

        $helper = new Shipserv_Helper_Pattern();

        $categories = $helper->parseCategories($params['input']);

        // Send to the view as JSON or a PHP array
        if ($params['format'] == 'json') {
            $this->_helper->json((array)$categories);
        } else {
            $this->view->categories = $categories;
        }
    }

    /**
     * Controller action for browsing countries (organised by continent)
     * 
     * @access public
     */
    public function countryBrowseAction() {
        $params = $this->params;

        // fetch the DB resource
        $db = $this->getInvokeArg('bootstrap')->getResource('db');

        // fetch the ports for the autocomplete and place in an array
        $countriesAdapter = new Shipserv_Oracle_Countries($db);

        $countries = $countriesAdapter->fetchCountriesByContinent();

        // Send to the view as JSON or a PHP array
        if ($params['format'] == 'json') {
            $this->_helper->json((array)$countries);
        } else {
            $this->view->countries = $countries;
        }
    }

    /**
     * Controller action for browsing ports
     * 
     * @access public
     */
    public function portBrowseAction() {
        $params = $this->params;

        // fetch the DB resource
        $db = $this->getInvokeArg('bootstrap')->getResource('db');

        $portAdapter = new Shipserv_Oracle_Ports($db);

        $ports = $portAdapter->fetchPortsByCountry($params['countryCode']);

        if ($params['format'] == 'json') {
            $this->_helper->json((array)$ports);
        } else {
            $this->view->countries = $ports;
        }
    }

}