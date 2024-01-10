<?php
/**
 * Represents the RFQ (request for quote)
 *
 */

class Shipserv_Tradenet_RequestForQuote extends Shipserv_Object {
	private $db;
	
	protected $rfq_internal_ref_no;
	
	public $rfq_header;
	
	public $rfq_line_items;
	
	public $rfq_suppliers;
	
	public $rfq_suppliers_details;
	
	public function __construct($rfq_internal_ref_no, $db = ''){
		if (!is_object($db)) {
			$db = $this->getDb();
		}
        $this->db = $db;
		
		$this->rfq_internal_ref_no = $rfq_internal_ref_no;

		$this->rfq_header       = $this->getRFQHeader();
		$this->rfq_line_items   = $this->getRFQLineItems();
		$this->rfq_suppliers    = $this->getSuppliers();
        $this->rfq_buyer        = $this->getBuyer();

		//TODO: add methods in to output the tag cloud for this;
	}

    public function getInternalRefNo() {
        return $this->rfq_internal_ref_no;
    }

    /**
     * Checks if this RFQ was sent to Match engine
     *
     * @return  bool
     */
    public function isMatchRFQ() {
        $sql = "
            SELECT
              count(*)
            FROM
              request_for_quote r,
              rfq_quote_relation rq
            WHERE
              r.rfq_internal_ref_no = rq.rqr_rfq_internal_ref_no
              AND r.rfq_internal_ref_no = :rfqId
              AND rq.rqr_spb_branch_code = 999999
        ";

        $result = $this->db->fetchOne($sql, array('rfqId' => $this->rfq_internal_ref_no));

        return ($result > 0);
    }

    /**
     * Returns the first "non-match engine" buyer data (code, country and continent)
     * @todo: why first only?
     *
     * @return array
     */
    private function getBuyer() {
        $sql = "
          SELECT
            bb.BYB_BRANCH_CODE,
            c.cnt_con_code,
            c.cnt_country_code
          FROM
            buyer_branch bb,
            country c,
            rfq_quote_relation rqr
          WHERE
            bb.byb_country = c.cnt_country_code
            AND rqr.rqr_byb_branch_code = bb.byb_branch_code
            AND rqr.rqr_rfq_internal_ref_no IN (
              SELECT
                RFQ_INTERNAL_REF_NO
              FROM
                request_for_quote
              WHERE
                rqr.rqr_byb_branch_code = request_for_quote.rfq_byb_branch_code
                AND rfq_ref_no IN (
                  SELECT
                    rfq_ref_no
                  FROM
                    request_for_quote
                  WHERE
                    rfq_internal_ref_no = :rfq
                )
                AND RFQ_BYB_BRANCH_CODE != 11107
            )
            AND RQR_SPB_BRANCH_CODE != 999999
          GROUP BY
            bb.BYB_BRANCH_CODE,
            c.cnt_con_code,
            c.cnt_country_code
        ";
        $results = $this->db->fetchAll($sql, array('rfq' => $this->rfq_internal_ref_no));

        return $results[0];
    }

