<?php
/**
 * Returns GMV figures for the specific supplier and date range
 *
 * Initially written by Elvir, refactored and modified by Yuriy Akopov on 2016-08-09, S17177
 *
 */
class Shipserv_Report_GmvReport extends Shipserv_Report
{
	/**
	 * @var Shipserv_Supplier
	 */
	protected $supplier = null;

	/**
	 * @var array
	 */
    protected $data = null;

	/**
	 * @var array
	 */
    protected $h = null;

	/**
	 * @var array
	 */
    protected $gmv = null;

    /**
	 * @param   Shipserv_Supplier   $supplier
	 * @param   DateTime            $dateFrom
	 * @param   DateTime            $dateTo
     *
     * @return  Shipserv_Report_GmvReport
     */
    public static function getInstance(Shipserv_Supplier $supplier, DateTime $dateFrom, DateTime $dateTo)
    {
    	$object = new self();
    	$object->supplier = $supplier;

        $responseData = $object->getDataFromAdapter('gmv-detail', $object->supplier->tnid, $dateFrom->format('Ymd'), $dateTo->format('Ymd'));

	    $data = array(
		    'gmv-detail' 		=> $responseData['po-total-value']['purchase-orders'],
		    'gmv-detail-total' 	=> $responseData['po-total-value']['count'],
		    'startDate'         => $dateFrom->format("d M Y"),
		    'endDate'           => $dateTo->format("d M Y")
        );

        //Rounding (see DE4200) - putting in the model for consistency across usages
        if ($data['gmv-detail']) {
            foreach($data['gmv-detail'] as &$row) {
                $row['adjusted-cost']  = round($row['adjusted-cost'], 2);
                $row['total-cost-usd'] = round($row['total-cost-usd'], 2);
                $row['currency-rate']  = round($row['currency-rate'], 3);
            }
        }

    	$object->data = $data;
        $object->gmv  = $object->getSortedArrayOfGmvDetailByBuyer();
        $object->h    = $object->getDataByBuyerGroupByParent();

        return $object;
    }

    /**
     * @return mixed
     */
    public function getSortedArrayOfGmvDetailByBuyer()
    {
        if (count($this->data['gmv-detail'])  == 0) {
            return $this->data['gmv-detail'];
        }
        $sortedArray = $this->data['gmv-detail'];
        usort($sortedArray, array( $this, 'buyerSort' ));
        return $sortedArray;
    }

    /**
     * Sorting data by buyer
     * @param $a
     * @param $b
     * @return in
     */
    private function buyerSort($a, $b)
    {
        $c = strnatcasecmp($a['buyer-name'], $b['buyer-name']);
        if ($c === 0) {
            $c = strnatcasecmp($a['buyer-tnid'], $b['buyer-tnid']);
            if ($c === 0) {
                $c = strnatcasecmp($a['ord-ref-no'], $b['ord-ref-no']); //ASC
            }
        }
        return $c;
    }

    /**
     * Getting hierarchy of the buyer found on the report
     * @param $buyerIds
     * @return array
     */
    public function getAllHierarchyOfBuyerIds()
    {
        if( count($this->getUniqueBuyerAsArray()) == 0 ) return array();
        $sql = "
            WITH tmp_buyerlist as 
            (
            SELECT
                byb_branch_code,
                byb_name,
                parent_branch_code,
                parent_name
            FROM
                buyer
            WHERE
                byb_branch_code IN (" . implode(",", array_keys($this->getUniqueBuyerAsArray())) . ")
            ORDER BY
              parent_name, byb_name
            ) 
            
            SELECT
                *
            FROM
            tmp_buyerlist
            WHERE 
              byb_branch_code NOT IN (
                select distinct parent_branch_code from tmp_buyerlist
              ) 
              OR byb_branch_code = parent_branch_code";

        $db = Shipserv_Helper_Database::getSsreport2Db();
        $data = array();
        foreach( $db->fetchAll($sql) as $row )
        {
            $data['parent'][$row['PARENT_BRANCH_CODE']][$row['BYB_BRANCH_CODE']] = array('NAME' => $row['PARENT_NAME']);
            $data['child'][$row['BYB_BRANCH_CODE']][$row['PARENT_BRANCH_CODE']] = array('NAME' => $row['BYB_NAME'] );
            $data['company'][$row['PARENT_BRANCH_CODE']] = $row['PARENT_NAME'];
            $data['company'][$row['BYB_BRANCH_CODE']] = $row['BYB_NAME'];
        }

        return $data;
    }

