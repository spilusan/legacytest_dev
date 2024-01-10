<?php

/**
 * Db connection and simple query operations.
 */
class ajwp_Db
{
	private static $inst;
	
	private $db;
	
	public static function getSingleton ()
	{
		if (! self::$inst)
		{
			self::$inst = new self();
		}
		
		return self::$inst;
	}
	
	public function getConn ()
	{
		return $this->getDb();
	}
	
	public function quote($value)
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        $value = str_replace("'", "''", $value);
        return "'" . addcslashes($value, "\000\n\r\\\032") . "'";
    }
	
	/**
	 * Execute query
	 * 
	 * @return null
	 * Throws Exception on error.
	 */
	public function execute ($sql)
	{
		$conn = $this->getDb();
		
		// Prepare statement
		$stid = oci_parse($conn, $sql); // returns false on failure
		
		// Handle failure
		if ($stid === false)
		{
			throw new Exception();
		}
		
		// Execute statement
		$executeOk = oci_execute($stid);
		
		// Handle failure
		if ($executeOk === false)
		{
			throw new Exception();
		}
		
		// Release statement
		oci_free_statement($stid);
	}
	
	/**
	 * Execute query and return result set as array
	 * 
	 * @return array
	 */
	public function runQuery ($sql, &$nrows = 0)
	{
		$conn = $this->getDb();
		
		// Prepare statement
		$stid = oci_parse($conn, $sql); // returns false on failure
		
		// Handle failure
		if ($stid === false)
		{
			throw new Exception();
		}
		
		// Execute statement
		$executeOk = oci_execute($stid);
		
		// Handle failure
		if ( $executeOk === false)
		{
			throw new Exception();
		}
		
		// Fetch result rows into array
		$res = array();
		$nrows = oci_fetch_all($stid, $res, null, null, OCI_FETCHSTATEMENT_BY_ROW); // returns false on failure
		
		// Handle failure
		if ($nrows === false)
		{
			throw new Exception();
		}
		
		// Release statement
		oci_free_statement($stid);
		
		return $res;
	}
	
	public function runQueryPullCol($sql, $colName)
	{
		$colName = strtoupper($colName);
		
		$res = array();
		foreach ($this->runQuery($sql) as $row)
		{
			$res[] = $row[$colName];
		}
		
		return $res;
	}
	
	private function getDb ()
	{
		if (! $this->db)
		{
			$_config = array(
				'dbname'       => '(DESCRIPTION=(ADDRESS=(PROTOCOL=tcp)(HOST=samson.myshipserv.com)(PORT=1521))(CONNECT_DATA=(SID=SSUK04)))',
				'username'     => 'sservdba',
				'password'     => 'jason',
			);
				
			$this->db = oci_connect($_config['username'], $_config['password'], $_config['dbname']);
		}
		
		return $this->db;		
	}
}

/**
 * A simple iterator interface to replace PHP's ludicrously complicated
 * Iterator interface.
 */
interface ajwp_IteratorI
{
	/**
	 * @return mixed, or false if no more items
	 */
	public function next ();
}

/**
 * Represents a session, consisting in session events.
 */
interface ajwp_SessionI
{
	public function getId ();
	
	/**
	 * @return array of ajwp_SessionEvent
	 */
	public function getEvents ();
}

/**
 * Used by ajwp_Session
 */
class ajwp_Session_ExcessEventsException extends Exception { }

/**
 * Provides a sorted list of events (searches, supplier impressions, inquiries)
 * for given session id.
 */
abstract class ajwp_Session implements ajwp_SessionI
{
	const SEARCH_TABLE = 'ajwp_pages_statistics';
	const SUPPLIER_TABLE = 'ajwp_pages_statistics_supplier';
	const INQUIRY_TABLE = 'ajwp_pages_inquiry';
	
	private $sessionId;
	private $cache;
	
	public function __construct ($sessionId)
	{
		$this->sessionId = $sessionId;
	}
	
	public function getId ()
	{
		return $this->sessionId;
	}
	
	/**
	 * Fetch searches, supplier page impressions and enquiries for session,
	 * ordered by time increasing.
	 * 
	 * @return array of ajwp_SessionEvent
	 */
	public function getEvents ()
	{
		if (! $this->cache)
		{
			$res = array();
			
			try
			{
				$this->readSearches($res);
				$this->readSupplierImps($res);
				$this->readInquiries($res);
				
				$res = $this->sortEvents($res);
			}
			catch (ajwp_Session_ExcessEventsException $e)
			{
				// Thrown to indicate an oversize (junk) session
				// Return empty array
				$res = array();
			}
			
			$this->cache = $res;
		}
		
		return $this->cache;
	}
	
