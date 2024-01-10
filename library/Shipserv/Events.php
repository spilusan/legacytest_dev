<?php

/**
 * Events Object
 * 
 * @package Shipserv
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2010, ShipServ Ltd
 */
class Shipserv_Events extends Shipserv_Object
{
	private $oracleAdapter;
	
	/**
	 * Defines whether memcache should be used
	 *
	 * @access private
	 * @var boolean
	 */
	private $useCache = false;
	
	/**
	 * The memcache TTL
	 * 
	 * @access private
	 * @var int
	 */
	private $cacheTTL = 600;
	
	/**
	 * Array of event types, associated with the adapter method that fetches
	 * them. The 'count' parameter is the maximum number of events to fetch,
	 * as long as count($this->events) + 'count' <= $this->numEvents
	 *
	 * Note that the order of this array is important. Elements further down the
	 * list will be skipped if $this->numEvents has been reached
	 * 
	 * @access private
	 * @var array
	 */
	private $eventTypes = array('searches'         => array('method' => 'fetchSearchEvents',
															'count'  => 35),
								'ordersReceived'   => array('method' => 'fetchOrderEvents',
													        'count'  => 35),
								'enquiries'        => array('method' => 'fetchEnquiryEvents',
															'count'  => 20),
								'impressions'      => array('method' => 'fetchImpressionEvents',
															'count'  => 20),
								'newSuppliers'     => array('method' => 'fetchNewSupplierEvents',
															'count'  => 10),
								'updatedSuppliers' => array('method' => 'fetchUpdatedSupplierEvents',
															'count'  => 10),
								'rfqsSent'         => array('method' => 'fetchRfqsSent',
															'count'  => 10),
								'ordersAccepted'   => array('method' => 'fetchOrderAcceptedEvents',
															'count'  => 10)
								);
	
	/**
	 * The number of events of each type that should be fetched
	 * 
	 * @abstract private
	 * @var int
	 */
	private $numEvents;
	
	/**
	 * The events data that is pulled from the DB
	 * 
	 * @access protected
	 * @var array
	 */
	protected $events = array();
	
	/**
	 * Unicode object to fix text
	 * 
	 * @access private
	 * @var Shipserv_Unicode
	 */
	private $unicode;
	
	/**
	 * Sets up DB adapter for events
	 * 
	 * @access public
	 * @param object $db An oracle resource
	 */
    public function __construct ($db)
    {
    	$this->oracleAdapter = new Shipserv_Oracle_Events($db);
		$this->refAdapter    = new Shipserv_Oracle_Reference($db);
		$this->unicode       = new Shipserv_Unicode();
		
		// Memcache should only be used in the live environment so as to make
		// testing easier
		$this->useCache = (Myshipserv_Config::isInProduction() || Myshipserv_Config::isInUat())? true : false;
    }
	
	/**
	 * Method to fetch all recent events. Types are driven by $this->eventTypes
	 * 
	 * Will attempt to fetch from cache first
	 * 
	 * @access public
	 * @param int $time The time in seconds prior to sysdate(), within which the event must exist
	 * @param int $numEvents The total number of events to fetch
	 * @param boolean $randomise randomises the events
	 * @return array An array of events
	 */
	public function fetchRecentEvents($time = 600, $numEvents = 120, $randomise = true)
	{
		$this->time      = $time;
		$this->numEvents = $numEvents;
		
		if ($this->useCache)
		{
			$memcache = Shipserv_Memcache::getMemcache();
			$key      = Shipserv_Memcache::generateKey('RECENTEVENTS_'.$time);
			
			if (!$this->events = $memcache->get($key))
			{
				$this->events = $this->processEventTypes();
				
				$memcache->set($key, $this->events, false, $time);
			}
		}
		else
		{
			$this->events = $this->processEventTypes();
		}
		
		if ($randomise)
		{
			shuffle($this->events);
		}
		
		return $this->events;
	}
	
	/**
	 * Cycles through the event types and fetches and merges the data
	 * 
	 * @access private
	 * @return array
	 */
	private function processEventTypes ()
	{
		$events = array();
		foreach ($this->eventTypes as $eventType => $method)
		{
			$eventsSoFar = count($events);
			
			if ($eventsSoFar < $this->numEvents)
			{
				// if there are enough spaces left to fill, and there's enough
				// space for $method['count'] events, then use that otherwise
				// fill up whatever space is left
				$count = ($eventsSoFar + $method['count'] <= $this->numEvents) ? $method['count'] : $this->numEvents - $eventsSoFar;
				
				$events = array_merge($events, call_user_func(array($this, $method['method']), $count));
			}
		}
		
		return $events;
	}
	
