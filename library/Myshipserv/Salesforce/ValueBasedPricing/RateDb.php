<?php
/**
 * Class retrieving billable GMV for billable suppliers, but unlike Myshipserv_Salesforece_ValueBasedPricing_Rate
 * is based on Active Promotion multi-tiered rate and is, hopefully, not a mess unlike that one
 *
 * @author  Yuriy Akopov
 * @story   S16472
 * @date    2016-04-29
 */
// commented out by Yuriy Akopov on 2016-06-08, S16162 as Salesforce library is moved to composer
// require_once ('/var/www/libraries/SalesForce/SforceEnterpriseClient.php');
class Myshipserv_Salesforce_ValueBasedPricing_RateDb extends Myshipserv_Salesforce_Base
{
	/**
	 * @var null|resource
	 */
	protected $errorCsv = null;

	/**
	 * @var Myshipserv_Logger_Base
	 */
	protected $logger = null;

	/**
	 * @var DateTime|null
	 */
	protected $dateStart = null;

	/**
	 * @var DateTime|null
	 */
	protected $dateEnd = null;

	/**
	 * @var DateTime|null
	 */
	protected $dateEndInc = null;

	/**
	 * @var array
	 */
	protected $errors = array();

	/**
	 * Returns errors generated during the last sync
	 * 
	 * @return array
	 */
	public function getErrors()
    {
		return $this->errors;
	}

	/**
	 * @param   Myshipserv_Logger_File  $logger
	 * @param   DateTime                $dateStart
	 * @param   DateTime                $dateEnd
	 */
	public function __construct(Myshipserv_Logger_File $logger, DateTime $dateStart, DateTime $dateEnd)
    {
		$this->logger = $logger;

		parent::initialiseConnection();

		$this->dateStart = $dateStart;
		$this->dateEnd   = $dateEnd;
		$this->dateEndInc = clone($dateEnd);
		$this->dateEndInc->modify('-1 day');

		
		$this->logger->log("Initialised date interval from " . $this->dateStart->format('Y-m-d H:i:s') . " to " . $this->dateEnd->format('Y-m-d H:i:s'));
	}

	/**
	 * Logs and also prints the message
	 *
	 * @param   string  $message
	 */
	protected function output($message)
    {
		$this->logger->log($message);
		
		$now = new DateTime();
		print "[" . $now->format('Y-m-d H:i:s') . "] " . $message;
	}

	/**
	 * Retrieves basic SalesForce information about the the given supplier
	 *
	 * @param   int     $supplierId
	 *
	 * @return  array
	 * @throws  Myshipserv_Salesforce_Exception
	 */
	public function getSupplierSalesforceInfo($supplierId)
    {
		try {
			$response = $this->querySalesforce(
				"
				SELECT
					Id,
					TNID__c,
					Name,
					Contracted_under__r.Id,
					Contracted_under__r.Type_of_agreement__c,
					Contracted_under__r.Status,
					Contracted_under__r.StartDate
				FROM
					Account
				WHERE
					TNID__c != null
			        AND TNID__c = " . $supplierId . "
				", true
			);

		} catch (SoapFault $e) {
			$this->output(
				get_class($e) . ": " .	(isset($e->faultstring) ? $e->faultstring : "") . $e->getMessage() .
				" - Last SF request: " . print_r($this->sfConnection->getLastRequest(), true)
			);
			throw new Myshipserv_Salesforce_Exception($e->getMessage());
		}

		if ($response->size == 0) {
			$message = "Supplier " . $supplierId . " not found in SalesForce";

			$this->output($message);
			throw new Myshipserv_Salesforce_Exception($message);
		}

		$accountRecord = $response->records[0];
		$contractRecord = $accountRecord->Contracted_under__r;

		$salesForceInfo = array(
			'accountId'     => $accountRecord->Id,
			'contractId'    => null,
			'contractStart' => null,
			'rateSetId'     => null
		);

		if (
			isset($contractRecord->Id) and
			($contractRecord->Status === 'Active') and
			in_array($contractRecord->Type_of_agreement__c, self::getContractStatusSpellings())
		) {
			// active SalesForce contract
			$salesForceInfo['contractId'] = $contractRecord->Id;

			// convert the date
			if (strlen($contractRecord->StartDate)) {
				$tmpDateBits = explode("-", $contractRecord->StartDate);
				$dateObject = mktime(0, 0, 0, $tmpDateBits[1], $tmpDateBits[2], $tmpDateBits[0]);

				$salesForceInfo['contractStart'] = date('d-M-Y', $dateObject);
			}

			// check for RateSet ID which we might also need for the generated CSV
			try {
				$response = $this->querySalesforce(
					"
					SELECT
						Id,
						PO_percentage_fee__c,
						Target_PO_Fee__c,
						Target_PO_Fee_Lock_Period_Days__c
					FROM
						Rate__c
					WHERE
						Contract__c='" . $salesForceInfo['contractId'] . "'
						AND Active_Rates__c=true
					", true
				);

			} catch (SoapFault $e) {
				$this->output(
					get_class($e) . ": " .	(isset($e->faultstring) ? $e->faultstring : "") . $e->getMessage() .
					" - Last SF request: ", print_r($this->sfConnection->getLastRequest(), true)
				);
				throw new Myshipserv_Salesforce_Exception($e->getMessage());
			}

			if ($response->size == 0) {
				$message = "RateSet not found in SalesForce for a contracted supplier " . $supplierId . ", contract " . $salesForceInfo['contractId'];

				$this->output($message);
				throw new Myshipserv_Salesforce_Exception($message);
			}

			$rateRecord = $response->records[0];

			$salesForceInfo['rateSetId'] = $rateRecord->Id;
		}

		return $salesForceInfo;
	}