    /**
     * Returning array of the data group by parent
     * @return array
     */
    public function getDataByBuyerGroupByParent()
    {
    	$gmv = $this->gmv;

        $h = $this->getAllHierarchyOfBuyerIds();

        foreach((array)$h['parent'] as $parentId => $children)
        {
            foreach($gmv as $row)
            {
            	if( $row['doc-type'] == "PO" )
            	{
	            	$row['url-p'] = Shipserv_Order::getPrintableUrl($row['internal-ref-no']);
	            	$row['url-i'] = '/reports/invalid-txn-picker?documentInternalRefNo=' . $row['internal-ref-no'] . '&a=Search&h=' . md5($row['doc-type'] . $row['internal-ref-no']);
            	}
            	else
            	{
            		$row['url-p'] = Shipserv_PurchaseOrderConfirmation::getPrintableUrl($row['internal-ref-no']);
            		$row['url-i'] = '/reports/invalid-txn-picker?documentInternalRefNo=' . $row['internal-ref-no'] . '&a=Search&h=' . md5($row['doc-type'] . $row['internal-ref-no']);
            	}
            	// get url to printables
            	// get url to invalid txn picker
                if( $row['buyer-tnid'] == $parentId )
                {
                    $h['parent'][$parentId][$parentId]['DATA'][] = $row;
                }

                foreach( $children as $childId => $child )
                {
                    if( $parentId != $childId )
                    {
                        if( $row['buyer-tnid'] == $childId )
                        {
                        	$h['parent'][$parentId][$childId]['DATA'][] = $row;
                        }
                    }
                }
            }
        }

        return $h;
    }

    /**
     * Returning unique buyer
     */
    public function getUniqueBuyerAsArray()
    {
        $uniqueTnids = array();
        foreach((array)$this->gmv as $row) {
            $uniqueTnids[$row['buyer-tnid']] = true;
        }
        return $uniqueTnids;
    }