	/**
	 * @return ajwp_SessionEvent
	 */
	abstract protected function makeEventFromSearch ($searchText, $timeStamp);
	
	/**
	 * @return ajwp_SessionEvent
	 */
	abstract protected function makeEventFromSupplier($supplierId, $timeStamp);
	
	/**
	 * @return ajwp_SessionEvent
	 */
	abstract protected function makeEventFromInquiry(array $supplierIdArr, $timeStamp);
	
	/**
	 * @param array &$res Populated with ajwp_SessionEvent
	 * @return null
	 */
	private function readSearches (array &$res)
	{
		$sql = sprintf(
			"select pst_id, to_char(pst_search_date_time, 'yyyy-mm-dd hh24:mi:ss') time_str, pst_search_text from %s where pst_session_id = %s",
			self::SEARCH_TABLE,
			$this->dbEscape($this->sessionId)
		);
		
		$qRows = $this->runQuery($sql);
		
		// Trap sessions with an excessive number of actions
		// todo: query results have already been retrieved - not optimal
		if (count($qRows) > 50) throw new ajwp_Session_ExcessEventsException();
		
		foreach ($qRows as $row)
		{
			// Some records are polluted with html entities: correct them
			$cleanSearchText = htmlspecialchars_decode($row['PST_SEARCH_TEXT'], ENT_QUOTES);
			
			$event = $this->makeEventFromSearch($cleanSearchText, $this->timeToStamp($row['TIME_STR']));
			$res[] = $event;
		}
	}
	
	/**
	 * @param array &$res Populated with ajwp_SessionEvent
	 * @return null
	 */
	private function readSupplierImps (array &$res)
	{
		$sql = sprintf(
			"select pss_id, pss_spb_sup_org_code, pss_spb_branch_code, to_char(pss_view_date, 'yyyy-mm-dd hh24:mi:ss') time_str from %s where pss_session_id = %s",
			self::SUPPLIER_TABLE,
			$this->dbEscape($this->sessionId)
		);
		
		$qRows = $this->runQuery($sql);
		
		// Trap sessions with an excessive number of actions
		// todo: query results have already been retrieved - not optimal
		if (count($qRows) > 50) throw new ajwp_Session_ExcessEventsException();
		
		foreach ($qRows as $row)
		{
			$event = $this->makeEventFromSupplier($row['PSS_SPB_BRANCH_CODE'], $this->timeToStamp($row['TIME_STR']));
			$res[] = $event;
		}
	}
	
	/**
	 * @param array &$res Populated with ajwp_SessionEvent
	 * @return null
	 */
	private function readInquiries (array &$res)
	{
		// Query to fetch inquiries for session. Constraint on pin_status avoids spam.
		$sql = sprintf(
			"select pin_id, to_char(pin_creation_date, 'yyyy-mm-dd hh24:mi:ss') time_str from %s where pin_session_id = %s and pin_status = 'RELEASED'",
			self::INQUIRY_TABLE,
			$this->dbEscape($this->sessionId)
		);
		
		$qRows = $this->runQuery($sql);
		
		// Trap sessions with an excessive number of actions
		// todo: query results have already been retrieved - not optimal
		if (count($qRows) > 50) throw new ajwp_Session_ExcessEventsException();
		
		foreach ($qRows as $row)
		{
			// Fetch array of supplier branch codes who are recipients of inquiry
			$sql = "select distinct pir_spb_branch_code from pages_inquiry_recipient where pir_pin_id = {$row['PIN_ID']}";
			$supplierIdArr = $this->runQueryPullCol($sql, 'PIR_SPB_BRANCH_CODE');
			
			$event = $this->makeEventFromInquiry($supplierIdArr, $this->timeToStamp($row['TIME_STR']));
			$res[] = $event;
		}
	}
	
	/**
	 * Sorts a list of session events by time increasing.
	 * 
	 * @param array $toSort Array of ajwp_SessionEvent
	 * @return array of ajwp_SessionEvent (keys not maintained, indexed numerically).
	 */
	private function sortEvents (array $toSort)
	{
		$tmp = array();
		foreach ($toSort as $k => $v)
		{
			$tmp[$k] = $v->getTime();
		}
		
		asort($tmp);
		
		$res = array();
		foreach ($tmp as $k => $v)
		{
			$res[] = $toSort[$k];
		}
		
		return $res;
	}
	