	/**
	 * Returns billable GMV for the provided date interval
	 *
	 * A modified version of the query from Report Service with the difference that is groups GMV by rate
	 *
	 * @author  Yuriy Akopov
	 * @date    2016-03-18
	 * @story   S15989
	 *
	 * @param   int         $supplierId
	 * @param   array       $intervals
	 * @param   bool        $onlyTargetRate
	 *
	 * @return  array
	 */
	protected function getBilledIntervalCurrentGmv($supplierId, array $intervals, $onlyTargetRate)
	{
		$params = array(
			'tnid' => $supplierId
		);

		// convert the provided set of time intervals into SQL parameters and expressions
		$constraints = array();
		$intervalNo = 1;
		foreach ($intervals as $interval) {
			// date intervals as parameters
			$params['lowerDate' . $intervalNo] = $interval['from']->format('Y-m-d H:i:s');
			$params['upperDate' . $intervalNo] = $interval['till']->format('Y-m-d H:i:s');
			// expressions relying on those parameters
			$constraints[] = array(
				'from' => 'TO_DATE(:lowerDate' . $intervalNo . ", 'YYYY-MM-DD HH24:MI:SS')",
				'till' => 'TO_DATE(:upperDate' . $intervalNo . ", 'YYYY-MM-DD HH24:MI:SS')"
			);

			$intervalNo++;
		}

		// build actual constraint from the prepared expressions and the field name provided
		$getDateConstraint = function ($fieldDate) use ($constraints) {
			$where = array();
			foreach ($constraints as $datePair) {
				$where[] = $fieldDate . ' >= ' . $datePair['from'] . ' AND ' . $fieldDate . ' < ' . $datePair['till'];
			}

			return '
				(
					(' . implode(') OR (', $where) . ')
				)
			';
		};

		if ($onlyTargetRate) {
			$rateConstraint = "ord_sbr_rate_std = 0";
		} else {
			$rateConstraint = "ord_sbr_rate_std = 1";
		}

		$sql = "
			SELECT
			  ord_sbr_rate_std,
			  ord_sbr_rate_value,
			  NVL(SUM(gmv), 0) AS gmv
			FROM
			  (
			    -- easy part - original new orders with no replacements
			    SELECT
			      ord.ord_sbr_rate_std,
			      ord.ord_sbr_rate_value,
			      NVL(SUM(orig.ord_total_cost_discounted_usd), 0) AS gmv
			    FROM
			      billable_po_orig orig
			      JOIN ord ON
			        ord.ord_internal_ref_no = orig.ord_internal_ref_no
			    WHERE
			      -- orig.ord_submitted_date >= TO_DATE(:lowerDate , 'yyyymmdd')
			      -- AND orig.ord_submitted_date < TO_DATE(:upperDate, 'yyyymmdd')
			      " . $getDateConstraint('orig.ord_submitted_date') . "
			      
			      AND orig.spb_branch_code = :tnid
			      -- orders that have no replacements in the billed time interval
			      AND NOT EXISTS(
			        SELECT /*+UNNEST*/
			          NULL
			        FROM
			          billable_po_rep
			        WHERE
			          -- ord_submitted_date >= TO_DATE(:lowerDate , 'yyyymmdd')
			          -- AND ord_submitted_date < TO_DATE(:upperDate, 'yyyymmdd')
			          " . $getDateConstraint('ord_submitted_date') . "
			          AND spb_branch_code = :tnid
			          AND ord_original_no = orig.ord_internal_ref_no
			      )
			      -- no BuyerConnect transactions
			      AND nvl(orig.ord_is_buyerconnect, 0) = 0
			      AND nvl(orig.ord_is_consortia, 0) = 0 
			    GROUP BY
			      -- ord.ord_sbr_id,
			      ord.ord_sbr_rate_std,
			      ord.ord_sbr_rate_value

			    UNION ALL

			    -- order replacements in the billed interval
			    SELECT
			      ord.ord_sbr_rate_std,
			      ord.ord_sbr_rate_value,
			      NVL(SUM(rep.ord_total_cost_discounted_usd), 0) AS gmv
			    FROM
			      billable_po_rep rep
			      JOIN ord ON
			        ord.ord_internal_ref_no = rep.ord_internal_ref_no
			    WHERE
			      -- only the most recent replacements in the chain, if there were more than one for the same order
			      rep.primary_id IN (
			        SELECT /*+HASH_SJ*/
			          MAX(PRIMARY_ID)
			        FROM
			          billable_po_rep
			        WHERE
			          -- ord_submitted_date >= TO_DATE(:lowerDate , 'yyyymmdd')
			          -- AND ord_submitted_date < TO_DATE(:upperDate, 'yyyymmdd') -- + 0.99999 and
			          " . $getDateConstraint('ord_submitted_date') . "
			          AND spb_branch_code = :tnid
			        GROUP BY
			          ord_original_no
			      )
			      AND nvl(rep.ord_is_buyerconnect, 0) = 0
			      AND nvl(rep.ord_is_consortia, 0) = 0 
			    GROUP BY
			      ord.ord_sbr_rate_std,
			      ord.ord_sbr_rate_value
			  )
		    WHERE
		        " . $rateConstraint . "		  
			GROUP BY
			  ord_sbr_rate_std,
			  ord_sbr_rate_value
		";

		/*
		print $sql . "\n";
		print_r($params);
		die;
		*/

		$key = implode(
		    '_',
            array(
                __FUNCTION__,
                md5($sql),
                md5(print_r($params, true))
    		)
        );
		$rows = $this->fetchCachedQuery($sql, $params, $key, self::MEMCACHE_TTL, Shipserv_Oracle::SSREPORT2);

		return $rows;
	}

