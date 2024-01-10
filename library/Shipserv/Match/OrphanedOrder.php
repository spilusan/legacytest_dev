<?php
/**
 * Class to handle orphaned order. 
 * 
 * Supported mode:
 * 	- Buyer mode = match | all | backtest
 *  - 
 * @author Elvir Leonard
 *
 *		
 		// ---------------------------------------------------------------------------------		
		// TODO (ASAP)
		// ---------------------------------------------------------------------------------
		// step 0: Ask JP/Anne if we separate the ORD that has connection to QOT that was not resulted
		// 		   by connecting PUBLIC REF NO
		// 
		// step 1: Analyse average line items COUNT difference between QOT and ORD and also TOTAL PRICE
		//
		// step 2: Analyse average difference on the following:
		// 			- a. Ref No
		// 			- b. Subject
		// 			- c. Vessel name
		// 			- d. Cost
		// 					- d.1. cost in AVG(ABS value)
		// 					- d.2. cost in AVG(% value)
		//
		// step 3: Change the algorithm to do the following
		// - instead of using scoring of 10/60 for each 
		// - split it into 3 steps
		// - step a: run the must haves
		// - step b: exclude the additional line items 
		// -         (eg: QOT LI=5 and ORD LI=10, then just get the similarities of the first 5 in ORD LI)
		// -		 if LI similarities of that 5 LI is 90% then proceed to step c
		// - step c: check other attributes (refno, subject, vessel, cost) making sure that they are within the 
		// - 		 range that has been concluded from step 2 above (analysis) (Y/N)
		
		// analyse line item count between QOT and ORD
		// price : get the average difference QOT and ORD and consider that on the matching process against
		// disregard: remove rfq ref no
		// 3rd step should be discounting not adding to the score
		// analyse differences on vesselname, qotref, rfq ref and come up w/ the norm
		// line item:
		// - if count the same, the similarities > 90
		// - if count not the same, the n-diff should be >90 the same
		// price analysis
		// do 2 ways:
		// - average of price difference in ABS number
		// - average of price difference in PC number
		
		// --------------------------------
		// line item check
		// every single PO line item needs to be on the quote
		// if there are more than > 2 then we allow 1 failure
		
		// if number of li in qot is less than what's on order, 
		// then use the one in qot amke sure that the line item on quote
		// should be on order 
		
		// if number of line item in qot > than the order then allow 1 
		// failure and make sure that the line items in order are in the quote  
		// line item match should be more than 95%
 */
class Shipserv_Match_OrphanedOrder extends Shipserv_Object
{
	// max allowed execution time
	const MAX_EXECUTION_TIME = 9000;
	
	// number of PO per batch (one execution have multiple batches)
	const BATCH_SIZE = 200;
	
	// start date of the PO
	const PERIOD_START = '01-AUG-2012';
	
	// all modes
	const MODE_MATCH_BUYER    = 'match';
	const MODE_ALL_BUYER  	= 'all';
	const MODE_TEST_TXN  	= 'backtest-all';
	const MODE_MATCH_TEST_TXN  	= 'backtest-match';
	const MODE_CUSTOM = "custom";
	
	// if it's a backtested mode, it'll pull 1000 PO/samples
	const MODE_TEST_SAMPLE_SIZE = 1000;
	
	// in order a QOT to be considered to be a candidate 
	// it needs to score > ALGORITHM_MIN_SCORE
	const ALGORITHM_MIN_SCORE = 30; // max is 60 -- 30 is 50% accurate
	
	// oldest allowable QOT/RFQ from ORD in months
	const ALGORITHM_MONTH_DIFFERENCE_FOR_QOT = 6;
	
	// minimum score for each criteria
	const THRESHOLD_SCORE_VESSEL = 98;
	const THRESHOLD_SCORE_SUBJECT = 95;
	const THRESHOLD_SCORE_EACH_LINEITEM = 90;
	const THRESHOLD_SCORE_ALL_LINEITEM = 50;
	
	// minimum score for aggregate score
	const MAX_LINEITEM = 50;
	
	// number of character of LI 
	const MAX_LINEITEM_LENGTH_TO_COMPARE = 40;
	
	// for CUSTOM mode, it'll only process this particular ORD_INTERNAL_REF_NO
	const TEST_ORD_ID_FOR_CUSTOM_BACKTESTING_MODE = 3668601;
	
	
	function __construct( $logger, $mode, $verboseLog = false, $dbForAnalysis = "sservdba" )
	{
		$this->logger = $logger;
		$this->mode = $mode;
		$this->verboseLog = $verboseLog;
		$this->dbSSReport2 = $this->getSsreport2Db();
		$this->db = $this->getDbByType($dbForAnalysis);
		
		$this->config = $this->getConfig();
		$this->stopWatch = new Shipserv_Helper_Stopwatch(true);
		$this->logger->log("Using: " . $dbForAnalysis . " db for analysis");
	}
	
	/**
	 * returns current time
	 * @return string
	 */
	private function getCurrentTime()
	{
		return date("d-m-Y h:i:s");
	}
	
