<?php
/**
* Return the aditionl buyer related columns for a row
*/

class Shipserv_Oracle_Targetcustomers_Buyerinfo {

	/**
    * @var Singleton The reference to *Singleton* instance of this class
    */
    private static $instance;
    protected $db;
    protected $dao;
    protected $hierarchy;

    // flattened list of buyer branches participating in AutoSource
    // added by Yuriy Akopov on 2017-08-25, BUY-671
    protected static $autoSourceBranchIds = null;

    /**
    * Returns the *Singleton* instance of this class.
    *
    * @return Shipserv_Oracle_Targetcustomers_Buyerinfo
    */
    public static function getInstance()
    {
        if (null === static::$instance) {
            static::$instance = new static();
        }
        
        return static::$instance;
    }

    /**
    * Protected what we have to hide
    */
    protected function __construct() {
    	$this->db = Shipserv_Helper_Database::getSsreport2Db();
    	$this->sservdba = Shipserv_Helper_Database::getDb();
    	$this->dao = new Shipserv_Oracle_Targetcustomers_Dao;
    }
    private function __clone()  {}

    public function setHierarchy( $hierarchy )
    {
    	$this->hierarchy = $hierarchy;
    	return static::$instance;
    }

	/**
	 * A simpler function that only calculates the number of RFQs between the buyer and supplier since the specified date
	 * This is because the RFQ_COUNT value provided by getBuyerInfo() doesn't seem to be correct, but the query is complex
	 * and I don't want to amend it without Attila knowing
	 *
	 * @author  Yuriy Akopov
	 * @date    2016-03-02
	 *
	 * @param   int         $bybBranchCode
	 * @param   int         $spbBranchCode
	 * @param   DateTime    $filterDate
	 *
	 * @return  int
	 */
	public function getRfqCountInfo($bybBranchCode, $spbBranchCode, DateTime $filterDate, $includeChildren = true) {

		//Use recursive query if icludeChildren is On
		$filterDate = $this->roundFilterDate($filterDate);
		$branchList = $this->getBranchList($bybBranchCode);

		$params = array(
				'spbBranchCode' => $spbBranchCode
			);

		if ($includeChildren === true) {

			$buyerListSql = "
				SELECT
					b.byb_branch_code
				FROM
					buyer b
				WHERE
					b.byb_branch_code IN (".$branchList .")
			";
		} else {
			$buyerListSql = "
				SELECT
					TO_NUMBER(:bybBranchCode) byb_branch_code 
				FROM
					dual
			";
			$params['bybBranchCode'] = $bybBranchCode;
		}

		$sql = "
			WITH buyer_data AS
			(".$buyerListSql."), base AS
			(
			    SELECT
					COUNT(rfq.rfq_internal_ref_no) AS rfq_count
				FROM
					rfq
					JOIN buyer_data bd
					ON (rfq.byb_branch_code = bd.byb_branch_code)
				WHERE
					rfq.spb_branch_code = :spbBranchCode
					AND rfq.rfq_submitted_date >= " . Shipserv_Helper_Database::getOracleDateExpr($filterDate) . "

				UNION ALL
				SELECT
					COUNT(rfq.rfq_internal_ref_no) AS rfq_count
				FROM
					rfq
					JOIN rfq rfq_src ON
					rfq_src.rfq_internal_ref_no = rfq.rfq_pom_source
			        JOIN buyer_data bd
			        ON (rfq_src.byb_branch_code = bd.byb_branch_code)
				WHERE
					rfq.spb_branch_code = :spbBranchCode
					AND rfq.rfq_submitted_date >= " . Shipserv_Helper_Database::getOracleDateExpr($filterDate) . "
					AND rfq_src.spb_branch_code=999999
			)

				SELECT
					SUM(rfq_count) AS rfq_count
				FROM
					base
		";

		// print($sql); print_r($params); die();

		$result = $this->dao->fetchQuery(
			$sql,
			$params, 
			__METHOD__
		);

		return (int) $result[0]['RFQ_COUNT'];
	}