	/**
	 * Returns previous billed GMV which was replaced in the current interval for the provided date interval
	 *
	 * A modified version of the query from Report Service with the difference that is groups GMV by rate
	 *
	 * @author  Yuriy Akopov
	 * @date    2016-03-18
	 * @story   S15989
	 *
	 * @param   int     $supplierId
	 * @param   array   $intervals
	 * @param   bool    $onlyTargetRate
	 *
	 * @return  array
	 */
	protected function getBilledIntervalReplacedGmv($supplierId, array $intervals, $onlyTargetRate)
    {
		$params = array(
			'tnid' => $supplierId,
		);

		// convert the provided set of time intervals into SQL parameters and expressions
		$constraints = array();
		$intervalNo = 1;
		foreach ($intervals as $interval) {
			$params['lowerDate' . $intervalNo] = $interval['from']->format('Y-m-d H:i:s');
			$params['upperDate' . $intervalNo] = $interval['till']->format('Y-m-d H:i:s');

			$constraints[] = array(
				'from' => 'TO_DATE(:lowerDate' . $intervalNo . ", 'YYYY-MM-DD HH24:MI:SS')",
				'till' => 'TO_DATE(:upperDate' . $intervalNo . ", 'YYYY-MM-DD HH24:MI:SS')"
			);

			$intervalNo++;
		}

		// build actual constraint from the prepared expressions and the field name provided
		$getDateConstraint = function ($field) use ($constraints) {
			$where = array();
			foreach ($constraints as $datePair) {
				$where[] = $field . ' >= ' . $datePair['from'] . ' AND ' . $field . ' < ' . $datePair['till'];
			}

			return '((' . implode(') OR (', $where) . '))';
		};

		// build actual constraint from the prepared expressions and the field name provided, but for replaced order interval
		$getDateConstraintPast = function ($field) use ($constraints) {
			$where = array();
			foreach ($constraints as $datePair) {
				$where[] = $field . ' >= ADD_MONTHS(' . $datePair['from'] . ', -12) AND ' . $field . ' < ' . $datePair['from'];
			}

			return '
				(
					(' . implode(') OR (', $where) . ')
				)
			';
		};

		if ($onlyTargetRate) {
			$rateConstraint = "ord_sbr_rate_std = 0";
		} else {
			$rateConstraint = "ord_sbr_rate_std = 1";
		}

		$sql = "
			SELECT
			  ord_sbr_rate_std,
			  ord_sbr_rate_value,
			  NVL(SUM(gmv), 0) AS gmv
			FROM
			  (
			    -- original orders placed outside of billed interval
			    SELECT
			      ord.ord_sbr_rate_std,
			      ord.ord_sbr_rate_value,
			      NVL(SUM(orig.ord_total_cost_discounted_usd),0) AS gmv
			    FROM
			      billable_po_orig orig
			      JOIN ord ON
			        ord.ord_internal_ref_no = orig.ord_internal_ref_no
			    WHERE
			      -- orig.ord_submitted_date >= ADD_MONTHS(TO_DATE(:lowerDate, 'yyyymmdd'), -12)
			      -- AND orig.ord_submitted_date < TO_DATE(:lowerDate, 'yyyymmdd')
			      " . $getDateConstraintPast('orig.ord_submitted_date') . "
			      AND orig.spb_branch_code = :tnid
			      -- there is a replacement in the billed interval
			      AND EXISTS(
			        SELECT /*+HASH_SJ*/
			          NULL
			        FROM
			          billable_po_rep
			        WHERE
			          -- ord_submitted_date >= TO_DATE(:lowerDate, 'yyyymmdd')
			          -- AND ord_submitted_date < TO_DATE(:upperDate, 'yyyymmdd')
			          " . $getDateConstraint('ord_submitted_date') . "
			          AND spb_branch_code = :tnid
			          AND ORD_ORIGINAL_NO = orig.ORD_INTERNAL_REF_NO
			      )
			      -- but no replacements before the billed interval
			      AND NOT EXISTS(
			        SELECT /*+UNNEST*/
			          NULL
			        FROM
			          billable_po_rep repPrev
			        WHERE
			          -- ord_submitted_date >= ADD_MONTHS(TO_DATE(:lowerDate, 'yyyymmdd'), -12)
			          -- AND ord_submitted_date < TO_DATE(:lowerDate, 'yyyymmdd')
			          " . $getDateConstraintPast('ord_submitted_date') . "
			          AND spb_branch_code = :tnid
			          AND EXISTS(
			            SELECT /*+HASH_SJ*/
			              NULL
			            FROM
			              billable_po_rep repCur
			            WHERE
			              -- repCur.ord_submitted_date >= TO_DATE(:lowerDate, 'yyyymmdd')
			              -- AND repCur.ord_submitted_date < TO_DATE(:upperDate, 'yyyymmdd')
			              " . $getDateConstraint('repCur.ord_submitted_date') . "
			              AND repCur.spb_branch_code = :tnid
			              AND repCur.ORD_ORIGINAL_NO = repPrev.ORD_ORIGINAL_NO
			          )
			          AND repPrev.ORD_ORIGINAL_NO = orig.ORD_INTERNAL_REF_NO
			      )
			      AND nvl(orig.ord_is_buyerconnect, 0) = 0
                  AND nvl(orig.ord_is_consortia, 0) = 0 
			    GROUP BY
			      -- ord.ord_sbr_id,
			      ord.ord_sbr_rate_std,
			      ord.ord_sbr_rate_value

			    UNION ALL

			    -- replacements replaced again in the billed interval
			    SELECT
			      ord.ord_sbr_rate_std,
			      ord.ord_sbr_rate_value,
			      NVL(SUM(rep.ord_total_cost_discounted_usd), 0) AS total
			    FROM
			      billable_po_rep rep
			      JOIN ord ON
			        ord.ord_internal_ref_no = rep.ord_internal_ref_no
			    WHERE
			      rep.PRIMARY_ID IN (
			        SELECT /*+HASH_SJ*/
			          MAX(PRIMARY_ID)
			        FROM
			          billable_po_rep repPrev
			        WHERE
			          -- ord_submitted_date >= ADD_MONTHS(TO_DATE(:lowerDate , 'yyyymmdd'), -12)
			          -- AND ord_submitted_date < TO_DATE(:lowerDate, 'yyyymmdd')
			          " . $getDateConstraintPast('ord_submitted_date') . "
			          AND spb_branch_code = :tnid
			          -- which were replaced in the billed interval
			          AND EXISTS(
			            SELECT /*+HASH_SJ*/
			              NULL
			            FROM
			              billable_po_rep repCur
			            WHERE
			              -- repCur.ord_submitted_date >= TO_DATE(:lowerDate, 'yyyymmdd')
			              -- AND repCur.ord_submitted_date < TO_DATE(:upperDate, 'yyyymmdd')
			              " . $getDateConstraint('repCur.ord_submitted_date') . "
			              AND repCur.spb_branch_code = :tnid
			              AND repCur.ORD_ORIGINAL_NO = repPrev.ORD_ORIGINAL_NO
			          )
			        GROUP by ord_original_no
			      )
			      AND nvl(rep.ord_is_buyerconnect, 0) = 0
			      AND nvl(rep.ord_is_consortia, 0) = 0 
			    GROUP BY
			      ord.ord_sbr_rate_std,
			      ord.ord_sbr_rate_value
			  )
			WHERE
				" . $rateConstraint . "
			GROUP BY
			  -- ord_sbr_id,
			  ord_sbr_rate_std,
			  ord_sbr_rate_value
		";

		/*
		print $sql . "\n";
		print_r($params);
		die;
		*/

		$key = implode(
		    '_',
            array(
                __FUNCTION__,
                md5($sql),
                md5(print_r($params, true))
    		)
        );
		$rows = $this->fetchCachedQuery($sql, $params, $key, self::MEMCACHE_TTL, Shipserv_Oracle::SSREPORT2);

		return $rows;
	}

