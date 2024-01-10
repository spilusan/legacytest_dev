<?php

/**
 * Class for reading supplier branckes data from Oracle
 *
 * @author Attila O  2015-04-01
 * 
 */

class Shipserv_Report_Supplier_Branches extends Shipserv_Report
{

	/**
	* Return an array with a list of the approved suppliers
	* @param integer $orgCode, Code of the organisation
	* @param string $keyword, The string to filter the result
	* @param integer $listOrder, number form 1 to 5, column to order the list
	* @param boolean $isAsc, true if the list must be in ascending order  (not mandantory)
	* @param integer currentPageNr, the numer of the current page for the pagination (not mandantory)
	* @param integer itemsPerPage, how many items we allow on a page (not mandantory)
	* @return array
	*/

	const
    	//DB = 'ssreport2'
    	DB = 'sservdba'
    	;

    /**
    * Returns the list of approved suppliers
    * @param integet $orgCode, the code of the buyer org.
    * @param string $keyword, the search string, if emppty, no filter used
    * @param boolean $isAch, true of false, if the result must be in ascending or descending order
    * @param integer $currentPageNr, the current page number of the pagination
    * @param integer $itemsPerPage, the items per page in pagination
    *
    * @return array, Multi dimensional array, raiseAlert if alert checkbos is checked, pageCount. the total number of patinated pates, data, sub array with the list of aoorived suppliers 
    */
    public function getSupplierList($orgCode, $keyword, $listOrder, $isAsc = true, $currentPageNr = 1, $itemsPerPage = 20)
    {
    	//get the order key failsafe way
    	$orderKey = ($listOrder > 5 || $listOrder<1) ? 0 : $listOrder-1;
    	$db = $this->getDbByType(self::DB);



		//get  if the state of the enabled checkbox is on
		$sql = "
		SELECT
			bos_approved_supplier_enabled 
		FROM 
			buyer_org_setting
		WHERE
			BOS_BYO_ORG_CODE = :orgCode";

		$params = array(
    		'orgCode' =>$orgCode
    	);

    	$rowSet = $db->fetchAll($sql, $params);
    	reset($rowSet);
		$row = current($rowSet);
    	$raiseAlert = ($row !== false) ? ($row['BOS_APPROVED_SUPPLIER_ENABLED'] == 1) : false;

    	//fields we order by
    	$orderFields = array(
				'spb_branch_code',
				'spb_name',
				'spb_city',
				'spb_country',
				'spb_last_tran_date',
    		);

    	$sql = "
			SELECT
			     spb_branch_code
			    ,spb_name
			    ,spb_city
			    ,spb_country
			   , GREATEST(nvl(TO_CHAR(latest_rqr,'yyyy-mm-dd'),' '), nvl(TO_CHAR(latest_po,'yyyy-mm-dd'),' '), nvl(TO_CHAR(latest_qot,'yyyy-mm-dd'),' '), nvl(TO_CHAR(latest_poc,'yyyy-mm-dd'),' ')) spb_last_tran_date
			FROM (
				SELECT 
				spb_branch_code
				,spb_name
				,spb_city
				,spb_country
				,spb_last_tran_date
				,(select max(rqr_submitted_date) last_transaction FROM rfq_quote_relation WHERE rqr_spb_branch_code = sb.spb_branch_code) latest_rqr
				,(select max(ord_submitted_date) last_transaction FROM purchase_order WHERE ord_spb_branch_code = sb.spb_branch_code) latest_po
				,(select max(qot_submitted_date) last_transaction FROM quote WHERE qot_spb_branch_code = sb.spb_branch_code) latest_qot
				,(select max(poc_submitted_date) last_transaction FROM po_confirmation WHERE poc_spb_branch_code = sb.spb_branch_code) latest_poc
	      		FROM
					buyer_supplier_blacklist b, supplier_branch sb
				WHERE
					sb.spb_branch_code = b.bsb_spb_branch_code
				AND
					b.bsb_type = 'whitelist'
				AND
					bsb_byo_org_code = :orgCode";

			//If keywords are set, add keyword to the query
			if ('' != $keyword) {
				$sql .= "\nAND
					lower(spb_name) like :generalKeyWord
				";
			}

			$sql .= ")";

			$sql .= "\nORDER BY ".$orderFields[$orderKey];
			if (!$isAsc) {
				$sql .= " DESC";
			}




		//If keywords are set, add parameter for it
    	if ('' != $keyword) {
    		$params['generalKeyWord'] = '%'.strtolower($keyword).'%';
			}

    	$result = $db->fetchAll($sql, $params);

    	$data = array();
    	//paginate the result
		$paginator = Zend_Paginator::factory($result);
		$paginator->setItemCountPerPage($itemsPerPage);
		$paginator->setCurrentPageNumber($currentPageNr);

		//convert the data to array, which will be converted to json later
		$pageCount = count($paginator);
		if ($pageCount > 0) {
			foreach ($paginator as $row ) {
				$data[] = array("tnid" => $row['SPB_BRANCH_CODE'], "name" => $row['SPB_NAME'], 'city' => $row['SPB_CITY'], 'country' => $row['SPB_COUNTRY'], 'lastused' => $row['SPB_LAST_TRAN_DATE']);
			}
		}

		//return the essential data for the json
        return array(
        		'raiseAlert' => $raiseAlert,
	        	'pageCount' => $pageCount,
	        	'data' => $data
        	);

    }


    /**
    *	Create a CSV for excel from the Approved Suppliers table 
    *	@param integer $orgCode, the code of the organization
    */
    public function getSupplierExcelList( $orgCode )
    {

    	$fieldNames = array(
    		'SPB_BRANCH_CODE' => 'TNID',
    		'SPB_NAME' => 'Name',
    		'SPB_CITY' => 'City',
    		'SPB_COUNTRY' => 'Country',
    		'SPB_LAST_TRAN_DATE' => 'Last Used',
    		);



    	$sql = "SELECT ";
    	$sep = false;
    	foreach ($fieldNames as $key => $value) {
    		$expr = ($key == 'SPB_LAST_TRAN_DATE') ? 'GREATEST(latest_rqr, latest_po, latest_qot, latest_poc) SPB_LAST_TRAN_DATE' : $key;
    		
    		if ($sep) {
    			$sql .= ','.$expr;
    		} else {
    			$sql .= $expr;
    			$sep = true;
    		}
  	   	}
		$sql .="
			FROM (
			SELECT 
	    		 spb_branch_code
	    		,spb_name
	    		,spb_city
	    		,spb_country
	    		,spb_last_tran_date
		        ,(select max(RQR_SUBMITTED_DATE) last_transaction from rfq_quote_relation where rqr_spb_branch_code = sb.spb_branch_code) latest_rqr
		        ,(select max(ORD_SUBMITTED_DATE) last_transaction from purchase_order where ord_spb_branch_code = sb.spb_branch_code) latest_po
		        ,(select max(QOT_SUBMITTED_DATE) last_transaction from quote where qot_spb_branch_code = sb.spb_branch_code) latest_qot
		        ,(select max(POC_SUBMITTED_DATE) last_transaction from po_confirmation where poc_spb_branch_code = sb.spb_branch_code) latest_poc
     		FROM
				buyer_supplier_blacklist b, supplier_branch sb
			WHERE
				sb.spb_branch_code = b.bsb_spb_branch_code
			AND
				b.bsb_type = 'whitelist'
			AND
				bsb_byo_org_code = :orgCode
			ORDER BY 
				spb_name)
				";

    	$params = array(
    		'orgCode' =>$orgCode
    		);

    	$db = $this->getDbByType(self::DB);
    	$result = $db->fetchAll($sql, $params);

    	foreach ($fieldNames as $key => $value) {
    		$data[0][] = $value;
  	   	}
		$i = 0;  	 
		foreach ($result as $row ) {
			$i++;
			foreach ($fieldNames as $key => $value) {
					$data[$i][] = $row[$key];
  	   			}
			}
  		return $this->arrayToCsv($data,",",'"',true); 
    }

    /**
    * Create a string escaped csv string from the array
    */
    protected function arrayToCsv( array $fields, $delimiter = ',', $enclosure = '"', $encloseAll = false, $nullToMysqlNull = false )
    {
		$delimiter_esc = preg_quote($delimiter, '/');
		$enclosure_esc = preg_quote($enclosure, '/');

		$outputString = "";
		foreach($fields as $tempFields) {
		    $output = array();
		    foreach ( $tempFields as $field ) {
		        if ($field === null && $nullToMysqlNull) {
		            $output[] = 'NULL';
		            continue;
		        }

		        // Enclose fields containing $delimiter, $enclosure or whitespace
		        /*
		        if ( $encloseAll || preg_match( "/(?:${delimiter_esc}|${enclosure_esc}|\s)/", $field ) ) {
		            $field = $enclosure . str_replace($enclosure, $enclosure . $enclosure, $field) . $enclosure;
		        }
		        */
		        $output[] = str_replace($delimiter, "", $field); //Removing enclosure, but make sure, that valid, Requested by Stuart
		        //$output[] = $field." ";
		    }
		    $outputString .= implode( $delimiter, $output )."\r\n";
		}
		return $outputString;
 }
}