	/**
	 * Private method to fetch search events and format
	 * 
	 * @access private
	 * @return array
	 */
	private function fetchSearchEvents ($count)
	{
		$searches = $this->oracleAdapter->fetchSearchEvents($count, $this->time);
		$sourceHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('SearchSource');
		$eventMapSrc  = $sourceHelper->getObscuredKey('EVENT_MAP');
		
		$badWords = $this->refAdapter->fetchBadWords();
		
		$events = array();
		if (is_array($searches))
		{
			$searchUrl = new Myshipserv_View_Helper_SearchUrl();
			
			foreach ($searches as $search)
			{
				// check for bad words. If it contains one - skip it.
				foreach ($badWords as $badWord)
				{
					if (stristr($search['PST_SEARCH_TEXT'], $badWord) != false)
					{
						continue 2;
					}
				}
				
				$geodata = unserialize(stripslashes($search['PST_GEODATA']));
				
				$location = null;
				if ($search['PST_PORT'])
				{
					$location = $search['PST_PORT'];
				}
				elseif ($search['PST_COUNTRY'])
				{
					$location = $search['PST_COUNTRY'];
				}
				
				$mainTitle = 'Searching';
				
				if ($search['PST_SEARCH_TEXT'])
				{
					$mainTitle.= ' for '.htmlentities($search['PST_SEARCH_TEXT'], ENT_QUOTES);
				}
				
				if ($location)
				{
					$mainTitle.= ' in '.$location;
				}
				
				$subTitle = '';
				if ($search['PST_RESULTS_RETURNED'])
				{
					$subTitle = $search['PST_RESULTS_RETURNED'].' matches found';
				}
				
				$url = $search['PST_FULL_QUERY'];
				// strip out the ssrc, and replace it with the new search source for the map
				$url = preg_replace('/\&ssrc=([a-z0-9-]+)\&?/i', '&ssrc='.$eventMapSrc.'&', $url);
				
				$events[] = array('eventType'  => 'search',
								  'keywords'   => self::convertToUtf8($search['PST_SEARCH_TEXT']),
								  'mainTitle'  => self::convertToUtf8($mainTitle),
								  'subTitle'   => self::convertToUtf8($subTitle),
								  'location'   => self::convertToUtf8($location),
								  'numResults' => self::convertToUtf8($search['PST_RESULTS_RETURNED']),
								  'link'       => $url,
								  'lat'        => $geodata->latitude,
								  'lng'        => $geodata->longitude);
			}
		}
		
		return $events;
	}
	
	public static function convertToUtf8($input)
	{
		return iconv('UTF-8', 'UTF-8//IGNORE', $input);
	}
	
	/**
	 * Private method to fetch new supplier events and format
	 * 
	 * @access private
	 * @return array
	 */
	private function fetchNewSupplierEvents ($count)
	{
		$newSuppliers = $this->oracleAdapter->fetchNewSupplierEvents($count, $this->time);
		
		$events = array();
		if (is_array($newSuppliers))
		{
			$supplierProfileUrl = new Myshipserv_View_Helper_SupplierProfileUrl();
			
			$subTitle = '';
			
			foreach ($newSuppliers as $supplier)
			{
				// create the title
				$mainTitle = $this->unicode->UTF8entities($supplier['NAME']);
				
				$location = array();
				
				if ($supplier['SPB_CITY'] != '-' && trim($supplier['SPB_CITY']) != '')
				{
					$location[] = $supplier['SPB_CITY'];
				}
				
				if( trim($supplier['CNT_NAME']) != "" )
				{
					$location[] = $supplier['CNT_NAME'];
				}
				
				if( count($location) > 0 )
				{
					$mainTitle = $mainTitle . ' in ' . implode(", ", $location);
				}
				
				$mainTitle .= ' has joined ShipServ';
				
				// temporary supplier array used to produce the link
				$spb = array('tnid' => $supplier['TNID'],
							 'name' => $supplier['NAME']);
				
				// form an array that will be turned into JSON
				$events[] = array('eventType'  => 'newlisting',
								  'mainTitle'  => $mainTitle,
								  'subTitle'   => $subTitle,
								  'location'   => $location,
								  'link'       => $supplierProfileUrl->supplierProfileUrl($spb),
								  'address'    => array('address1' => $supplier['SPB_BRANCH_ADDRESS_1'],
														'address2' => $supplier['SPB_BRANCH_ADDRESS_2'],
														'city'     => $supplier['SPB_CITY'],
														'state'    => $supplier['SPB_STATE_PROVINCE'],
														'zip'      => $supplier['SPB_ZIP_CODE'],
														'country'  => $supplier['CNT_NAME']),
								  'latitude'   => $supplier['SPB_LATITUDE'],
								  'longitude'  => $supplier['SPB_LONGITUDE']);
			}
		}
		
		return $events;
	}
	