	/**
	 * Main function
	 */
	public function process()
	{
		$this->logger->log("Processing orphaned order " . $this->getCurrentTime() . " mode: " . $this->mode);
		$this->processOrder();
		$this->logger->log("End");
	}
	
	/**
	 * Storing the result
	 * @param unknown $ordId
	 * @param unknown $qotId
	 * @param unknown $totalScore
	 * @param unknown $qotType
	 * @param unknown $qots
	 * @param string $isTest
	 */
	private function store($ordId, $qotId, $totalScore, $qotType, $qots, $isTest = false)
	{
		$sql = "
			INSERT INTO 
				match_order_quote (moq_ord_internal_ref_no, moq_qot_internal_ref_no, moq_total_score, 	moq_qot_type, 	moq_is_test, 	moq_date_created, 	moq_created_by, moq_debug)
				VALUES( 		   :ordId, 					:qotId, 				 :totalScore, 		:qotType, 		:isTest, 		sysdate, 			:createdBy, 	:debug)
		";
		$params = array(
			'ordId' 		=> $ordId, 
			'qotId' 		=> $qotId,
			'totalScore'	=> $totalScore,
			'qotType' 		=> $qotType, 
			'isTest' 		=> ($isTest)?1:0, 
			'debug' 		=> (count($qots)>0)?serialize($qots):"", 
			'createdBy' 	=> get_class($this) 
		);
		
		return $this->dbSSReport2->query($sql, $params);
	}
	
	/**
	 * Getting the NON-processed orphaned order by cross checking the output table with the order 
	 * @return list of orphaned order
	 */
	private function getOrphanedOrder()
	{
		
		if( $this->mode == self::MODE_MATCH_BUYER )
		{
			$sql = "
				SELECT
				    /*+ LEADING(ord) FIRST_ROWS(10) INDEX( ord IDX_ORD_N14 ) INDEX( ord IDX_ORD_N15 ) */
				    ord_internal_ref_no
				  , ord_submitted_date
				FROM
				  ord
				WHERE
				  qot_internal_ref_no IS null
				  AND byb_branch_code IN (
				    SELECT DISTINCT byb_branch_code FROM rfq WHERE
				    spb_branch_code=999999
				  )
				  AND ord_submitted_date BETWEEN TO_DATE(:startDate) AND SYSDATE
				  AND NOT EXISTS(
				        SELECT 1 FROM match_order_quote moq WHERE ord.ord_internal_ref_no=moq.moq_ord_internal_ref_no
				      )
					
				  AND rownum<=" . self::BATCH_SIZE . "
			";		
			
			return $this->dbSSReport2->fetchAll($sql, array('startDate' => self::PERIOD_START));
		}
		else if( $this->mode == self::MODE_ALL_BUYER )
		{
			$sql = "
				SELECT
				    /*+ LEADING(ord) FIRST_ROWS(10) INDEX( ord IDX_ORD_N14 ) INDEX( ord IDX_ORD_N15 ) */
				    ord_internal_ref_no
				  , ord_submitted_date
				  , qot_internal_ref_no
				FROM
				  ord
				WHERE
				  ord_submitted_date BETWEEN TO_DATE(:startDate) AND SYSDATE
				  AND NOT EXISTS(
				        SELECT 1 FROM match_order_quote moq WHERE ord.ord_internal_ref_no=moq.moq_ord_internal_ref_no
				      )
				  AND rownum<=" . self::BATCH_SIZE . "
			";
			
			return $this->dbSSReport2->fetchAll($sql, array('startDate' => self::PERIOD_START));
		}
		else if( $this->mode == self::MODE_TEST_TXN || $this->mode == self::MODE_MATCH_TEST_TXN || $this->mode == self::MODE_CUSTOM )
					
		{
			$sql = "
				SELECT
				    /*+ LEADING(ord) FIRST_ROWS(10) INDEX( ord IDX_ORD_N14 ) INDEX( ord IDX_ORD_N15 ) */
				    ord.ord_internal_ref_no
				  , ord.ord_submitted_date
				FROM
				  match_orphaned_order_test mot,
				  ord
				WHERE
				  mot.ord_internal_ref_no=ord.ord_internal_ref_no
--				  AND NOT EXISTS(
--				        SELECT 1 FROM match_order_quote moq WHERE ord.ord_internal_ref_no=moq.moq_ord_internal_ref_no
--				      )
				  AND mot.ord_internal_ref_no NOT IN (SELECT moq_ord_internal_ref_no FROM match_order_quote)
				  AND rownum<=" . self::BATCH_SIZE . "
			";
			
			return $this->dbSSReport2->fetchAll($sql);
		}
	}
	private function prepareTestData()
	{
		
		if( $this->mode == self::MODE_CUSTOM )
		{
/*			
			if( $this->verboseLog === true ) $this->logger->log("Deleting test data");
			$sql = "DELETE FROM match_order_quote WHERE moq_is_test=1";
			$this->dbSSReport2->query($sql);
			$this->dbSSReport2->commit();

			$sql = "DELETE FROM match_orphaned_order_test";
			$this->dbSSReport2->query($sql);
			$this->dbSSReport2->commit();
			
*/				
			if( $this->verboseLog === true ) $this->logger->log("Inserting test data");
				
			// inserting newly random orders
			$sql = "
				INSERT INTO match_orphaned_order_test
				SELECT
					ord_internal_ref_no,
					qot_internal_ref_no
				FROM
					ord
				WHERE
					ord_internal_ref_no=:ordId
			";
			
			$this->dbSSReport2->query($sql, array('ordId' => self::TEST_ORD_ID_FOR_CUSTOM_BACKTESTING_MODE));
		}
		
		if( $this->mode == self::MODE_TEST_TXN )
		{
			if( $this->verboseLog === true ) $this->logger->log("Inserting test data");
				
			// inserting newly random orders
			$sql = "
				INSERT INTO match_orphaned_order_test
				SELECT 
					ord_internal_ref_no,
					qot_internal_ref_no
				FROM
					ord
				WHERE
					qot_internal_ref_no IS NOT NULL
					AND ord.ord_internal_ref_no NOT IN (SELECT ord_internal_ref_no FROM match_orphaned_order_test)
					AND ord.ord_submitted_date BETWEEN TO_DATE(:startDate) AND SYSDATE
					AND rownum <= " . self::MODE_TEST_SAMPLE_SIZE . "
				ORDER BY dbms_random.value
			";	
			$this->dbSSReport2->query($sql, array('startDate' => self::PERIOD_START));
		}
		else if( $this->mode == self::MODE_MATCH_TEST_TXN )
		{
			if( $this->verboseLog === true ) $this->logger->log("Inserting test data");
				
			// inserting match order
			$sql = "
				INSERT INTO match_orphaned_order_test
							
				SELECT 
				  ord.ord_internal_ref_no
				  , qot_buyer.qot_internal_ref_no
				FROM 
				  qot qot_buyer
				  , qot qot_match
				  , ord
				WHERE 
				  qot_match.byb_branch_code=11107
				  AND qot_buyer.byb_branch_code!=11107
				  AND qot_match.spb_branch_code=qot_buyer.spb_branch_code
				  AND qot_match.qot_subject=qot_buyer.qot_subject
				  AND qot_match.qot_ref_no=qot_buyer.qot_ref_no
				  AND ord.qot_internal_ref_no=qot_buyer.qot_internal_ref_no
				  AND ord.ord_internal_ref_no NOT IN (SELECT ord_internal_ref_no FROM match_orphaned_order_test)
			";
			$this->dbSSReport2->query($sql);
		}
	}
	
