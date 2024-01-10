<?php

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
 * Iterator seeded by SQL query and issuing paged queries
 * behind the scene to retrieve rows efficiently by batch.
 * Batch size is controlled by the BATCH_SIZE constant.
 */
class ajwp_QueryIterator implements ajwp_IteratorI
{
	// todo: increase this
	const BATCH_SIZE = 1000;
	
	private $sql;
	
	// Null, false, or statement resource: use getter
	private $stid;
	
	private $nextBatch = 0;
	
	public function __construct ($sql)
	{
		$this->sql = $sql;
	}
	
	/**
	 * @return array or false if no more rows
	 */
	public function next ()
	{
		return $this->fetchRow();
	}
	
	private function getStid ()
	{
		// If there is no current statement, or last attempt failed ...
		if (! $this->stid)
		{
			$conn = ajwp_Db::getSingleton()->getConn();
			
			// Create statement handle from query string
			$this->stid = oci_parse($conn, $this->getnextQuery()); // returns false on failure
			
			// Handle failure
			if ($this->stid === false)
			{
				throw new Exception();
			}
			
			// Execute query
			$executeOk = oci_execute($this->stid);
			
			// Handle failure
			if (! $executeOk)
			{
				throw new Exception();
			}
		}
		
		return $this->stid;
	}
	
	private function getNextQuery ()
	{
		$sql = $this->getPagedQuery($this->nextBatch * self::BATCH_SIZE, self::BATCH_SIZE);
		return $sql;
	}
	
	private function getPagedQuery ($offset, $n)
	{
		$maxRow = $offset + $n;
		$minRow = $offset + 1;
		
		return 
			"select * from
			(
				select /*+ FIRST_ROWS(n) */ a.*, ROWNUM rnum from
				(
					{$this->sql}
				) a 
				where ROWNUM <= $maxRow
			) 
			where rnum  >= $minRow";
	}
	
	/**
	 * @return array or false if no more rows
	 */
	private function fetchRow ()
	{
		// Repeat twice if need be ...
		for ($i = 0; $i < 2; $i++)
		{
			// Read row: returns row array, or false if no more rows
			$row = oci_fetch_assoc($this->getStid());
			
			// On success ...
			if ($row !== false)
			{
				// Exit loop
				break;
			}
			
			// Else (no more rows), on first time round ...
			else if ($i == 0)
			{
				// Advance to next batch (and loop to try again)
				$this->advanceBatch();
			}
		}
		
		return $row;
	}
	
	private function advanceBatch ()
	{
		// Release current statement
		oci_free_statement($this->stid);
		
		// Reset statement member (statement getter will create and cache advanced statement on next call)
		$this->stid = null;
		
		// Advance batch counter
		$this->nextBatch++;
	}
	
	public function __destruct ()
	{
		if ($this->stid) oci_free_statement($this->stid);
	}
}

/**
 * Iterator combining rows from 3 source tables:
 * 
 * 		SEARCH_TABLE - table listing search events on Pages
 * 		SUPPLIER_TABLE - table listing supplier profile impression events on Pages
 * 		INQUIRY_TABLE - table listing inquiry events on Pages
 *
 * Rows are pulled from each and popped into a sorted queue such that rows
 * are returned by time ascending. This algorithm used only works if the rows
 * are ordered by time ascending within each of the source tables. The idea
 * behind this is to avoid an expensive sort over a huge amount of data.
 */
abstract class ajwp_SessionEventIterator implements ajwp_IteratorI
{
	const SEARCH_TABLE = 'ajwp_pages_statistics';
	const SUPPLIER_TABLE = 'ajwp_pages_statistics_supplier';
	const INQUIRY_TABLE = 'ajwp_pages_inquiry';
	
	const TYPE_SEARCH = 'SEARCH';
	const TYPE_SUPPLIER = 'SUPPLIER';
	const TYPE_INQUIRY = 'INQUIRY';
	
	private $searchIterator;
	private $supplierIterator;
	private $inquiryIterator;
	
	private $firstPass = true;
	private $queue = array();
	