	/**
	 * Private method to fetch updated supplier and format
	 * 
	 * @access private
	 * @return array
	 */
	private function fetchUpdatedSupplierEvents ($count)
	{
		$updatedSuppliers = $this->oracleAdapter->fetchNewSupplierEvents($count, $this->time);
		
		$events = array();
		if (is_array($updatedSuppliers))
		{
			$supplierProfileUrl = new Myshipserv_View_Helper_SupplierProfileUrl();
			
			foreach ($updatedSuppliers as $supplier)
			{
				// create the title
				$mainTitle = $this->unicode->UTF8entities($supplier['NAME']);
				$subTitle = '';
				$location = array();
				if ($supplier['SPB_CITY'] != '-' && trim($supplier['SPB_CITY']) != '')
				{
					$location[] = $supplier['SPB_CITY'];
				}
				
				if( trim($supplier['CNT_NAME']) != "" )
				{
					$location[] = $supplier['CNT_NAME'];
				}
				
				if( count($location) > 0 )
				{
					$mainTitle .= ' in ' . implode(", ", $location);
				}
				
				$mainTitle .= ' has updated their listing';
				
				// temporary supplier array used to produce the link
				$spb = array('tnid' => $supplier['TNID'],
							 'name' => $supplier['NAME']);
				
				// form an array that will be turned into JSON
				$events[] = array('eventType'  => 'updatedlisting',
								  'mainTitle'  => $mainTitle,
								  'subTitle'   => $subTitle,
								  'location'   => $location,
								  'link'       => $supplierProfileUrl->supplierProfileUrl($spb),
								  'address'    => array('address1' => $supplier['SPB_BRANCH_ADDRESS_1'],
														'address2' => $supplier['SPB_BRANCH_ADDRESS_2'],
														'city'     => $supplier['SPB_CITY'],
														'state'    => $supplier['SPB_STATE_PROVINCE'],
														'zip'      => $supplier['SPB_ZIP_CODE'],
														'country'  => $supplier['CNT_NAME']),
								  'latitude'   => $supplier['SPB_LATITUDE'],
								  'longitude'  => $supplier['SPB_LONGITUDE']);
			}
		}
		
		return $events;
	}
	
	/**
	 * Private method to fetch enquiry (RFQ) events and format
	 * 
	 * @access private
	 * @return array
	 */
	private function fetchEnquiryEvents ($count)
	{
		$enquiryEvents = $this->oracleAdapter->fetchEnquiryEvents($count, $this->time);
		
		$events = array();
		if (is_array($enquiryEvents))
		{
			foreach ($enquiryEvents as $enquiry)
			{
				$pinID = $enquiry['PIN_ID'];
				if (!array_key_exists($pinID, $events))
				{
					$events[$pinID] = array();
					
					$geodata = unserialize(stripslashes($enquiry['PIN_GEODATA']));
					
					$events[$pinID]['mainTitle'] = 'New enquiry sent';
					$events[$pinID]['eventType'] = 'trade';
					
					$events[$pinID]['sender'] = array('name' => $enquiry['PIN_NAME'],
													  'lat'  => ($geodata->latitude) ? $geodata->latitude : rand(0, 90) * (rand(0,2) - 1),
													  'lng'  => ($geodata->longitude) ? $geodata->longitude :  rand(0, 90) * (rand(0,2) - 1));
					
					$events[$pinID]['recipients'] = array();
				}
				
				$events[$pinID]['recipients'][] = array('eventType' => 'trade',
														'address'   => array('address1' => $enquiry['SPB_BRANCH_ADDRESS_1'],
																			 'address2' => $enquiry['SPB_BRANCH_ADDRESS_2'],
																			 'city'     => $enquiry['SPB_CITY'],
																			 'state'    => $enquiry['SPB_STATE_PROVINCE'],
																			 'zip'      => $enquiry['SPB_ZIP_CODE'],
																			 'country'  => $enquiry['CNT_NAME']),
														'latitude'  => $enquiry['SPB_LATITUDE'],
														'longitude' => $enquiry['SPB_LONGITUDE']);
			}
			
			foreach ($events as $pinID => $data)
			{
				$events[$pinID]['subTitle'] = count($data['recipients']).' recipient' . (count($data['recipients']) > 1 ? 's' : '');
			}
		}
		
		return $events;
	}
	