	private function isOriginatedFromMatch( $qotId )
	{
		// compare attributes of the qot making sure that it 
		// is the same w/ match quote
		$sql = "				
			SELECT 
			  qot_match.byb_branch_code
			FROM 
			  qot qot_buyer
			  , qot qot_match
			WHERE 
			  qot_match.byb_branch_code=11107
			  AND qot_buyer.byb_branch_code!=11107
			  AND qot_match.spb_branch_code=qot_buyer.spb_branch_code
			  AND qot_match.qot_subject=qot_buyer.qot_subject
			  AND qot_match.qot_ref_no=qot_buyer.qot_ref_no
			  AND qot_buyer.qot_internal_ref_no=:qotId
		";
		$buyer = $this->dbSSReport2->fetchOne($sql, array('qotId' => $qotId));
		
		// check if qotId is sent to match | shipserv.match.buyerId
		return ( $buyer == $this->config->shipserv->match->buyerId );
	}
	
	public function trackingMatchConversion()
	{
		$sql = "
			SELECT 
			  rfq_sent_to_match.rfq_internal_ref_no rfq_sent_to_match
			  , rfq_forwarded_by_match.rfq_internal_ref_no rfq_forwarded_by_match
			  , qot_match.qot_internal_ref_no qot_received_by_match
			  , direct_qot.qot_internal_ref_no direct_qot
			  , direct_rfq.rfq_internal_ref_no direct_rfq
			  , (SELECT ord_internal_ref_no FROM ord WHERE qot_internal_ref_no=direct_qot.qot_internal_ref_no AND rownum=1) direct_ord
			FROM 
			  qot qot_match
			  , qot direct_qot
			  , rfq rfq_forwarded_by_match
			  , rfq rfq_sent_to_match
			  , rfq direct_rfq
			WHERE
				-- qot sent to match should have 11107 as buyer branch
			  	qot_match.byb_branch_code=11107
				AND qot_match.rfq_internal_ref_no=rfq_forwarded_by_match.rfq_internal_ref_no
			    -- rfq sent to match needs to have 999999 on spb_branch_code, rfq_ref_no should match 
				-- and there should be a clear connection between the rfq forwarded by match 
				-- and rfq received by match
		      	AND rfq_sent_to_match.spb_branch_code=999999
		      	AND rfq_sent_to_match.rfq_ref_no=rfq_forwarded_by_match.rfq_ref_no
		      	AND rfq_sent_to_match.rfq_internal_ref_no=rfq_forwarded_by_match.rfq_pom_source
				
			  	-- qot sent to buyer directly outside match should have NON match proxy buyer tnid
			    AND direct_qot.byb_branch_code!=11107
				-- qot sent to buyer directly should match spb_branch_code, qot_subject, 
				-- qot_ref_no and the quote should be sent after the qot sent to match 
			    AND qot_match.spb_branch_code=direct_qot.spb_branch_code
			    AND qot_match.qot_subject=direct_qot.qot_subject
			    AND qot_match.qot_ref_no=direct_qot.qot_ref_no
			    AND qot_match.qot_submitted_date<=direct_qot.qot_submitted_date

				-- rfq sent directly to the supplier (without going through match)
				-- should have the same: rfq_ref_no, spb_branch_code, and the 
				-- rfq_internal_ref_no should not be the same as the one that was sent to
				-- 999999 and not the same with the one that being forwarded by match
				-- direct rfq should be sent after rfq_sent_to_match
			    AND direct_rfq.rfq_ref_no=rfq_sent_to_match.rfq_ref_no
			    AND direct_rfq.spb_branch_code=qot_match.spb_branch_code
			    AND direct_rfq.rfq_internal_ref_no!=rfq_forwarded_by_match.rfq_internal_ref_no
			    AND direct_rfq.rfq_internal_ref_no!=rfq_sent_to_match.rfq_internal_ref_no
				AND direct_rfq.rfq_submitted_date>=rfq_sent_to_match.rfq_submitted_date
				
			  	-- linking direct qot and direct rfq
			    AND direct_rfq.rfq_internal_ref_no=direct_qot.rfq_internal_ref_no
			    AND direct_qot.qot_internal_ref_no!=qot_match.qot_internal_ref_no
							
		";
	}
	