	/**
	 * Turn time string from db into a timestamp.
	 * Expects 'Y-m-d H:i:s' and interprets time as UTC.
	 * 
	 * @return int Timestamp
	 */
	private function timeToStamp ($timeStr)
	{
		$res = strtotime($timeStr . ' UTC');
		if ($res === false) throw new Exception();
		
		return $res;
	}
	
	private function runQuery ($sql)
	{
		return ajwp_Db::getSingleton()->runQuery($sql);
	}
	
	private function runQueryPullCol($sql, $colName)
	{
		return ajwp_Db::getSingleton()->runQueryPullCol($sql, $colName);
	}
	
	private function dbEscape ($val)
	{
		return ajwp_Db::getSingleton()->quote($val);
	}
}

class ajwp_SessionImpl extends ajwp_Session
{	
	protected function makeEventFromSearch ($searchText, $timeStamp)
	{
		$event = ajwp_SessionEvent::fromSearch($searchText, $timeStamp);
		return $event;
	}
	
	protected function makeEventFromSupplier($supplierId, $timeStamp)
	{
		$event = ajwp_SessionEvent::fromSupplier($supplierId, $timeStamp);
		return $event;
	}
	
	protected function makeEventFromInquiry(array $supplierIdArr, $timeStamp)
	{
		$event = ajwp_SessionEvent::fromInquiry($supplierIdArr, $timeStamp);
		return $event;
	}
}

/**
 * Classifies a search string in terms of a product category. Various methods
 * are implemented for achieving this.
 */
class ajwp_SearchToCategory
{
	private $searchText;
	
	public function __construct ($searchText)
	{
		$this->searchText = $searchText;
	}
	
	/**
	 * Try to match search string to a category name.
	 * 
	 * @return int Category id, or null
	 */
	public function viaCategory ()
	{
		// Match on lower case category name
		$sql = sprintf(
			"select id from product_category where lower(name) = lower(%s)",
			$this->dbEscape($this->searchText)
		);
		
		// Execute query and pull back array of category ids
		$res = $this->runQueryPullCol($sql, 'id');
		
		// Only valid if one and only one category matched
		if (count($res) == 1)
		{
			return $res[0];
		}
		
		// Otherwise no match
		return null;
	}
	
	/**
	 * @return array of int Category ids
	 */
	public function viaBrand ()
	{
		// Match on lower case brand name
		$sql = sprintf("select id from brand where lower(name) = lower(%s)",
			$this->dbEscape($this->searchText));
		
		// Run query and pull back array of matching brand ids
		$brandIdArr = $this->runQueryPullCol($sql, 'id');
		
		// Only valid if one and only one brand matched
		if (count($brandIdArr) == 1)
		{
			// Query to get categories for brand id
			$sql = "select distinct bct_product_category_id from brand_category where bct_brand_id = {$brandIdArr[0]}";
			
			// Return array of category ids
			return $this->runQueryPullCol($sql, 'bct_product_category_id');			
		}
		
		// Otherwise, return empty array
		return array();
	}
	
	/**
	 * @return array of int Category ids
	 */
	public function viaSupplier ()
	{
		// Match on lower case supplier organisation name
		$sql = sprintf("select sup_org_code from supplier_organisation where lower(sup_supplier_name) = lower(%s)",
			$this->dbEscape($this->searchText));
		
		// Fetch array of matching supplier organisation codes
		$supplierIdArr = $this->runQueryPullCol($sql, 'sup_org_code');
		
		// Only valid if one and only one supplier matched
		if (count($supplierIdArr) == 1)
		{
			// Query to get categories for supplier org code
			$sql = "select distinct product_category_id from supply_category where supplier_org_code = {$supplierIdArr[0]}";
			
			// Return array of org codes
			return $this->runQueryPullCol($sql, 'product_category_id');
		}
		
		// Otherwise, return empty array
		return array();
	}
	
	/**
	 * @return array of int Category ids
	 */
	public function viaSupplierBranch ()
	{
		$sql = sprintf("select spb_branch_code from supplier_branch where lower(spb_name) = lower(%s)",
			$this->dbEscape($this->searchText)
		);
		
		// Fetch array of matching supplier organisation codes
		$supplierIdArr = $this->runQueryPullCol($sql, 'spb_branch_code');
		
		// Only valid if one and only one supplier matched
		if (count($supplierIdArr) == 1)
		{
			// Query to get categories for supplier branch code
			$sql = "select distinct product_category_id from supply_category where supplier_branch_code = {$supplierIdArr[0]}";
			
			// Return array of branch codes
			$catIdArr = $this->runQueryPullCol($sql, 'product_category_id');
			return $catIdArr;
		}
		
		// Otherwise, return empty array
		return array();
	}
	