    /**
     * Returns IDs and some related data about RFQ original suppliers (suppliers RFQ has been sent to),
     * also initialises rfq_suppliers_details member variable
     *
     * Query was modified by Yuriy Akopov on 2013-09-25, DE4198 to exclude suppliers of
     * unrelated RFQs sharing the same public ID
     *
     * @todo: WARNING! the query used won't return correct suppliers for non-match RFQs, so if the function is going
     * to be used outside of Match context, it should be redesigned
     *
     * Reverted to Shipserv_Rfq method because of the defect DE4719 (by Yuriy Akopov on 2014-04-24)
     * @todo: this is a hotfix, later the code dependant on this method should be-written to use Shipserv_Rfq directly and not via this proxy!
     *
     * @return array
     */
    private function getSuppliers() {
        $match = new SHipserv_Match_Match();
        $countryMap = $match->getCountryMap();

        $rfq = Shipserv_Rfq::getInstanceById($this->rfq_internal_ref_no);
        $suppliers = $rfq->getSuppliers();

        $legacySupplierStructure = array();
        foreach ($suppliers as $supplierInfo) {
            $supplier = Shipserv_Supplier::getInstanceById($supplierInfo[Shipserv_Rfq::RFQ_SUPPLIERS_BRANCH_ID], '', true);

            $legacySupplierStructure[] = array(
                'BRANCHCODE' => $supplierInfo[Shipserv_Rfq::RFQ_SUPPLIERS_BRANCH_ID],
                'CONTINENT'  => $countryMap[Shipserv_Match_Match::COUNTRY_MAP_BY_COUNTRY][$supplier->countryCode],
                'COUNTRY'    => $supplier->countryCode,
                'NAME'       => $supplier->name,
                // 'RQR_BYB_BRANCH_CODE' => null
            );
        }

        $this->rfq_suppliers_details = $legacySupplierStructure;

        $output = array();
        foreach ($this->rfq_suppliers_details as $result) {
            $output[] = $result['BRANCHCODE'];
        }

        return $output;

        /*
        $sql = "
        SELECT DISTINCT
          sb.spb_branch_code      as BranchCode,
          c.cnt_con_code          as Continent,
          c.cnt_country_code      as country,
          sb.spb_name             as Name,
          rqr.rqr_byb_branch_code
        FROM
          (
            SELECT
              1 AS suspicious,
              -- ID of the RFQ we think is relevant (simpler fields match)
              rfq_candidates.rfq_internal_ref_no,
              -- concatenated product description of that RFQ's line items
              (
                SELECT
                  RTRIM(xmlagg(xmlelement(c, lower( SUBSTR(TRIM(REGEXP_REPLACE(TRIM(rfl_product_desc), '[[:cntrl:]]', '__')), 0, 20)) || ',') ).extract ('//text()'), ',') \"li_concat\"
                FROM
                  rfq_line_item
                WHERE
                  rfl_rfq_internal_ref_no = rfq_candidates.rfq_internal_ref_no
              ) li_concat_candidate,
              -- concatenated product description of the original RFQ line items (repeated for every row, but by that we avoid another subquery)
              (
                SELECT
                  RTRIM(xmlagg(xmlelement(c, lower( SUBSTR(TRIM(REGEXP_REPLACE(TRIM(rfl_product_desc), '[[:cntrl:]]', '__')), 0, 20)) || ',') ).extract ('//text()'), ',') \"li_concat\"
                FROM
                  rfq_line_item
                WHERE
                  rfl_rfq_internal_ref_no = rfq_orig.rfq_internal_ref_no
              ) li_concat_orig
            FROM
              request_for_quote rfq_orig
              JOIN request_for_quote rfq_candidates ON
                  rfq_candidates.rfq_internal_ref_no != :rfq
                  AND (rfq_candidates.rfq_sourcerfq_internal_no IS NULL OR rfq_candidates.rfq_sourcerfq_internal_no != :rfq              )
                  -- same public reference number
                  AND rfq_candidates.rfq_ref_no = rfq_orig.rfq_ref_no
                  -- subject which is both empty or includes the original (or vice versa)
                  AND (
                    (
                      rfq_candidates.rfq_subject IS NULL
                      AND rfq_orig.rfq_subject IS NULL
                    )
                    OR INSTR(rfq_candidates.rfq_subject, rfq_orig.rfq_subject) > 0
                    OR INSTR(rfq_orig.rfq_subject, rfq_candidates.rfq_subject) > 0
                  )
                  -- same number of line items
                  AND rfq_candidates.rfq_line_item_count = rfq_orig.rfq_line_item_count
                  -- date created within -2 / +2 days from the date of the original RFQ
                  AND rfq_candidates.rfq_created_date BETWEEN (rfq_orig.rfq_created_date - 2) AND (rfq_orig.rfq_created_date + 2)
            WHERE
              rfq_orig.rfq_internal_ref_no = :rfq         -- the RFQ we're looking at

            UNION

            SELECT
              0 AS suspicious,
              rfq_internal_ref_no,
              NULL AS li_concat_candidate,
              NULL AS li_concat_orig
            FROM
              request_for_quote
            WHERE
              rfq_internal_ref_no = :rfq
              OR rfq_sourcerfq_internal_no = :rfq
          ) rfq_candidates

          JOIN rfq_quote_relation rqr ON
            rqr.rqr_rfq_internal_ref_no = rfq_candidates.rfq_internal_ref_no

          -- joining resulting RFQs with meta data we need in the particular use case
          JOIN supplier_branch sb ON
            rqr.rqr_spb_branch_code = sb.spb_branch_code
          JOIN country c ON
            sb.spb_country = c.cnt_country_code
        WHERE
          -- line items check
          (
            -- RFQ is an original one so check can be skipped
            rfq_candidates.suspicious = 0
            -- line item description is similar
            OR (
              utl_match.edit_distance(rfq_candidates.li_concat_candidate, rfq_candidates.li_concat_orig) < (length(rfq_candidates.li_concat_orig) / 10)
            )
          )
          -- supplier filters
          AND sb.spb_branch_code != 999999            -- hide suppliers from RFQs sent to match
          AND sb.directory_entry_status = 'PUBLISHED' -- hide suppliers which aren't public
        ";

        // legacy part of the function not changed by Yuriy Akopov
        $this->rfq_suppliers_details = $this->db->fetchAll($sql, array('rfq' => $this->rfq_internal_ref_no));;
        foreach ($this->rfq_suppliers_details as $result) {
            $output[] = $result['BRANCHCODE'];
        }

        return $output;
        */
    }