	/**
	 * getting the candidate of QOT based on OrdId
	 * @param unknown $ordId
	 * @deprecated
	 */
	private function getCandidateOfQotByOrdId( $ordId )
	{
		$sql = "  
			SELECT 
			  qot_with_score.* 
			  , (	
					qot_with_score.score_public_ref_no 
			   	 	+ qot_with_score.score_subject
			    	+ qot_with_score.score_cost
			   	 	+ qot_with_score.score_vessel_name
			   	 	+ qot_with_score.score_line_item
					+ qot_with_score.score_rfq_ref_no
				)
			    AS total_score
			FROM
			(
		        SELECT
		          x.*
		          , utl_match.JARO_WINKLER_SIMILARITY(x.ord_line_item, x.qot_line_item)/100 * 10 as score_line_item
		        FROM
		        (
		          SELECT 
		            ord.ord_internal_ref_no
		            , qot.qot_internal_ref_no
		            , DECODE(LOWER(TRIM(qot.qot_ref_no)),     LOWER(TRIM(ord.ord_ref_no)), 10, 0) as score_public_ref_no
		            , qot.qot_ref_no
		            , ord.ord_ref_no
		            , '||' d1
		            , CASE WHEN qot.qot_subject IS NOT null AND ord.ord_subject IS NOT null THEN
						DECODE(LOWER(TRIM(qot.qot_subject)),    LOWER(TRIM(ord.ord_subject)), 10, 0)
					  ELSE
						0
					  END as score_subject
		            , qot.qot_subject
		            , ord.ord_subject
		            , '||' d2
		            , CASE qot.qot_total_cost 
		            WHEN 0 THEN 
		              -100
		                ELSE
		                  DECODE(qot.qot_total_cost, ord.ord_total_cost, 10, 0)
		              END as score_cost
		            , qot.qot_total_cost
		            , ord.ord_total_cost
		            , '||' d3
		            , utl_match.JARO_WINKLER_SIMILARITY(rfq.rfq_vessel_name, ord.ord_vessel_name)/100 * 10 as score_vessel_name
		            , rfq.rfq_vessel_name
		            , ord.ord_vessel_name
		            , (
		                SELECT
		                  RTRIM( xmlagg( xmlelement( c, lower( SUBSTR(TRIM(li_desc), 0, 20) ) || ',' ) order by  lower( SUBSTR(TRIM(li_desc), 0, 20) ) ).extract ( '//text()' ), ',' ) 
		                FROM
		                (
		                  SELECT    REGEXP_REPLACE(TRIM(oli_desc), '[[:cntrl:]]', '__') li_desc
		                  FROM      order_line_item
		                  WHERE     oli_order_internal_ref_no = :ordId
						  			AND rownum < 30
		                )
		            ) as ord_line_item
		            , (
		                  SELECT    
		                    RTRIM( xmlagg( xmlelement( c, lower( TRIM(REGEXP_REPLACE(SUBSTR(TRIM(qli_desc),0,20), '[[:cntrl:]]', '__')) ) || ',' ) order by  lower( TRIM(REGEXP_REPLACE(SUBSTR(TRIM(qli_desc),0,20), '[[:cntrl:]]', '__')) ) ).extract ( '//text()' ), ',' ) 
		                  FROM      quote_line_item
		                  WHERE     
		                  	qli_qot_internal_ref_no = qot.qot_internal_ref_no
							AND rownum < 30
		            ) as qot_line_item
		            , '||' d4
		            , DECODE(LOWER(TRIM(rfq.rfq_ref_no)),     LOWER(TRIM(ord.ord_ref_no)), 10, 0) as score_rfq_ref_no
		            , rfq.rfq_ref_no
		            , ord.ord_ref_no ord_ref_no_2
								
		          FROM 
		            request_for_quote rfq,
		            quote qot,
		            purchase_order ord
		          WHERE 
		            ord.ord_internal_ref_no=:ordId			    
		            -- Supplier branch on PO and supplier branch on quote must match
		            AND qot.qot_spb_branch_code=ord.ord_spb_branch_code 
		            AND qot.qot_byb_branch_code=ord.ord_byb_buyer_branch_code
		            -- PO date/time must be later than quote date/time
		            AND qot.qot_submitted_date < ord.ord_submitted_date
		            -- To reduce processing time, we should only look for quotes that are issued X months prior to the PO date.
		            AND qot.qot_submitted_date > ADD_MONTHS(ord.ord_submitted_date, -" . self::ALGORITHM_MONTH_DIFFERENCE_FOR_QOT . ")
		            AND rfq.rfq_internal_ref_no=qot.qot_rfq_internal_ref_no
		          	/*
		                -- Removing the qots that's already been linked
		          		AND qot.qot_internal_ref_no NOT IN (
		                  	SELECT o.ord_qot_internal_ref_no 
		            		FROM purchase_order o
		                  	WHERE 
		                  		o.ord_spb_branch_code = ord.ord_spb_branch_code
		                    	AND o.ord_byb_buyer_branch_code=ord.ord_byb_buyer_branch_code
		                      	AND ord_qot_internal_ref_no IS NOT null
		                )
		          	*/	
		        ) x
			) qot_with_score
			WHERE
				(
					qot_with_score.score_public_ref_no 
			   	 	+ qot_with_score.score_subject
			    	+ qot_with_score.score_cost
			   	 	+ qot_with_score.score_vessel_name
			   	 	+ qot_with_score.score_line_item
		            + qot_with_score.score_rfq_ref_no
				) > " . self::ALGORITHM_MIN_SCORE . "
			ORDER BY
			  ord_internal_ref_no ASC
			  , (
					qot_with_score.score_public_ref_no 
			   	 	+ qot_with_score.score_subject
			    	+ qot_with_score.score_cost
			   	 	+ qot_with_score.score_vessel_name
			   	 	+ qot_with_score.score_line_item
					+ qot_with_score.score_rfq_ref_no
				) DESC	
		";


		
		return $this->db->fetchAll($sql, array('ordId' => $ordId));
	}
	
