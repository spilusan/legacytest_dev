<?php
/**
 * This class is responsible to rebuild cubes related to match
 * which is run every day
 *
 * @todo Instead of dropping the table, we might want to re-calculate delta
 * @todo consider incremental update
 * @todo fix one day behind becouse of calling _v4 stored functions on the table generated previous day
 *
 * @author Elvir
 *
 */
class Shipserv_Match_Report_AdoptionRate_Cron extends Shipserv_Object
{
	const ONLY_USE_1_MONTH_DATA = false;

    /**
     * Shipserv_Match_Report_AdoptionRate_Cron constructor.
     * @return Shipserv_Match_Report_AdoptionRate_Cron
     */
	public function __construct()
	{
		$this->db = Shipserv_Helper_Database::getDb();
		$this->reporting = Shipserv_Helper_Database::getSsreport2Db();
		$this->logger = new Myshipserv_Logger_File('match-cron-kpi-adoption');
		$this->logToConsole = false;
	}

    /**
     * Update buyer stats
     * @return null
     */
	public function updateBuyerStats()
	{
		// calculating average PO in USD
		$sql = "
			MERGE INTO buyer b USING
			(
			  SELECT
			    byb_branch_code
			    , AVG(final_total_cost_usd) average_po_cost_usd
			  FROM
			    ord_traded_gmv
			  WHERE
			    ord_orig_submitted_date BETWEEN SYSDATE-365 AND SYSDATE-1
			  GROUP BY
			    byb_branch_code
			) t
			ON (b.byb_branch_code=t.byb_branch_code)
			WHEN MATCHED THEN
			  UPDATE SET b.avg_ord_total_cost_12m=t.average_po_cost_usd
		";
		$this->query($sql);

		// calculating trend
		$sql = "
			MERGE INTO buyer b USING
			(
			  SELECT
			    month_3.byb_branch_code
			    , month_3.total_gmv m3
			    , month_6.total_gmv m6
			    , (
					CASE
						WHEN month_3.total_gmv IS NULL AND month_6.total_gmv IS NULL THEN null
						WHEN (month_3.total_gmv < month_6.total_gmv) OR (month_3.total_gmv IS NULL AND month_6.total_gmv IS NOT null) THEN 0
						WHEN (month_3.total_gmv >= month_6.total_gmv) OR (month_3.total_gmv IS NOT NULL AND month_6.total_gmv IS NULL) THEN 1
						WHEN month_3.total_gmv = month_6.total_gmv THEN 2
					END
				) trend

			  FROM
			    (
			      SELECT
			        o.byb_branch_code
			        , SUM(o.final_total_cost_usd) total_gmv
			      FROM
			        ord_traded_gmv o JOIN ord oo ON (oo.ord_internal_ref_no=o.ord_internal_ref_no)
			      WHERE
			        o.ord_orig_submitted_date BETWEEN add_months(trunc(sysdate,'mm'),-1) - 90 AND add_months(trunc(sysdate,'mm'),-1)
			      GROUP BY
			        o.byb_branch_code
			    ) month_3
			    ,
			    (
			      SELECT
			        o.byb_branch_code
			        , SUM(o.final_total_cost_usd) total_gmv
			      FROM
			        ord_traded_gmv o JOIN ord oo ON (oo.ord_internal_ref_no=o.ord_internal_ref_no)
			      WHERE
			        o.ord_orig_submitted_date BETWEEN add_months(trunc(sysdate,'mm'),-1) - 180 AND add_months(trunc(sysdate,'mm'),-1) - 90
			      GROUP BY
			        o.byb_branch_code
			    ) month_6
			  WHERE
			    month_3.byb_branch_code=month_6.byb_branch_code (+)
			) t
			ON (b.byb_branch_code=t.byb_branch_code)
			WHEN MATCHED THEN
			  UPDATE SET b.gmv_trend_3m=t.trend

		";
		$this->query($sql);

	}