    /**
     * Returns object representation of RFQ and buyer fields
     * @todo: everywhere else associative arrays are used, what's the case here for stdClass?
     *
     * @return bool|stdClass
     */
    private function getRFQHeader() {
		$sql = "
          SELECT
            rfq.*,
            bb.*,
            c.cnt_country_code as PortCountry,
            c.cnt_con_code as PortContinent,
            c2.cnt_country_code as BuyerCountry,
            c2.cnt_con_code as BuyerContinent
          FROM
            request_for_quote rfq
            INNER JOIN buyer_branch bb ON
              rfq.rfq_byb_branch_code = bb.byb_branch_code
            LEFT JOIN Port p ON
              rfq.rfq_delivery_port = p.prt_port_code
            LEFT JOIN country c ON
              p.prt_cnt_country_code = c.cnt_country_code
            LEFT JOIN country c2 ON
              bb.byb_country = c2.cnt_country_code
            WHERE
              bb.byb_branch_code = rfq_byb_branch_code
              AND rfq_internal_ref_no = :rfq
        ";
        $header = $this->db->fetchAll($sql, array('rfq' => $this->rfq_internal_ref_no));

		try {
			$tmp = $header[0];

			$keys = array_keys($tmp);
			$sHelper = new Shipserv_Helper_String();
			$output = array();
		
			foreach($keys as $key) {
				$output[$sHelper->to_camel_case($key)] = $tmp[$key];
			}
			
			return (object) $output;
			
		} catch (exception $ex) {
			return false;
		}
	}

    /**
     * Returns RFQ line items as stdClass objects or false if they weren't found
     * @todo: everywhere else associative arrays are used, what's the case here for stdClass?
     *
     * @return array|bool
     */
    private function getRFQLineItems() {
		$sql = "
          SELECT
            *
          FROM
            rfq_line_item
          WHERE
            rfl_rfq_internal_ref_no = :rfq
          ORDER BY
            rfl_line_item_no
        ";
        $results = $this->db->fetchAll($sql, array('rfq' => $this->rfq_internal_ref_no));

        $output = array();
        $sHelper = new Shipserv_Helper_String();

		try {
			foreach ($results as $result){
				$tmp = array_keys($result);
				foreach($tmp as $key){
					$new[$sHelper->to_camel_case($key)] = $result[$key];
				}

				$output[] = (object) $new;
			}

            return $output;

		} catch (Exception $ex) {
			return false;
		}
	}
	
	public function getInstanceOfRfq()
	{
		return Shipserv_Rfq::getInstanceById($this->rfq_internal_ref_no);
	}
}
