<?php

require_once 'phesame/class.Phesame.php';

class SesameController extends Myshipserv_Controller_Action
{

    public function init()
    {
        /* Initialize action controller here */
    }
    
    public function indexAction()
    {
        $phesame = new Phesame('localhost:8080/sesame/servlets');
        $phesame->setResultFormat('xml');
        $phesame->setUploadFormat('rdfxml');
        $phesame->setQueryLanguage('SeRQL');
        
        print_r($phesame->listWriteable());
    }


}