	/**
	 * Returns next record as array:
	 * 
	 * 		array(
	 * 			'data' => array(...),
	 * 			'timestamp' => 1234,
	 * 			'type' => 'SEARCH' | 'SUPPLIER' | 'INQUIRY'
	 * 		)
	 *
	 * 'data' contains the actual db row, 'timestamp' is used to sort,
	 * 'type' indicates source of row.
	 * 
	 * @return array or false if end iteration
	 */
	public function next ()
	{		
		// On first pass, initialise queue
		if ($this->firstPass)
		{
			// Reset flag
			$this->firstPass = false;
			
			// Read all sources
			$this->readSearch();
			$this->readSupplier();
			$this->readInquiry();
		}
		
		// Default return value for end iteration
		$res = false;
		
		// Attempt to pull next item from queue
		$nxt = $this->pullFromQueue();
		
		// On success ...
		if ($nxt !== null)
		{
			// Get source of retrieved item
			$type = $nxt['type'];
			
			// Replace pulled item according to source
			if ($type == self::TYPE_SEARCH)
			{
				$this->readSearch();
			}
			elseif ($type == self::TYPE_SUPPLIER)
			{
				$this->readSupplier();
			}
			elseif ($type == self::TYPE_INQUIRY)
			{
				$this->readInquiry();
			}
			else
			{
				// Logic error: unexpected source token
				throw new Exception();
			}
			
			// Set return value
			$res = $nxt;
		}
		
		return $res;
	}
	
	/**
	 * @return ajwp_IteratorI
	 */
	abstract protected function makeIteratorFromSql ($sql);
	
	/**
	 * Read key from array, throwing exception if key does not exist.
	 */
	private function readKey (array $arr, $key)
	{
		if (! array_key_exists($key, $arr))
		{
			throw new Exception();
		}
		
		return $arr[$key];
	}
	
	/**
	 * @return array or null
	 */
	private function pullFromQueue ()
	{
		$res = null;
		
		// If queue is not empty ...
		if ($this->queue)
		{
			// Pull item off top
			$res = $this->queue[0];
			
			// Take a local copy of queue
			$myQ = $this->queue;
			
			// Remove top item
			unset($myQ[0]);
			
			// Reindex
			$myQ = array_values($myQ);
			
			// Replace master queue
			$this->queue = $myQ;
		}
		
		return $res;
	}
	
	/**
	 * @return null
	 */
	private function pushToQueue (array $row, $timestamp, $type)
	{
		// Take a local copy of queue & add new data
		$myQ = $this->queue;
		$myQ[] = array('data' => $row, 'timestamp' => (int) $timestamp, 'type' => (string) $type);
		
		// Create an array suitable for sorting: key => timestamp
		$sortQ = array();
		foreach ($myQ as $k => $item)
		{
			$sortQ[$k] = $item['timestamp'];
		}
		asort($sortQ);
		
		// Loop on sorted queue and use key to retrieve data rows
		$newQ = array();
		foreach ($sortQ as $k => $junk)
		{
			$newQ[] = $myQ[$k];
		}
		
		// Replace master queue
		$this->queue = $newQ;
	}
	
	/**
	 * @return null
	 */
	private function readSearch()
	{
		if (! $this->searchIterator)
		{
			$sql = sprintf("select a.*, to_char(pst_search_date_time, 'yyyy-mm-dd hh24:mi:ss') time_str from %s a", self::SEARCH_TABLE);
			$this->searchIterator = $this->makeIteratorFromSql($sql);
		}
		
		$row = $this->searchIterator->next();
		if ($row !== false)
		{
			$this->pushToQueue($row, $this->timeToStamp($row['TIME_STR']), self::TYPE_SEARCH);
		}
	}
	
	/**
	 * @return null
	 */
	private function readSupplier()
	{
		if (! $this->supplierIterator)
		{
			$sql = sprintf("select a.*, to_char(pss_view_date, 'yyyy-mm-dd hh24:mi:ss') time_str from %s a", self::SUPPLIER_TABLE);
			$this->supplierIterator = $this->makeIteratorFromSql($sql);
		}
		
		$row = $this->supplierIterator->next();
		if ($row !== false)
		{
			$this->pushToQueue($row, $this->timeToStamp($row['TIME_STR']), self::TYPE_SUPPLIER);
		}
	}
	
