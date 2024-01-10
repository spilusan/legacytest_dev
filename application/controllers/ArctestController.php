<?php

class ArctestController extends Zend_Controller_Action
{

    public function init()
    {
        /* Initialize action controller here */
    }

    public function indexAction()
    {
        $bootstrap   = $this->getInvokeArg('bootstrap');
        $configArray = $bootstrap->getOptions();
        $ontology    = new Shipserv_Adapters_Ontology($configArray['shipserv']['services']['ontology']['url']);
        
        $gmdss     = $ontology->query('SELECT * WHERE { ?ptype <http://rdf.myshipserv.com/schema/zone> <http://rdf.myshipserv.com/brands.rdf#gmdss> }');
        $lifeboats = $ontology->query('SELECT * WHERE { ?ptype <http://rdf.myshipserv.com/schema/zone> <http://rdf.myshipserv.com/brands.rdf#lifeboats> }');
        
        $this->view->serviceURL = $configArray['shipserv']['services']['ontology']['url'];
        $this->view->gmdss      = $gmdss;
        $this->view->lifeboats  = $lifeboats;
        
    }
}