	/**
	 * Returns pure date intervals for where to calculate GMV
	 *
	 * @param   int $supplierId
	 *
	 * @return  array
	 */
	protected function getContractedIntervals($supplierId)
    {
		$rateObj = new Shipserv_Supplier_Rate($supplierId);
		$rates = $rateObj->getRatesInTheInterval($this->dateStart, $this->dateEnd);

		$intervals = array();
		foreach ($rates as $rate) {
			$intervals[] = array(
				'from' => $rate[Shipserv_Supplier_Rate::COL_VALID_FROM],
				'till' => $rate[Shipserv_Supplier_Rate::COL_VALID_TILL]
			);
		}

		return $intervals;
	}

	/**
	 * Glues time intervals of supplier rates of the same value and type in order to match GMV grouping
	 *
	 * @param   int $supplierId
	 *
	 * @return  array
	 */
	protected function groupSupplierRatesDates($supplierId)
    {
		$rateObj = new Shipserv_Supplier_Rate($supplierId);
		$rates = $rateObj->getRatesInTheInterval($this->dateStart, $this->dateEnd);

		$groupedRates = array();

		$glueIntervals = function ($key, array $rate) use (&$groupedRates) {
			if (!array_key_exists($key, $groupedRates)) {
				$groupedRates[$key] = array(
					'from' => $rate[Shipserv_Supplier_Rate::COL_VALID_FROM],
					'till' => $rate[Shipserv_Supplier_Rate::COL_VALID_TILL]
				);
			} else {
				$groupedRates[$key]['from'] = min($groupedRates[$key]['from'], $rate[Shipserv_Supplier_Rate::COL_VALID_FROM]);
				$groupedRates[$key]['till'] = max($groupedRates[$key]['till'], $rate[Shipserv_Supplier_Rate::COL_VALID_TILL]);
			}
		};

		foreach ($rates as $row) {
			$standardRateKey = 'standard_' . (float) $row[Shipserv_Supplier_Rate::COL_RATE_STANDARD];
			$glueIntervals($standardRateKey, $row);

			if ($row[Shipserv_Supplier_Rate::COL_RATE_TARGET] > 0) {
				$targetRateKey = 'target_' . (float) $row[Shipserv_Supplier_Rate::COL_RATE_TARGET];
				$glueIntervals($targetRateKey, $row);
			}
		}

		foreach ($groupedRates as $rateKey => $rate) {
			// turn non-inclusive end dates into inclusive as expected by SalesForce
			if ($rate['till'] === $this->dateEnd) {
				$groupedRates[$rateKey]['till'] = $this->dateEndInc;
			}
		}

		return $groupedRates;
	}

