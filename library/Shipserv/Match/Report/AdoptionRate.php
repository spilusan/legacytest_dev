<?php

class Shipserv_Match_Report_AdoptionRate extends Shipserv_Object
{
	public $startDate;
	public $endDate;

    private $db;
    private $reportingDb;
    private $buyerId = "";
    private $buyerIdsToExclude = array(10813, 10615, 10026, 10414);
    private $showNonBuyer = false;

    const STR_DATE_MATCH_STARTED = "01-APR-2012";

    public function __construct()
    {
        $this->db = Shipserv_Helper_Database::getDb();
        $this->reportingDb = Shipserv_Helper_Database::getSsreport2Db();

        //Shipserv_DateTime::previousQuarter($startDate, $endDate);
        Shipserv_DateTime::monthsAgo(3, $startDate, $endDate);

        $this->startDate = $startDate->format('d-M-Y');
        $this->endDate = $endDate->format('d-M-Y');
        $this->startDateObject = $startDate;
        $this->endDateObject = $endDate;
    }


	public function setPurchaserEmail($email)
    {
    	$this->email = $email;
    }

    public function getPurchaserEmail()
    {
    	return $this->email;
    }

    public function setVesselName($vesselName)
    {
    	$this->vesselName = $vesselName;
    }

    public function getVesselName()
    {
    	return $this->vesselName;
    }

    public function includeNonMatchBuyer()
    {
    	$this->showNonBuyer = true;
    }

	public function getStartDate($returnAsObject = true)
    {
    	if( $returnAsObject === true )
    		return $this->startDateObject;
    }

    public function getEndDate($returnAsObject = true)
    {
    	if( $returnAsObject === true )
    		return $this->endDateObject;
    }

    public function setStartDate($d, $m, $y)
    {
        if (preg_match('#^[0-9]+$#', $m)) {
            $month = strtoupper(date("M", mktime(0, 0, 0, $m, 10)));
        } else {
            $month = $m;
        }

        $day = str_pad((int) $d, 2, "0", STR_PAD_LEFT);
        $this->startDate = "$day-$month-$y";
        $this->startDateObject->setDate((int)$y, (int)$m, (int)$d);
    }

    /**
     *
     * @param Integer $d
     * @param String/Integer $m Accepts JAN, FEB or 01, 02 or 1,2
     * @param Integer $y Accepts 2/4 digit year representation!
     */
    public function setEndDate($d, $m, $y) {
        if (preg_match('#^[0-9]+$#', $m)) {
            $month = strtoupper(date("M", mktime(0, 0, 0, $m, 10)));
        } else {
            $month = $m;
        }

        $day = str_pad((int) $d, 2, "0", STR_PAD_LEFT);
        $this->endDate = "$day-$month-$y";

        $this->endDateObject->setDate((int)$y, (int)$m, (int)$d);

    }

	private function getKeyForRfqEvents($startDate, $endDate, $buyerId)
	{
		return strtolower($startDate) . " to " . strtolower($endDate) . " " . (($buyerId===null)?"ALL":"BUYER");
	}

	public function getStatsForRfqEventPerBuyer($startDate = null, $buyerId = null, $sentToMatchOnly = false)
	{
		$startDate = ($startDate !== null) ? $startDate: $this->startDate;

		if (is_array($buyerId)) {
			$buyerId = implode(",", $buyerId);
		}

		// checking if event already been calculated previously
		$sql = "SELECT COUNT(*) FROM match_b_buyer_rfq_event WHERE LOWER(TRIM(date_period))=LOWER('" . $this->getKeyForRfqEvents($startDate, $this->endDate, $buyerId) . "')";
		if( $buyerId !== null )
		{
			$sql .= " AND byb_branch_code IN (" . $buyerId . ")";
		}

		if( (int)$this->reportingDb->fetchOne($sql) == 0)
		{
			$sql = "
				INSERT INTO match_b_buyer_rfq_event
					SELECT
						byb_branch_code
					    , COUNT(DISTINCT rfq_event_hash)
						, '" . $this->getKeyForRfqEvents($startDate, $this->endDate, $buyerId) . "' date_period
					FROM
					    " . ( ($sentToMatchOnly===true) ? 'match_b_rfq_to_match' : 'rfq' ) . "
					WHERE
					    rfq_submitted_date BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate, 'DD-MON-YYYY') + 0.99999
					  	"
					  	.
					  	(
				  			($buyerId !== null)
				  				?
				  					"AND byb_branch_code in (" . $buyerId .")"
				  				:
				  					"
						               AND exists (
						                SELECT null FROM match_b_rfq_to_match match
						                WHERE match.rfq_submitted_date BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate, 'DD-MON-YYYY') + 0.99999
						                and match.byb_branch_code = rfq.byb_branch_code
						              )
				  					"
    					)
    					.
    					"
						GROUP BY byb_branch_code
			";

			$params = array('startDate' => $startDate, 'endDate' => $this->endDate);