	public function processS1GetCandidateOfQuotesByOrderId( $ordId )
	{
		$sql = "
		  SELECT
			b.qot_internal_ref_no qot_internal_ref_no
			, b.ord_internal_ref_no ord_internal_ref_no
			, b.qot_line_item_count
			, b.ord_line_item_count
			, b.li_to_use
		  FROM
		  (
		    SELECT
		      a.*
		      , (
		          SELECT
		            RTRIM( xmlagg( xmlelement( c, lower( SUBSTR(TRIM(REGEXP_REPLACE(TRIM(oli_desc), '[[:cntrl:]]', '__')), 0, " . self::MAX_LINEITEM_LENGTH_TO_COMPARE . ") ) || ',' ) order by  lower( SUBSTR(TRIM(REGEXP_REPLACE(TRIM(oli_desc), '[[:cntrl:]]', '__')), 0, " . self::MAX_LINEITEM_LENGTH_TO_COMPARE . ") ) ).extract ( '//text()' ), ',' )
		          FROM      order_line_item
		          WHERE     oli_order_internal_ref_no = a.ord_internal_ref_no
		                    AND rownum <= CASE 
											WHEN a.li_to_use = 'ORD' THEN a.ord_line_item_count
		                                  	WHEN a.li_to_use = 'QOT' THEN a.qot_line_item_count
		                                   	ELSE a.qot_line_item_count
		                                 END
		      ) as ord_line_item
		      , (
		          SELECT
		            RTRIM( xmlagg( xmlelement( c, lower( SUBSTR(TRIM(REGEXP_REPLACE(TRIM(qli_desc), '[[:cntrl:]]', '__')), 0, " . self::MAX_LINEITEM_LENGTH_TO_COMPARE . ") ) || ',' ) order by  lower( SUBSTR(TRIM(REGEXP_REPLACE(TRIM(qli_desc), '[[:cntrl:]]', '__')), 0, " . self::MAX_LINEITEM_LENGTH_TO_COMPARE . ") ) ).extract ( '//text()' ), ',' )
		          FROM      quote_line_item
		          WHERE     qli_qot_internal_ref_no = a.qot_internal_ref_no
		          AND rownum <= CASE 
									WHEN a.li_to_use = 'ORD' THEN a.ord_line_item_count
		                            WHEN a.li_to_use = 'QOT' THEN a.qot_line_item_count
		                            ELSE a.qot_line_item_count
		                       END
		      ) as qot_line_item
		      , (
		          SELECT
		            RTRIM( xmlagg( xmlelement( c, lower( SUBSTR(TRIM(REGEXP_REPLACE(TRIM(rfl_product_desc), '[[:cntrl:]]', '__')), 0, " . self::MAX_LINEITEM_LENGTH_TO_COMPARE . ") ) || ',' ) order by  lower( SUBSTR(TRIM(REGEXP_REPLACE(TRIM(rfl_product_desc), '[[:cntrl:]]', '__')), 0, " . self::MAX_LINEITEM_LENGTH_TO_COMPARE . ") ) ).extract ( '//text()' ), ',' )
		          FROM      rfq_line_item
		          WHERE     rfl_rfq_internal_ref_no = a.rfq_internal_ref_no
		          AND rownum <= CASE 
									WHEN a.li_to_use = 'ORD' THEN a.ord_line_item_count
		                            WHEN a.li_to_use = 'QOT' THEN a.qot_line_item_count
		                            ELSE a.qot_line_item_count
		                       END
		      ) as rfq_line_item
		            		
		    FROM
		    (
		
		      SELECT
		       	qot.qot_rfq_internal_ref_no rfq_internal_ref_no
		        , qot.qot_internal_ref_no qot_internal_ref_no
		        , ord.ord_internal_ref_no ord_internal_ref_no
		        , CASE
		            WHEN qot_line_item_count>" . self::MAX_LINEITEM . " THEN " . self::MAX_LINEITEM . "
		            ELSE qot_line_item_count
		          END
		          AS
		          qot_line_item_count
		        , CASE
		            WHEN ord_line_item_count>" . self::MAX_LINEITEM . " THEN " . self::MAX_LINEITEM . "
		            ELSE ord_line_item_count
		          END
		          AS
		          ord_line_item_count
		        , CASE
		            WHEN qot_line_item_count > ord_line_item_count THEN 'ORD'
		            WHEN ord_line_item_count > qot_line_item_count THEN 'QOT'
		            ELSE 'QOT'
		          END
		          AS li_to_use
		      FROM
		        quote qot,
		        purchase_order ord
		      WHERE
		        ord.ord_internal_ref_no=:ordId
		        -- Supplier branch on PO and supplier branch on quote must match
		        AND qot.qot_spb_branch_code=ord.ord_spb_branch_code
		        AND qot.qot_byb_branch_code=ord.ord_byb_buyer_branch_code
		        -- PO date/time must be later than quote date/time
		        AND qot.qot_created_date < ord.ord_created_date
		        -- To reduce processing time, we should only look for quotes that are issued X months prior to the PO date.
		        AND qot.qot_created_date > ADD_MONTHS(ord.ord_created_date, -" . self::ALGORITHM_MONTH_DIFFERENCE_FOR_QOT . ")
		    ) a
		  ) b
		  WHERE
		    b.ord_line_item IS NOT null
		    AND b.qot_line_item IS NOT null
		    -- pulling the one that is > self::THRESHOLD_SCORE_ALL_LINEITEM% match
		    -- checking the ORD LI vs RFQ LI
		    AND (
		    	utl_match.JARO_WINKLER_SIMILARITY(b.ord_line_item, b.qot_line_item) > " . self::THRESHOLD_SCORE_ALL_LINEITEM . "
		    	OR utl_match.JARO_WINKLER_SIMILARITY(b.ord_line_item, b.rfq_line_item) > " . self::THRESHOLD_SCORE_ALL_LINEITEM . "
		    )
		  ORDER BY
		    utl_match.JARO_WINKLER_SIMILARITY(b.ord_line_item, b.qot_line_item) + utl_match.JARO_WINKLER_SIMILARITY(b.ord_line_item, b.rfq_line_item) DESC
		
		";
		$data = $this->db->fetchAll($sql, array('ordId' => $ordId));
		if( $this->verboseLog === true ) $this->logger->log("Processing " . $ordId . " - " . count($data) . " QOTs candidate found");
		return $data;
	}
	
