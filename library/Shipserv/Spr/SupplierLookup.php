<?php

/**
 * Class Shipserv_Spr_SupplierLookup
 * Queries for supplier lookup
 * @author attilaolbrich
 */
class Shipserv_Spr_SupplierLookup extends Shipserv_Object
{

    /**
     * Return all suppliers, traded with the buyers
     * @param array $buyerBranches
     * @param array $supplierIDs
     * @param string $keywords
     * @param integer $limit
     * @return array
     */
    public function getDefaultSpbList(array $buyerBranches, array $supplierIDs = null, $keywords = "", $limit = null)
	{

        $filterList = array();
        $orderList = array();
        $keywordList = array();
        $sbpFilter = "";

        // Generate keyword list array for the SQL
	    if (trim($keywords) !== '') {
            $keywordList = array_filter(preg_split('/[\s,.\x5c\x2F]+/', trim(strtolower($keywords))));
            $sbpFilter = ((int)$keywords > 0) ? ' or spb.spb_branch_code = ' . (int)$keywords : null;

            foreach (array_keys($keywordList) as $key) {
                array_push($filterList, "LOWER(spb.spb_name)  LIKE  '%' || :keyword$key || '%' ESCAPE '='");
                array_push($orderList, "CASE WHEN INSTR(lower(spb.spb_name), lower(:keyword$key)) = 0 THEN 0 ELSE 1 END,");
            }
        }

		$sql = '
		    WITH supplierlist as (
             
               SELECT DISTINCT
                spb_branch_code
                /*+ INDEX(r IDX_RFQ_N2) */
               FROM 
                rfq r 
                WHERE r.byb_branch_code in (' . implode(',', $buyerBranches) . ")
             ), orderlist as (
                SELECT
                 /*+ INDEX(o2 IDX_CACTUS_ORD_SUPPLIER_SEARCH) */
                  SUM(o2.ord_total_cost_usd)  ord_total_cost_usd
                  ,o2.spb_branch_code
                  ,(
                  COUNT(DISTINCT CASE WHEN 
                    (TRUNC(o2.ORD_SUBMITTED_DATE)) > TO_DATE(:lastyear, 'YYYYMMDD') THEN 1 END
                  )) has_order
                FROM
                  ord o2 
                WHERE
                    o2.byb_branch_code in (" . implode(',', $buyerBranches) . ")
                    -- and TRUNC(o2.ord_submitted_date) >= TO_DATE(:transactionrange, 'YYYYMMDD') 
                GROUP BY 
                  o2.spb_branch_code
             ), base AS (
                SELECT 
                  /*+ INDEX(spb PK_SUPPLIER) */
                  spb.spb_name value,
                  spb.spb_branch_code tnid,
                  spb.spb_branch_code pk,
                  spb_country_code country,
                  cnt.cnt_name country_name,
                  o.has_order has_order,
                  o.ord_total_cost_usd ord_total_cost_usd,
                  COUNT(spb.spb_branch_code) OVER (PARTITION BY 1) itemcount
                FROM
                  supplier spb LEFT JOIN orderlist o ON (
                    o.spb_branch_code = spb.spb_branch_code
                    )
                   JOIN country cnt ON (
                    cnt.cnt_code = spb.spb_country_code
                   )
                    LEFT JOIN supplierlist sl ON (
                    sl.spb_branch_code = spb.spb_branch_code
                   )
                   WHERE spb.spb_is_test_account = 0
                   AND (
                    sl.spb_branch_code is not null
                    or has_order = 1)
                   " . PHP_EOL;

	    // If keywords are supplied then add it to filter the SQL
        if (count($keywordList) > 0) {
            $sql .= " and ((" . implode(' or ', $filterList) . ")" . $sbpFilter . ")" . PHP_EOL;
        }

        if ($supplierIDs) {
            $sql .= 'and spb.spb_branch_code in (:' . implode(', :', array_keys($supplierIDs)) . ')' . PHP_EOL;
        }
        
		$sql .= 'ORDER BY' . PHP_EOL;

        // If keywords are supplied then we have to bring up the items first where we have the keyword in the supplier name
        if (count($keywordList) > 0) {
            $sql .= 'CASE WHEN INSTR(lower(spb.spb_name), lower(:keywords)) = 1 THEN 0 ELSE 1 END,' . PHP_EOL . implode(PHP_EOL, $orderList);
        }

        $sql .= '
			  nvl(o.ord_total_cost_usd, 0) DESC,
			  spb.spb_name,
			  spb.spb_branch_code
			  )
			  
			  SELECT * from base' . PHP_EOL;
        if ($limit) {
            $sql .= ' WHERE rownum <= :limit';
        }

		$params = array(
				'lastyear' => date('Ymd', strtotime('-12 months'))
				//'transactionrange' => date("Ymd", strtotime("-37 months"))
		);

		if ($supplierIDs) {
            $params = array_merge($params, $supplierIDs);
        }
		
        if (count($keywordList) > 0) {
            $params['keywords'] = $keywords;
            foreach ($keywordList as $key => $word) {
                $params['keyword' . $key] = $word;
            }
        }

        // Limit the amount of result, if requested
        if ($limit) {
            $params['limit'] = (int)$limit;
        }

		// print $sql . "\n" . print_r($params, true); die();
		
		$key = md5(__CLASS__ . '_' . __FUNCTION__ . '_' . $sql . '_' . serialize($params));
		$result = $this->fetchCachedQuery($sql, $params, $key, self::MEMCACHE_TTL, Shipserv_Oracle::SSREPORT2);
		
		return $this->camelCaseRecordSet($result);
		
	}

	/**
	 * Camel case the whole recordset
	 *
	 * @param array $recordSet	Record set array
	 * @return array camel cased, and filtered recordset according to the selected page
	 */
	protected function camelCaseRecordSet($recordSet)
	{
		$data = array();
		foreach ($recordSet as $value) {
			$data[] = $this->camelCase($value);
		}
		return $data;
	}
}