<?php
class Shipserv_Match_Report_Buyer extends Shipserv_Object
{
	protected $buyerId = null;
	protected $startRow = null;
	protected $limit = null ;

	public $endDate = null;
	public $startDate = null;

	function __construct($buyerId)
	{
		$this->buyerId = $buyerId;
		$this->db = Shipserv_Helper_Database::getDb();
		$this->reporting = Shipserv_Helper_Database::getSsreport2Db();
	}

	public function getBuyerName()
	{
		$sql = "SELECT byb_name FROM buyer WHERE byb_branch_code=:tnid";
		return $this->reporting->fetchOne($sql, array('tnid' => $this->buyerId));
	}

	public function getRfqSentToMatch( $vesselName = null, $purchaserEmail = null)
	{
		$endRow = $this->startRow + $this->limit -1 ;

		$sql = "
			SELECT
			    ttt.rfq_internal_ref_no,
                ttt.byb_branch_code,
                ttt.spb_branch_code,
                ttt.rfq_submitted_date,
                ttt.rfq_vessel_name,
                ttt.rfq_ref_no,
                ttt.rfq_count,
                ttt.rfq_subject,
                ttt.rfq_line_count,
                ttt.rfq_pom_source,
                ttt.rfq_email,
                ttt.is_orphaned_po,
                ttt.cheapest_qot_spb_by_byb,
                ttt.cheapest_qot_spb_by_match,
                ttt.cheapest_qot_spb_by_byb_100,
                ttt.cheapest_qot_spb_by_match_100,
                ttt.potential_saving,
                ttt.realised_saving,
                ttt.has_order,
                ttt.has_quote,
                ttt.ord_id_by_byb,
                ttt.total_buyer_supplier,
                ttt.total_match_supplier,
                ttt.total_qot_by_byb,
                ttt.total_declined_qot_by_byb,
                ttt.total_qot_by_match,
                ttt.total_declined_qot_by_match,
                ttt.actual_savings,
                ttt.potential_savings,
                ttt.ord_id_by_match,
                ttt.total_potential_saving,
                ttt.total_actual_saving,
                ttt.purchaser_name,
                ttt.rn,
                ttt.total_rows,
                ttt.total_po_byb,
                ttt.total_po_match,
				(
					SELECT
						s.spb_name || ' (' || s.spb_branch_code || ')'
					FROM
						supplier s JOIN ord o
						ON (o.spb_branch_code=s.spb_branch_code)
					WHERE
						(
							ord_internal_ref_no=ttt.ORD_ID_BY_MATCH
							OR ord_internal_ref_no=ttt.ORD_ID_BY_BYB
						)
						AND rownum=1
				) winner_supplier_name
		        , ttt.ORD_ID_BY_MATCH
		        , ttt.ORD_ID_BY_BYB
				, CASE
		            WHEN ttt.ORD_ID_BY_BYB IS NOT NULL  AND ttt.ORD_ID_BY_MATCH IS null THEN 'ord_by_buyer'
		            WHEN ttt.ORD_ID_BY_MATCH IS NOT NULL AND ttt.ORD_ID_BY_BYB IS NULL THEN 'ord_by_match'
		            WHEN ttt.ORD_ID_BY_MATCH IS NOT NULL AND ttt.ORD_ID_BY_BYB IS NOT NULL THEN 'both'
				END
		        ord_status

			FROM
			(
				SELECT
					qqq.*
					, rownum rn
					, COUNT(*) OVER () total_rows
					, SUM(
						CASE
				            WHEN qqq.ORD_ID_BY_BYB IS NOT NULL AND qqq.ORD_ID_BY_MATCH IS null THEN 1
				            WHEN qqq.ORD_ID_BY_MATCH IS NOT null THEN 0
						END
					) OVER() total_po_byb
					, SUM(
						CASE
				            WHEN qqq.ORD_ID_BY_BYB IS NOT NULL AND qqq.ORD_ID_BY_MATCH IS null THEN 0
				            WHEN qqq.ORD_ID_BY_MATCH IS NOT null THEN 1
						END
					) OVER() total_po_match
				FROM
				(
					SELECT
						DISTINCT r.*
				        , (
	                  		SELECT ord_internal_ref_no FROM match_b_ord_from_byb_s_spb
	                  		WHERE
								rfq_sent_to_match=r.rfq_internal_ref_no
								AND rownum=1
						) ORD_ID_BY_BYB
						, (
							SELECT
								COUNT(*)
							FROM
								match_b_rfq_also_sent_to_buyer
							WHERE
								byb_branch_code=:buyerId
								AND rfq_ref_no=r.rfq_ref_no
						) TOTAL_BUYER_SUPPLIER
						, (
							SELECT COUNT(*)
							FROM match_b_rfq_forwarded_by_match
							WHERE
								rfq_count=1
								AND rfq_sent_to_match=r.rfq_internal_ref_no
						) TOTAL_MATCH_SUPPLIER
						, (
							SELECT COUNT(*)
							FROM match_b_rfq_also_sent_to_buyer rr JOIN match_b_qot_from_byb_spb q ON (rr.rfq_internal_ref_no=q.rfq_internal_Ref_no)
							WHERE
								rr.rfq_sent_to_match=r.rfq_internal_ref_no
								AND rr.is_declined=0
						) TOTAL_QOT_BY_BYB

						, (
							SELECT COUNT(*)
							FROM match_b_rfq_also_sent_to_buyer rr
							WHERE
								rr.rfq_sent_to_match=r.rfq_internal_ref_no
								AND rr.is_declined>0
						) TOTAL_DECLINED_QOT_BY_BYB


						, (
							SELECT COUNT(*)
							FROM match_b_rfq_forwarded_by_match rr JOIN match_b_qot_match q ON (q.rfq_internal_ref_no=rr.rfq_internal_ref_no)
							WHERE
								rr.rfq_sent_to_match=r.rfq_internal_ref_no
								AND rr.is_declined=0
						) TOTAL_QOT_BY_MATCH
						, (
							SELECT COUNT(*)
							FROM match_b_rfq_forwarded_by_match rr
							WHERE
								rr.rfq_sent_to_match=r.rfq_internal_ref_no
								AND rr.is_declined>0
						) TOTAL_DECLINED_QOT_BY_MATCH

						, r.realised_saving ACTUAL_SAVINGS
		  				, r.potential_saving POTENTIAL_SAVINGS
						, (
							SELECT
								ord_internal_ref_no
							FROM
								match_b_order_by_match_spb o
							WHERE
								rfq_sent_to_match=r.rfq_internal_ref_no
								AND rownum=1
								AND not exists (
									SELECT 1 FROM match_b_ord_from_byb_s_spb oo WHERE o.ord_internal_ref_no=oo.ord_internal_ref_no
								)
						) ORD_ID_BY_MATCH
						, SUM(r.potential_saving) OVER () TOTAL_POTENTIAL_SAVING
						, SUM(r.realised_saving) OVER () TOTAL_ACTUAL_SAVING
						, CNTC_PERSON_NAME PURCHASER_NAME
					FROM
						match_b_rfq_to_match r JOIN contact c
						ON 	(
							    c.cntc_doc_type='RFQ'
							    AND r.rfq_internal_ref_no=c.cntc_doc_internal_ref_no
								AND c.cntc_branch_qualifier='BY'
							)
					WHERE
						r.spb_branch_code=999999
					    AND r.rfq_submitted_date BETWEEN to_date(:startDate) AND to_date(:endDate) + 0.99999
						AND r.byb_branch_code=:buyerId
						"
			  			.
			  				(($vesselName!="") ? "AND LOWER(TRIM(r.rfq_vessel_name))='" . trim(strtolower($vesselName)) . "'" : "" )
			  			.
			  			"
						" . (($purchaserEmail != "") ? "AND LOWER(c.cntc_person_email_address)=LOWER('" . $purchaserEmail . "')":"") . "

					ORDER BY
						r.rfq_submitted_date DESC

				) qqq
			) ttt
			" . ( (is_null($this->startRow) ) ? "":"WHERE rn BETWEEN " . $this->startRow . " AND " . ( $endRow ) ). "
		";

		$params = array(
			'buyerId'=> $this->buyerId,
			'startDate' => $this->startDate,
			'endDate' => $this->endDate
		);


		//echo $sql; print_r($params); die();

		return $this->reporting->fetchAll($sql, $params);
	}

	public function setStartRow( $no )
	{
		$this->startRow = $no;
	}

	public function setLimit( $no )
	{
		$this->limit = $no;
	}

	public function setStartDate($d, $m, $y) {
		if (preg_match('#^[0-9]+$#', $m)) {
			$month = strtoupper(date("M", mktime(0, 0, 0, $m, 10)));
		} else {
			$month = $m;
		}

		$day = str_pad((int) $d, 2, "0", STR_PAD_LEFT);
		$this->startDate = "$day-$month-$y";
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
	}

}