	/*

	public function getRfqCountInfo($bybBranchCode, $spbBranchCode, DateTime $filterDate) {
		$sql = "
			SELECT
			  SUM(rfqs.rfq_count) AS rfq_count
			FROM
			  (
			    SELECT
			      COUNT(rfq.rfq_internal_ref_no) AS rfq_count
			    FROM
			      rfq
			    WHERE
			      rfq.spb_branch_code = :spbBranchCode
			      AND rfq.byb_branch_code = :bybBranchCode
			      AND rfq.rfq_submitted_date >= " . Shipserv_Helper_Database::getOracleDateExpr($filterDate) . "

			    UNION ALL

			    SELECT
			      COUNT(rfq.rfq_internal_ref_no) AS rfq_count
			    FROM
			      rfq
			      JOIN rfq rfq_src ON
			        rfq_src.rfq_internal_ref_no = rfq.rfq_pom_source
			    WHERE
			      rfq.spb_branch_code = :spbBranchCode
			      AND rfq_src.byb_branch_code = :bybBranchCode
			      AND rfq.rfq_submitted_date >= " . Shipserv_Helper_Database::getOracleDateExpr($filterDate) . "
			      AND rfq_src.spb_branch_code=999999

			  ) rfqs
		";

		$result = $this->dao->fetchQuery(
			$sql,
			array(
				'spbBranchCode' => $spbBranchCode,
				'bybBranchCode' => $bybBranchCode
			),
			__METHOD__
		);

		return (int) $result[0]['RFQ_COUNT'];
	}
	*/

    /**
     * Returns buyer branch address details for Active Promotion
     *
     * @author  Yuriy Akopov
     * @date    2017-08-29
     * @story   BUY-671
     *
     * @param   int $buyerId
     *
     * @return  array
     */
	protected function getBuyerInfoAddress($buyerId)
    {
        $select = new Zend_Db_Select(Shipserv_Helper_Database::getDb());
        $select
            ->from(
                array('byb' => Shipserv_Buyer_Branch::TABLE_NAME),
                array(
                    'byb.' . Shipserv_Buyer_Branch::COL_ID,
                    'byb.' . Shipserv_Buyer_Branch::COL_NAME,
                    'byb.' . Shipserv_Buyer_Branch::COL_CITY,
                )
            )
            ->joinLeft(
                array('cnt' => Shipserv_Oracle_Countries::TABLE_NAME),
                'cnt.' . Shipserv_Oracle_Countries::COL_CODE_COUNTRY . ' = byb.' . Shipserv_Buyer_Branch::COL_COUNTRY,
                array(
                    'cnt.' . Shipserv_Oracle_Countries::COL_NAME_COUNTRY
                )
            )
            ->where('byb.' . Shipserv_Buyer_Branch::COL_ID . ' = ?', (int) $buyerId)
        ;

        // @todo: can be cached as buyer details don't change often
        $row = $select->getAdapter()->fetchRow($select);

        return $row;
    }

    /**
     * Returns buyer branch GMV details for Active Promotion
     *
     * @author  Yuriy Akopov
     * @date    2017-08-29
     * @story   BUY-671
     *
     * @param   int         $spbBranchCode
     * @param   array       $buyerBranchIds
     * @param   DateTime    $filterDate
     *
     * @return array
     */
    protected function getBuyerInfoGmv($spbBranchCode, array $buyerBranchIds, DateTime $filterDate = null)
    {
        if (!$filterDate) {
            $filterDate = new DateTime();
            $filterDate->modify("-12 months");  // @todo: move the default period definition closer to controller
        }

        $select = new Zend_Db_Select(Shipserv_Helper_Database::getSsreport2Db());
        $select
            ->from(
                array('otg' => 'ord_traded_gmv'),
                array(
                    'ORD_COUNT'       => new Zend_Db_Expr('COUNT(DISTINCT otg.ord_internal_ref_no)'),
                    'GMV'             => new Zend_Db_Expr('SUM(otg.ord_total_cost_usd)'),
                    'LAST_ORDER'      => new Zend_Db_Expr("TO_CHAR(MAX(otg.ord_orig_submitted_date), 'YYYY-MM-DD HH24:MI:SS')"),
                    'FIRST_ORDER'     => new Zend_Db_Expr("TO_CHAR(MIN(otg.ord_orig_submitted_date), 'YYYY-MM-DD HH24:MI:SS')")
                )
            )
            ->where('otg.spb_branch_code = ?', (int) $spbBranchCode)
            ->where('otg.byb_branch_code IN (?)', $buyerBranchIds)
            ->where('otg.ord_orig_submitted_date >= ' . Shipserv_Helper_Database::getOracleDateExpr($filterDate))
        ;

        $row = $select->getAdapter()->fetchRow($select);

        return $row;

    }