	/**
	 * Create small cubes for each match related events
	 */
	public function rebuildTable()
	{
		/**
		 * STEP 1. Handling replacement RFQ - only consider the latest doc/RFQ in the trail
		 */
		$sql = "DROP TABLE tmp_m_b_replacement_rfq_0 CASCADE CONSTRAINTS";
		$this->queryDrop($sql);

		$sql = "
			CREATE TABLE tmp_m_b_replacement_rfq_0 AS
			    SELECT * FROM
			    (
			      SELECT
			        rfq_internal_ref_no,
			        rfq_count,
			        rfq_original,
			        row_number() OVER (PARTITION BY rfq_original ORDER BY rfq_internal_ref_no DESC NULLS LAST) as rfq_row_num
			      FROM rfq
			      WHERE
			        spb_branch_code=999999
			        AND rfq_count=0
			      ORDER BY
			        rfq_original
			    )
			    WHERE rfq_row_num=1
		";
		$this->query($sql);

		sleep(20);

		$sql = "DROP TABLE tmp_m_b_rfq_to_match CASCADE CONSTRAINTS";
		$this->queryDrop($sql);

		$sql = "
			CREATE TABLE tmp_m_b_rfq_to_match  AS
			  SELECT
				DISTINCT
				r.rfq_internal_ref_no
			    , r.byb_branch_code
				, r.spb_branch_code
				, r.rfq_submitted_date
				, r.rfq_vessel_name
				, r.rfq_ref_no
				, r.rfq_count
				, r.rfq_subject
				, r.rfq_line_count
				, r.rfq_pom_source
				, (SELECT cntc_person_email_address FROM contact WHERE rfq_internal_ref_no=cntc_doc_internal_ref_no AND cntc_doc_type='RFQ' AND cntc_person_email_address IS NOT null AND rownum=1 ) rfq_email
				, 0 is_orphaned_po
				, r.rfq_event_hash
			  FROM
			    rfq r
			  WHERE
			    r.spb_branch_code=999999
			    AND r.rfq_count=1
			    AND r.rfq_internal_ref_no NOT IN ( SELECT rfq_original FROM tmp_m_b_replacement_rfq_0 WHERE rfq_original IS NOT null)
			";
		$this->query($sql);

		$sql = "
			INSERT INTO tmp_m_b_rfq_to_match
			  SELECT
				DISTINCT
				r.rfq_internal_ref_no
			    , r.byb_branch_code
				, r.spb_branch_code
				, r.rfq_submitted_date
				, r.rfq_vessel_name
				, r.rfq_ref_no
				, r.rfq_count
				, r.rfq_subject
				, r.rfq_line_count
				, r.rfq_pom_source
				, (SELECT cntc_person_email_address FROM contact WHERE rfq_internal_ref_no=cntc_doc_internal_ref_no AND cntc_doc_type='RFQ' AND cntc_person_email_address IS NOT null AND rownum=1 ) rfq_email
				, 0 is_orphaned_po
				, r.rfq_event_hash
			  FROM
			    rfq r JOIN contact c ON (rfq_internal_ref_no=cntc_doc_internal_ref_no AND cntc_doc_type='RFQ')
			  WHERE
			    r.rfq_internal_ref_no IN ( SELECT rfq_internal_ref_no FROM tmp_m_b_replacement_rfq_0 WHERE rfq_original IS NOT null)
				AND NOT EXISTS( SELECT 1 FROM tmp_m_b_rfq_to_match mmm WHERE r.rfq_internal_ref_no=mmm.rfq_internal_ref_no)
			    AND r.rfq_count=0
				AND r.spb_branch_code=999999
		";
		$this->query($sql);

		/**
		 * STEP 1.1 Consider orphaned PO that's not on the list
		 */
		$sql = "
			INSERT INTO tmp_m_b_rfq_to_match
			  SELECT
				DISTINCT
				r.rfq_internal_ref_no
			    , r.byb_branch_code
				, r.spb_branch_code
				, r.rfq_submitted_date
				, r.rfq_vessel_name
				, r.rfq_ref_no
				, r.rfq_count
				, r.rfq_subject
				, r.rfq_line_count
				, r.rfq_pom_source
				, (SELECT cntc_person_email_address FROM contact WHERE rfq_internal_ref_no=cntc_doc_internal_ref_no AND cntc_doc_type='RFQ' AND cntc_person_email_address IS NOT null AND rownum=1 ) rfq_email
				, 1 is_orphaned_po
				, r.rfq_event_hash
			  FROM
			    linkable_orphaned_po lop JOIN rfq r ON (lop.lop_rfq_internal_ref_no=r.rfq_internal_ref_no)
				JOIN contact c ON (r.rfq_internal_ref_no=cntc_doc_internal_ref_no AND cntc_doc_type='RFQ')
			  WHERE
			    r.rfq_count=0
				AND r.spb_branch_code=999999
				AND NOT EXISTS( SELECT 1 FROM tmp_m_b_rfq_to_match mmm WHERE r.rfq_internal_ref_no=mmm.rfq_internal_ref_no)

		";
		$this->query($sql);

		$sql = "CREATE INDEX IDX_TMP_B_M2_1 ON tmp_m_b_rfq_to_match (BYB_BRANCH_CODE, RFQ_SUBMITTED_DATE)";
		$this->query($sql);

		$sql = "CREATE INDEX IDX_TMP_B_M2_2 ON tmp_m_b_rfq_to_match (RFQ_INTERNAL_REF_NO)";
		$this->query($sql);

		$sql = "CREATE INDEX IDX_TMP_B_M2_3 ON tmp_m_b_rfq_to_match (RFQ_EVENT_HASH)";
		$this->query($sql);



		/**
	  	 * STEP 1. Adding few columns to tmp_m_b_rfq_to_match
		 */
		$sql = "
			ALTER TABLE tmp_m_b_rfq_to_match ADD
			(
				cheapest_qot_spb_by_byb NUMBER(10)
				, cheapest_qot_spb_by_match NUMBER(10)
				, cheapest_qot_spb_by_byb_100 NUMBER(10)
				, cheapest_qot_spb_by_match_100 NUMBER(10)
		        , potential_saving NUMBER(8,2)
		        , realised_saving NUMBER(8,2)
				, has_order NUMBER(1,0)
				, has_quote NUMBER(1,0)
			)
		";
		$this->query($sql);

		if (self::ONLY_USE_1_MONTH_DATA == true) {
			$sql = "
				DELETE FROM tmp_m_b_rfq_to_match
				WHERE
					rfq_submitted_date < TO_DATE('01-APR-2014')
					--rfq_internal_ref_no != 12278699
			";
			$this->query($sql);
		}

		/*
		// !!!! remove this --------------------------------------------------------------------------------
		$sql = "
			DELETE FROM tmp_m_b_rfq_to_match
			WHERE
				byb_branch_code!=11050
		";
		$this->query($sql);

		$sql = "
			DELETE FROM tmp_m_b_rfq_to_match
			WHERE
				rfq_submitted_date < TO_DATE('01-JUN-2014')
				OR rfq_submitted_date > TO_DATE('30-SEP-2014', 'DD-MON-YYYY') + 0.99999
		";
		$this->query($sql);
		// !!!! remove this --------------------------------------------------------------------------------
		*/




		$sql = "DROP TABLE tmp_m_b_rfq_forwarded_by_match CASCADE CONSTRAINTS";
		$this->queryDrop($sql);

		/**
    	 * STEP 2. Create table to store forwarded RFQ by match operator/recommendation engine
		 */
		$sql = "
			CREATE TABLE tmp_m_b_rfq_forwarded_by_match  AS
				SELECT
					rfq_forwarded.rfq_internal_ref_no
				    , rfq_forwarded.byb_branch_code
					, rfq_forwarded.spb_branch_code
					, rfq_forwarded.rfq_submitted_date
					, rfq_forwarded.rfq_vessel_name
					, rfq_forwarded.rfq_ref_no
				    , rfq_forwarded.rfq_count
					, rfq_forwarded.rfq_subject
					, rfq_forwarded.rfq_line_count
				    , rfq_forwarded.rfq_pom_source
					, 0 is_declined
					, rfq_m.byb_branch_code 		orig_byb_branch_code
					, rfq_m.rfq_submitted_date 		original_date
					, rfq_m.rfq_internal_ref_no 	rfq_sent_to_match
				FROM
				  rfq rfq_forwarded
				  , tmp_m_b_rfq_to_match rfq_m
				WHERE
				  rfq_forwarded.rfq_pom_source=rfq_m.rfq_internal_ref_no
				  AND rfq_forwarded.rfq_event_hash=rfq_m.rfq_event_hash -- new addition
		";
		$this->query($sql);

		$sql = "CREATE INDEX IDX_TMP_B_RFWD_1 ON tmp_m_b_rfq_forwarded_by_match (rfq_sent_to_match)";
		$this->query($sql);

		$sql = "CREATE INDEX IDX_TMP_B_RFWD_2 ON tmp_m_b_rfq_forwarded_by_match (orig_byb_branch_code, rfq_sent_to_match)";
		$this->query($sql);

		$sql = "CREATE INDEX IDX_TMP_B_RFWD_3 ON tmp_m_b_rfq_forwarded_by_match(rfq_sent_to_match, byb_branch_code)";
		$this->query($sql);


		/**
		 * STEP 3. Create a table to store RFQs that forwarded to buyer selected suppliers
		 * 		in the same match RFQ events
		 */
		$sql = "DROP TABLE tmp_m_b_rfq_also_sent_to_buyer CASCADE CONSTRAINTS";
		$this->queryDrop($sql);

		$sql = "
			CREATE TABLE tmp_m_b_rfq_also_sent_to_buyer  AS
				SELECT
				  DISTINCT
					rfq_b.rfq_internal_ref_no
				    , rfq_b.byb_branch_code
					, rfq_b.spb_branch_code
					, rfq_b.rfq_submitted_date
					, rfq_b.rfq_vessel_name
					, rfq_b.rfq_ref_no
					, rfq_b.rfq_count
					, rfq_b.rfq_subject
					, rfq_b.rfq_line_count
				    , rfq_b.rfq_pom_source
					, 0 is_declined
				  	, rfq_m.rfq_internal_ref_no rfq_sent_to_match
				FROM
				  tmp_m_b_rfq_to_match rfq_m
				  , rfq rfq_b
				WHERE
				  rfq_m.rfq_event_hash=rfq_b.rfq_event_hash
				  AND rfq_m.byb_branch_code=rfq_b.byb_branch_code
				  AND NOT EXISTS (
						SELECT 1 FROM match_imported_rfq_proc_list@livedb_link WHERE mir_rfq_internal_ref_no=rfq_b.rfq_internal_ref_no
				  )
				  AND rfq_m.rfq_event_hash=rfq_b.rfq_event_hash -- new addition
--				  AND rfq_m.rfq_line_count=rfq_b.rfq_line_count -- Unsure if we should do this
				  AND rfq_b.spb_branch_code!=999999
--				  AND rfq_b.rfq_count=1
--				  AND regexp_replace(regexp_replace(NVL(rfq_m.rfq_subject,'IS_NULL'), '^Ship(s|S)erv Match on behalf of ([^\:])+', ''), '\: ', '')
--		              = regexp_replace(regexp_replace(NVL(rfq_b.rfq_subject,'IS_NULL'), '^Ship(s|S)erv Match on behalf of ([^\:])+', ''), '\: ', '')
--		          AND rfq_b.spb_branch_code NOT IN (
--					  SELECT DISTINCT spb_branch_code
--					  FROM
--						tmp_m_b_rfq_forwarded_by_match
--					  WHERE
--						rfq_sent_to_match=rfq_m.rfq_internal_ref_no
--				  )
		";
		$this->query($sql);

		$sql = "";


		$sql = "CREATE INDEX IDX_TMP_B_R2BYSS_1 ON tmp_m_b_rfq_also_sent_to_buyer (rfq_sent_to_match)";
		$this->query($sql);

        $sql = "CREATE INDEX IDX_TMP_B_R2BYSS_2 ON tmp_m_b_rfq_also_sent_to_buyer (rfq_internal_ref_no)";
        $this->query($sql);



		$sql = "DROP TABLE tmp_m_b_rfq_to_match_buyer CASCADE CONSTRAINTS";
		$this->queryDrop($sql);


		/**
		 * STEP 4: Create aggregate table which combine RFQ sent to match and buyer selected suppliers
		 */
		$sql = "
			CREATE TABLE tmp_m_b_rfq_to_match_buyer  AS
			SELECT
			  rfq_internal_ref_no
			  , 'match' RFQ_TYPE
			  , rfq_ref_no
			  , byb_branch_code
			  , spb_branch_code
			  , rfq_submitted_date
			  , rfq_subject
			  , rfq_line_count
			  , rfq_pom_source
			  , rfq_internal_ref_no rfq_sent_to_match
			FROM
			  tmp_m_b_rfq_to_match

			UNION

			SELECT
			  rfq_internal_ref_no
			  , 'buyer' RFQ_TYPE
			  , rfq_ref_no
			  , byb_branch_code
			  , spb_branch_code
			  , rfq_submitted_date
			  , rfq_subject
			  , rfq_line_count
		      , rfq_pom_source
			  , rfq_sent_to_match
			FROM
			  tmp_m_b_rfq_also_sent_to_buyer
		";
		$this->query($sql);

		$sql = "CREATE INDEX IDX_TMP_B_R2MB_1 ON tmp_m_b_rfq_to_match_buyer (rfq_sent_to_match)";
		$this->query($sql);

		/**
		 * STEP 5: Gathering QOT
		 */
		$sql = "DROP TABLE tmp_m_b_replacement_qot_0 CASCADE CONSTRAINTS";
		$this->queryDrop($sql);

		$sql = "
			CREATE TABLE tmp_m_b_replacement_qot_0 AS
			    SELECT * FROM
			    (
			      SELECT
			        qot_internal_ref_no,
			        qot_count,
			        qot_original,
			        row_number() OVER (PARTITION BY qot_original ORDER BY qot_internal_ref_no DESC NULLS LAST) as qot_row_num
			      FROM qot
			      WHERE
			        byb_branch_code=11107
			        AND qot_submitted_date > TO_DATE('01-JAN-2011')
			        AND qot_count=0
			      ORDER BY
			        qot_original
			    )
			    WHERE qot_row_num=1
		";

		$this->query($sql);

		/**
		 * Create table to store quotes received by match which will then get forwarded
		 * to buyer
		 */

		$sql = "DROP TABLE tmp_m_b_qot_match CASCADE CONSTRAINTS";
		$this->queryDrop($sql);

		$sql = "
			CREATE TABLE tmp_m_b_qot_match  AS
				SELECT
				  qot_m.qot_internal_ref_no
			      , qot_m.byb_branch_code
				  , qot_m.spb_branch_code
				  , qot_m.qot_submitted_date
				  , qot_m.qot_ref_no
				  , qot_m.qot_count
				  , qot_m.qot_subject
				  , qot_m.rfq_internal_ref_no
				  , qot_m.qot_total_cost
				  , qot_m.qot_total_line_item_cost
				  , qot_m.qot_currency
				  , qot_m.QOT_TOTAL_VBP_USD
			  	  , qot_m.QOT_TOTAL_COST_USD

				  , qot_m.qot_total_cost_discounted
			  	  , qot_m.qot_total_cost_discounted_usd

				  , rfq_m.orig_byb_branch_code 	orig_byb_branch_code
				  , rfq_m.original_date 		original_date
				  , rfq_m.rfq_sent_to_match 	rfq_sent_to_match
				  , rfq_m.rfq_line_count
				  , qot_m.qot_line_count
				  , 0 potential_saving
				FROM
				  qot qot_m
				  , tmp_m_b_rfq_forwarded_by_match rfq_m
				WHERE
				  qot_m.rfq_internal_ref_no=rfq_m.rfq_internal_ref_no
				  AND qot_count=1
				  AND qot_internal_ref_no NOT IN (SELECT qot_original FROM tmp_m_b_replacement_qot_0 WHERE qot_original IS NOT null)
			";
		$this->query($sql);

		$sql = "
			INSERT INTO tmp_m_b_qot_match

				SELECT
				  	qot_m.qot_internal_ref_no
			      , qot_m.byb_branch_code
				  , qot_m.spb_branch_code
				  , qot_m.qot_submitted_date
				  , qot_m.qot_ref_no
				  , qot_m.qot_count
				  , qot_m.qot_subject
				  , qot_m.rfq_internal_ref_no
				  , qot_m.qot_total_cost
				  , qot_m.qot_total_line_item_cost
				  , qot_m.qot_currency
				  , qot_m.QOT_TOTAL_VBP_USD
			  	  , qot_m.QOT_TOTAL_COST_USD

				  , qot_m.qot_total_cost_discounted
			  	  , qot_m.qot_total_cost_discounted_usd

				  , rfq_m.orig_byb_branch_code orig_byb_branch_code
				  , rfq_m.original_date original_date
				  , rfq_m.rfq_sent_to_match rfq_sent_to_match
				  , rfq_m.rfq_line_count
				  , qot_m.qot_line_count
				  , 0 potential_saving
				FROM
				  qot qot_m
				  , tmp_m_b_rfq_forwarded_by_match rfq_m
				WHERE
				  qot_m.rfq_internal_ref_no=rfq_m.rfq_internal_ref_no
				  AND qot_count=0
				  AND qot_internal_ref_no IN (SELECT qot_original FROM tmp_m_b_replacement_qot_0 WHERE qot_original IS NOT null)
				  AND NOT EXISTS (
					SELECT 1 FROM tmp_m_b_qot_match qqq
					WHERE qqq.qot_internal_ref_no=qot_m.qot_internal_ref_no
				  )
			";
		$this->query($sql);

		$sql = "CREATE INDEX IDX_TMP_B_Q2M_1 ON tmp_m_b_qot_match (rfq_sent_to_match)";
        $this->query($sql);
        $sql = "CREATE INDEX IDX_TMP_B_Q2M_2 ON tmp_m_b_qot_match (byb_branch_code)";
        $this->query($sql);
        $sql = "CREATE INDEX IDX_TMP_B_Q2M_3 ON tmp_m_b_qot_match (rfq_internal_ref_no)";
		$this->query($sql);


		// flagging RFQ forwarded if RFQ's been declined
		$sql = "
			UPDATE tmp_m_b_rfq_forwarded_by_match r
			SET r.is_declined=1
			WHERE
				EXISTS (
					SELECT 1 FROM tmp_m_b_qot_match q
					WHERE qot_total_cost_usd=0
						AND r.rfq_internal_ref_no=q.rfq_internal_ref_no
						AND r.spb_branch_code=q.spb_branch_code
				)
		";
		$this->query($sql);

		$sql = "
			UPDATE tmp_m_b_rfq_forwarded_by_match r
			SET r.is_declined=2
			WHERE
				EXISTS (
					SELECT 1 FROM rfq_resp r2
					WHERE
						r2.rfq_internal_ref_no=r.rfq_internal_ref_no
						AND r2.spb_branch_code=r.spb_branch_code
						AND r2.rfq_resp_sts='DEC'
				)
		";
		$this->query($sql);



		/**
		 * Create table to store imported QUOTEs by the user
		 */

		$sql = "DROP TABLE tmp_m_b_imported_qot_on_match CASCADE CONSTRAINTS";
		$this->queryDrop($sql);

		/** THIS IS FIXED BY SQL BELOW IT
		$sql = "
			CREATE TABLE tmp_m_b_imported_qot_on_match  AS
			SELECT
			  qot_b.qot_internal_ref_no
		      , qot_b.byb_branch_code
			  , qot_b.spb_branch_code
			  , qot_b.qot_submitted_date
			  , qot_b.qot_ref_no
			  , qot_b.qot_count
			  , qot_b.qot_subject
			  , qot_b.rfq_internal_ref_no
			  , qot_b.QOT_TOTAL_VBP_USD
			  , qot_b.QOT_TOTAL_COST_USD

			  , qot_b.qot_total_cost_discounted
			  , qot_b.qot_total_cost_discounted_usd

			  , rfq_o.rfq_submitted_date original_date
			  , rfq_o.rfq_internal_ref_no rfq_sent_to_match
			  , qot_m.qot_internal_ref_no qot_sent_to_match
			FROM
			  tmp_m_b_qot_match qot_m
			  , qot qot_b
			  , tmp_m_b_rfq_to_match rfq_o
			  , tmp_m_b_rfq_forwarded_by_match rfq_m
			WHERE
			  qot_b.qot_submitted_date > qot_m.qot_submitted_date
			  AND qot_b.qot_ref_no=qot_m.qot_ref_no
			  AND qot_b.spb_branch_code=qot_m.spb_branch_code
			  AND rfq_o.rfq_internal_ref_no=rfq_m.rfq_pom_source
			  AND rfq_m.rfq_internal_ref_no=qot_m.rfq_internal_ref_no
			  AND rfq_o.byb_branch_code=qot_b.byb_branch_code
			  AND qot_b.qot_internal_ref_no IN (SELECT mir_qot_internal_ref_no FROM match_imported_rfq_proc_list@livedb_link)
        	  AND regexp_replace(regexp_replace(NVL(qot_b.qot_subject,'IS_NULL'), '^Ship(s|S)erv Match on behalf of ([^\:])+', ''), '\: ', '')
            	  = regexp_replace(regexp_replace(NVL(qot_m.qot_subject,'IS_NULL'), '^Ship(s|S)erv Match on behalf of ([^\:])+', ''), '\: ', '')
		";
		*/

		$sql = "
			CREATE TABLE tmp_m_b_imported_qot_on_match  AS

			SELECT
		        DISTINCT

			   qot_b.qot_internal_ref_no
		        , qot_b.byb_branch_code
			  , qot_b.spb_branch_code
			  , qot_b.qot_submitted_date
			  , qot_b.qot_ref_no
			  , qot_b.qot_count
			  , qot_b.qot_subject
			  , qot_b.rfq_internal_ref_no
			  , qot_b.QOT_TOTAL_VBP_USD
			  , qot_b.QOT_TOTAL_COST_USD

			  , qot_b.qot_total_cost_discounted
			  , qot_b.qot_total_cost_discounted_usd

			  , qot_m.original_date original_date
			  , qot_m.rfq_sent_to_match rfq_sent_to_match
			  , qot_m.qot_internal_ref_no qot_sent_to_match
			FROM
			  tmp_m_b_qot_match qot_m
			  , qot qot_b
		        , rfq rfq_b2m
		        , rfq rfq_b2import
		        , match_imported_rfq_proc_list@livedb_link imported_qot
			WHERE
			  qot_b.qot_submitted_date > qot_m.qot_submitted_date
			  AND qot_b.qot_ref_no=qot_m.qot_ref_no
			  AND qot_b.spb_branch_code=qot_m.spb_branch_code
			  AND qot_b.qot_internal_ref_no IN (SELECT mir_qot_internal_ref_no FROM match_imported_rfq_proc_list@livedb_link)
       		  AND regexp_replace(regexp_replace(NVL(qot_b.qot_subject,'IS_NULL'), '^Ship(s|S)erv Match on behalf of ([^\:])+', ''), '\: ', '')
        	  = regexp_replace(regexp_replace(NVL(qot_m.qot_subject,'IS_NULL'), '^Ship(s|S)erv Match on behalf of ([^\:])+', ''), '\: ', '')

	        AND rfq_b2m.rfq_internal_ref_no=qot_m.rfq_sent_to_match
	        AND qot_b.qot_internal_ref_no=imported_qot.mir_qot_internal_ref_no
	        AND imported_qot.mir_rfq_internal_ref_no=rfq_b2import.rfq_internal_ref_no

	        -- now compare the rfq sent to match vs rfq that trigger the import
	        AND rfq_b2m.rfq_ref_no=rfq_b2import.rfq_ref_no
	        AND rfq_b2m.byb_branch_code=rfq_b2import.byb_branch_code
	        AND rfq_b2m.rfq_line_count=rfq_b2import.rfq_line_count


		";
		$this->query($sql);

		$sql = "CREATE INDEX IDX_TMP_B_QIMP_1 ON tmp_m_b_imported_qot_on_match (rfq_sent_to_match)";
		$this->query($sql);

		$sql = "DROP TABLE tmp_m_b_order_by_match_spb CASCADE CONSTRAINTS";
		$this->queryDrop($sql);

		/**
    	 * STEP : create table to store order created by match suppliers
		 */

		// this particular one creates order resulted from QOT import
		$sql = "
			CREATE TABLE tmp_m_b_order_by_match_spb  AS
				SELECT
				  o.ord_internal_ref_no
				  , o.ord_original
				  , o.ord_ref_no
				  , o.qot_internal_ref_no
				  , o.rfq_internal_ref_no
				  , o.spb_branch_code
				  , o.byb_branch_code
				  , o.ord_submitted_date
				  , o.ord_imo_no
				  , o.ves_id
				  , o.ord_vessel_name
				  , o.ord_subject
				  , o.ord_delivery_date
				  , o.ord_total_cost
				  , o.ord_currency
				  , o.ord_count
				  , o.ord_line_count
				  , o.ord_acc_count
				  , o.ord_dec_count
				  , o.ord_poc_count
				  , o.ord_resp_date
				  , o.ord_poc_date
				  , o.ord_po_sts
				  , o.ord_alert_last_send_date
				  , o.ord_line_count_real
				  , o.ord_total_cost_discounted
				  , o.ORD_TOTAL_VBP_USD
				  , o.ORD_TOTAL_VBP_DISCOUNTED_USD
				  , o.ORD_TOTAL_COST_USD
				  , o.ORD_TOTAL_COST_DISCOUNTED_USD
				  , qot_b.original_date original_date
				  , qot_b.rfq_sent_to_match
				  , qot_b.qot_internal_ref_no qot_imported
				  , qot_b.qot_sent_to_match qot_sent_to_match
				  , 0 is_orphaned_po
				FROM
				  ord o,
				  tmp_m_b_imported_qot_on_match qot_b
				WHERE
				  o.qot_internal_ref_no=qot_b.qot_internal_ref_no

		";
		$this->query($sql);

		$sql = "CREATE INDEX IDX_TMP_B_OBM_1 ON tmp_m_b_order_by_match_spb (rfq_sent_to_match)";
		$this->query($sql);

		$sql = "CREATE INDEX IDX_TMP_B_OBM_2 ON tmp_m_b_order_by_match_spb(ord_submitted_date)";
		$this->query($sql);

		/**
		 * STEP : create quotes received by buyer selected suppliers
		 */
		$sql = "DROP TABLE tmp_m_b_replacement_qot_1 CASCADE CONSTRAINTS";
		$this->queryDrop($sql);

		$sql = "
			CREATE TABLE tmp_m_b_replacement_qot_1 AS
			    SELECT * FROM
			    (
			      SELECT
			        qot_internal_ref_no,
			        qot_count,
			        qot_original,
			        row_number() OVER (PARTITION BY qot_original ORDER BY qot_internal_ref_no DESC NULLS LAST) as qot_row_num
			      FROM
					qot qot_b
			  		, tmp_m_b_rfq_also_sent_to_buyer rfq_b
			      WHERE
			        qot_b.rfq_internal_ref_no=rfq_b.rfq_internal_ref_no
			        AND qot_count=0
			      ORDER BY
			        qot_original
			    )
			    WHERE qot_row_num=1
				";
		$this->query($sql);

		$sql = "DROP TABLE tmp_m_b_qot_from_byb_spb CASCADE CONSTRAINTS";
		$this->queryDrop($sql);

		/**
 		 * getting the order sent to buyer selected suppliers
		 */
		$sql = "
			CREATE TABLE tmp_m_b_qot_from_byb_spb  AS
				SELECT
				  DISTINCT
				  qot_b.qot_internal_ref_no
			      , qot_b.byb_branch_code
				  , qot_b.spb_branch_code
				  , qot_b.qot_submitted_date
				  , qot_b.qot_ref_no
				  , qot_b.qot_count
				  , qot_b.rfq_internal_ref_no
				  , qot_b.qot_total_cost
				  , qot_b.qot_total_line_item_cost
				  , qot_b.qot_currency

			  	  , qot_b.QOT_TOTAL_VBP_USD
			  	  , qot_b.QOT_TOTAL_COST_USD

				  , qot_b.qot_total_cost_discounted
			  	  , qot_b.qot_total_cost_discounted_usd

				  , rfq_b.rfq_submitted_date original_date
				  , rfq_b.rfq_sent_to_match rfq_sent_to_match

				  , rfq_b.rfq_line_count
				  , qot_b.qot_line_count


				FROM
				  qot qot_b
				  , tmp_m_b_rfq_also_sent_to_buyer rfq_b
				WHERE
				  qot_b.rfq_internal_ref_no=rfq_b.rfq_internal_ref_no
				  AND qot_b.qot_count=1
				  AND qot_b.qot_internal_ref_no NOT IN (SELECT qot_original FROM tmp_m_b_replacement_qot_1 WHERE qot_original IS NOT null)
		          AND qot_b.qot_internal_ref_no NOT IN (SELECT qot_internal_ref_no FROM tmp_m_b_imported_qot_on_match)
		          AND qot_b.qot_internal_ref_no NOT IN (SELECT qot_internal_ref_no FROM tmp_m_b_qot_match)
		";
		$this->query($sql);

		$sql = "
			INSERT INTO tmp_m_b_qot_from_byb_spb
				SELECT
				  DISTINCT
				  qot_b.qot_internal_ref_no
			      , qot_b.byb_branch_code
				  , qot_b.spb_branch_code
				  , qot_b.qot_submitted_date
				  , qot_b.qot_ref_no
				  , qot_b.qot_count
				  , qot_b.rfq_internal_ref_no
				  , qot_b.qot_total_cost
				  , qot_b.qot_total_line_item_cost
				  , qot_b.qot_currency


			  	  , qot_b.QOT_TOTAL_VBP_USD
			  	  , qot_b.QOT_TOTAL_COST_USD

				  , qot_b.qot_total_cost_discounted
			  	  , qot_b.qot_total_cost_discounted_usd

				  , rfq_b.rfq_submitted_date original_date
				  , rfq_b.rfq_sent_to_match rfq_sent_to_match

				  , rfq_b.rfq_line_count
				  , qot_b.qot_line_count

				FROM
				  qot qot_b
				  , tmp_m_b_rfq_also_sent_to_buyer rfq_b
				WHERE
				  qot_b.rfq_internal_ref_no=rfq_b.rfq_internal_ref_no
				  AND qot_b.qot_count=0
				  AND qot_b.qot_internal_ref_no IN (SELECT qot_internal_ref_no FROM tmp_m_b_replacement_qot_1 WHERE qot_original IS NOT null)
				  AND qot_b.qot_internal_ref_no NOT IN (SELECT qot_internal_ref_no FROM tmp_m_b_imported_qot_on_match)
		          AND qot_b.qot_internal_ref_no NOT IN (SELECT qot_internal_ref_no FROM tmp_m_b_qot_match)
				  AND NOT EXISTS (
					SELECT 1 FROM tmp_m_b_qot_from_byb_spb qqq
					WHERE qqq.qot_internal_ref_no=qot_b.qot_internal_ref_no
				  )
			";
		$this->query($sql);

		$sql = "CREATE INDEX IDX_TMP_B_QFBS_1 ON tmp_m_b_qot_from_byb_spb (rfq_sent_to_match)";
		$this->query($sql);


		$sql = "DROP TABLE tmp_m_b_ord_from_byb_s_spb CASCADE CONSTRAINTS";
		$this->queryDrop($sql);

		// flagging RFQ sent by buyer if RFQ's been declined
		$sql = "
			UPDATE tmp_m_b_rfq_also_sent_to_buyer r
			SET r.is_declined=1
			WHERE
				EXISTS (
					SELECT 1 FROM tmp_m_b_qot_from_byb_spb q
					WHERE qot_total_cost_usd=0
						AND r.rfq_internal_ref_no=q.rfq_internal_ref_no
						AND r.spb_branch_code=q.spb_branch_code

				)
		";
		$this->query($sql);

		$sql = "
			UPDATE tmp_m_b_rfq_also_sent_to_buyer r
			SET r.is_declined=2
			WHERE
				EXISTS (

					SELECT 1 FROM rfq_resp r2
					WHERE
						r2.rfq_internal_ref_no=r.rfq_internal_ref_no
						AND r2.spb_branch_code=r.spb_branch_code
						AND r2.rfq_resp_sts='DEC'
				)
		";
		$this->query($sql);

		/**
		 * Storing buyer selected PO from match RFQ event
		 */
		$sql = "
			CREATE TABLE tmp_m_b_ord_from_byb_s_spb  AS
				SELECT
				  DISTINCT
				  o.ord_internal_ref_no
				  , o.ord_original
				  , o.ord_ref_no
				  , o.qot_internal_ref_no
				  , o.rfq_internal_ref_no
				  , o.spb_branch_code
				  , o.byb_branch_code
				  , o.ord_submitted_date
				  , o.ord_imo_no
				  , o.ves_id
				  , o.ord_vessel_name
				  , o.ord_subject
				  , o.ord_delivery_date
				  , o.ord_total_cost
				  , o.ord_currency
				  , o.ord_count
				  , o.ord_line_count
				  , o.ord_acc_count
				  , o.ord_dec_count
				  , o.ord_poc_count
				  , o.ord_resp_date
				  , o.ord_poc_date
				  , o.ord_po_sts
				  , o.ord_alert_last_send_date
				  , o.ord_line_count_real
				  , o.ord_total_cost_discounted

				  , o.ORD_TOTAL_VBP_USD
				  , o.ORD_TOTAL_VBP_DISCOUNTED_USD
				  , o.ORD_TOTAL_COST_USD
				  , o.ORD_TOTAL_COST_DISCOUNTED_USD

				  , qot_b.original_date original_date
				  , qot_b.rfq_sent_to_match rfq_sent_to_match

				  , 0 is_orphaned_po
				FROM
				  ord o,
				  tmp_m_b_qot_from_byb_spb qot_b
				WHERE
				  o.qot_internal_ref_no=qot_b.qot_internal_ref_no


		";
		$this->query($sql);

		// orphaned PO
		$sql = "
			INSERT INTO tmp_m_b_ord_from_byb_s_spb

			SELECT

			  DISTINCT

			  o.ord_internal_ref_no
			  , o.ord_original
			  , o.ord_ref_no
			  , o.qot_internal_ref_no
			  , o.rfq_internal_ref_no
			  , o.spb_branch_code
			  , o.byb_branch_code
			  , o.ord_submitted_date
			  , o.ord_imo_no
			  , o.ves_id
			  , o.ord_vessel_name
			  , o.ord_subject
			  , o.ord_delivery_date
			  , o.ord_total_cost
			  , o.ord_currency
			  , o.ord_count
			  , o.ord_line_count
			  , o.ord_acc_count
			  , o.ord_dec_count
			  , o.ord_poc_count
			  , o.ord_resp_date
			  , o.ord_poc_date
			  , o.ord_po_sts
			  , o.ord_alert_last_send_date
			  , o.ord_line_count_real
			  , o.ord_total_cost_discounted

			  , o.ORD_TOTAL_VBP_USD
			  , o.ORD_TOTAL_VBP_DISCOUNTED_USD
			  , o.ORD_TOTAL_COST_USD
			  , o.ORD_TOTAL_COST_DISCOUNTED_USD

			  , q.qot_submitted_date original_date
			  , r.rfq_sent_to_match

			  , 1
			FROM
			  linkable_orphaned_po
			  , tmp_m_b_rfq_also_sent_to_buyer r
			  , ord o
			  , qot q
			WHERE
			  r.rfq_sent_to_match=lop_rfq_internal_ref_no
			  AND o.ord_internal_ref_no=lop_ord_internal_ref_no
			  AND o.qot_internal_ref_no=q.qot_internal_ref_no (+)
			  AND NOT EXISTS (
			    SELECT 1
			    FROM tmp_m_b_ord_from_byb_s_spb ooo
			    WHERE ooo.ord_internal_ref_no=o.ord_internal_ref_no
			  )
		      AND EXISTS (
		      	SELECT 	1 FROM tmp_m_b_rfq_also_sent_to_buyer r2
		        WHERE 	r2.rfq_internal_ref_no=r.rfq_internal_ref_no
		        		AND r2.spb_branch_code=o.spb_branch_code
		      )
			  AND o.ord_submitted_date > r.rfq_submitted_date
		";
		$this->query($sql);

		$sql = "CREATE INDEX IDX_TMP_B_OFBS_1 ON tmp_m_b_ord_from_byb_s_spb (rfq_sent_to_match)";
		$this->query($sql);

		// including ORPHANED ORDERS
		$sql = "
			INSERT INTO tmp_m_b_ord_from_byb_s_spb
			SELECT
			  DISTINCT
			  opo.ord_internal_ref_no
			  , opo.ord_original
			  , opo.ord_ref_no
			  , q2m.qot_internal_ref_no
			  , rf.rfq_internal_ref_no
			  , rf.spb_branch_code
			  , opo.byb_branch_code
			  , opo.ord_submitted_date
			  , opo.ord_imo_no
			  , opo.ves_id
			  , opo.ord_vessel_name
			  , opo.ord_subject
			  , opo.ord_delivery_date
			  , opo.ord_total_cost
			  , opo.ord_currency
			  , opo.ord_count
			  , opo.ord_line_count
			  , opo.ord_acc_count
			  , opo.ord_dec_count
			  , opo.ord_poc_count
			  , opo.ord_resp_date
			  , opo.ord_poc_date
			  , opo.ord_po_sts
			  , opo.ord_alert_last_send_date
			  , opo.ord_line_count_real
			  , opo.ord_total_cost_discounted

			  , opo.ORD_TOTAL_VBP_USD
			  , opo.ORD_TOTAL_VBP_DISCOUNTED_USD
			  , opo.ORD_TOTAL_COST_USD
			  , opo.ORD_TOTAL_COST_DISCOUNTED_USD

			  , rf.rfq_submitted_date
			  , rf.rfq_sent_to_match

			  , 0

			FROM
			  tmp_m_b_rfq_also_sent_to_buyer rf
			  , tmp_m_b_qot_from_byb_spb q2m
			  , (
			    SELECT
			      DISTINCT o_ord.*
			    FROM
			      tmp_m_b_rfq_also_sent_to_buyer r2m
			      , ord o_ord
			    WHERE
			      o_ord.qot_internal_ref_no IS null
			      AND o_ord.ord_ref_no=r2m.rfq_ref_no
			      AND regexp_replace(regexp_replace(TRIM(NVL(r2m.rfq_subject,'IS_NULL')), '^Ship(S|s)erv Match on behalf of ([^\:])+', ''), '\: ', '')
			          = regexp_replace(regexp_replace(TRIM(NVL(o_ord.ord_subject,'IS_NULL')), '^Ship(S|s)erv Match on behalf of ([^\:])+', ''), '\: ', '')
			      AND NOT EXISTS(SELECT 1 FROM tmp_m_b_order_by_match_spb x WHERE o_ord.ord_internal_ref_no=x.ord_internal_ref_no )
			      AND NOT EXISTS(SELECT 1 FROM tmp_m_b_ord_from_byb_s_spb x WHERE o_ord.ord_internal_ref_no=x.ord_internal_ref_no )
			      --AND r2m.byb_branch_code=:buyerId
			  ) opo
			WHERE
			  rf.rfq_ref_no=opo.ord_ref_no
			  AND opo.spb_branch_code=rf.spb_branch_code
			  AND regexp_replace(regexp_replace(TRIM(NVL(opo.ord_subject,'IS_NULL')), '^Ship(S|s)erv Match on behalf of ([^\:])+', ''), '\: ', '')
			      = regexp_replace(regexp_replace(TRIM(NVL(rf.rfq_subject,'IS_NULL')), '^Ship(S|s)erv Match on behalf of ([^\:])+', ''), '\: ', '')
			  AND q2m.rfq_internal_ref_no=rf.rfq_internal_ref_no
			  AND NOT EXISTS (
				SELECT 1 FROM tmp_m_b_ord_from_byb_s_spb ooo
				WHERE opo.ord_internal_ref_no=ooo.ord_internal_ref_no
			  )
		";
		$this->query($sql);

		$sql = "
			INSERT INTO tmp_m_b_order_by_match_spb
			SELECT
			  DISTINCT
			  opo.ord_internal_ref_no
			  , opo.ord_original
			  , opo.ord_ref_no
			  , q2m.qot_internal_ref_no
			  , rf.rfq_internal_ref_no
			  , opo.spb_branch_code
			  , opo.byb_branch_code
			  , opo.ord_submitted_date
			  , opo.ord_imo_no
			  , opo.ves_id
			  , opo.ord_vessel_name
			  , opo.ord_subject
			  , opo.ord_delivery_date
			  , opo.ord_total_cost
			  , opo.ord_currency
			  , opo.ord_count
			  , opo.ord_line_count
			  , opo.ord_acc_count
			  , opo.ord_dec_count
			  , opo.ord_poc_count
			  , opo.ord_resp_date
			  , opo.ord_poc_date
			  , opo.ord_po_sts
			  , opo.ord_alert_last_send_date
			  , opo.ord_line_count_real
			  , opo.ord_total_cost_discounted

			  , opo.ORD_TOTAL_VBP_USD
			  , opo.ORD_TOTAL_VBP_DISCOUNTED_USD
			  , opo.ORD_TOTAL_COST_USD
			  , opo.ORD_TOTAL_COST_DISCOUNTED_USD

			  , rf.original_date
			  , rf.rfq_sent_to_match
			  , q2m.qot_internal_ref_no
			  , q2m.qot_internal_ref_no

			  , 0 is_orphaned_po

			FROM
			  tmp_m_b_rfq_forwarded_by_match rf
			  , tmp_m_b_qot_match q2m
			  , (
			    SELECT
			      o_ord.*
			    FROM
			      tmp_m_b_rfq_to_match r2m
			      , ord o_ord
			    WHERE
			      o_ord.qot_internal_ref_no IS null
			      AND o_ord.ord_ref_no=r2m.rfq_ref_no
			      AND regexp_replace(regexp_replace(TRIM(NVL(r2m.rfq_subject,'IS_NULL')), '^Ship(S|s)erv Match on behalf of ([^\:])+', ''), '\: ', '')
			          = regexp_replace(regexp_replace(TRIM(NVL(o_ord.ord_subject,'IS_NULL')), '^Ship(S|s)erv Match on behalf of ([^\:])+', ''), '\: ', '')
			      AND NOT EXISTS(SELECT 1 FROM tmp_m_b_order_by_match_spb x WHERE o_ord.ord_internal_ref_no=x.ord_internal_ref_no )
			      AND NOT EXISTS(SELECT 1 FROM tmp_m_b_ord_from_byb_s_spb x WHERE o_ord.ord_internal_ref_no=x.ord_internal_ref_no )
			      --AND r2m.byb_branch_code=:buyerId
			  ) opo
			WHERE
			  rf.rfq_ref_no=opo.ord_ref_no
			  AND opo.spb_branch_code=rf.spb_branch_code
			  AND regexp_replace(regexp_replace(TRIM(NVL(opo.ord_subject,'IS_NULL')), '^Ship(S|s)erv Match on behalf of ([^\:])+', ''), '\: ', '')
			      = regexp_replace(regexp_replace(TRIM(NVL(rf.rfq_subject,'IS_NULL')), '^Ship(S|s)erv Match on behalf of ([^\:])+', ''), '\: ', '')
			  AND q2m.rfq_internal_ref_no=rf.rfq_internal_ref_no
			  AND NOT EXISTS (
				SELECT 1 FROM tmp_m_b_order_by_match_spb ooo
				WHERE opo.ord_internal_ref_no=ooo.ord_internal_ref_no
			  )
		";
		$this->query($sql);

		/**
		 * using result from orphaned PO algorithm to pull order done by match selected suppliers
		 */
		$sql = "
			INSERT INTO tmp_m_b_order_by_match_spb
			SELECT
			  DISTINCT
				opo.ord_internal_ref_no
			  , opo.ord_original
			  , opo.ord_ref_no
			  , null --q2m.qot_internal_ref_no
			  , null --rf.rfq_internal_ref_no
			  , opo.spb_branch_code
			  , opo.byb_branch_code
			  , opo.ord_submitted_date
			  , opo.ord_imo_no
			  , opo.ves_id
			  , opo.ord_vessel_name
			  , opo.ord_subject
			  , opo.ord_delivery_date
			  , opo.ord_total_cost
			  , opo.ord_currency
			  , opo.ord_count
			  , opo.ord_line_count
			  , opo.ord_acc_count
			  , opo.ord_dec_count
			  , opo.ord_poc_count
			  , opo.ord_resp_date
			  , opo.ord_poc_date
			  , opo.ord_po_sts
			  , opo.ord_alert_last_send_date
			  , opo.ord_line_count_real
			  , opo.ord_total_cost_discounted

			  , opo.ORD_TOTAL_VBP_USD
			  , opo.ORD_TOTAL_VBP_DISCOUNTED_USD
			  , opo.ORD_TOTAL_COST_USD
			  , opo.ORD_TOTAL_COST_DISCOUNTED_USD

			  , opo.rfq_submitted_date
			  , opo.rfq_sent_to_match
			  , null --q2m.qot_internal_ref_no -- this is empty because this is orphaned PO
			  , null --q2m.qot_internal_ref_no -- this is empty because this is orphaned PO

			  , 1 -- is_orphaned_po
			FROM
			  (
			    SELECT
			      o.*
				  , r.rfq_submitted_date
				  , r.rfq_internal_ref_no rfq_sent_to_match
			    FROM
			      tmp_m_b_rfq_to_match r2m
			      , linkable_orphaned_po o_ord
            	  , ord o
				  , rfq r
			    WHERE
            		r2m.rfq_internal_ref_no=o_ord.lop_rfq_internal_ref_no
					AND r.rfq_internal_ref_no=o_ord.lop_rfq_internal_ref_no
            		AND o.ord_internal_ref_no=lop_ord_internal_Ref_no
			      	AND NOT EXISTS(SELECT 1 FROM tmp_m_b_order_by_match_spb x WHERE o_ord.lop_ord_internal_ref_no=x.ord_internal_ref_no )
			      	AND NOT EXISTS(SELECT 1 FROM tmp_m_b_ord_from_byb_s_spb x WHERE o_ord.lop_ord_internal_ref_no=x.ord_internal_ref_no )
					AND o.spb_branch_code IN (SELECT spb_branch_code FROM tmp_m_b_rfq_forwarded_by_match WHERE rfq_sent_to_match=r2m.rfq_internal_ref_no)
--					AND o.spb_branch_code NOT IN (SELECT spb_branch_code FROM tmp_m_b_rfq_also_sent_to_buyer WHERE rfq_sent_to_match=r2m.rfq_internal_ref_no)
				    AND o.ord_submitted_date > r2m.rfq_submitted_date

			  ) opo
		";
		$this->query($sql);

		/**
		 * once all tables are built, then calculate the following:
		 * - potential savings
		 * - realised savings
		 * - cheapest qot
		 */
		$sql = "
			UPDATE
			  tmp_m_b_rfq_to_match r
			SET
			  potential_saving					= get_potential_saving_by_rfq_v4(r.rfq_internal_ref_no, 1)
			  , cheapest_qot_spb_by_byb			= get_cheapest_qotid_v4(r.rfq_internal_ref_no, r.rfq_event_hash, 'buyer', 1)
			  , cheapest_qot_spb_by_match		= get_cheapest_qotid_v4(r.rfq_internal_ref_no, r.rfq_event_hash, 'match', 1)
			  , cheapest_qot_spb_by_byb_100		= get_cheapest_qotid_v4(r.rfq_internal_ref_no, r.rfq_event_hash, 'buyer-100-quoted', 1)
			  , cheapest_qot_spb_by_match_100	= get_cheapest_qotid_v4(r.rfq_internal_ref_no, r.rfq_event_hash, 'match-100-quoted', 1)
		";
		$this->query($sql);

		$sql = "
			UPDATE
			  tmp_m_b_rfq_to_match r
			SET
			  realised_saving					= get_actual_saving_by_rfq_v2(r.rfq_internal_ref_no)
			WHERE
			  potential_saving > 0
		";
		$this->query($sql);

		$sql = "
			UPDATE
			  tmp_m_b_rfq_to_match mr
			SET
			  has_order=1
			WHERE
			  rfq_internal_ref_no IN  (
			    SELECT DISTINCT rfq_sent_to_match
			    FROM tmp_m_b_order_by_match_spb
			  )

		";
		$this->query($sql);

		$sql = "
			UPDATE
			  tmp_m_b_rfq_to_match mr
			SET
			  has_quote=1
			WHERE
			  EXISTS (
			    SELECT 1
			    FROM tmp_m_b_qot_match q
			    WHERE
			    mr.rfq_internal_ref_no = q.rfq_sent_to_match
			  )
		";
		$this->query($sql);

		$sql = "
		MERGE INTO tmp_m_b_qot_match tbl1 USING
		(

		  -- --------------------------------------------------------------------------
		  -- --------------------------------------------------------------------------
		  -- START 'WITH' STATEMENT
		  -- --------------------------------------------------------------------------
		  -- --------------------------------------------------------------------------
		  -- --------------------------------------------------------------------------
		  -- getting buyer selected suppliers' quotes
		  -- --------------------------------------------------------------------------
		  WITH
		  byb_s_qot AS
		  (
		    SELECT
		      r2m.rfq_event_hash
		      , (
		          CASE
		            WHEN 
		            	qm.rfq_line_count=0 AND qm.qot_line_count>0 
		            	OR (qm.rfq_line_count<=qm.qot_line_count) 
		            THEN
		              100
		            WHEN qm.rfq_line_count>0 AND qm.qot_line_count>0 THEN
		              qm.qot_line_count/qm.rfq_line_count*100
		          END
		      ) completeness
		      , qot_total_cost_usd
		    FROM
		      tmp_m_b_rfq_to_match r2m JOIN tmp_m_b_qot_from_byb_spb qm
		        ON (r2m.rfq_internal_ref_no=qm.rfq_sent_to_match)
		      WHERE
		      	qot_total_cost_usd > 0

		  )
		  ,
		  -- --------------------------------------------------------------------------
		  -- --------------------------------------------------------------------------
		  -- and find the most complete and cheapest
		  -- --------------------------------------------------------------------------
		  byb_s_qot_agg AS
		  (
			  SELECT
	            rfq_event_hash
	            , completeness
	            , qot_total_cost_usd
	          FROM
	          (
	            SELECT
	              rfq_event_hash,
	              completeness,
	              qot_total_cost_usd,
	              ROW_NUMBER()
	              OVER (PARTITION BY rfq_event_hash ORDER BY completeness DESC, qot_total_cost_usd ASC) rn
	            FROM
	              byb_s_qot
	          )
	          WHERE
	            rn=1
		  )
		  ,
		  -- --------------------------------------------------------------------------
		  -- --------------------------------------------------------------------------
		  -- the outcome/output of all competitive buyer selected suppliers' quote
		  -- --------------------------------------------------------------------------
		  buyer_selected_qot AS
		  (
		    SELECT
		      b.*
		    FROM
		      byb_s_qot_agg a JOIN byb_s_qot b ON (
		        a.rfq_event_hash=b.rfq_event_hash
		        AND a.completeness=b.completeness
		        AND a.qot_total_cost_usd=b.qot_total_cost_usd
		      )
		  )

		  -- --------------------------------------------------------------------------
		  -- --------------------------------------------------------------------------
		  -- then get all match suppliers' quotes
		  -- --------------------------------------------------------------------------
		  ,
		  mch_qot AS
		  (
		    SELECT
		      r2m.rfq_event_hash
		      , qm.qot_internal_ref_no
		      , qm.spb_branch_code
		      , qm.rfq_line_count
		      , qm.qot_line_count
		      , (
		          CASE
		            WHEN qm.rfq_line_count=0 AND qm.qot_line_count>0 
		            	OR (qm.rfq_line_count<=qm.qot_line_count) 
                	THEN
		              100
		            WHEN qm.rfq_line_count>0 AND qm.qot_line_count>0 THEN
		              qm.qot_line_count/qm.rfq_line_count*100
		          END
		      ) completeness
		      , qot_total_cost_usd
		    FROM
		      tmp_m_b_rfq_to_match r2m JOIN tmp_m_b_qot_match qm
		        ON (r2m.rfq_internal_ref_no=qm.rfq_sent_to_match)
			WHERE
          		qot_total_cost_usd > 0
		  )
		  ,

		  -- --------------------------------------------------------------------------
		  -- --------------------------------------------------------------------------
		  -- for all quotes that is >= the buyer selected quote's completeness
		  -- we should calculate the potential savings
		  -- --------------------------------------------------------------------------
		  mch_qot_ps AS
		  (
		    SELECT
		      a.rfq_event_hash
		      , a.qot_internal_ref_no
		      , a.spb_branch_code
		      , (
		          CASE
		            WHEN a.completeness >= b.completeness AND a.qot_total_cost_usd < b.qot_total_cost_usd AND a.qot_total_cost_usd>0 AND b.qot_total_cost_usd>0 THEN
		              b.qot_total_cost_usd-a.qot_total_cost_usd
		          END
		      ) potential_saving
		    FROM
		      mch_qot a JOIN buyer_selected_qot b
		        ON (a.rfq_event_hash=b.rfq_event_hash)
		    WHERE
		      a.completeness>0
		      AND a.qot_total_cost_usd > 0
		  )
		  SELECT DISTINCT qot_internal_ref_no, spb_branch_code, potential_saving FROM mch_qot_ps WHERE potential_saving > 0

		  -- --------------------------------------------------------------------------
		  -- --------------------------------------------------------------------------
		  -- END 'WITH' STATEMENT
		  -- --------------------------------------------------------------------------


		) tbl2

		ON (
		  tbl1.qot_internal_ref_no=tbl2.qot_internal_ref_no
		  AND tbl1.spb_branch_code=tbl2.spb_branch_code

		)
		WHEN MATCHED THEN
		  UPDATE SET tbl1.potential_saving=tbl2.potential_saving

		";
		$this->query($sql);

		//The following block will add indexex, swap temp tables, and fix index names after swapping, so the sript can execute once again
		$sql = "CREATE INDEX IDX_TMP_B_M1_1 ON tmp_m_b_rfq_to_match_buyer (byb_branch_code, rfq_submitted_date)";
		$this->query($sql);
		$sql = "CREATE INDEX IDX_TMP_B_M3_1 ON tmp_m_b_rfq_forwarded_by_match (orig_byb_branch_code, original_date)";
		$this->query($sql);
		$sql = "CREATE INDEX IDX_TMP_B_M4_1 ON tmp_m_b_qot_match (orig_byb_branch_code, original_date)";
		$this->query($sql);
		$sql = "CREATE INDEX IDX_TMP_B_M5_1 ON tmp_m_b_order_by_match_spb (byb_branch_code, original_date)";
		$this->query($sql);
		$sql = "CREATE INDEX IDX_TMP_B_MOM_1 ON tmp_m_b_order_by_match_spb (byb_branch_code, rfq_sent_to_match, ORD_SUBMITTED_DATE)";
		$this->query($sql);
		$sql = "CREATE INDEX IDX_TMP_B_MFXB_1 ON tmp_m_b_rfq_also_sent_to_buyer (byb_branch_code, rfq_submitted_date, rfq_ref_no)";
		$this->query($sql);

		$sql = "CREATE INDEX IDX_TMP_B_RFBM_1 ON tmp_m_b_rfq_forwarded_by_match (orig_byb_branch_code, original_date, rfq_ref_no)";
		$this->query($sql);

		//Drop old tables
		$sql = "DROP TABLE match_b_replacement_rfq_0 CASCADE CONSTRAINTS";
		$this->queryDrop($sql);

		$sql = "DROP TABLE match_b_rfq_to_match CASCADE CONSTRAINTS";
		$this->queryDrop($sql);

		$sql = "DROP TABLE match_b_rfq_forwarded_by_match CASCADE CONSTRAINTS";
		$this->queryDrop($sql);

		$sql = "DROP TABLE match_b_rfq_also_sent_to_buyer CASCADE CONSTRAINTS";
		$this->queryDrop($sql);

		$sql = "DROP TABLE match_b_rfq_to_match_buyer CASCADE CONSTRAINTS";
		$this->queryDrop($sql);

		$sql = "DROP TABLE match_b_replacement_qot_0 CASCADE CONSTRAINTS";
		$this->queryDrop($sql);

		$sql = "DROP TABLE match_b_qot_match CASCADE CONSTRAINTS";
		$this->queryDrop($sql);

		$sql = "DROP TABLE match_b_imported_qot_on_match CASCADE CONSTRAINTS";
		$this->queryDrop($sql);

		$sql = "DROP TABLE match_b_order_by_match_spb CASCADE CONSTRAINTS";
		$this->queryDrop($sql);

		$sql = "DROP TABLE match_b_replacement_qot_1 CASCADE CONSTRAINTS";
		$this->queryDrop($sql);

		$sql = "DROP TABLE match_b_qot_from_byb_spb CASCADE CONSTRAINTS";
		$this->queryDrop($sql);

		$sql = "DROP TABLE match_b_ord_from_byb_s_spb CASCADE CONSTRAINTS";
		$this->queryDrop($sql);

		//Rename tables to be final
		$sql = "RENAME tmp_m_b_replacement_rfq_0 TO match_b_replacement_rfq_0";
		$this->queryRename($sql);

		$sql = "RENAME tmp_m_b_rfq_to_match TO match_b_rfq_to_match";
		$this->queryRename($sql);

		$sql = "RENAME tmp_m_b_rfq_forwarded_by_match TO match_b_rfq_forwarded_by_match";
		$this->queryRename($sql);

		$sql = "RENAME tmp_m_b_rfq_also_sent_to_buyer TO match_b_rfq_also_sent_to_buyer";
		$this->queryRename($sql);

		$sql = "RENAME tmp_m_b_rfq_to_match_buyer TO match_b_rfq_to_match_buyer";
		$this->queryRename($sql);

		$sql = "RENAME tmp_m_b_replacement_qot_0 TO match_b_replacement_qot_0";
		$this->queryRename($sql);

		$sql = "RENAME tmp_m_b_qot_match TO match_b_qot_match";
		$this->queryRename($sql);

		$sql = "RENAME tmp_m_b_imported_qot_on_match TO match_b_imported_qot_on_match";
		$this->queryRename($sql);

		$sql = "RENAME tmp_m_b_order_by_match_spb TO match_b_order_by_match_spb";
		$this->queryRename($sql);

		$sql = "RENAME tmp_m_b_replacement_qot_1 TO match_b_replacement_qot_1";
		$this->queryRename($sql);

		$sql = "RENAME tmp_m_b_qot_from_byb_spb TO match_b_qot_from_byb_spb";
		$this->queryRename($sql);

		$sql = "RENAME tmp_m_b_ord_from_byb_s_spb TO match_b_ord_from_byb_s_spb";
		$this->queryRename($sql);

		//We also must rename indexes

		$sql = "ALTER INDEX IDX_TMP_B_M2_1 RENAME TO IDX_B_M2_1";
		$this->queryIndex($sql);

		$sql = "ALTER INDEX IDX_TMP_B_M2_2 RENAME TO IDX_B_M2_2";
		$this->queryIndex($sql);

		$sql = "ALTER INDEX IDX_TMP_B_M2_3 RENAME TO IDX_B_M2_3";
		$this->queryIndex($sql);

		$sql = "ALTER INDEX IDX_TMP_B_RFWD_1 RENAME TO IDX_B_RFWD_1";
		$this->queryIndex($sql);

		$sql = "ALTER INDEX IDX_TMP_B_RFWD_2  RENAME TO IDX_B_RFWD_2";
		$this->queryIndex($sql);

		$sql = "ALTER INDEX IDX_TMP_B_RFWD_3 RENAME TO IDX_B_RFWD_3";
		$this->queryIndex($sql);

		$sql = "ALTER INDEX IDX_TMP_B_R2BYSS_1 RENAME TO IDX_B_R2BYSS_1";
		$this->queryIndex($sql);

        $sql = "ALTER INDEX IDX_TMP_B_R2BYSS_2 RENAME TO IDX_B_R2BYSS_2";
        $this->queryIndex($sql);

		$sql = "ALTER INDEX IDX_TMP_B_R2MB_1 RENAME TO IDX_B_R2MB_1";
		$this->queryIndex($sql);

		$sql = "ALTER INDEX IDX_TMP_B_Q2M_1 RENAME TO IDX_B_Q2M_1";
		$this->queryIndex($sql);

        $sql = "ALTER INDEX IDX_TMP_B_Q2M_2 RENAME TO IDX_B_Q2M_2";
        $this->queryIndex($sql);

        $sql = "ALTER INDEX IDX_TMP_B_Q2M_3 RENAME TO IDX_B_Q2M_3";
        $this->queryIndex($sql);

		$sql = "ALTER INDEX IDX_TMP_B_QIMP_1 RENAME TO IDX_B_QIMP_1";
		$this->queryIndex($sql);

		$sql = "ALTER INDEX IDX_TMP_B_OBM_1 RENAME TO IDX_B_OBM_1";
		$this->queryIndex($sql);

		$sql = "ALTER INDEX IDX_TMP_B_OBM_2 RENAME TO IDX_B_OBM_2";
		$this->queryIndex($sql);

		$sql = "ALTER INDEX IDX_TMP_B_QFBS_1 RENAME TO IDX_B_QFBS_1";
		$this->queryIndex($sql);


		//New indexes are also hast to be renamed, as original tables are dropped now, and we cah use the index names
		$sql = "ALTER INDEX IDX_TMP_B_M1_1 RENAME TO IDX_B_M1_1";
		$this->queryIndex($sql);

		$sql = "ALTER INDEX IDX_TMP_B_M3_1 RENAME TO IDX_B_M3_1";
		$this->queryIndex($sql);

		$sql = "ALTER INDEX IDX_TMP_B_M4_1 RENAME TO IDX_B_M4_1";
		$this->queryIndex($sql);

		$sql = "ALTER INDEX IDX_TMP_B_M5_1 RENAME TO IDX_B_M5_1";
		$this->queryIndex($sql);

		$sql = "ALTER INDEX IDX_TMP_B_MOM_1 RENAME TO IDX_B_MOM_1";
		$this->queryIndex($sql);

		$sql = "ALTER INDEX IDX_TMP_B_MFXB_1 RENAME TO IDX_B_MFXB_1";
		$this->queryIndex($sql);

		$sql = "ALTER INDEX IDX_TMP_B_RFBM_1 RENAME TO IDX_B_RFBM_1";
		$this->queryIndex($sql);

		$this->log("done..");

	}