	/**
	 * Private method to fetch enquiry (RFQ) events and format
	 * 
	 * @access private
	 * @return array
	 */
	private function fetchOrderEvents ($count)
	{
		$orderEvents = $this->oracleAdapter->fetchOrderEvents($count, $this->time);
		
		$events = array();
		if (is_array($orderEvents))
		{
			foreach ($orderEvents as $order)
			{
				// create the title
				$mainTitle = $this->unicode->UTF8entities($order['SUPPLIER_NAME']).' has received an order';
				$subTitle  = '';
				$location = array();
				
				if ($order['SPB_CITY'] != '-' && trim($order['SPB_CITY']) != "" )
				{
					$location[] = trim($order['SPB_CITY']);
				}
				
				if( $order['SUPPLIER_COUNTRY'] != "" )
				{
					$location[] = $order['SUPPLIER_COUNTRY'];
				}
				
				if( count($location) > 0 )
				{
					$subTitle = 'in ' . implode(", ", $location);
				}
				
				$recipients = array(array('eventType' => 'trade',
										  'address' => array('address1' => $order['BYB_BRANCH_ADDRESS_1'],
															'address2' => $order['BYB_BRANCH_ADDRESS_2'],
															'city'     => $order['BYB_CITY'],
															'state'    => $order['BYB_STATE_PROVINCE'],
															'zip'      => $order['BYB_ZIP_CODE'],
															'country'  => $order['BUYER_COUNTRY'])
										  ));
				
				$events[] = array('eventType'  => 'trade',
								  'mainTitle'  => $mainTitle,
								  'subTitle'   => $subTitle,
								  'recipients' => $recipients,
								  'sender'     => array('address' => array('address1'  => $order['SPB_BRANCH_ADDRESS_1'],
																			'address2' => $order['SPB_BRANCH_ADDRESS_2'],
																			'city'     => $order['SPB_CITY'],
																			'state'    => $order['SPB_STATE_PROVINCE'],
																			'zip'      => $order['SPB_ZIP_CODE'],
																			'country'  => $order['SUPPLIER_COUNTRY']),
																			'latitude'  => $order['SPB_LATITUDE'],
																			'longitude' => $order['SPB_LONGITUDE']));
				
				
				
			}
		}
		
		return $events;
	}
	
	/**
	 * Private method to fetch enquiry (RFQ) events and format
	 * 
	 * @access private
	 * @return array
	 */
	private function fetchOrderAcceptedEvents ($count)
	{
		$orderEvents = $this->oracleAdapter->fetchOrderAcceptedEvents($count, $this->time);
		
		$events = array();
		if (is_array($orderEvents))
		{
			foreach ($orderEvents as $order)
			{
				// create the title
				$mainTitle = $this->unicode->UTF8entities($order['SUPPLIER_NAME']).' has accepted an order';
				$subTitle = "";
				$location = array();
				
				if ( $order['SPB_CITY'] != '-' && trim($order['SPB_CITY']) != "" )
				{
					$location[] = trim($order['SPB_CITY']);
				}
				
				if( $order['SUPPLIER_COUNTRY'] != "" )
				{
					$location[] = $order['SUPPLIER_COUNTRY'];
				}
				
				if( count($location) > 0 )
				{
					$subTitle = 'in ' . implode(", ", $location);
				}
				
				
				$recipients = array(array('eventType' => 'trade',
										  'address' => array('address1' => $order['BYB_BRANCH_ADDRESS_1'],
															'address2' => $order['BYB_BRANCH_ADDRESS_2'],
															'city'     => $order['BYB_CITY'],
															'state'    => $order['BYB_STATE_PROVINCE'],
															'zip'      => $order['BYB_ZIP_CODE'],
															'country'  => $order['BUYER_COUNTRY'])
										  ));
				
				$events[] = array('eventType'  => 'trade',
								  'mainTitle'  => $mainTitle,
								  'subTitle'   => $subTitle,
								  'recipients' => $recipients,
								  'sender'     => array('address' => array('address1'  => $order['SPB_BRANCH_ADDRESS_1'],
																			'address2' => $order['SPB_BRANCH_ADDRESS_2'],
																			'city'     => $order['SPB_CITY'],
																			'state'    => $order['SPB_STATE_PROVINCE'],
																			'zip'      => $order['SPB_ZIP_CODE'],
																			'country'  => $order['SUPPLIER_COUNTRY']),
																			'latitude'  => $order['SPB_LATITUDE'],
																			'longitude' => $order['SPB_LONGITUDE']));
			}
		}
		
		return $events;
	}
	
