<?php

/**
 * Object to handle retrieval of supplier competitors
 * Rewritten on 24/11/11 to use new pages_competitor_cache
 * @package ShipServ
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
class Shipserv_Supplier_Competitorlist
{
	/**
	 * ArrayObject containing a list of TradeNet IDs of competitors
	 * 
	 * @access private
	 * @var object
	 */
	private $competitors;
	
	private $tnids = array();
	
	/**
	 * Array containing the list of methods that should be used to populate the
	 * competitor list. Each method is called until the required number of
	 * competitors is retrieved. The key is used to separate out the returned
	 * TNIDs.
	 * 
	 * @access private
	 * @var array
	 */
	private $methodList = array('coquoters'                => 'fetchCoquoters',
								'categoryCountryMatches'   => 'fetchCategoryCountryMatches',
								'categoryContinentMatches' => 'fetchCategoryContinentMatches',
								'categoryWorldwideMatches' => 'fetchCategoryWorldwideMatches',
								'countryMatches'           => 'fetchCountryMatches',
								'continentMatches'         => 'fetchContinentMatches');
	
	/**
	 * The number of competitors to fetch for the given supplier ID
	 * 
	 * @access private
	 * @var int
	 */
	private $numCompetitors = 0;
	
	/**
	 * The TradeNet ID of the supplier for which competitors should be fetched
	 * 
	 * @access private
	 * @var int
	 */
	private $supplierTnid;
	
	/**
	 * An adapter to Oracle for suppliers
	 * 
	 * @access private
	 * @var object
	 */
	private $supplierAdapter;
	
	/**
	 * Boolean to state if results cache should be used. Defined in the constructor,
	 * and is set to FALSE if the environment is development
	 *
	 * @access private
	 * @var boolean
	 */
	private $useCache;
	
	/**
	 * Creates a new competitor list for a given TradeNet ID
	 * 
	 * @access public
	 * @param int $tnid The TradeNet ID of the supplier for which competitors
	 *                  should be fetched
	 * @param object $db An Oracle database resource
	 */
	public function __construct ($tnid, $db)
	{
		$this->supplierTnid    = $tnid;
                if($_SERVER['APPLICATION_ENV'] == 'dev'){
                    $this->useCache = false;
                }else{
                    $this->useCache = true;
                }
		//$this->useCache        = ($_SERVER['APPLICATION_ENV'] == 'dev') ? true : false;
		$this->competitors     = new ArrayObject(array());
		
		$this->supplierAdapter = new Shipserv_Oracle_Suppliers($db);
	}
	
	/**
	 * Fetches a list of TNIDs, ordered by method type and then by rank (each rank
	 * is dependent on the method type - RFQ Count, intersecting categories)
	 * 
	 * @access public
	 * @param int $numCompetitors
	 * @return array
	 */
	public function fetchOrdered ($numCompetitors)
	{
		$orderedTNIDs = array();
		$competitors  = $this->fetch($numCompetitors);
		$displayOrder = $this->fetchDisplayOrder();
		
		//$setStorage = "";
		
		foreach ($displayOrder as $storage)
		{
			foreach ((array) $this->competitors[$storage] as $competitor)
			{
				
				$orderedTNIDs[] = $competitor['TNID'];
			}
		}
                		
		if(count($orderedTNIDs) == 0){
			//$orderedTNIDs = array();
			//If the array is empty we can assume the source is PCC and therefore is already ordered without using displayOrder array, by DB instead.
			foreach($competitors as $competitor){
				$orderedTNIDs[] = $competitor['TNID'];
			}
		}
		
		return $orderedTNIDs;
	}
	
	/**
	 * Fetches a list of competitors for the defined supplier TradeNet ID
	 * 
	 * @access public
	 * @param int $numCompetitors The number of competitors to return
	 * @return object ArrayObject
	 */
	public function fetch ($numCompetitors)
	{
		$competitorCount = 0;
		//See if we get results from cache, otherwise continue.
		if($numCompetitors  <= 10){
                    $competitors = $this->supplierAdapter->fetchSupplierCompetitorsFromCache($this->supplierTnid, $numCompetitors, false);
                    if(count($competitors)>0){
                        foreach($competitors as $cmp){
                                $this->tnids[] = $cmp['TNID'];
                                //$competitors['TNID'] = $cmp['supplierTnid'];
                        }
                        return $competitors;
                    }
		}
		//Fetching from cache returned nothing so revert to previous method
		foreach ($this->methodList as $storage => $method)
		{
			$competitors = call_user_func(array($this, $method));
			
			if (is_array($competitors))
			{
				if(count($competitors) > 0){
					$slotsRemaining = $numCompetitors - $competitorCount;
					$this->competitors[$storage] = array_slice($competitors, 0, $slotsRemaining);

					foreach ($this->competitors[$storage] as $comp)
					{
						$this->tnids[] = $comp['TNID'];
					}

					$competitorCount += count($this->competitors[$storage]);
				}
			}
			
			if ($competitorCount == $numCompetitors)
			{
				break;
			}
		}
		
		return $this->competitors;
	}
	
	/**
	 * Fetches the order of storage types in which competitors should be displayed
	 * 
	 * @access public
	 * @return array An ordered array of storage types
	 */
	public function fetchDisplayOrder ()
	{
		return array_keys($this->methodList);
	}
	
	/**
	 * Fetches a list of TNIDs based on the pages_supplier_coquoter table, which
	 * is populated by a stored procedure run once a month
	 * 
	 * @access private
	 * @return array
	 */
	private function fetchCoquoters ()
	{
		return $this->supplierAdapter->fetchCoquoters($this->supplierTnid,
													  $this->useCache);
	}
	
	/**
	 * Fetches a list of TNIDs based on the country of the original supplier,
	 * ranked by the number of intersecting categories
	 * 
	 * @access private
	 * @return array
	 */
	private function fetchCategoryCountryMatches ()
	{
		return $this->supplierAdapter->fetchCategoryCountryMatches($this->supplierTnid,
																   $this->tnids,
																   $this->useCache);
	}
	
	/**
	 * Fetches a list of TNIDs based on the country of the original supplier,
	 * ranked by the number of intersecting categories
	 * 
	 * @access private
	 * @return array
	 */
	private function fetchCategoryContinentMatches ()
	{
		return $this->supplierAdapter->fetchCategoryContinentMatches($this->supplierTnid,
																	 $this->tnids,
																	 $this->useCache);
	}
	
	/**
	 * Fetches a list of TNIDs regardless of location, ranked by the number of
	 * intersecting categories
	 *
	 * @access private
	 * @return array
	 */
	private function fetchCategoryWorldwideMatches ()
	{
		return $this->supplierAdapter->fetchCategoryWorldwideMatches($this->supplierTnid,
																	 $this->tnids,
																	 $this->useCache);
	}
	
	/**
	 * Fetches a list of TNIDs based on the country of the original supplier,
	 * ranked by traderank
	 * 
	 * @access private
	 * @return array
	 */
	private function fetchCountryMatches ()
	{
		return $this->supplierAdapter->fetchCountryMatches($this->supplierTnid,
														   $this->tnids,
														   $this->useCache);
	}
	
	
	/**
	 * Fetches a list of TNIDs based on the continent of the original supplier,
	 * ranked by traderank
	 * 
	 * @access private
	 * @return array
	 */
	private function fetchContinentMatches ()
	{
		return $this->supplierAdapter->fetchContinentMatches($this->supplierTnid,
															 $this->tnids,
															 $this->useCache);
	}
}