	/**
	 * @return null
	 */
	private function readInquiry()
	{
		if (! $this->inquiryIterator)
		{
			$sql = sprintf("select a.*, to_char(pin_creation_date, 'yyyy-mm-dd hh24:mi:ss') time_str from %s a", self::INQUIRY_TABLE);
			$this->inquiryIterator = $this->makeIteratorFromSql($sql);
		}
		
		$row = $this->inquiryIterator->next();
		if ($row !== false)
		{
			$this->pushToQueue($row, $this->timeToStamp($row['TIME_STR']), self::TYPE_INQUIRY);
		}
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
}

class ajwp_SessionEventIteratorImpl extends ajwp_SessionEventIterator
{
	/**
	 * @return ajwp_IteratorI
	 */
	protected function makeIteratorFromSql($sql)
	{
		return new ajwp_QueryIterator($sql);
	}
}

/**
 * Algorithm flawed: do not use.
 */
class ajwp_SessionMarker
{
	public function run ()
	{
		$eventIterator = new ajwp_SessionEventIteratorImpl();
		
		// Initialise session counter (0 value used to mark first pass through loop)
		$sessionCounter = 0;
		
		// Used to count rows processed
		$rowCounter = 0;
		
		// id, ip, browser, user code and timestamp values to be retrieved for each event
		$id = null;
		$lastIp = $ip = null;
		$lastBrowser = $browser = null;
		$lastUser = $user = null;
		$lastTimestamp = $timestamp = null;
		
		// Loop on records
		while ($row = $eventIterator->next())
		{
			$type = $this->readKey($row, 'type');
			$data = $this->readKey($row, 'data');
			
			// Fetch event timestamp
			$timestamp = $this->readKey($row, 'timestamp');
			
			// Fetch ip, browser and user code according to event type
			if ($type == ajwp_SessionEventIterator::TYPE_SEARCH)
			{
				$id = $this->readKey($data, 'PST_ID');
				$ip = $this->readKey($data, 'PST_IP_ADDRESS');
				$browser = $this->readKey($data, 'PST_BROWSER');
				$user = $this->readKey($data, 'PST_USR_USER_CODE');
			}
			elseif ($type == ajwp_SessionEventIterator::TYPE_SUPPLIER)
			{
				$id = $this->readKey($data, 'PSS_ID');
				$ip = $this->readKey($data, 'PSS_VIEWER_IP_ADDRESS');
				$browser = $this->readKey($data, 'PSS_BROWSER');
				$user = $this->readKey($data, 'PSS_USR_USER_CODE');
			}
			elseif ($type == ajwp_SessionEventIterator::TYPE_INQUIRY)
			{
				$id = $this->readKey($data, 'PIN_ID');
				$user = $this->readKey($data, 'PIN_USR_USER_CODE');
				
				// ip & browser not available in this case, but user is
				// always logged in, so no matter: carry over from previous
				// loop to mask their absence.
				$ip = $lastIp;
				$browser = $lastBrowser;
			}
			else
			{
				// Logic error: unrecognised row source marker
				throw new Exception();
			}
			
			// If this is the first event, increment the session counter (to 1)
			if ($sessionCounter == 0)
			{
				$sessionCounter++;
			}
			// If not, test event against previous event ...
			else
			{
				// If ip, browser and user match those of the last event
				// and the last event is no more than 10 minutes ago
				// this is the same session
				$sameSession = $lastIp == $ip && $lastBrowser == $browser && $lastUser == $user && $lastTimestamp > ($timestamp - 60 * 10);
				
				// Advance the session counter if not the same session
				if (! $sameSession)
				{
					$sessionCounter++;
				}
			}
			
			// Mark session against event
			$this->markSession($type, $id, $sessionCounter);
			
			// Log every 1000th row
			if ($rowCounter % 1000 == 0 && $rowCounter != 0)
			{
				$this->log("Marked seesion on n rows: $rowCounter");
			}
			
			// Record values for comparison in next loop
			$lastIp = $ip;
			$lastBrowser = $browser;
			$lastUser = $user;
			$lastTimestamp = $timestamp;
			
			// Increment row counter
			$rowCounter++;
		}
	}
	
	private function markSession($type, $id, $sessionCounter)
	{
		$db = ajwp_Db::getSingleton();
		
		$idQuoted = $db->quote($id);
		$sessionCounterQuoted = $db->quote($sessionCounter);
		
		// Set table, sessionCol & idCol according to type
		if ($type == ajwp_SessionEventIterator::TYPE_SEARCH)
		{
			$table = ajwp_SessionEventIterator::SEARCH_TABLE;
			$sessionCol = 'pst_session_id';
			$idCol = 'pst_id';
		}
		elseif ($type == ajwp_SessionEventIterator::TYPE_SUPPLIER)
		{
			$table = ajwp_SessionEventIterator::SUPPLIER_TABLE;
			$sessionCol = 'pss_session_id';
			$idCol = 'pss_id';
		}
		elseif ($type == ajwp_SessionEventIterator::TYPE_INQUIRY)
		{
			$table = ajwp_SessionEventIterator::INQUIRY_TABLE;
			$sessionCol = 'pin_session_id';
			$idCol = 'pin_id';
		}
		else
		{
			// Logic error: unrecognised row source marker
			throw new Exception();
		}
		
		$sql = "update $table set $sessionCol = $sessionCounterQuoted where $idCol = $idQuoted";
		$db->execute($sql);
	}
	
	private function log ($msg)
	{
		echo "$msg\n";
	}
	
	/**
	 * Read key from array, throwing exception if key does not exist.
	 */
	private function readKey (array $arr, $key)
	{
		if (! array_key_exists($key, $arr))
		{
			throw new Exception();
		}
		
		return $arr[$key];
	}
}