	/**
	 * Private method to fetch enquiry (RFQ) events and format
	 * 
	 * @access private
	 * @return array
	 */
	private function fetchRfqsSent ($count)
	{
		$rfqEvents = $this->oracleAdapter->fetchRfqsSent($count, $this->time);
		
		$events = array();
		if (is_array($rfqEvents))
		{
			foreach ($rfqEvents as $order)
			{
				// create the title
				$mainTitle = $this->unicode->UTF8entities($order['SUPPLIER_NAME']).' has received an RFQ';
				$subTitle  = '';
				if ($rfq['SPB_CITY'] && $rfq['SPB_CITY'] != '-')
				{
					$subTitle.= trim($rfq['SPB_CITY']).', ';
				}
				$subTitle.= $rfq['SUPPLIER_COUNTRY'];
				
				if( $subTitle != "" )
				{
					$subTitle = 'in ' . $subTitle;
				}
				
				$recipients = array(array('eventType' => 'trade',
										  'address' => array('address1' => $order['BYB_BRANCH_ADDRESS_1'],
															'address2' => $order['BYB_BRANCH_ADDRESS_2'],
															'city'     => $order['BYB_CITY'],
															'state'    => $order['BYB_STATE_PROVINCE'],
															'zip'      => $order['BYB_ZIP_CODE'],
															'country'  => $order['BUYER_COUNTRY'])
										  ));
				
				$events[] = array('eventType'  => 'trade',
								  'mainTitle'  => $mainTitle,
								  'subTitle'   => $subTitle,
								  'recipients' => $recipients,
								  'sender'     => array('address' => array('address1'  => $order['SPB_BRANCH_ADDRESS_1'],
																			'address2' => $order['SPB_BRANCH_ADDRESS_2'],
																			'city'     => $order['SPB_CITY'],
																			'state'    => $order['SPB_STATE_PROVINCE'],
																			'zip'      => $order['SPB_ZIP_CODE'],
																			'country'  => $order['SUPPLIER_COUNTRY']),
																			'latitude'  => $order['SPB_LATITUDE'],
																			'longitude' => $order['SPB_LONGITUDE']));
			}
		}
		
		return $events;
	}
	
	/**
	 * Private method to fetch impression events and format
	 * 
	 * @access private
	 * @return array
	 */
	private function fetchImpressionEvents ($count)
	{
		$impressions = $this->oracleAdapter->fetchImpressionEvents($count, $this->time);
		
		$events = array();
		if (is_array($impressions))
		{
			$supplierProfileUrl = new Myshipserv_View_Helper_SupplierProfileUrl();
			
			$subTitle = '';
			
			foreach ($impressions as $supplier)
			{
				// create the title
				$mainTitle = 'A buyer is looking at '.$this->unicode->UTF8entities($supplier['NAME']);
				$subTitle  = '';
				if ($supplier['SPB_CITY'] != '-')
				{
					$subTitle.= $supplier['SPB_CITY'].', ';
				}
				$subTitle.= $supplier['CNT_NAME'];
				
				// temporary supplier array used to produce the link
				$spb = array('tnid' => $supplier['TNID'],
							 'name' => $supplier['NAME']);
				
				// form an array that will be turned into JSON
				$events[] = array('eventType'  => 'impression',
								  'mainTitle'  => $mainTitle,
								  'subTitle'   => $subTitle,
								  'location'   => $location,
								  'link'       => $supplierProfileUrl->supplierProfileUrl($spb),
								  'address'    => array('address1' => $supplier['SPB_BRANCH_ADDRESS_1'],
														'address2' => $supplier['SPB_BRANCH_ADDRESS_2'],
														'city'     => $supplier['SPB_CITY'],
														'state'    => $supplier['SPB_STATE_PROVINCE'],
														'zip'      => $supplier['SPB_ZIP_CODE'],
														'country'  => $supplier['CNT_NAME']),
								  'latitude'   => $supplier['SPB_LATITUDE'],
								  'longitude'  => $supplier['SPB_LONGITUDE']);
			}
		}
		
		return $events;
	}
	
	
	
	public function fetchSearchEventsCount ($days = 7)
	{
		$result = $this->oracleAdapter->fetchSearchEventsCount($days, $this->useCache);
		
		return $result[0]['SEARCHCOUNT'];
	}
	
	public function fetchNewSuppliersCount ($days = 30)
	{
		$result = $this->oracleAdapter->fetchNewSuppliersCount($days, $this->useCache);
		
		return $result[0]['NEWSUPPLIERSCOUNT'];
	}
	
	public function fetchUpdatedSuppliersCount ($days = 30)
	{
		$result = $this->oracleAdapter->fetchUpdatedSuppliersCount($days, $this->useCache);
		
		return $result[0]['UPDATEDSUPPLIERSCOUNT'];
	}
}