	/**
	 * Assigns time interval associated with supplier value rates in the billed period
	 * This is not entirely logical and is suited to what the clients expect to get in SalesForce
	 * e.g. two intervals with a break in between will form a long one without a break etc.
	 *
	 * @param   int     $supplierId
	 * @param   array   $gmvRows
	 * @param   array   $joinedIntervals
	 *
	 * @return  array
	 */
	protected function assignGmvTimeIntervals($supplierId, array $gmvRows, array $joinedIntervals)
    {
		$supplierRateDates = $this->groupSupplierRatesDates($supplierId);
		$this->logger->log("Found " . count($supplierRateDates) . " rate intervals and " . count($gmvRows) . " GMV rows for supplier " . $supplierId);

		// rates provided in $gmvRows will be grouped by rate value and type (standard/target)
		// in order to assign a date interval to them, we need to look for the same value and type rate in active
		// supplier rates and use the corresponding interval, or use the whole billed period as an interval

		// var_dump($gmvRows);
		// var_dump($supplierRateDates);

		$gmvData = array();

		foreach ($supplierRateDates as $rateKey => $rateDates) {
			$rateKeyInfo = explode('_', $rateKey); // this is ugly, should be dedicated fields

			$rateRow = array(
				'start'         => $rateDates['from'],
				'end'           => $rateDates['till'],
				'rateStandard'  => ($rateKeyInfo[0] === 'standard'),
				'rateValue'     => (float) $rateKeyInfo[1],
				'ratePrior'     => false,
				'gmv'           => 0  // for the rate to appear in the report even if there was no recorded GMV for it
			);

			foreach ($gmvRows as $index => $row) {
				// careful: this rule should be aligned with groupSupplierRatesDates() logic
				$gmvRateKey = (($row['ORD_SBR_RATE_STD'] == 1) ? 'standard' : 'target');
				$gmvRateKey .= '_' . (float) $row['ORD_SBR_RATE_VALUE'];

				if ($rateKey === $gmvRateKey) {
					$rateRow['gmv'] += (float) $row['GMV'];
					unset($gmvRows[$index]);    // removing the row as processed
				}
			}

			$gmvData[] = $rateRow;
		}

		if (!empty($gmvRows)) {
			$this->logger->log(count($gmvRows) . " GMV rows were not matched to supplier " . $supplierId . " contracted rates");

			foreach ($gmvRows as $row) {
				// remaining GMV rows for which there were no matching rates in the intervals (replacements?)
				$gmvData[] = array(
					'start' => $joinedIntervals['from'],
					'end'   => (($joinedIntervals['till'] == $this->dateEnd) ? $this->dateEndInc : $joinedIntervals['till']),
					'rateStandard' => ($row['ORD_SBR_RATE_STD'] == 1),
					'rateValue' => (float) $row['ORD_SBR_RATE_VALUE'],
					'ratePrior'  => true,   // rate was not found in this month's rates, could be replacements or locked relationships
					'gmv' => $row['GMV']
				);
			}
		}

		return $gmvData;
	}	