			try {
				$this->reportingDb->query($sql, $params);
			}
			catch(Exception $e)
			{
				echo "ERRORRR";
				print_r($params);
				echo $e;
				die();
			}
		}
		else
		{
			if( $buyerId !== null )
			{
				if (!is_array($buyerId)) {
					$params['buyerId'] = $buyerId;
				}
			}
		}
	}

	public function getStatByBuyerId($buyerId)
	{
		$this->buyerId = $buyerId;
		return $this->getStat($buyerId, "SINGLE");
	}

	public function getBuyerId()
	{
		return $this->buyerId;
	}

	public function getAverageStatistic()
	{
		$result = $this->getStat(null, "AVG" );
		return $result[0];
	}

	public function getBestPerformerStatistic()
	{
		$result = $this->getStat(null, "BEST" );
		return $result[0];
	}

	public function getBybBranchCodeUsingMatch( $buyerId = null )
	{
		$checkVessel = false;
		
		if( $buyerId != null )
		{
			$checkVessel = true;
		}

		$db = $this->reportingDb;

		$sql = "
			SELECT
          		buyer.byb_branch_code
			  	, byb_using_match.first_rfq_to_match
				, ( SELECT TO_CHAR(rfq_submitted_date, 'DD-MON-YYYY') FROM match_b_rfq_to_match WHERE rfq_internal_ref_no=byb_using_match.first_rfq_to_match ) FIRST_MATCH_RFQ_BY_BYB
				, buyer.byb_name
			  	, buyer.byb_country_code
			FROM
				(
				  SELECT
				    r.byb_branch_code
				    , MIN(r.rfq_internal_ref_no) first_rfq_to_match
				  FROM
				    match_b_rfq_to_match r
				  WHERE
					r.spb_branch_code=999999
				    AND r.byb_branch_code!=11107
	    			" . ( ( $checkVessel === true && $this->vesselName != "" ) ? " AND r.rfq_vessel_name LIKE " . $db->quote('%' . $this->vesselName . '%') : "" ) . "
				  GROUP BY
				    r.byb_branch_code
				) byb_using_match
				, buyer
			WHERE
			  buyer.byb_branch_code=byb_using_match.byb_branch_code
			  AND LOWER(buyer.byb_name) NOT LIKE '%test%'
			  AND LOWER(buyer.byb_name) NOT LIKE '%demo%'
    		  AND buyer.byb_branch_code NOT IN (" . implode(",", $this->buyerIdsToExclude) . ")
    		  --AND buyer.byb_branch_code=11300
		";
		$params = array('startDate' => $this->startDate, 'endDate' => $this->endDate);

		//echo $sql; print_r($params); die();

		foreach( $this->reportingDb->fetchAll($sql) as $x)
		{
			$y[] = $x['BYB_BRANCH_CODE'];

			$this->getStatsForRfqEventPerBuyer($this->startDate, $x['BYB_BRANCH_CODE']);

			// getting the rfq event since match went live
			$this->getStatsForRfqEventPerBuyer($x['FIRST_MATCH_RFQ_BY_BYB'], $x['BYB_BRANCH_CODE'], true);

			$sql = "
				SELECT
					CASE
						-- if it's before the period
			            WHEN TO_DATE(:firstDateUsingMatch) < TO_DATE(:startDate) AND TO_DATE(:firstDateUsingMatch) < TO_DATE(:endDate, 'DD-MON-YYYY') + 0.99999 THEN
				            TO_DATE(:startDate)
						-- if it's in between
						WHEN TO_DATE(:firstDateUsingMatch) > TO_DATE(:startDate) AND TO_DATE(:firstDateUsingMatch) < TO_DATE(:endDate, 'DD-MON-YYYY') + 0.99999 THEN
							TO_DATE(:firstDateUsingMatch)
						ELSE
				            TO_DATE(:startDate)
		         	END as DATE_TO_USE
		        FROM DUAL
       		";

			$params2 = array(
				'startDate' => $this->startDate,
				'endDate' => $this->endDate,
				'firstDateUsingMatch' => $x['FIRST_MATCH_RFQ_BY_BYB']
			);

			$this->getStatsForRfqEventPerBuyer($this->reportingDb->fetchOne($sql, $params2), $x['BYB_BRANCH_CODE']);
		}

		return $y;
	}

	public function getVesselForPeriod($buyerId, $startDate = null, $endDate = null)
	{
		$sd = ( $startDate == null ) ? $this->startDate : $startDate;
		$ed = ( $endDate == null ) ? $this->endDate : $endDate;

		$params = array('startDate' => $sd, 'endDate' => $ed);

		$buyerIds = explode(',',$buyerId);
		$buyerIdParamList = '';

		for ($i = 0 ; $i<count($buyerIds); $i++) {
			$buyerIdParamList .= ($buyerIdParamList == '') ? ':buyerId'.$i : ',:buyerId'.$i;
			$params[':buyerId'.$i] = (int)$buyerIds[$i];
		}

		$sql = "
  			SELECT
			  TRIM(RFQ_VESSEL_NAME) NAME
			  , COUNT(*) RFQ_TOTAL
			FROM
			  match_b_rfq_to_match r
			WHERE
			  r.byb_branch_code IN (".$buyerIdParamList .")
			  AND rfq_submitted_date BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate, 'DD-MON-YYYY') + 0.99999
			GROUP BY
		      RFQ_VESSEL_NAME
			ORDER BY
			  RFQ_VESSEL_NAME ASC
		";

		$key = md5($sql . print_r($params, true));
		$results = $this->fetchCachedQuery ($sql, $params, $key, (60*60*2), 'ssreport2');

		return $results;

	}

	public function getVesselForBuyer($buyerId)
	{


		$params = array();

		$buyerIds = explode(',',$buyerId);
		$buyerIdParamList = '';

		for ($i = 0 ; $i<count($buyerIds); $i++) {
			$buyerIdParamList .= ($buyerIdParamList == '') ? ':buyerId'.$i : ',:buyerId'.$i;
			$params[':buyerId'.$i] = (int)$buyerIds[$i];
		}

		$sql = "
			SELECT 
				 name
				,COUNT(has_txn) rfq_total
			FROM
			(
				SELECT
					 TRIM(rfq_vessel_name) name
					,1 has_txn
				FROM
					match_b_rfq_to_match r
				WHERE
					r.byb_branch_code IN (".$buyerIdParamList .")
				UNION ALL
				SELECT
					 TRIM(UPPER(vpf_vessel_name)) name
					,null has_txn
				FROM
					vessel_profile@livedb_link
				WHERE
					vpf_buyer_branch_code IN (".$buyerIdParamList .")
					AND vpf_active_sts = 'ACT'
			)
			GROUP BY
				NAME
			ORDER BY
				NAME
		";

		$key = md5($sql . print_r($params, true));
		$results = $this->fetchCachedQuery ($sql, $params, $key, (60*60*2), 'ssreport2');

		return $results;

	}

	public function getPurchaserForPeriod($buyerId, $startDate = null, $endDate = null, $vesselName = null)
	{
		$db = Shipserv_Helper_Database::getSsreport2Db();
		$params = array();
		$paramNames = array();

		if (is_array($buyerId)) {
			for ($i=0; $i<count($buyerId); $i++) {
				$params['buyerId'.$i] = $buyerId[$i];
				array_push($paramNames, ':buyerId'.$i);
			}
		} else {
			$params = array('buyerId' => $buyerId);
			array_push($paramNames, ':buyerId');
		}

		$sql = "
			SELECT
			  c.CNTC_PERSON_NAME
			  , c.cntc_person_email_address
			  , COUNT(*) total
			FROM
			  match_b_rfq_to_match r JOIN contact c  ON (
			    c.cntc_doc_type='RFQ'
			    AND r.rfq_internal_ref_no=c.cntc_doc_internal_ref_no
				AND c.cntc_branch_qualifier='BY'
			  )
			WHERE
			  r.byb_branch_code IN (".implode(',', $paramNames).")
			  AND rfq_submitted_date BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate, 'DD-MON-YYYY') + 0.99999
			  AND c.cntc_person_email_address IS NOT null
	          AND c.cntc_branch_qualifier='BY'
			  "
			  .
			  	(($vesselName!="") ? "AND LOWER(TRIM(r.rfq_vessel_name))=" . $db->quote(trim(strtolower($vesselName))) : "" )
			  .
			  "
			GROUP BY
			  c.CNTC_PERSON_NAME
			  , c.cntc_person_email_address

			ORDER BY
			  c.CNTC_PERSON_NAME ASC
		";

		$sd = ( $startDate == null ) ? $this->startDate : $startDate;
		$ed = ( $endDate == null ) ? $this->endDate : $endDate;

		$params['startDate'] = $sd;
		$params['endDate'] = $ed;

		$key = md5($sql . print_r($params, true));

		$results = $this->fetchCachedQuery ($sql, $params, $key, (60*60*2), 'ssreport2');

		return $results;

	}

    public function getStat( $buyerId = null, $mode = "all" )
    {
    	$mode = strtoupper($mode);

    	// getting unique buyer who using match and also get key stats related to the buyer and
    	// the adoption rate of this buyer
    	$buyerIds = $this->getBybBranchCodeUsingMatch($buyerId);

    	if( count($buyerIds) == 0 && $buyerId !== null )
    	{
    		$buyerIds[] = $buyerId;
    	}

    	// getting the rfq event for the date range
    	$this->getStatsForRfqEventPerBuyer();

	    $db = Shipserv_Helper_Database::getSsreport2Db();

    	if( $mode == "ALL" || $mode == "BEST" || $mode == "AVG"  || $mode == "SINGLE" )
    	{

    		if( $mode == "SINGLE" )
    		{
    			$this->getStatsForRfqEventPerBuyer($this->startDate, $buyerId);
    			$buyerIds = is_array($buyerId) ? $buyerId : array($buyerId);
    		}

	    	$sql = "
    		-- ----------- --
    		-- DBG: ALL    --
    		-- ----------- --
			SELECT
			  t.*
	          , (
	            SELECT MAX(rfq_submitted_date) FROM match_b_rfq_to_match WHERE byb_branch_code=t.BUYER_TNID
	          ) LAST_TXN_DATE_TO_MATCH

			  , (
			    SELECT
			      COUNT( DISTINCT o.ord_internal_ref_no )
			    FROM
			      match_b_order_by_match_spb o
	    			JOIN match_b_rfq_to_match r ON
	    				(
	    					r.rfq_internal_ref_no=o.rfq_sent_to_match
    					)
	    			JOIN contact c ON
	    				(
	    					c.cntc_doc_type='RFQ'
	    					AND r.rfq_internal_ref_no=c.cntc_doc_internal_ref_no
	    					AND c.cntc_branch_qualifier='BY'
	    				)
			    WHERE
			      o.byb_branch_code=t.buyer_tnid
	    		  AND r.byb_branch_code=t.buyer_tnid
			      AND r.rfq_submitted_date BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate, 'DD-MON-YYYY') + 0.99999
	    			" . (($mode == 'SINGLE' && $this->vesselName != "") ? "AND LOWER(r.rfq_vessel_name)=LOWER(" . $db->quote($this->vesselName) . ")":"") . "
	    			" . (($mode == 'SINGLE' && $this->email != "") ? "AND LOWER(c.cntc_person_email_address)=LOWER(" . $db->quote($this->email) . ")":"") . "

			  ) po_by_match
			  , (
			    SELECT
			      COUNT(*)
			    FROM
			      match_b_order_by_match_spb o JOIN contact c ON
	    			( c.cntc_doc_type='ORD' AND o.ord_internal_ref_no=c.cntc_doc_internal_ref_no AND c.cntc_branch_qualifier='BY' )
			    WHERE
			      o.byb_branch_code=t.buyer_tnid
			      AND o.original_date BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate, 'DD-MON-YYYY') + 0.99999
			      AND o.rfq_sent_to_match NOT IN (
			        SELECT DISTINCT rfq_sent_to_match FROM match_b_rfq_also_sent_to_buyer
			      )
	    		  "
	    		  .
	    		  	(($mode == 'SINGLE' && $this->vesselName != "") ? "
	    		  	-- comments: when vessel is specified
				    AND o.rfq_sent_to_match IN (
				    	SELECT DISTINCT rfq_internal_ref_no FROM match_b_rfq_to_match r
	    		  		WHERE LOWER(r.rfq_vessel_name)=LOWER(" . $db->quote($this->vesselName) . ")
	    		  	)":"")
	    		  .

	    		  (($mode == 'SINGLE' && $this->email != "") ? "
	    		  	-- comments: when email's specified
				    AND LOWER(c.cntc_person_email_address)=LOWER(" . $db->quote($this->email) . ")":"")
	    		  .
	    		  "
			  ) po_by_match_supplier_only
			  , (
			      SELECT
			        SUM(
			          get_dollar_value( ord_currency , ord_total_cost , ord_submitted_date )
			        )
			      FROM
			          ord o JOIN contact c ON
	    			  ( c.cntc_doc_type='ORD' AND o.ord_internal_ref_no=c.cntc_doc_internal_ref_no AND c.cntc_branch_qualifier='BY' )
			      WHERE
			          o.byb_branch_code=t.buyer_tnid
			          AND ord_submitted_date BETWEEN :startDate AND :endDate
		    		  "
		    		  .
		    		  	(($mode == 'SINGLE' && $this->vesselName != "") ? "
		    		  	-- comments: when vessel is specified
					    AND rfq_internal_ref_no IN (
					    	SELECT DISTINCT rfq_internal_ref_no FROM rfq r
		    		  		WHERE LOWER(r.rfq_vessel_name)=LOWER(" . $db->quote($this->vesselName) . ")
		    		  	)":"")
		    		  .
		    		  	(($mode == 'SINGLE' && $this->email != "") ? "
	    		  		-- comments: when email's specified
				    	AND LOWER(c.cntc_person_email_address)=LOWER(" . $db->quote($this->email) . ")":"")
		    		  .
		    		  "
			      ) TOTAL_ORD_IN_USD
			    , (
			        SELECT
			          	SUM(potential_saving)
			      	FROM
			        	match_b_rfq_to_match ps JOIN contact c ON
	    			  	( c.cntc_doc_type='RFQ' AND ps.rfq_internal_ref_no=c.cntc_doc_internal_ref_no AND c.cntc_branch_qualifier='BY')
			      	WHERE
			      		rfq_submitted_date BETWEEN to_date(:startDate) AND TO_DATE(:endDate, 'DD-MON-YYYY') + 0.99999
			        	AND ps.byb_branch_code=t.buyer_tnid
	    				" . (($mode == 'SINGLE' && $this->vesselName != "") ? "AND LOWER(ps.rfq_vessel_name)=LOWER(" . $db->quote($this->vesselName) . ")":"") . "
						" . (($mode == 'SINGLE' && $this->email != "") ? "AND LOWER(c.cntc_person_email_address)=LOWER(" . $db->quote($this->email) . ")":"") . "
			      ) as potential_savings
			    , (
			        SELECT
			          	SUM(realised_saving)
			      	FROM
			        	match_b_rfq_to_match ps JOIN contact c ON
	    			  	( c.cntc_doc_type='RFQ' AND ps.rfq_internal_ref_no=c.cntc_doc_internal_ref_no AND c.cntc_branch_qualifier='BY')
			      	WHERE
			        	rfq_submitted_date BETWEEN to_date(:startDate) AND TO_DATE(:endDate, 'DD-MON-YYYY') + 0.99999
			       		AND ps.byb_branch_code=t.buyer_tnid
						" . (($mode == 'SINGLE' && $this->vesselName != "") ? "AND LOWER(ps.rfq_vessel_name)=LOWER(" . $db->quote($this->vesselName) . ")":"") . "
						" . (($mode == 'SINGLE' && $this->email != "") ? "AND LOWER(c.cntc_person_email_address)=LOWER(" . $db->quote($this->email) . ")":"") . "
			      ) as actual_savings

				  , (
					SELECT
					  COUNT(DISTINCT rfq_event_hash)
					FROM
					  rfq
					WHERE
					  byb_branch_code=t.buyer_tnid
					  AND rfq_submitted_date BETWEEN to_date(:startDate) AND TO_DATE(:endDate, 'DD-MON-YYYY') + 0.99999
				  ) as rfq_event_within_period
			  FROM
			  (
			    SELECT
			          yyyy.byb_name buyer_name
			        , yyyy.byb_branch_code buyer_tnid
			        , yyyy.byb_country_code
			        , yyyy.FIRST_MATCH_RFQ_BY_BYB
			        , yyyy.byb_contract_type
			        , yyyy.byb_account_region
			        , yyyy.parent_branch_code
			        , CASE WHEN yyyy.BYB_IS_INACTIVE_ACCOUNT = 1 THEN 'INA' ELSE 'ACT' END AS BYB_TRADING_STATUS
			        , yyyy.BYB_NO_OF_TRADING_SHIPS
			        , (SELECT total FROM match_b_buyer_rfq_event WHERE byb_branch_code=yyyy.byb_branch_code AND LOWER(TRIM(date_period))=LOWER(:startDate) || ' to ' || LOWER(:endDate) || ' all' AND rownum=1) rfq_all
			        , (
					  -- todo: include vessel name
			          SELECT
			            total
			          FROM
			            match_b_buyer_rfq_event
			          WHERE
			            byb_branch_code=yyyy.byb_branch_code
			            AND LOWER(TRIM(date_period)) =
			                LOWER(
			                CASE
			                  -- if it's before the period
			                   	WHEN TO_DATE(yyyy.first_match_rfq_by_byb) < TO_DATE(:startDate) AND TO_DATE(yyyy.first_match_rfq_by_byb) < TO_DATE(:endDate, 'DD-MON-YYYY') + 0.99999 THEN
			                     	TO_DATE(:startDate)
			                  -- if it's in between
			                  	WHEN TO_DATE(yyyy.first_match_rfq_by_byb) > TO_DATE(:startDate) AND TO_DATE(yyyy.first_match_rfq_by_byb) < TO_DATE(:endDate, 'DD-MON-YYYY') + 0.99999 THEN
			                    	TO_DATE(yyyy.first_match_rfq_by_byb)
			                  	ELSE
			                       	TO_DATE(:startDate)
			                    END
			                ) || ' to ' || LOWER(:endDate) || ' buyer'
			            AND rownum=1
			        ) as rfq_event_match_start
			        , (
						-- todo: include vessel name
			          	SELECT
			            	total
			          	FROM
			            	match_b_buyer_rfq_event
			          	WHERE
			            	byb_branch_code=yyyy.byb_branch_code
			            	AND LOWER(TRIM(date_period)) = LOWER(:startDate) || ' to ' || LOWER(:endDate) || ' buyer'
			            	AND rownum=1
			        ) as rfq_event_in_period
			        , (
						SELECT
							COUNT(DISTINCT rfq_event_hash)
						FROM
							match_b_rfq_to_match JOIN contact c ON
	    			  		( c.cntc_doc_type='RFQ' AND rfq_internal_ref_no=c.cntc_doc_internal_ref_no AND c.cntc_branch_qualifier='BY' )

						WHERE
							match_b_rfq_to_match.byb_branch_code=yyyy.byb_branch_code
							AND rfq_submitted_date BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate, 'DD-MON-YYYY') + 0.99999
							" . (($mode == 'SINGLE' && $this->vesselName != "") ? "AND LOWER(rfq_vessel_name)=LOWER(" . $db->quote($this->vesselName) . ")":"") . "
							" . (($mode == 'SINGLE' && $this->email != "") ? "AND LOWER(c.cntc_person_email_address)=LOWER(" . $db->quote($this->email) . ")":"") . "
    				) rfq_match
			        , (
						SELECT
							COUNT(*)
						FROM
							match_b_rfq_to_match JOIN contact c ON
	    			  		( c.cntc_doc_type='RFQ' AND rfq_internal_ref_no=c.cntc_doc_internal_ref_no AND c.cntc_branch_qualifier='BY')
						WHERE
							match_b_rfq_to_match.byb_branch_code=yyyy.byb_branch_code
							AND rfq_submitted_date < TO_DATE(:startDate)
							" . (($mode == 'SINGLE' && $this->vesselName != "") ? "AND LOWER(rfq_vessel_name)=LOWER(" . $db->quote($this->vesselName) . ")":"") . "
							" . (($mode == 'SINGLE' && $this->email != "") ? "AND LOWER(c.cntc_person_email_address)=LOWER(" . $db->quote($this->email) . ")":"") . "
    				) rfq_match_past
			        , (
						SELECT COUNT(*)
						FROM match_b_rfq_forwarded_by_match JOIN contact c ON
	    			  		( c.cntc_doc_type='RFQ' AND rfq_internal_ref_no=c.cntc_doc_internal_ref_no AND c.cntc_branch_qualifier='BY')
						WHERE
							orig_byb_branch_code=yyyy.byb_branch_code
							AND original_date BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate, 'DD-MON-YYYY') + 0.99999
							" . (($mode == 'SINGLE' && $this->vesselName != "") ? "AND LOWER(rfq_vessel_name)=LOWER(" . $db->quote($this->vesselName) . ")":"") . "
							" . (($mode == 'SINGLE' && $this->email != "") ? "AND LOWER(c.cntc_person_email_address)=LOWER(" . $db->quote($this->email) . ")":"") . "

    				) rfq_forwarded_by_match
			        , (
						SELECT COUNT(*)
						FROM
							match_b_qot_match q JOIN match_b_rfq_to_match r
								ON ( r.rfq_internal_ref_no=q.rfq_sent_to_match)
									JOIN contact c ON ( c.cntc_doc_type='RFQ' AND r.rfq_internal_ref_no=c.cntc_doc_internal_ref_no AND c.cntc_branch_qualifier='BY')

						WHERE
							orig_byb_branch_code=yyyy.byb_branch_code
							AND original_date BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate, 'DD-MON-YYYY') + 0.99999
							AND qot_total_cost_usd>0
							" . (($mode == 'SINGLE' && $this->vesselName != "") ? "AND LOWER(r.rfq_vessel_name)=LOWER(" . $db->quote($this->vesselName) . ")":"") . "
							" . (($mode == 'SINGLE' && $this->email != "") ? "AND LOWER(c.cntc_person_email_address)=LOWER(" . $db->quote($this->email) . ")":"") . "
    				) quotes_received_by_match
			        , (
						SELECT
							COUNT(DISTINCT rfq_vessel_name)
						FROM
							match_b_rfq_to_match JOIN contact c
							ON ( c.cntc_doc_type='RFQ' AND rfq_internal_ref_no=c.cntc_doc_internal_ref_no AND c.cntc_branch_qualifier='BY' )
						WHERE
							match_b_rfq_to_match.byb_branch_code=yyyy.byb_branch_code
							AND rfq_submitted_date BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate, 'DD-MON-YYYY') + 0.99999
							" . (($mode == 'SINGLE' && $this->vesselName != "") ? "AND LOWER(rfq_vessel_name)=LOWER(" . $db->quote($this->vesselName) . ")":"") . "
							" . (($mode == 'SINGLE' && $this->email != "") ? "AND LOWER(c.cntc_person_email_address)=LOWER(" . $db->quote($this->email) . ")":"") . "
    				) TOTAL_SHIPS_IN_PERIOD
			        , get_total_rfq_event_v2(yyyy.byb_branch_code, to_date(:startDate), TO_DATE(:endDate, 'DD-MON-YYYY') + 0.99999, 'match and buyer') TOTAL_RFQ_SENT_TO_BOTH
			        , get_total_rfq_event_v2(yyyy.byb_branch_code, to_date(:startDate), TO_DATE(:endDate, 'DD-MON-YYYY') + 0.99999, 'match only') rfq_match_only
			        , (
			            SELECT
			              COUNT( DISTINCT ord_internal_ref_no )
			            FROM
			              match_b_order_by_match_spb
			            WHERE
			              byb_branch_code=yyyy.byb_branch_code
			              AND rfq_sent_to_match IN (
			                SELECT rfq_sent_to_match
			                FROM match_b_rfq_also_sent_to_buyer
			                    WHERE RFQ_SUBMITTED_DATE BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate, 'DD-MON-YYYY') + 0.99999
			              )
			              AND ORIGINAL_DATE BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate, 'DD-MON-YYYY') + 0.99999
			        ) PO_BY_MATCH_N_BUYER
			    FROM
			      (
			        SELECT
			          byb_using_match.*
			          , buyer.byb_name
			          , buyer.byb_country_code
			          , buyer.byb_contract_type
			          , buyer.byb_account_region
			          , buyer.BYB_IS_INACTIVE_ACCOUNT
			          , buyer.BYB_NO_OF_TRADING_SHIPS
			          , (SELECT TO_CHAR(MIN(rfq_submitted_date), 'DD-MON-YYYY') FROM match_b_rfq_to_match WHERE rfq_internal_ref_no=byb_using_match.first_rfq_to_match) FIRST_MATCH_RFQ_BY_BYB
			          , CASE WHEN buyer.parent_branch_code = buyer.byb_branch_code THEN null ELSE buyer.parent_branch_code END parent_branch_code
			        FROM
						(
	    					"
	    					.

	    					(
    							($this->showNonBuyer === true) ?
			    					"
				                      SELECT
				                        DISTINCT r.byb_branch_code
    									, (
    										SELECT
    											MIN(x.rfq_internal_ref_no)
    										FROM
    											rfq x
    										WHERE
												x.byb_branch_code=r.byb_branch_code
    											AND x.spb_branch_code=999999
										) first_rfq_to_match
				                      FROM
									    rfq r
									  WHERE
									    r.rfq_submitted_date BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate, 'DD-MON-YYYY') + 0.99999

    								"
    								:
			    					"
									  SELECT
									    r.byb_branch_code
									    , MIN(r.rfq_internal_ref_no) first_rfq_to_match
									  FROM
									    match_b_rfq_to_match r
									  WHERE
									    r.byb_branch_code IN (" . implode(", ", $buyerIds) . ")
									    AND r.spb_branch_code=999999
									  GROUP BY
									    r.byb_branch_code

	    						      UNION ALL

                    				  SELECT
									    DISTINCT b.parent_branch_code byb_branch_code
									    , NULL first_rfq_to_match
									  FROM
                      					buyer b
									  WHERE
									    b.byb_branch_code IN (" . implode(", ", $buyerIds) . ")
					                    AND b.parent_branch_code NOT IN (" . implode(", ", $buyerIds) . ")

								    "
	    					)
						    .
						    "

						) byb_using_match
	    			  , buyer
			        WHERE
			          byb_using_match.byb_branch_code=buyer.byb_branch_code
			      ) yyyy
			  ) t
	    	";
    	}


       $sql = "