	/**
	* Run any other Query and log result
	* @param string $sql the SQL to run
    * @param string $logMessage The message to log, If null will automatically generate it according to the SQL
	* @return unknown
	*/
	public function query($sql, $logMessage = null)
	{
		if ($logMessage) {
			$this->log($logMessage);
		} else {
			++$this->tableNo;
			$totalTable = 55;
			$this->log("rebuilding table number: " . $this->tableNo . " of " . $totalTable . " (" . round(($this->tableNo/$totalTable) * 100) . "%)");
		}
		
		$this->log("currently executing: " . substr(trim($sql), 0, 50));

		try {
		    $startExectution = microtime(true);
		    $this->reporting->query($sql);
            $exectuionTime = microtime(true) - $startExectution;

            $this->log(" - sql excetuded in " . $exectuionTime . " msec");
			$this->reporting->commit();
            $this->log(" - sql committed");
		} catch (Exception $e) {
			$this->log("-- exception thrown: " .  $e->getMessage());
		}
	}

	/**
	* Run Drop other Query and log result
	* @param string $sql the SQL to run
	* @return unknown
	*/
	public function queryDrop($sql)
	{
		$this->query($sql, "Dropping table... ");
	}

	/**
	* Run Indexing Query and log result
	* @param string $sql the SQL to run
	* @return unknown
	*/
	public function queryIndex($sql)
	{
		$this->query($sql, "Renaming index ...");
	}

	/**
	* Run Rename Query and log result
	* @param string $sql the SQL to run
	* @return unknown
	*/
	public function queryRename($sql)
	{
		$this->query($sql, "Renaming table ...");
	}

	/**
	* Log cron propcess
	* @param string $message The message to log
	* @return unknown
	*/
	protected function log($message)
	{
		if ($this->logToConsole === true) {
			echo $message."\n";
		}
		$this->logger->log($message);
	}
	
}