	public function processOrder()
	{
		if( $this->mode == self::MODE_CUSTOM )
		{
			if( $this->verboseLog === true ) $this->logger->log("Deleting test data");
			$sql = "DELETE FROM match_order_quote WHERE moq_is_test=1";
			$this->dbSSReport2->query($sql);
			$this->dbSSReport2->commit();
		
			$sql = "DELETE FROM match_orphaned_order_test";
			$this->dbSSReport2->query($sql);
			$this->dbSSReport2->commit();
		
		}
		
		// run
		while( 1 != 2 )
		{
			// check time elapse
			if( $this->stopWatch->getTotal() > self::MAX_EXECUTION_TIME)
			{
				$this->logger->log("exiting.. (elapsed time " . self::MAX_EXECUTION_TIME ."s)");
				exit;
			}
	
			if( $this->mode == self::MODE_TEST_TXN || $this->mode == self::MODE_MATCH_TEST_TXN || $this->mode == self::MODE_CUSTOM )
			{
				$this->prepareTestData();
			}
	
	
			$this->logger->log("Getting next batch of PO");
			$orders = $this->getOrphanedOrder();
			if( count($orders) == 0 )
			{
				$this->logger->log("exiting.. (nothing left to process)");
				exit;
			}
	
			$this->logger->log("" . count($orders) . " order(s) found");
			foreach($orders as $ord)
			{
				++$totalOrderProcessed;
				$orderId = $ord['ORD_INTERNAL_REF_NO'];
				$candidateQuoteIds = $matchedQuoteIds = array();
				$matchedQuoteId = null;
				if( $this->verboseLog === true ) $this->logger->log("1 - Processing " . $orderId);
				
				// send this to stdout
				echo ".";
				// getting the list of the quote candidate that matches the first check and the line items are > 50% similar
				foreach( (array) $this->processS1GetCandidateOfQuotesByOrderId( $orderId ) as $data )
				{
					$candidateQuoteIds[] = $data['QOT_INTERNAL_REF_NO'];
					
					if( $this->processS2CheckQuoteLineItemDetail( $data ) === true || $this->processS2CheckRfqLineItemDetail( $data ) === true)
					{
						if( $this->processS3LastCheck($data) === true )
						{
							$matchedQuoteIds[] = $data['QOT_INTERNAL_REF_NO'];
						}
					}
				}
				
				// if multiple quote matches then use the latest doc
				sort($matchedQuoteIds);
				$matchedQuoteId = $matchedQuoteIds[count($matchedQuoteIds)-1];
				// storing result to db
				// store($ordId, $qotId, $totalScore, $qotType, $qots, $isTest = false)
				$this->store(
					$orderId, 
					$matchedQuoteId, 
					"" /*score*/, 
					($this->isOriginatedFromMatch($matchedQuoteId)===true?'match':'non-match'), 
					$candidateQuoteIds, 
					($this->mode == self::MODE_TEST_TXN || $this->mode == self::MODE_MATCH_TEST_TXN || $this->mode == self::MODE_CUSTOM)
				);

				// check time elapse
				$this->stopWatch->click();
				if( $this->stopWatch->getTotal() > self::MAX_EXECUTION_TIME)
				{
					$this->logger->log("exiting.. (elapsed time " . self::MAX_EXECUTION_TIME ."s)");
					exit;
				}
			}
		}
	}
	