    /**
     * Returns GMV report as a CSV string
     *
     * @return string
     */
    public function generateCsvForGmvReport()
    {
        $h = $this->h;
        $supplier = $this->supplier;

        $csvKeys = array(
            'buyer-tnid'        => 'Buyer TNID',
            'buyer-name'        => 'Buyer Name',
            'doc-type'          => 'Doc Type',
			'doc-status'        => 'Status',
            'ord-ref-no'        => 'PO Ref. No.',
            'poc-ref-no'        => 'POC Ref. No.',

	        // added by Yuriy Akopov on 2016-01-07
	        'internal-ref-no' => 'ShipServ Ref. No.',

        	'vessel-name'		=> 'Vessel name',
        	'imo'				=> 'IMO No.',
            'submitted-date'    => 'Submitted Date',
            'total-cost'        => 'Total Cost',
            'currency'          => 'Currency',
            'currency-rate'     => 'Exchange Rate',
            'total-cost-usd'    => 'Total Cost USD',
            'adjusted-cost'     => 'Adjusted Cost (USD)',
            'group-total'       => 'Group total',
        	'is-price-corrected' => 'Median value?',

	        // added by Yuriy Akopov on 2016-08-09, S17177
	        'rate-standard'     => 'Standard rate',
	        'rate-value'        => 'Rate value'
        );

        $summary = $this->getTotalTransaction();

        $csv = "Supplier, " . $supplier->name . "\n";
        $csv .= "Adjusted Total Order Value, " . $summary['adjustedWithGMV'] . "\n";
        $csv .= $this->arrayToCsv(array_values($csvKeys));

	    $totalPerGroup = array();

        foreach ((array)$h['parent'] as $parentId => $childrenData) {
            $companyName = $h['company'][$parentId];
            $rows = $childrenData[$parentId]['DATA'];

            for ($i = 0; $i < count($rows); $i++) {
	            if (
	                ($rows[$i]['internal-ref-no'] == $rows[$i + 1]['internal-ref-no']) and
	                ($rows[$i]['doc-type'] == 'POC') and
	                ($rows[$i + 1]['doc-type'] == 'PO')
	            ) {
	            	$tmp = $rows[$i];
		            $rows[$i] = $rows[$i + 1];
		            $rows[$i + 1] = $tmp;
	            }
            }

            foreach((array)$rows as $row) {
            	$totalPerGroup[$parentId] += $row['adjusted-cost'];
            }

            foreach ($childrenData as $companyId => $data) {
            	if ($companyId != $parentId) {
		            foreach((array)$data['DATA'] as $row) {
		           		$totalPerGroup[$parentId] += $row['adjusted-cost'];
		           	}
            	}
            }

            $row = array(
	            'buyer-tnid'        => $parentId . ' Group',
	            'buyer-name'        => $companyName,
				'doc-type'          => '',
				'doc-status'        => '',
	            'ord-ref-no'        => '',
	            'poc-ref-no'        => '',

	            // added by Yuriy Akopov on 2016-01-07
	            'internal-ref-no' => '',

	            'vessel-name'		=> '',
	            'imo-no'			=> '',
	            'submitted-date'    => '',
	            'total-cost'        => '',
	            'currency'          => '',
	            'currency-rate'     => '',
	            'total-cost-usd'    => '',
	            'adjusted-cost'     => '',
	            'group-total'     	=> $totalPerGroup[$parentId],

	            // added by Yuriy Akopov on 2016-08-09, S17177
	            'rate-standard'     => '',
	            'rate-value'        => ''
            );

            $csv .= $this->arrayToCsv($row, array_keys($csvKeys));

            foreach((array) $rows as $row) {
                $csv .= $this->arrayToCsv($row, array_keys($csvKeys));
            }

            if (count($rows) == 0) {
                $row = array(
                    'buyer-tnid'        => $parentId,
                    'buyer-name'        => $companyName,
					'doc-type'          => '',
					'doc-status'        => '',
                    'ord-ref-no'        => '',
                    'poc-ref-no'        => '',

	                // added by Yuriy Akopov on 2016-01-07
	                'internal-ref-no' => '',

                	'vessel-name'		=> '',
                	'imo-no'			=> '',
                    'submitted-date'    => '',
                    'total-cost'        => '',
                    'currency'          => '',
                    'currency-rate'     => '',
                    'total-cost-usd'    => '',
                    'adjusted-cost'     => '',

	                // added by Yuriy Akopov on 2016-08-09, S17177
	                'rate-standard'     => '',
	                'rate-value'        => ''
                );

                $csv .= $this->arrayToCsv($row, array_keys($csvKeys));
            }

            foreach ($childrenData as $companyId => $data) {
                if ($companyId != $parentId) {
                    // $companyName = $data['NAME'];
                    $rows = $data['DATA'];

                    for ($i = 0; $i < count($rows); $i++) {
	                    if (
	                        ($rows[$i]['internal-ref-no'] == $rows[$i+1]['internal-ref-no']) and
	                        ($rows[$i]['doc-type'] == "POC") and
	                        ($rows[$i+1]['doc-type'] == "PO")
	                    ) {
	                        $tmp = $rows[$i];
                            $rows[$i] = $rows[$i+1];
                            $rows[$i+1] = $tmp;
	                    }
                    }

                    foreach((array)$rows as $row)
                    {
                        $row['buyer-name'] = '      ' . $row['buyer-name'];
                        $row['buyer-tnid'] = '      ' . $row['buyer-tnid'];

                        $csv .= $this->arrayToCsv($row, array_keys($csvKeys));
                    }
                }
            }
        }
        return $csv;
    }