    /**
     * Returns buyer branch transaction stats details for Active Promotion
     *
     * @author  Yuriy Akopov
     * @date    2017-08-29
     * @story   BUY-671
     *
     * @param   int         $spbBranchCode
     * @param   array       $buyerBranchIds
     * @param   DateTime    $filterDate
     *
     * @return array
     */
    protected function getBuyerInfoTransactions($spbBranchCode, array $buyerBranchIds, DateTime $filterDate)
    {
        if (!$filterDate) {
            $filterDate = new DateTime();
            $filterDate->modify("-12 months");  // this to move the default period definition closer to controller
        }

        $selectPocCount = new Zend_Db_Select(Shipserv_Helper_Database::getDb());
        $selectPocCount
            ->from(
                array('poc' => 'poc'),
                new Zend_Db_Expr('COUNT(poc.ord_internal_ref_no)')
            )
            ->where('poc.ord_internal_ref_no = po.ord_internal_ref_no')
        ;

        $selectBase = new Zend_Db_Select(Shipserv_Helper_Database::getSsreport2Db());
        $selectBase
            ->from(
                array('po' => 'linked_rfq_qot_po'),
                array(
                    'RFQ_INTERNAL_REF_NO' => 'po.rfq_internal_ref_no',
                    'HAS_PO' => 'po.has_po',
                    'HAS_QOT' => 'po.has_qot',
                    'HAS_POC' => new Zend_Db_Expr(
                        'CASE
                            WHEN po.ord_internal_ref_no IS NOT NULL THEN (' . $selectPocCount->assemble() . ')
                            ELSE 0
                        END'
                    )
                )
            )
            ->where('po.spb_branch_code = ?', $spbBranchCode)
            ->where('po.byb_branch_code IN (?)', $buyerBranchIds)
            // ->where('po.is_competitive = 1')
            ->where('po.rfq_submitted_date >= ' . Shipserv_Helper_Database::getOracleDateExpr($filterDate))
        ;

        $select = new Zend_Db_Select(Shipserv_Helper_Database::getSsreport2Db());
        $select
            ->from(
                array('rate_base' => $selectBase),
                array(
                    'RFQ_COUNT'       => new Zend_Db_Expr('COUNT(rate_base.rfq_internal_ref_no)'),
                    'QOT_COUNT'       => new Zend_Db_Expr(
                        'SUM(
                            CASE
                                WHEN rate_base.has_qot = 1 OR rate_base.has_po = 1 THEN 1
                                ELSE 0
                            END
                        )'
                    )
                )
            )
        ;

        $row = $select->getAdapter()->fetchRow($select);