	private function runQueryPullCol($sql, $colName)
	{
		return ajwp_Db::getSingleton()->runQueryPullCol($sql, $colName);
	}
	
	private function dbEscape ($val)
	{
		return ajwp_Db::getSingleton()->quote($val);
	}
}

/**
 * Represents a session event (search, supplier impression or inquiry).
 * Principally, provides a list of categories associated with an event.
 */
class ajwp_SessionEvent
{
	const WEIGHT_SEARCH = 1.0;
	const WEIGHT_SUPPLIER = 1.0;
	const WEIGHT_INQUIRY = 3.0;
	
	// String used for debug
	public $dbgSource;
	
	private $timestamp;
	private $weight;
	private $categories = array();
	
	public function __construct ($timestamp, $weight = 1)
	{
		$this->timestamp = (int) $timestamp;
		$this->weight = (float) $weight;
	}
	
	/**
	 * @param mixed $categoryId
	 * @param bool $allowMultiInsert When false, category is not added if already present
	 */
	public function addCategory ($categoryId, $allowMultiInsert = false)
	{
		if (! $allowMultiInsert)
		{
			$this->categories[$categoryId] = 1;
		}
		else
		{
			$this->categories[$categoryId] = @$this->categories[$categoryId] + 1;
		}
	}
	
	/**
	 * @return int Timestamp
	 */
	public function getTime ()
	{
		return $this->timestamp;
	}
	
	/**
	 * @return float Weight representing importance of this event for determining the category of a session.
	 */
	public function getWeight ()
	{
		return $this->weight;
	}
	
	/**
	 * An id may be present more than once in return array according to how
	 * many times it was added. See addCategory().
	 * 
	 * @return array of category ids
	 */
	public function getCategories ()
	{
		$res = array();
		foreach ($this->categories as $catId => $n)
		{
			for ($i = $n; $i > 0; $i--)
			{
				$res[] = $catId;
			}
		}
		return $res;
	}
	
	/**
	 * Create object from inquiry recipient
	 *
	 * @return ajwp_SessionEvent
	 */
	public static function fromInquiry (array $recipientIdArr, $timestamp)
	{
		// Query to fetch categories for each supplier specified
		$sql = sprintf(
			"select distinct supplier_branch_code, product_category_id from supply_category where supplier_branch_code in (%s)",
			join(', ', self::quoteList($recipientIdArr))
		);
		
		$o = new self($timestamp, self::WEIGHT_INQUIRY);
		foreach (self::runQuery($sql) as $row)
		{
			// Multi-insert = true: the same category present across multiple
			// suppliers is registered multiple times and therefore counts more.
			$o->addCategory($row['PRODUCT_CATEGORY_ID'], true);
		}
		
		$o->dbgSource = "fromInquiry(recipientIdArr = {" . join(', ', $recipientIdArr) . "}, timestamp = $timestamp)";
		return $o;
	}
	
	/**
	 * Create object from supplier id
	 *
	 * @return ajwp_SessionEvent
	 */
	public static function fromSupplier ($supplierId, $timestamp)
	{
		$sql = sprintf(
			"select distinct product_category_id from supply_category where supplier_branch_code = %s",
			self::dbEscape($supplierId)
		);
		
		$o = new self($timestamp, self::WEIGHT_SUPPLIER);
		foreach (self::runQuery($sql) as $row)
		{
			$o->addCategory($row['PRODUCT_CATEGORY_ID']);
		}
		
		$o->dbgSource = "fromSupplier(supplierId = $supplierId, timestamp = $timestamp)";
		return $o;
	}
	
	/**
	 * Create object from search string
	 *
	 * @return ajwp_SessionEvent
	 */
	public static function fromSearch ($searchText, $timestamp)
	{
		$o = new self($timestamp, self::WEIGHT_SEARCH);
		$o->dbgSource = "fromSearch(searchText = '$searchText', timestamp = $timestamp)";
		
		$sToCat = new ajwp_SearchToCategory($searchText);
		
		// Test search string for match with product category
		$catId = $sToCat->viaCategory();
		if ($catId !== null)
		{
			$o->addCategory($catId);
			$o->dbgSource .= "; sub-method = 'viaCategory'";
			return $o;
		}
		
		// Test search string for match with brand or supplier
		foreach (array('viaBrand', 'viaSupplierBranch', 'viaSupplier') as $testMethod)
		{
			$cats = $sToCat->$testMethod();
			if (count($cats) > 0)
			{
				foreach ($cats as $id) $o->addCategory($id);
				$o->dbgSource .= "; sub-method = '$testMethod'";
				break;
			}
		}
		
		return $o;
	}
	
