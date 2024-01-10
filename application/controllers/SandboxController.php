<?php

/**
 * Controller for testing ideas
 *
 * @package myshipserv
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
class SandboxController extends Myshipserv_Controller_Action
{
    
    /**
     * Add some context switching to the controller so that the appropriate
     * actions invoke XMLHTTPRequest stuff
     * 
     * @access public
     */
    public function init ()
    {
    	parent::init();
    
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('fetchRecentEvents', 'json')
                    ->initContext();
    }
    
    public function indexAction ()
    {
        // action body
    }
	
	public function searchmapAction ()
	{
		$this->_helper->layout->setLayout('blank');   
		
		$config = Zend_Registry::get('options');
		
		$this->view->googleMapsApiKey = $config['google']['services']['maps']['apiKey'];
	}
	
	/**
	 * Action for fetching recent events (searches, new listings, etc.)
	 *
	 * Returns JSON data.
	 *
	 * @access public
	 */
	public function fetchRecentEventsAction ()
	{
		$config = Zend_Registry::get('options');
		$db = $this->getInvokeArg('bootstrap')->getResource('db');
		
			//
			//var message = '<h3>Searching for ' + location.keywords;
			//if (location.keywords) {
			//	message+= ' in ' + location.where
			//}
			//message+= '</h3><h4>' + location.numResults + ' matches found</h4>';
			//
		
		$data = array();
		$data['Locations'][] = array('eventType'  => 'search',
									 'keywords'   => 'Alfa Laval',
									 'mainTitle'  => 'Searching for Alfa Laval in Singapore',
									 'subTitle'   => '137 matches found',
									 'link'       => '/',
									 'where'      => 'Singapore',
									 'numResults' => 137,
									 'lat'        => '0.00',
									 'lng'        => '0.00');
		
		$data['Locations'][] = array('eventType'  => 'search',
									 'keywords'   => 'Ship Repair Services',
									 'mainTitle'  => 'Searching for Ship Repair Services in United Kingdom',
									 'subTitle'   => '19 matches found',
									 'link'       => '/',
									 'where'      => 'United Kingdom',
									 'numResults' => 19,
									 'lat'        => '44.797916',
									 'lng'        => '-93.278046');
		
		$data['Locations'][] = array('eventType'  => 'search',
									 'keywords'   => 'Chandlery',
									 'mainTitle'  => 'Searching for Chandlery in Panama',
									 'subTitle'   => '21 matches found',
									 'link'       => '/',
									 'where'      => 'Panama',
									 'numResults' => 21,
									 'lat'        => '42.797916',
									 'lng'        => '-73.278046');
		
		$data['Locations'][] = array('eventType'  => 'search',
									 'keywords'   => 'Rope',
									 'mainTitle'  => 'Searching for Rope in China',
									 'subTitle'   => '78 matches found',
									 'link'       => '/',
									 'where'      => 'China',
									 'numResults' => 78,
									 'lat'        => '23.797916',
									 'lng'        => '23.278046');
		
		$data['Locations'][] = array('eventType'  => 'search',
									 'keywords'   => 'Medical & Hospital',
									 'mainTitle'  => 'Searching for Medical & Hospital in Japan',
									 'subTitle'   => '213 matches found',
									 'link'       => '/',
									 'where'      => 'Japan',
									 'numResults' => 213,
									 'lat'        => '23.797916',
									 'lng'        => '53.278046');
		
		$data['Locations'][] = array('eventType'  => 'search',
									 'keywords'   => 'Auxiliary Engine Spares',
									 'mainTitle'  => 'Searching for Auxiliary Engine Spares in Japan',
									 'subTitle'   => '43 matches found',
									 'link'       => '/',
									 'where'      => 'Japan',
									 'numResults' => 43,
									 'lat'        => '13.797916',
									 'lng'        => '73.278046');
		
		$data['Locations'][] = array('eventType'  => 'search',
									 'keywords'   => 'rubber chock',
									 'mainTitle'  => 'Searching for rubber chock in Botswana',
									 'subTitle'   => '4 matches found',
									 'link'       => '/',
									 'where'      => 'Botswana',
									 'numResults' => 4,
									 'lat'        => '14.797916',
									 'lng'        => '80.278046');
		
		$data['Locations'][] = array('eventType'  => 'newlisting',
									 'mainTitle'  => 'Scania Botswana (Pty) Ltd joined ShipServ',
									 'subTitle'   => 'Gaborone, Botswana',
									 'link'       => '/supplier/profile/s/scania-botswana-pty-ltd-85233',
									 'lat'        => '0.797916',
									 'lng'        => '85.278046');
		
		$this->_helper->json((array)$data);
	}
	
	public function ajwpAction ()
	{
		exit;
	}
	
	private function getDb ()
	{
		return $this->getInvokeArg('bootstrap')->getResource('db');
	}
}