        return $row;
    }

    /**
     * Returns meta information requires for lists of buyer branches on Active Promotion tabs
     *
     * Serves the same purpose as retiredGetBuyerInfo() and implements the same logic, only does it by running queries separately instead of a big one
     *
     * @author  Yuriy Akopov
     * @date    2017-08-29
     * @story   BUY-671
     *
     * @param   int         $bybBranchCode
     * @param   int         $spbBranchCode
     * @param   bool        $fullReport
     * @param   DateTime    $filterDate
     * @param   bool        $includeChildren
     *
     * @return array
     */
    public function getBuyerInfo($bybBranchCode, $spbBranchCode, $fullReport = false, DateTime $filterDate = null, $includeChildren = true)
    {
        $cacheKey = implode(
            '_',
            array(
                __METHOD__,
                (int) $bybBranchCode,
                (int) $spbBranchCode,
                $fullReport ? '1' : '0',
                ($filterDate) ? $filterDate->format('Y-m-d H:i:s') : 'NULL',
                $includeChildren ? '1' : '0'
            )
        );

        $memcache = Shipserv_Oracle_Targetcustomers_Dao::getMemcache();
        if ($memcache) {
            $result = $memcache->get($cacheKey);
            if ($result !== false) {
                return $result;
            }
        }

        $filterDateStr = $this->roundFilterDate($filterDate);
        if (!is_null($filterDate)) {
            $filterDate = new DateTime($filterDateStr);
        }

        if ($includeChildren) {
            $branchList = $this->getBranchList($bybBranchCode);
            $buyerBranchIds = explode(',', $branchList);
            foreach ($buyerBranchIds as $index => $branchId) {
                $buyerBranchIds[$index] = (int) trim($branchId);
            }
        } else {
            $buyerBranchIds = array($bybBranchCode);
        }

        $addressInfo = $this->getBuyerInfoAddress($bybBranchCode);
        $gmvInfo = $this->getBuyerInfoGmv($spbBranchCode, $buyerBranchIds, $filterDate);

        $buyerInfo = array_merge($addressInfo, $gmvInfo);

        if ($fullReport) {
            $transactionInfo = $this->getBuyerInfoTransactions($spbBranchCode, $buyerBranchIds, $filterDate);
            $buyerInfo = array_merge($buyerInfo, $transactionInfo);
        }

        if (empty($buyerInfo)) {
            // should not really be happening, but leaving it here for legacy compatibility
            $buyerInfo = array(
                'CNT_NAME' => '',
                'BYB_CITY' => '',
                'VESSEL_COUNT'      => 0,
                'VESSEL_TYPE_COUNT' => 0,
                'GMV' => 0,
                'LAST_ORDER'    => null,
                'FIRST_ORDER'   => null,
                'ORD_COUNT'     => null
            );
        }

        if ($memcache) {
            // cache the result
            $memcache->set($cacheKey, $buyerInfo, false, Shipserv_Oracle_Targetcustomers_Dao::CACHE_TTL);
        }

        /*
        if ($bybBranchCode == 11710) {
            print("<pre>");
            print_r($buyerInfo);
            print("</pre>");
            die;
        }
        */

        return $buyerInfo;
    }

	/**
	 * returns additional columns for the specific buyer branch. If fullreport is set (for targeget page), the RFQ count, and Quote rate also calculated
	 * if $gilterdate is not null, then the gmv is calculated from this date, else it is calculated for the previous 12 months
	 *
	 * Updated by Yuriy Akopov on 2016-03-03 to add support for date and time filtering as opposed to string date only
	 *
	 * @param   int         $bybBranchCode
	 * @param   int         $spbBranchCode
	 * @param   bool        $fullReport
	 * @param   DateTime    $filterDate
	 *
	 * @return  array
	 */
	public function retiredGetBuyerInfo($bybBranchCode, $spbBranchCode, $fullReport = false, DateTime $filterDate = null, $includeChildren = true)
	{

		//Use recursive query if icludeChildren is On
		$filterDate = $this->roundFilterDate($filterDate);
		$branchList = $this->getBranchList($bybBranchCode);

		$params = array(
				'spbBranchCode' => $spbBranchCode
				,'bybBranchCode' => $bybBranchCode
			);

		if ($includeChildren === true) {

			$buyerListSql = "
				SELECT
					b.byb_branch_code
				FROM
					buyer b
				WHERE
					b.byb_branch_code IN (".$branchList .")
			";

		} else {
			$buyerListSql = "
				SELECT
					TO_NUMBER(:bybBranchCode) byb_branch_code 
				FROM
					dual
			";
		}


		$sql = "
			WITH parent_buyer AS
	      	(
	    	  SELECT
			     b.byb_branch_code
			    ,c.cnt_name
			    ,b.byb_city
			    ,b.byb_name
			  FROM
			    buyer_branch@livedb_link b
			    LEFT JOIN country@livedb_link c
			    ON (c.cnt_country_code = b.byb_country)
          	  WHERE
          	    byb_branch_code =:bybBranchCode
      		), buyer_data AS
			(".$buyerListSql."), gmv AS
			(
			  SELECT
			      otg.byb_branch_code
			    , SUM(ord_total_cost_usd) gmv
			    , COUNT(DISTINCT ord_internal_ref_no) ord_count
			    , MAX(ord_orig_submitted_date) last_order
			    , MIN(ord_orig_submitted_date) first_order
			  FROM
			    ord_traded_gmv otg JOIN buyer_data bd
			    ON (bd.byb_branch_code = otg.byb_branch_code)
			  WHERE
			    otg.spb_branch_code = :spbBranchCode
			    \n";

			  if ( $filterDate ) {
			  	$sql .= "
			  	    AND otg.ord_orig_submitted_date >= " . Shipserv_Helper_Database::getOracleDateExpr($filterDate) . "
			  	";
			  } else {
			  	$sql .= "AND otg.ord_orig_submitted_date >= add_months(SYSDATE,-12)\n";
			  }
			  
			  $sql .="GROUP BY
			     otg.byb_branch_code
			)\n";

			if ($fullReport == true) {
				$sql .= "
					, rate_base AS (
						SELECT 
							 po.*
							,CASE WHEN po.ORD_INTERNAL_REF_NO is not NULL THEN (select count(*) from POC where ORD_INTERNAL_REF_NO = po.ORD_INTERNAL_REF_NO) ELSE 0 END has_poc
						FROM
							linked_rfq_qot_po po JOIN buyer_data bd
							ON (bd.byb_branch_code = po.byb_branch_code)
						WHERE
							SPB_BRANCH_CODE = :spbBranchCode
				            -- AND IS_COMPETITIVE = 1\n";


					  if ( $filterDate ) {
						  $sql .= "
			  	                AND rfq_submitted_date >= " . Shipserv_Helper_Database::getOracleDateExpr($filterDate) . "
			  	          ";
					  } else {
					  	$sql .= "AND rfq_submitted_date >= add_months(SYSDATE,-12)\n";
					  }

			 	$sql .= "
					), rates AS
					(
					SELECT 
					        byb_branch_code
					      , count(*) as rfq_count 
					      , sum(CASE WHEN has_qot = 1 or has_po = 1 THEN 1 else 0 END) qot_count
					      ,(
					        CASE WHEN
					          count(*) > 0
					        THEN
					          ROUND(
					           sum(CASE WHEN has_qot = 1 or has_po = 1 THEN 1 else 0 END) / count(*) * 100
					           ,2
					           )
					        ELSE
					          0
					        END
					        
					      ) qot_rate
							FROM 
								rate_base
					    	GROUP BY 
					      		byb_branch_code
					)\n";
			}

			$sql .= "
				, prepared_data AS
				(
					SELECT
						  g.gmv
						 ,g.last_order
						 ,g.first_order
						 ,g.ord_count
						 \n";
				if ($fullReport == true) {
						$sql .= "
						,r.rfq_count
						,r.qot_count\n";
				}

			$sql .= "
			FROM
			  buyer_data bd 
			  LEFT JOIN gmv g
			  ON (g.byb_branch_code = bd.byb_branch_code)\n";
				 if ($fullReport == true) {
					$sql .= " LEFT JOIN rates r
				 	 ON (r.byb_branch_code = bd.byb_branch_code)";
				 }

			$sql .= ")
				SELECT
					pb.*
					,(
						SELECT
							SUM(gmv) 
						FROM
							prepared_data
					) gmv
					,(
						SELECT
							TO_CHAR(MAX(last_order), 'YYYY-MM-DD')
						FROM
							prepared_data
					) last_order
					,(
						SELECT
							TO_CHAR(MIN(first_order), 'YYYY-MM-DD')
						FROM
							prepared_data
					) first_order
					,(
						SELECT
							SUM(ord_count)
						FROM
							prepared_data
					) ord_count\n";

					if ($fullReport == true) {
						$sql .="
          			,(
						SELECT
							SUM(rfq_count) 
						FROM
							prepared_data
					) rfq_count
                    ,(
						SELECT
							SUM(qot_count) 
						FROM
							prepared_data
					) qot_count\n";
					}

			$sql .="
					FROM
						parent_buyer pb 
					";


		// print $sql; print_r($params); die();

		$result = $this->dao->fetchQuery ($sql, $params, __METHOD__);

		/*
		if ($bybBranchCode == 11710) {
		    print("<pre>");
		    print_r($result);
		    print("</pre>");
		    die;
        }
		*/

        return (count($result) > 0) ? $result[0] : array(
						  'CNT_NAME' => ''
						, 'BYB_CITY' => ''
						, 'VESSEL_COUNT' => 0
						, 'VESSEL_TYPE_COUNT' => 0
						, 'GMV' => 0
						, 'LAST_ORDER' => null
						, 'FIRST_ORDER' => null
						, 'ORD_COUNT' => null
						);
	}

	/*
	* return the max qote per buyer
	* @param Supplier Branch Code
	*/
	public function getMaxQotPerBuyer( $spbBranchCode )
	{
		$sservdba = Shipserv_Helper_Database::getDb();

		$sql = "
			SELECT 
				  spb_promotion_max_quotes
				 ,spb_promotion_check_quotes  
			FROM
				supplier_branch
			WHERE
				spb_branch_code = :spbBranchCode
		";


		/*
		return $this->dao->fetchDbQuery (
			$sql
			,array(
				'spbBranchCode' => (int)$spbBranchCode
			)
			, __METHOD__
			);
		*/


		return $sservdba->fetchAll($sql,array(
				'spbBranchCode' => (int)$spbBranchCode,
			));
	}

	protected function getBranchList($parentTnid)
	{
		if ($this->hierarchy) {
			if (array_key_exists((int)$parentTnid,$this->hierarchy)) {
	 		   return implode(',',$this->hierarchy[(int)$parentTnid]);
			} else 
			{
 				return $parentTnid;
			}
		} else {
			return $this->getBranchListIfNoHierarchy($parentTnid);
		}
	}

	protected function getBranchListIfNoHierarchy( $parentTnid )
	{
		$memcache = Shipserv_Memcache::getMemcache();
		$key = 'activePromoGetBranchList_'.$parentTnid;

		if ($memcache)
		{
			$result = $memcache->get($key);
			if ($result !== false) return $result;
		}

		 $buyer =  Shipserv_Buyer_Branch::getInstanceById( $parentTnid );

		 if ($buyer->isTopLevelBranch()) {
		 	$result = implode(",",$buyer->getAllBranchesInTheHierarchy());
		 } else {
		 	$result = $parentTnid;
		 }
		 
		 if ($memcache) $memcache->set($key, $result, false, Shipserv_Oracle::MEMCACHE_TTL);

		 return $result;

	}

    /**
     * Returns flat list of IDs of buyer branches opted in for AutoSource, even if opted in via their organisation settings
     * @todo: does not check keyword set ownership, just 'opted in' flag (but this is not needed yet, as Liam told)
     *
     * @author  Yuriy Akopov
     * @date    2017-8-25
     * @story   BUY-671
     *
     * @return array
     */
	protected static function getFlatListOfAutoSourceBranches() {
        if (!is_null(self::$autoSourceBranchIds)) {
            return self::$autoSourceBranchIds;
        }

        // convert org/branch 2d array into a flat list of branches
        $autoSourceParticipants = Shipserv_Match_Buyer_Settings::getAutoMatchParticipants();
        
        $flatParticipantsList = array();
        foreach ($autoSourceParticipants as $orgId => $branchIds) {
            $flatParticipantsList = array_merge($flatParticipantsList, $branchIds);
        }

        self::$autoSourceBranchIds = array_unique($flatParticipantsList);
        return self::$autoSourceBranchIds;
    }

    /**
     * Returns true if the supplier buyer has opted it for Price Benchmarking (aka AutoSource aka AutoMatch)
     *
     * Originally created by Attila Olbrich in 2016-04
     * Modified by Yuriy Akopov on 2017-08-24
     *
     * @param   int $bybBranchCode
     *
     * @return  int
     */
	public function getSpendBenchmarkingEnabled($bybBranchCode)
	{
		$branchList = $this->getBranchList($bybBranchCode);
		$branches = explode(",", $branchList);   // @todo: not sure why a comma separated string is used and not an array (Yuriy)

        $autoSourceBranches = self::getFlatListOfAutoSourceBranches();

        $enabledIds = array_intersect($branches, $autoSourceBranches);

        /*
        if ($bybBranchCode == 11545) {
            print("<pre>");
            print_r($autoSourceBranches);
            print_r($enabledIds);
            print("</pre>");
            die;
        }
        */

        // return true if at least one of the branches we're ask to check participates in AutoSource
        return !empty($enabledIds);
	}

	/**
	* Round up date to next hour, to keep cacheing
     *
     * @return  string|null
	*/
	protected function roundFilterDate( $filterDate )
	{
		if ($filterDate) {
			if ($filterDate instanceof DateTime) {
			return $filterDate->format('Y-m-d H:') . '59:59';
			} else {
				return null;
			}
		} else {
			return null;
		}
	}

}