WITH BASE_DATA AS
(
	$sql
)
SELECT
  t4.*
  , (
  	CASE
  		WHEN t4.rfq_event_within_period > 0 AND t4.rfq_match > 0 THEN
  			t4.rfq_match/t4.rfq_event_within_period * 100
  		ELSE
  			0
  	END
  ) pc_rfq_sent_to_match
  , (
  	CASE WHEN t4.depth=1 THEN
  		t4.FIRST_MATCH_RFQ_BY_BYB_GROUP
  	ELSE
  		t4.FIRST_MATCH_RFQ_BY_BYB
  	END
  )
  FIRST_MATCH_RFQ_BY_BYB
  , (
  	CASE WHEN t4.depth=1 THEN
  		t4.LAST_TXN_DATE_TO_MATCH_GROUP
  	ELSE
  		t4.LAST_TXN_DATE_TO_MATCH
  	END
  )
  LAST_TXN_DATE_TO_MATCH
  , (
    CASE
      WHEN adoption_rate_in_pc > 1 THEN
        'Regular'
      WHEN adoption_rate_in_pc < 1 AND t4.rfq_match >= 1 THEN
        'Ocassional'
      WHEN adoption_rate_in_pc < 1 AND t4.rfq_match=0 AND t4.rfq_match_past > 0 THEN
        'Lapsed'
      WHEN adoption_rate_in_pc < 1 AND t4.rfq_match=0 AND t4.rfq_match_past=0 THEN
        'Never'
      ELSE
        'Never+'
    END
    ) AS adoption_profile
  , (
    CASE WHEN PO_BY_MATCH_N_BUYER>0 AND TOTAL_RFQ_SENT_TO_BOTH>0 THEN
      PO_BY_MATCH_N_BUYER/TOTAL_RFQ_SENT_TO_BOTH*100
    ELSE
      0
    END
    ) SWITCH_RATE
  , (
    CASE WHEN t4.actual_savings > 0 AND t4.rfq_match_only > 0 THEN
      t4.actual_savings/t4.rfq_match_only
    ELSE
      0
    END
    ) avg_saving_per_rfq
  , (
    CASE WHEN t4.potential_savings > 0 AND t4.total_ord_in_usd > 0 THEN
      t4.potential_savings/t4.total_ord_in_usd*100
    ELSE
      0
    END

    ) match_saving_out_of_all_po