	/**
	 * last step check when it compares vessel, subject and refno 
	 * @param unknown $data
	 * @return boolean
	 */
	public function processS3LastCheck($data)
	{
		$sql = "
			  SELECT
			    utl_match.JARO_WINKLER_SIMILARITY(rfq_vessel_name, ord_vessel_name) as score_vessel
			    , utl_match.JARO_WINKLER_SIMILARITY(rfq_subject, ord_subject) as score_subject
			    , utl_match.JARO_WINKLER_SIMILARITY(rfq_ref_no, ord_ref_no) as score_ref_no
			  FROM
			    qot JOIN rfq ON (qot.rfq_internal_ref_no=rfq.rfq_internal_ref_no),
			    ord
			  WHERE
			    ord.ord_internal_ref_no=:ordId
			    AND qot.qot_internal_ref_no=:qotId
		";		
		$params = array(
			'qotId' => $data['QOT_INTERNAL_REF_NO'],
			'ordId' => $data['ORD_INTERNAL_REF_NO']
		);
		$result = $this->dbSSReport2->fetchAll($sql, $params);
		
		if( $result[0]['SCORE_VESSEL'] >= self::THRESHOLD_SCORE_VESSEL && $result[0]['SCORE_SUBJECT'] >= self::THRESHOLD_SCORE_SUBJECT )
		{
			return true;
		}
		else
		{
			return false;
		}
	}
	
