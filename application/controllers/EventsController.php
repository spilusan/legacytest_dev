<?php

/**
 * Controller for testing ideas
 *
 * @package myshipserv
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
class EventsController extends Myshipserv_Controller_Action
{
    /**
     * Add some context switching to the controller so that the appropriate
     * actions invoke XMLHTTPRequest stuff
     * 
     * @access public
     */
    public function init()
    {
    	parent::init();
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('getRecentEvents', 'json')
                    ->initContext();
    }
	
	/**
	 * Action for fetching recent events (searches, new listings, etc.)
	 * 
	 * Returns JSON data.
	 * 
	 * @access public
	 */
	public function fetchRecentEventsAction()
	{
		$db = $this->db;
		
		$events = new Shipserv_Events($db);
		$data = array();
		$data['Locations'] = $events->fetchRecentEvents();
		$this->_helper->json((array)$data);
	}


	/**
	 * Action for fetching recent events as they are needed in HP event map
	 *
	 * Returns JSON data.
	 *
	 * @access public
	 */
	public function fetchRecentEventsHpAction()
	{
	    $data = array('Locations' => array());
	    //Normally we don't have enough data to test in local. Let's optionally get data from live
	    if ($this->getRequest()->getParam('preview') == 'true' && !Myshipserv_Config::isInProduction()) {
    	    $json = file_get_contents('http://www.shipserv.com/events/fetch-recent-events/fetch/10');
    	    foreach (json_decode($json)->Locations as $row) {
    	        if ($row->eventType == 'search' && $row->lat != "" && $row->lng != '') {
    	            $data['Locations'][] = $row;
    	        } elseif ($row->eventType != 'search') {
    	            $data['Locations'][] = $row;
    	        }
    	    }    	    
	    } else {
	        $events = new Shipserv_Events($this->db);
	        foreach ($events->fetchRecentEvents(10000) as $row) {
	            if ($row['eventType'] == 'search' && $row->lat != "" && $row->lng != '') {
	                $data['Locations'][] = $row;
	            } elseif ($row['eventType'] != 'search') {
	                $data['Locations'][] = $row;
	            }
	        }	        
	    }
	    $this->_helper->json((array)$data);
	}
	
	
	/**
	 * Action for fetching stats (buyer visits, buyer searches, new suppliers) 
	 *
	 * Returns JSON data.
	 *
	 * @access public
	 */
	public function fetchStatsAction()
	{
        $eventsAdapter = new Shipserv_Events($this->config['resources']['db']);
        $gapi = new Myshipserv_GAnalytics();
        $stats = array(
            'buyers_visited'     => (Myshipserv_Config::isInDevelopment()? '123456' : $gapi->getSiteVisits()), //Loading google data in local lead to long delays
            'buyers_searches'     => $eventsAdapter->fetchSearchEventsCount(7),
            'new_suppliers'      => $eventsAdapter->fetchNewSuppliersCount(30),
            //'suppliers_updated'  => number_format($eventsAdapter->fetchUpdatedSuppliersCount(30), 0, '.', ',')
        );
	    $this->_helper->json((array)$stats);
	}
	
	
	/**
	 * Action for generating the full screen map
	 *
	 * @access public
	 */
	public function fullScreenMapAction()
	{
		$this->view->user = $this->user;
		$config = $this->config;
		
		$this->view->googleMapsApiKey = $config['google']['services']['maps']['apiKey'];
	}

	/**
	 * Action for generating the full screen map with counter
	 *
	 * @access public
	 */
	public function largeScreenMapAction()
	{
		$this->_helper->layout->setLayout('blank');
		$this->view->user = $this->user;
		$this->view->params = $this->params;
		$config = $this->config;
		
		$this->view->googleMapsApiKey = $config['google']['services']['maps']['apiKey'];
	}

	/**
	 * Action for full screen map counter start value setup form
	 *
	 * @access public
	 */
	public function setupMapAction()
	{
		$this->_helper->layout->setLayout('blank');
		$this->view->user = $this->user;
		$config = $this->config;
		
		$this->view->googleMapsApiKey = $config['google']['services']['maps']['apiKey'];
	}

	/**
	 * Action for generating iframe screen map for third party sites
	 *
	 * @access public
	 */
	public function iframeMapAction()
	{
		$this->_helper->layout->setLayout('blank');
		$this->view->user = $this->user;
		$this->view->params = $this->params;
		$config = $this->config;
		
		$this->view->googleMapsApiKey = $config['google']['services']['maps']['apiKey'];
	}
}