FROM
(
  SELECT
    t3.*
    , (
        ROUND(
          t3.rfq_all/
            ( SELECT ABS(MONTHS_BETWEEN (TO_DATE(:startDate, 'DD-MON-YYYY'), TO_DATE(:endDate, 'DD-MON-YYYY') )) FROM DUAL )
        , 2)
      )
      AS avg_sent_per_month
    -- adoption rate
    , CASE WHEN t3.RFQ_EVENT_IN_PERIOD > 0 AND t3.rfq_match > 0 THEN
        t3.rfq_match / t3.RFQ_EVENT_IN_PERIOD * 100
      ELSE
        0
      END
      AS adoption_rate_in_pc

    -- supplier/rfq
    , CASE WHEN t3.rfq_forwarded_by_match=0 OR t3.rfq_match=0 THEN
          0
      ELSE
          t3.rfq_forwarded_by_match/t3.rfq_match
      END AS suppliers_per_rfq

    , CASE WHEN t3.po_by_match > 0 AND t3.rfq_match > 0 THEN
        (t3.po_by_match / t3.rfq_match) * 100
      ELSE
        0
      END
      AS win_rate

    -- quote rate
    , CASE
        WHEN t3.rfq_forwarded_by_match > 0 AND t3.quotes_received_by_match > 0
        THEN (t3.quotes_received_by_match / t3.rfq_forwarded_by_match) * 100
        ELSE 0
      END
      AS quote_rate

  FROM
  (
    -- HANDLING HIERARCICAL AGGREGATION
    SELECT
      level as depth
      ,	substr(replace(SYS_CONNECT_BY_PATH(decode(t1.buyer_tnid, null, t1.buyer_name, t1.buyer_tnid), '>'), '>', '/'), 2) path_id
      , (
          SELECT
            MIN(t2.FIRST_MATCH_RFQ_BY_BYB)
          FROM BASE_DATA t2
          START WITH t2.buyer_tnid=t1.buyer_tnid
          CONNECT BY PRIOR t2.buyer_tnid=t2.parent_branch_code
      ) FIRST_MATCH_RFQ_BY_BYB_GROUP

      , (
          SELECT
            MAX(t2.LAST_TXN_DATE_TO_MATCH)
          FROM BASE_DATA t2
          START WITH t2.buyer_tnid=t1.buyer_tnid
          CONNECT BY PRIOR t2.buyer_tnid=t2.parent_branch_code
      ) LAST_TXN_DATE_TO_MATCH_GROUP
      , t1.LAST_TXN_DATE_TO_MATCH
      , t1.BUYER_NAME
      , t1.BUYER_TNID
      , t1.BYB_COUNTRY_CODE
      , t1.FIRST_MATCH_RFQ_BY_BYB
      , t1.BYB_CONTRACT_TYPE
      , t1.BYB_ACCOUNT_REGION
      , t1.PARENT_BRANCH_CODE
      , t1.BYB_TRADING_STATUS
      , t1.rfq_match rfq_match_per_branch
	  , (
          SELECT SUM(t2.RFQ_EVENT_WITHIN_PERIOD)
          FROM BASE_DATA t2
          START WITH t2.buyer_tnid=t1.buyer_tnid
          CONNECT BY PRIOR t2.buyer_tnid=t2.parent_branch_code
      ) RFQ_EVENT_WITHIN_PERIOD
      , (
          SELECT SUM(t2.BYB_NO_OF_TRADING_SHIPS)
          FROM BASE_DATA t2
          START WITH t2.buyer_tnid=t1.buyer_tnid
          CONNECT BY PRIOR t2.buyer_tnid=t2.parent_branch_code
      ) BYB_NO_OF_TRADING_SHIPS
      , (
          SELECT SUM(t2.RFQ_ALL)
          FROM BASE_DATA t2
          START WITH t2.buyer_tnid=t1.buyer_tnid
          CONNECT BY PRIOR t2.buyer_tnid=t2.parent_branch_code
      ) RFQ_ALL
      , (
          SELECT SUM(t2.RFQ_EVENT_MATCH_START)
          FROM BASE_DATA t2
          START WITH t2.buyer_tnid=t1.buyer_tnid
          CONNECT BY PRIOR t2.buyer_tnid=t2.parent_branch_code
      ) RFQ_EVENT_MATCH_START
      , (
          SELECT SUM(t2.RFQ_EVENT_IN_PERIOD)
          FROM BASE_DATA t2
          START WITH t2.buyer_tnid=t1.buyer_tnid
          CONNECT BY PRIOR t2.buyer_tnid=t2.parent_branch_code
      ) RFQ_EVENT_IN_PERIOD
      , (
          SELECT SUM(t2.RFQ_MATCH)
          FROM BASE_DATA t2
          START WITH t2.buyer_tnid=t1.buyer_tnid
          CONNECT BY PRIOR t2.buyer_tnid=t2.parent_branch_code
      ) RFQ_MATCH
      , (
          SELECT SUM(t2.RFQ_MATCH_PAST)
          FROM BASE_DATA t2
          START WITH t2.buyer_tnid=t1.buyer_tnid
          CONNECT BY PRIOR t2.buyer_tnid=t2.parent_branch_code
      ) RFQ_MATCH_PAST
      , (
          SELECT SUM(t2.RFQ_FORWARDED_BY_MATCH)
          FROM BASE_DATA t2
          START WITH t2.buyer_tnid=t1.buyer_tnid
          CONNECT BY PRIOR t2.buyer_tnid=t2.parent_branch_code
      ) RFQ_FORWARDED_BY_MATCH
      , (
          SELECT SUM(t2.QUOTES_RECEIVED_BY_MATCH)
          FROM BASE_DATA t2
          START WITH t2.buyer_tnid=t1.buyer_tnid
          CONNECT BY PRIOR t2.buyer_tnid=t2.parent_branch_code
      ) QUOTES_RECEIVED_BY_MATCH
      , (
          SELECT SUM(t2.TOTAL_SHIPS_IN_PERIOD)
          FROM BASE_DATA t2
          START WITH t2.buyer_tnid=t1.buyer_tnid
          CONNECT BY PRIOR t2.buyer_tnid=t2.parent_branch_code
      ) TOTAL_SHIPS_IN_PERIOD
      , (
          SELECT SUM(t2.TOTAL_RFQ_SENT_TO_BOTH)
          FROM BASE_DATA t2
          START WITH t2.buyer_tnid=t1.buyer_tnid
          CONNECT BY PRIOR t2.buyer_tnid=t2.parent_branch_code
      ) TOTAL_RFQ_SENT_TO_BOTH
      , (
          SELECT SUM(t2.RFQ_MATCH_ONLY)
          FROM BASE_DATA t2
          START WITH t2.buyer_tnid=t1.buyer_tnid
          CONNECT BY PRIOR t2.buyer_tnid=t2.parent_branch_code
      ) RFQ_MATCH_ONLY
      , (
          SELECT SUM(t2.PO_BY_MATCH_N_BUYER)
          FROM BASE_DATA t2
          START WITH t2.buyer_tnid=t1.buyer_tnid
          CONNECT BY PRIOR t2.buyer_tnid=t2.parent_branch_code
      ) PO_BY_MATCH_N_BUYER
      , (
          SELECT SUM(t2.PO_BY_MATCH)
          FROM BASE_DATA t2
          START WITH t2.buyer_tnid=t1.buyer_tnid
          CONNECT BY PRIOR t2.buyer_tnid=t2.parent_branch_code
      ) PO_BY_MATCH
      , (
          SELECT SUM(t2.PO_BY_MATCH_SUPPLIER_ONLY)
          FROM BASE_DATA t2
          START WITH t2.buyer_tnid=t1.buyer_tnid
          CONNECT BY PRIOR t2.buyer_tnid=t2.parent_branch_code
      ) PO_BY_MATCH_SUPPLIER_ONLY
      , (
          SELECT SUM(t2.TOTAL_ORD_IN_USD)
          FROM BASE_DATA t2
          START WITH t2.buyer_tnid=t1.buyer_tnid
          CONNECT BY PRIOR t2.buyer_tnid=t2.parent_branch_code
      ) TOTAL_ORD_IN_USD
      , (
          SELECT SUM(t2.POTENTIAL_SAVINGS)
          FROM BASE_DATA t2
          START WITH t2.buyer_tnid=t1.buyer_tnid
          CONNECT BY PRIOR t2.buyer_tnid=t2.parent_branch_code
      ) POTENTIAL_SAVINGS
      , (
          SELECT SUM(t2.ACTUAL_SAVINGS)
          FROM BASE_DATA t2
          START WITH t2.buyer_tnid=t1.buyer_tnid
          CONNECT BY PRIOR t2.buyer_tnid=t2.parent_branch_code
      ) ACTUAL_SAVINGS
    FROM
      BASE_DATA t1
    START WITH t1.parent_branch_code IS null
    CONNECT BY PRIOR t1.buyer_tnid=t1.parent_branch_code
  ) t3
) t4
			ORDER BY rfq_match DESC

       	";

    	if( $mode == "AVG")
    	{
    		$sql = "
    			-- --------- --
    			-- DBG: AVG --
    			-- --------- --
				SELECT

    				123456 RFQ_ALL

    				, AVG(CASE WHEN zzzz.RFQ_MATCH IS NULL THEN 0 ELSE zzzz.RFQ_MATCH END) RFQ_MATCH
    				, AVG(CASE WHEN zzzz.pc_rfq_sent_to_match IS NULL THEN 0 ELSE zzzz.pc_rfq_sent_to_match END) pc_rfq_sent_to_match
    				, AVG(CASE WHEN zzzz.RFQ_FORWARDED_BY_MATCH IS NULL THEN 0 ELSE zzzz.RFQ_FORWARDED_BY_MATCH END) RFQ_FORWARDED_BY_MATCH
    				, AVG(CASE WHEN zzzz.QUOTES_RECEIVED_BY_MATCH IS NULL THEN 0 ELSE zzzz.QUOTES_RECEIVED_BY_MATCH END) QUOTES_RECEIVED_BY_MATCH
    				, AVG(CASE WHEN zzzz.TOTAL_RFQ_SENT_TO_BOTH IS NULL THEN 0 ELSE zzzz.TOTAL_RFQ_SENT_TO_BOTH END ) TOTAL_RFQ_SENT_TO_BOTH
    				, AVG(CASE WHEN zzzz.RFQ_MATCH_ONLY IS NULL THEN 0 ELSE zzzz.RFQ_MATCH_ONLY END ) RFQ_MATCH_ONLY
    				, AVG(CASE WHEN zzzz.PO_BY_MATCH_N_BUYER IS NULL THEN 0 ELSE zzzz.PO_BY_MATCH_N_BUYER END) PO_BY_MATCH_N_BUYER
    				, AVG(CASE WHEN zzzz.PO_BY_MATCH IS NULL THEN 0 ELSE zzzz.PO_BY_MATCH END ) PO_BY_MATCH
    				, AVG(CASE WHEN zzzz.TOTAL_ORD_IN_USD IS NULL THEN 0 ELSE zzzz.TOTAL_ORD_IN_USD END ) TOTAL_ORD_IN_USD
    				, AVG(CASE WHEN zzzz.AVG_SENT_PER_MONTH IS NULL THEN 0 ELSE zzzz.AVG_SENT_PER_MONTH END ) AVG_SENT_PER_MONTH
    				, AVG(CASE WHEN zzzz.ADOPTION_RATE_IN_PC IS NULL THEN 0 ELSE zzzz.ADOPTION_RATE_IN_PC END ) ADOPTION_RATE_IN_PC
    				, AVG(CASE WHEN zzzz.SUPPLIERS_PER_RFQ IS NULL THEN 0 ELSE zzzz.SUPPLIERS_PER_RFQ END ) SUPPLIERS_PER_RFQ
    				, AVG(CASE WHEN zzzz.WIN_RATE IS NULL THEN 0 ELSE zzzz.WIN_RATE END ) WIN_RATE
    				, AVG(CASE WHEN zzzz.QUOTE_RATE IS NULL THEN 0 ELSE zzzz.QUOTE_RATE END ) QUOTE_RATE
    				, AVG(CASE WHEN zzzz.POTENTIAL_SAVINGS IS NULL THEN 0 ELSE zzzz.POTENTIAL_SAVINGS END ) POTENTIAL_SAVINGS
    				, AVG(CASE WHEN zzzz.ACTUAL_SAVINGS IS NULL THEN 0 ELSE zzzz.ACTUAL_SAVINGS END ) ACTUAL_SAVINGS
    				, AVG(CASE WHEN zzzz.AVG_SAVING_PER_RFQ IS NULL THEN 0 ELSE zzzz.AVG_SAVING_PER_RFQ END ) AVG_SAVING_PER_RFQ
    				, AVG(CASE WHEN zzzz.MATCH_SAVING_OUT_OF_ALL_PO IS NULL THEN 0 ELSE zzzz.MATCH_SAVING_OUT_OF_ALL_PO END ) MATCH_SAVING_OUT_OF_ALL_PO
    				, AVG(CASE WHEN zzzz.TOTAL_ORD_IN_USD IS NULL THEN 0 ELSE zzzz.TOTAL_ORD_IN_USD END ) TOTAL_PO_IN_USD
   				FROM
    				(
    					" . $sql . "
    				) zzzz
    		";


    	}
    	else if( $mode == "BEST" )
    	{
    		$sql = "
    			-- --------- --
    			-- DBG: BEST --
    			-- --------- --
				SELECT
    				--MAX(pppp.RFQ_ALL)
    				123456 RFQ_ALL
    				, MAX(pppp.RFQ_MATCH) RFQ_MATCH
    				, MAX(pppp.pc_rfq_sent_to_match) pc_rfq_sent_to_match

    				, MAX(pppp.RFQ_FORWARDED_BY_MATCH) RFQ_FORWARDED_BY_MATCH
    				, MAX(pppp.QUOTES_RECEIVED_BY_MATCH) QUOTES_RECEIVED_BY_MATCH
    				, MAX(pppp.TOTAL_RFQ_SENT_TO_BOTH) TOTAL_RFQ_SENT_TO_BOTH
    				, MAX(pppp.RFQ_MATCH_ONLY) RFQ_MATCH_ONLY
    				, MAX(pppp.PO_BY_MATCH_N_BUYER) PO_BY_MATCH_N_BUYER
    				, MAX(pppp.PO_BY_MATCH) PO_BY_MATCH
    				, MAX(pppp.TOTAL_ORD_IN_USD) TOTAL_ORD_IN_USD
    				, MAX(pppp.AVG_SENT_PER_MONTH) AVG_SENT_PER_MONTH
    				, MAX(pppp.ADOPTION_RATE_IN_PC) ADOPTION_RATE_IN_PC
    				, MAX(pppp.SUPPLIERS_PER_RFQ) SUPPLIERS_PER_RFQ
    				, MAX(pppp.WIN_RATE) WIN_RATE
    				, MAX(pppp.QUOTE_RATE) QUOTE_RATE
    				, MAX(pppp.POTENTIAL_SAVINGS) POTENTIAL_SAVINGS
    				, MAX(pppp.ACTUAL_SAVINGS) ACTUAL_SAVINGS
    				, MAX(pppp.AVG_SAVING_PER_RFQ) AVG_SAVING_PER_RFQ
    				, MAX(pppp.MATCH_SAVING_OUT_OF_ALL_PO) MATCH_SAVING_OUT_OF_ALL_PO
    				, MAX(pppp.TOTAL_ORD_IN_USD) TOTAL_PO_IN_USD
	   			FROM
    				(
    					" . $sql . "
    				) pppp
    		";

    	}

    	//echo $sql; die();
    	$params = array('startDate' => $this->startDate, 'endDate' => $this->endDate);
    	$key = md5($sql . "MATCH_ADOPTION_RATE_KPI_" . print_r($params, true) . "_MODE_" . $mode . "_VESSEL_" . $this->vesselName ) ;
    	//if( $mode == 'AVG' ) { echo $sql; print_r($params); die();}

    	$results = $this->fetchCachedQuery ($sql, $params, $key, (60*60*2), 'ssreport2');
    	$sum = array(
    		'POTENTIAL_SAVINGS' => 0,
			'ACTUAL_SAVINGS' => 0,
			'AVG_SAVING_PER_RFQ' => 0,
			'RFQ_MATCH' => 0,
			'RFQ_FORWARDED_BY_MATCH' => 0,
			'RFQ_ALL' => 0,
			'PC_RFQ_SENT_TO_MATCH' => 0,
			'QUOTES_RECEIVED_BY_MATCH' => 0,
			'SUPPLIERS_PER_RFQ' => 0,
			'PO_BY_MATCH' => 0
    		);

    	if( $buyerId !== null )
    	{
    		foreach($results as $result)
    		{
    			if (is_array($buyerId)) {
    				//Summarize data 
    				$sum['POTENTIAL_SAVINGS'] += $result['POTENTIAL_SAVINGS'];
					$sum['ACTUAL_SAVINGS'] += $result['ACTUAL_SAVINGS'];
					$sum['RFQ_MATCH'] += $result['RFQ_MATCH'];
					$sum['RFQ_FORWARDED_BY_MATCH'] += $result['RFQ_FORWARDED_BY_MATCH'];
					$sum['RFQ_ALL'] += $result['RFQ_ALL'];
					$sum['QUOTES_RECEIVED_BY_MATCH'] += $result['QUOTES_RECEIVED_BY_MATCH'];
					$sum['SUPPLIERS_PER_RFQ'] += $result['SUPPLIERS_PER_RFQ'];
					$sum['PO_BY_MATCH'] += $result['PO_BY_MATCH'];
    			} else {
	    			if( $result['BUYER_TNID'] == $buyerId )
	    			{
	    				return $result;
	    			}
    			}
    		}
    	}

    	if (is_array($buyerId)) {
	    	$sum['PC_RFQ_SENT_TO_MATCH'] = ($sum['RFQ_MATCH'] != 0) ? ($sum['RFQ_MATCH'] /  $sum['RFQ_ALL']) * 100 : 0;
	    	$sum['AVG_SAVING_PER_RFQ'] = ($sum['RFQ_MATCH'] != 0) ? $sum['RFQ_FORWARDED_BY_MATCH'] / $sum['RFQ_MATCH'] : 0 ;
	    	return $sum;
    	} else {
    		return $results;
    	}
    }

    public function getTotalOrderInUsd($startDate, $endDate, $buyerId)
    {
    	$sql = "
			SELECT SUM(total_order_in_usd) total_in_usd
			FROM
			(
    			SELECT
    				get_dollar_value( ord_currency , ord_total_cost , ord_submitted_date ) AS total_order_in_usd
				FROM
    				ord
				WHERE
    				byb_branch_code=:buyerId
					AND ord_submitted_date BETWEEN :startDate AND :endDate
			)
    	";
    	$params['buyerId'] = $buyerId;

    	$key = "MATCH_ADOPTION_RATE_KPI_" . __FUNCTION__ . $this->startDate . $this->endDate . "___" . $buyerId . "_VESSEL_" . $this->vesselName;
    	$params = array('startDate' => $startDate, 'endDate' => $endDate, 'buyerId' => $buyerId);

    	$result = $this->fetchCachedQuery ($sql, $params, $key, (60*60*2), 'ssreport2');

    	return $result[0]['TOTAL_IN_USD'];
    }

    public static function getAverageData()
    {

    }

    public function getTotalRfqEvent()
    {
    	$sql = "
			SELECT COUNT(DISTINCT r.rfq_event_hash) TOTAL FROM rfq r WHERE
			r.rfq_submitted_date BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate, 'DD-MON-YYYY') + 0.99999
    	";
    	$params = array('startDate' => $this->startDate, 'endDate' => $this->endDate);
    	$key = md5($sql . "MATCH_ADOPTION_RATE_KPI_EVENT_HASH_" . print_r($params, true) ) ;

    	$result = $this->fetchCachedQuery ($sql, $params, $key, (60*60*2), 'ssreport2');

    	return $result[0]['TOTAL'];

    }
}