	/**
	 * comparing order's LI with the Quote LI
	 * @param unknown $data
	 */
	public function processS2CheckQuoteLineItemDetail($data)
	{
		// selection logic goes in here; ie how many line items from either side should be used
		$sql = "
			SELECT
			  COUNT(is_matched) total_matched
			FROM
			(
			  SELECT
			    utl_match.JARO_WINKLER_SIMILARITY(qli_desc, oli_desc) as score_line_item
			    , qli_desc
			    , oli_desc
			    , CASE 
			        WHEN utl_match.JARO_WINKLER_SIMILARITY(qli_desc, oli_desc) > " . self::THRESHOLD_SCORE_EACH_LINEITEM . " THEN 'matched'
			        ELSE 'not matched'
			      END
			      AS is_matched
			  FROM
			    (SELECT TRIM(REGEXP_REPLACE(TRIM(qli_desc), '[[:cntrl:]]|![[:alnum:]]|![\x80-\xFF]', '__')) qli_desc FROM quote_line_item WHERE qli_qot_internal_ref_no=:qotId AND rownum<=:rowNumForQuote) quote_line_item,
			    (SELECT TRIM(REGEXP_REPLACE(TRIM(oli_desc), '[[:cntrl:]]|![[:alnum:]]|![\x80-\xFF]', '__')) oli_desc FROM order_line_item WHERE oli_order_internal_ref_no=:ordId AND rownum<=:rowNumForOrder) order_line_item
			        		
			)
	      WHERE
	        is_matched='matched'
		  GROUP BY is_matched
		";
		
		//die($sql);
		$rowNum = ($data['LI_TO_USE'] == "QOT")?$data['QOT_LINE_ITEM_COUNT']:$data['ORD_LINE_ITEM_COUNT'];
		$params = array(
			'qotId' => $data['QOT_INTERNAL_REF_NO'], 
			'ordId' => $data['ORD_INTERNAL_REF_NO'],
			'rowNumForQuote' => $rowNum,
			'rowNumForOrder' => $rowNum
		);
		
		$totalLIMatched = $this->db->fetchOne($sql, $params);
		$totalLIMatched = ($totalLIMatched===false)?0:$totalLIMatched;
		
		$totalLIToUse = ($data['LI_TO_USE'] == "QOT") ? $data['QOT_LINE_ITEM_COUNT']:$data["ORD_LINE_ITEM_COUNT"];
		
		if( $this->verboseLog === true ) $this->logger->log(" 2a - Comparing Quote LI: " . $data['ORD_INTERNAL_REF_NO'] . ' and ' . $data['QOT_INTERNAL_REF_NO'] . ': ' . $totalLIMatched . "/" . $totalLIToUse . " matched");

		if( $totalLIMatched >= $totalLIToUse )
		{
			if( $this->verboseLog === true ) $this->logger->log("  3 - ORD " . $data['ORD_INTERNAL_REF_NO'] . ' matched with QOT ' . $data['QOT_INTERNAL_REF_NO']);
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * 
	 * @param unknown $data
	 * @return boolean
	 */
	public function processS2CheckRfqLineItemDetail($data)
	{
		// selection logic goes in here; ie how many line items from either side should be used
		$sql = "
			SELECT
			  COUNT(is_matched) total_matched
			FROM
			(
			  SELECT
			    utl_match.JARO_WINKLER_SIMILARITY(rfl_product_desc, oli_desc) as score_line_item
			    , rfl_product_desc
			    , oli_desc
			    , CASE
			        WHEN utl_match.JARO_WINKLER_SIMILARITY(rfl_product_desc, oli_desc) > " . self::THRESHOLD_SCORE_EACH_LINEITEM . " THEN 'matched'
			        ELSE 'not matched'
			      END
			      AS is_matched
			  FROM
			    (SELECT TRIM(REGEXP_REPLACE(TRIM(rfl_product_desc), '[[:cntrl:]]|![[:alnum:]]|![\x80-\xFF]', '__')) rfl_product_desc FROM rfq_line_item, quote WHERE qot_internal_ref_no=:qotId AND qot_rfq_internal_ref_no=rfl_rfq_internal_ref_no AND rownum<=:rowNumForQuote) rfq_line_item,
			    (SELECT TRIM(REGEXP_REPLACE(TRIM(oli_desc), '[[:cntrl:]]|![[:alnum:]]|![\x80-\xFF]', '__')) oli_desc FROM order_line_item WHERE oli_order_internal_ref_no=:ordId AND rownum<=:rowNumForOrder) order_line_item
			)
	      WHERE
	        is_matched='matched'
		  GROUP BY is_matched
		";
		$rowNum = ($data['LI_TO_USE'] == "QOT")?$data['QOT_LINE_ITEM_COUNT']:$data['ORD_LINE_ITEM_COUNT'];
		$params = array(
				'qotId' => $data['QOT_INTERNAL_REF_NO'],
				'ordId' => $data['ORD_INTERNAL_REF_NO'],
				'rowNumForQuote' => $rowNum,
				'rowNumForOrder' => $rowNum
		);
	
		$totalLIMatched = $this->db->fetchOne($sql, $params);
		$totalLIMatched = ($totalLIMatched===false)?0:$totalLIMatched;
	
		$totalLIToUse = ($data['LI_TO_USE'] == "QOT") ? $data['QOT_LINE_ITEM_COUNT']:$data["ORD_LINE_ITEM_COUNT"];
	
		if( $this->verboseLog === true ) $this->logger->log(" 2b - Comparing Rfq LI: " . $data['ORD_INTERNAL_REF_NO'] . ': ' . $totalLIMatched . "/" . $totalLIToUse . " matched");
	
		if( $totalLIMatched >= $totalLIToUse )
		{
			if( $this->verboseLog === true ) $this->logger->log("  3 - ORD " . $data['ORD_INTERNAL_REF_NO'] . ' matched with RFQ ' . $data['RFQ_INTERNAL_REF_NO']);
			return true;
		}
		else
		{
			return false;
		}
	}
	
}