	private static function quoteList(array $list)
	{
		$res = array();
		$db = ajwp_Db::getSingleton();
		foreach ($list as $v)
		{
			$res[] = $db->quote($v);
		}
		
		return $res;
	}
	
	private static function runQuery ($sql)
	{
		return ajwp_Db::getSingleton()->runQuery($sql);
	}
	
	private static function dbEscape ($val)
	{
		return ajwp_Db::getSingleton()->quote($val);
	}
}

/**
 * Logic used to derive one category for a session. Implements a scoring
 * system based on categories associated with session events
 * (e.g. search, supplier impression, inquiry).
 */
class ajwp_SessionToCat
{
	/**
	 * Determine a category of spend intent for a user session.
	 *
	 * Each event within the session is connected to a category or
	 * categories of spend intent. Points are summed for each category,
	 * and the top category is returned.
	 *
	 * Returns null in the case of insufficient data, or a tie.
	 * 
	 * @return int Cateogry id, or null
	 */
	public function categoriseSession (ajwp_SessionI $session, &$catHistogram = array())
	{
		$catScore = array();
		
		// Loop on events that occurred during session
		foreach ($session->getEvents() as $ev)
		{
			// Fetch categories associated with event
			$catIdArr = $ev->getCategories();
			$nCats = count($catIdArr);
			
			if ($nCats > 0)
			{
				// Score = (1 / num associated categories for event) * (importance weighting of event type)
				$sc = 1 / $nCats * $this->getEventWeight($ev);
				foreach ($catIdArr as $catId)
				{
					// Sum score for each category
					if (array_key_exists($catId, $catScore))
					{
						$catScore[$catId] += $sc;
					}
					else
					{
						$catScore[$catId] = $sc;
					}
				}
			}
		}
		
		// Sort by value descending, maintaining key association
		arsort($catScore);
		reset($catScore);
		
		// Copy into return variable
		$catHistogram = $catScore;
		
		// Create numerically indexed array containing ranked category ids
		$rankedCatIds = array_keys($catScore);
		
		// If there is only one ...
		if (count($rankedCatIds) == 1)
		{
			// Return it
			return $rankedCatIds[0];
		}
		// If there is more than one ...
		elseif (count($rankedCatIds) > 1)
		{
			// If there's not a tie, return it
			if ($catScore[$rankedCatIds[0]] != $catScore[$rankedCatIds[1]])
			{
				return $rankedCatIds[0];
			}
		}
		
		// Otherwise return null
		return null;
	}
	
	/**
	 * Returns a weighting to represent that some events are more
	 * significant than others.
	 * 
	 * @return float Weight
	 */
	private function getEventWeight (ajwp_SessionEvent $event)
	{
		return $event->getWeight();
	}
}

/**
 * Main program entry point.
 */
class ajwp_Main
{
	private $resCounter = 0;
	
	public function run ()
	{
		$it = $this->makeSessionIterator();
		$sCat = new ajwp_SessionToCat();
		while ($session = $it->next())
		{
			$catId = $sCat->categoriseSession($session, $histogram);
			$this->markSession($session, $catId, $histogram);
		}
	}
	
	protected function makeSessionIterator ()
	{
		// todo
	}
	
	private function markSession ($session, $catId, $histogram)
	{
		$sId = $session->getId();
		echo "Session $sId => category " . ($catId !== null ? $catId : '?') . " (" . $this->readCategoryName($catId) . ")\n";
		
		echo "Category score histogram:\n";
		$i = 0;
		foreach ($histogram as $k => $v)
		{
			echo " Category $k: $v (" . $this->readCategoryName($k) . ")\n";
			if (++$i >= 10)
			{
				if (count($histogram) > $i) echo " ...\n";
				break;
			}
		}
		
		echo "Event details:\n";
		$events = $session->getEvents();
		$i = 0;
		foreach ($events as $ev)
		{
			echo " " . $ev->dbgSource . "\n";
			if (++$i >= 10)
			{
				if (count($events) > $i) echo " ...\n";
				break;
			}
		}
		
		echo "Sessions processed: " . ++$this->resCounter . "\n";
		
		echo "---\n";
	}
	
	/**
	 * @param mixed $id Category id (may be null)
	 * @return string or null
	 */
	private function readCategoryName($id)
	{
		if ($id != null)
		{
			$conn = ajwp_Db::getSingleton();
			$res = $conn->runQueryPullCol(
				sprintf("select name from product_category where id = %s",
					$conn->quote($id)),
				'name'
			);
			return @$res[0];
		}
	}
	
	
}