    /**
     * Get total transaction
     * @return array
     */
    public function getTotalTransaction()
    {
        $h = $this->h;

        foreach((array)$h['parent'] as $parentId => $childrenData )
        {
            $companyName = $this->h['company'][$parentId];
            $rows =  $childrenData[$parentId]['DATA'];

            foreach((array)$rows as $row)
            {
                $totalCostAllBuyer += $row["total-cost-usd"];
                $totalAdjustedCostAllBuyer += $row["adjusted-cost"];
            }

            foreach( $childrenData as $companyId => $data )
            {
                if( $companyId!= $parentId )
                {
                    $companyName = $h['company'][$companyId];
                    $rows = $data['DATA'];

                    foreach((array)$rows as $row)
                    {
                        $totalCostAllBuyer += $row["total-cost-usd"];
                        $totalAdjustedCostAllBuyer += $row["adjusted-cost"];
                    }
                }
            }
        }

        $totalCostAllBuyerUsingGMVDetail = $totalAdjustedCostAllBuyerUsingGMVDetail = 0;
        foreach((array)$this->gmv as $row)
        {
            $totalCostAllBuyerUsingGMVDetail += $row["total-cost-usd"];
            $totalAdjustedCostAllBuyerUsingGMVDetail += $row["adjusted-cost"];
        }

        return array('total' => $totalCostAllBuyer, 'adjusted' => $totalAdjustedCostAllBuyer, 'totalWithGMV' => $totalCostAllBuyerUsingGMVDetail, 'adjustedWithGMV' => $totalAdjustedCostAllBuyerUsingGMVDetail);

    }


    /**
     * Converts a CSV hash to a string row as it appears in the file by matching the keys
     *
     * Refactored by Yuriy Akopov on 2016-08-09
     *
     * @param   array   $fields is the array to be serialised
     * @param   array   $keys   is an optional array of keys to be used
     * @param   string  $delimiter
     * @param   string  $enclosure
     *
     * if $keys is used, those only keys in $fields that exist in $keys will be used
     * and keys that exist in $keys but are not in $fields will be added as empty positions
     *
     * @return string
     */
    private function arrayToCsv(array $fields, $keys = null, $delimiter = ',', $enclosure = '"')
    {
        $str = '';
        $escape_char = '\\';

        $toCsv = $fields;

        if (!is_null($keys)) {
            $toCsv = array();

            foreach ($keys as $key) {
                if (strlen($fields[$key])) {
                    $toCsv[$key] = $fields[$key];
                } else {
                    $toCsv[$key] = '';
                }
            }
        }

        $stringValues = array();
        foreach ($toCsv as $value) {
            if (
            	strpos($value, $delimiter) !== false ||
                strpos($value, $enclosure) !== false ||
                strpos($value, "\n") !== false ||
                strpos($value, "\r") !== false ||
                strpos($value, "\t") !== false ||
                strpos($value, ' ') !== false
            ) {
            	// @todo: didn't check this escaping yet code but it looks shaky to be to be honest (Yuriy)
                $str2 = $enclosure;
                $escaped = 0;
                $len = strlen($value);

                for ($i = 0; $i < $len; $i++) {
	                if ($value[$i] === $escape_char) {
		                $escaped = 1;
	                } else if (!$escaped && $value[$i] == $enclosure) {
		                $str2 .= $enclosure;
	                } else {
		                $escaped = 0;
                    }

                    $str2 .= $value[$i];
                }

                $str2 .= $enclosure;
                $stringValues[] = sprintf($str2);
            } else {
	            $stringValues[] = sprintf($value);
            }
        }

        $str = implode($delimiter, $stringValues) . PHP_EOL;

        return $str;
    }

}