	/**
	 * Returns billing report figures for the given supplier
	 *
	 * @param   int     $supplierId
	 * @param   bool    $includeTnid
	 *
	 * @return  array
	 * @throws  Myshipserv_Salesforce_Exception
	 */
	public function getSupplierVbpCsvRows($supplierId, $includeTnid = true)
    {
		$supplierSalesForceInfo = $this->getSupplierSalesforceInfo($supplierId);

		// contracted intervals for standard rate queries
		$intervals = $this->getContractedIntervals($supplierId);

		// max joined interval for replacements
		$minFrom = null;
		$maxTill = null;
		foreach ($intervals as $interval) {
			if (is_null($minFrom)) {
				$minFrom = $interval['from'];
			} else {
				$minFrom = min($minFrom, $interval['from']);
			}

			if (is_null($maxTill)) {
				$maxTill = $interval['till'];
			} else {
				$maxTill = max($maxTill, $interval['till']);
			}
		}
		$joinedIntervals = array(array('from' => $minFrom, 'till' => $maxTill));

		// whole billed period for target rate queries
		$period = array(array(
			'from' => $this->dateStart,
			'till' => $this->dateEnd
		));

		// calculating GMV with standard rate for intervals where supplier was under contract in the billed period
		$standardRateCurrentGmvData = $this->getBilledIntervalCurrentGmv($supplierId, $intervals, false);
		$this->logger->log("Found " . count($standardRateCurrentGmvData) . " standard rate GMV bands for " . $supplierId);
		// calculating GMV with target rate for the whole billed period
		$targetRateCurrentGmvData = $this->getBilledIntervalCurrentGmv($supplierId, $period, true);
		$this->logger->log("Found " . count($targetRateCurrentGmvData) . " target rate GMV bands for " . $supplierId);
		// merging both results together
		$currentGmvData = array_merge($standardRateCurrentGmvData, $targetRateCurrentGmvData);

		/*
		// for replacements we have to run the query for every interval separately because those ORs will mess the query
		$standardRateReplacedGmvData = array();
		foreach ($intervals as $interval) {
			$standardRateIntervalReplacedGmvData = $this->getBilledIntervalReplacedGmv($supplierId, array($interval), false);
			$standardRateReplacedGmvData = array_merge($standardRateReplacedGmvData, $standardRateIntervalReplacedGmvData);
		}
		*/
		$standardRateReplacedGmvData = $this->getBilledIntervalReplacedGmv($supplierId, $joinedIntervals, false);
		$this->logger->log("Found " . count($standardRateReplacedGmvData) . " standard rate replaced GMV bands for " . $supplierId);
		// target rate is requested for a single interval, so it's okay
		$targetRateReplacedGmvData = $this->getBilledIntervalReplacedGmv($supplierId, $period, true);
		$this->logger->log("Found " . count($targetRateReplacedGmvData) . " target rate replaced GMV bands for " . $supplierId);
		$replacedGmvData = array_merge($standardRateReplacedGmvData, $targetRateReplacedGmvData);

		// var_dump($currentGmvData);
		// var_dump($replacedGmvData);

		// deduct replaced GMV from the current one
		$combinedGmvData = $currentGmvData;

		foreach ($replacedGmvData as $replaced) {
			$this->logger->log(
				"Looking to deduct " . $replaced['GMV'] . " GMV for supplier " . $supplierId .
				" at the rate standard=" . $replaced['ORD_SBR_RATE_STD'] . " value=" . $replaced['ORD_SBR_RATE_VALUE'] . "..."
			);

			// looking from where to deduct - searching for the same rate ID (or the lack of it)
			foreach ($currentGmvData as $index => $current) {
				if (
					($current['ORD_SBR_RATE_STD'] === $replaced['ORD_SBR_RATE_STD']) and
					($current['ORD_SBR_RATE_VALUE'] === $replaced['ORD_SBR_RATE_VALUE']) and
					($replaced['GMV'] != 0)
				) {
					// a current GMV row charged at the same rate was found
					$combinedGmvData[$index]['GMV'] -= $replaced['GMV'];
					$this->logger->log("...deducted from an existing row");
					continue(2);
				}
			}

			// no current GMV row from which to deduct, an new row with a negative amount should be added
			// theoretically, this should not happen in our current model because all the replacements are
			// charged at the same rate as the original
			$replaced['GMV'] *= (-1);
			$replaced['newRateRow'] = true;
			$combinedGmvData[] = $replaced;
			$this->logger->log("...added as a new row");
		}

		// var_dump($combinedGmvData);
		// die;

		// assign rate time intervals to resulting GMV rows
		// there might be rate intervals without any orders
		// there might be orders outside of rate intervals (locked relationship orders, replacements)
		// not everything is expected to match perfectly
		// in case of mismatch default date will be the whole period of bill
		$preparedGmvRows = $this->assignGmvTimeIntervals($supplierId, $combinedGmvData, $joinedIntervals[0]);

		$csvRows = array();
		foreach ($preparedGmvRows as $gmvRow) {
			// for standard rate intervals with 0 GMV we still want to show them,
			// but not target rate intervals with 0 GMV
			if ((!$gmvRow['rateStandard']) and ($gmvRow['gmv'] == 0)) {
				$this->logger->log("Skipping 0 GMV row for supplier " . $supplierId . ", standard=" . $gmvRow['rateStandard'] . ", rate=" . $gmvRow['rateValue']);
				continue;
			}

			// zero rate means no charge so this is skipped as well
			if ($gmvRow['rateValue'] == 0) {
				if ($gmvRow['gmv'] != 0) {
					$this->logger->log("Skipping " . $gmvRow['gmv'] . " for supplier " . $supplierId . " as the standard=" . $gmvRow['rateStandard'] . " rate value is 0");
				}
				continue;
			}

			if ($gmvRow['ratePrior'] and ($gmvRow['gmv'] == 0)) {
				// no need to include prior rate when it is for 0 GMV
				continue;
			}

			// S18134: attempt to find rateset Id for the rate value added on this row
			$rateObj = new Shipserv_Supplier_Rate($supplierId);
			$rateSetId = $rateObj->findRateSetId($gmvRow['rateValue'], $gmvRow['rateStandard'], $gmvRow['start'], $gmvRow['end']);
			if (is_null($rateSetId)) {
				// couldn't find rate ID in the database, falling back to the currently active rate set, if there is any
				$rateSetId = $supplierSalesForceInfo['rateSetId'];
			}

			$nextRow = array(
				'Period_start__c' => $gmvRow['start']->format('Y-m-d'),
				'Period_end__c'   => $gmvRow['end']->format('Y-m-d'),
				
				'Gross_Merchandise_Value__c' => round($gmvRow['gmv'], 2),
				
				'Rate_Set_Type__c' => $this->getGmvRowLabel($gmvRow), // ($gmvRow['rateStandard'] ? 'Standard' : 'Active Promo'),
				'Rate_value__c'    => $gmvRow['rateValue'],

				'Rate__c'               => $rateSetId,
				'TransactionAccount__c' => $supplierSalesForceInfo['accountId'],

				'TNID' => $supplierId
			);

			if (!$includeTnid) {
				unset($nextRow['TNID']);
			}

			$csvRows[] = $nextRow;
		}

		return $csvRows;
	}

	/**
	 * Returns SalesForce hint attribute string for the given rate/GMV row
	 *
	 * @param array $gmvRow
	 *
	 * @return string
	 */
	protected function getGmvRowLabel(array $gmvRow)
    {
		if ($gmvRow['ratePrior']) {
			return 'Prior Rate';
		}

		if (!$gmvRow['rateStandard']) {
			return 'Active Promo';
		}

		return 'Standard';
	}

	/**
	 * @param   bool    $includeTnid
	 *
	 * @return array
	 */
	public static function getCsvHeaders($includeTnid = true)
    {
		$headers = array(
			'Period_start__c',
			'Period_end__c',
			'Gross_Merchandise_Value__c',
			'Rate_Set_Type__c',
			'Rate_value__c',
			'Rate__c',
			'TransactionAccount__c',
		);

		if ($includeTnid) {
			$headers[] = 'TNID';
		}

		return $headers;
	}
}