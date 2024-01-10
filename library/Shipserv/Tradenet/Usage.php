<?php
/**
 * Class to pull number of documents + suppliers and buyer
 * @author Elvir
 *
 */
class Shipserv_Tradenet_Usage extends Shipserv_Object {
    
	public function setParams($params)
	{
		$this->params = $params;
		if($params['spbBranchCode'] != "")
		{
			$this->setSpbBranchCode($params['spbBranchCode']);
		}
		if($params['bybBranchCode'] != "")
		{
			$this->setBybBranchCode($params['bybBranchCode']);
		}
		
	}
	
	private function getData($sql)
	{
		$db = Shipserv_Helper_Database::getSsreport2Db();
		$data = $db->fetchAll($sql);
		foreach($data as $row)
		{
			$x[(int)str_replace($this->params['yyyy'], "", $row['WEEK_STR'])] = (int)$row['TOTAL'];
		}
		return $x;
	}
	
	public function setSpbBranchCode($code)
	{
		$this->spbBranchCode = $code;
	}
	
	public function setBybBranchCode($code)
	{
		$this->bybBranchCode = $code;
	}
	
	/**
	 * Getting the statistic of supplier weekly
	 */
	public function getRfqStats()
	{
		$sql = "
			SELECT
			  TO_CHAR(rfq_submitted_date, 'YYYYWW')  WEEK_STR
			  , COUNT(*) TOTAL
			FROM
			  rfq
			WHERE
			  rfq_submitted_date BETWEEN TO_DATE('01-JAN-" . $this->params['yyyy'] . "') AND TO_DATE('01-JAN-" . $this->params['yyyy'] . "') + 365
			  " . ( ($this->spbBranchCode!="")?"AND spb_branch_code=" . $this->spbBranchCode:"" ) . "
			  " . ( ($this->bybBranchCode!="")?"AND byb_branch_code=" . $this->bybBranchCode:"" ) . "
			GROUP BY
			  TO_CHAR(rfq_submitted_date, 'YYYYWW')
			ORDER BY 
			  TO_CHAR(rfq_submitted_date, 'YYYYWW') ASC
    	";
		return $this->getData($sql);
	}
	
	public function getQuoteStats()
	{
		$sql = "
			SELECT
			  TO_CHAR(qot_submitted_date, 'YYYYWW') WEEK_STR
			  , COUNT(*) TOTAL
			FROM
			  qot
			WHERE
			  qot_submitted_date BETWEEN TO_DATE('01-JAN-" . $this->params['yyyy'] . "') AND TO_DATE('01-JAN-" . $this->params['yyyy'] . "') + 365
			  " . ( ($this->spbBranchCode!="")?"AND spb_branch_code=" . $this->spbBranchCode:"" ) . "
			  " . ( ($this->bybBranchCode!="")?"AND byb_branch_code=" . $this->bybBranchCode:"" ) . "
			GROUP BY
			  TO_CHAR(qot_submitted_date, 'YYYYWW')
			ORDER BY 
			  TO_CHAR(qot_submitted_date, 'YYYYWW') ASC
		";
		return $this->getData($sql);
	}
	
	public function getOrderStats()
	{
		$sql = "
			SELECT
			  TO_CHAR(ord_submitted_date, 'YYYYWW') WEEK_STR
			  , COUNT(*) TOTAL
			FROM
			  ord
			WHERE
			  ord_submitted_date BETWEEN TO_DATE('01-JAN-" . $this->params['yyyy'] . "') AND TO_DATE('01-JAN-" . $this->params['yyyy'] . "') + 365
			  " . ( ($this->spbBranchCode!="")?"AND spb_branch_code=" . $this->spbBranchCode:"" ) . "
			  " . ( ($this->bybBranchCode!="")?"AND byb_branch_code=" . $this->bybBranchCode:"" ) . "
			GROUP BY
			  TO_CHAR(ord_submitted_date, 'YYYYWW')
			ORDER BY 
			  TO_CHAR(ord_submitted_date, 'YYYYWW') ASC
		";
		return $this->getData($sql);
	}
	
	public function getSupplierCreatedStats()
	{
		$sql = "
			SELECT
			  TO_CHAR(spb_created_date, 'YYYYWW')  WEEK_STR
			  , COUNT(*) TOTAL
			FROM
			  SUPPLIER_BRANCH@standby_link
			WHERE
			  spb_created_date BETWEEN TO_DATE('01-JAN-" . $this->params['yyyy'] . "') AND TO_DATE('01-JAN-" . $this->params['yyyy'] . "') + 365
			GROUP BY
			  TO_CHAR(spb_created_date, 'YYYYWW')
			ORDER BY 
			  TO_CHAR(spb_created_date, 'YYYYWW') ASC
		";	
		return $this->getData($sql);
		
	}

	public function getBuyerCreatedStats()
	{
		$sql = "
			SELECT
			  TO_CHAR(byb_created_date, 'YYYYWW') WEEK_STR
			  , COUNT(*) TOTAL
			FROM
			  BUYER_BRANCH@standby_link
			WHERE
			  byb_created_date BETWEEN TO_DATE('01-JAN-" . $this->params['yyyy'] . "') AND TO_DATE('01-JAN-" . $this->params['yyyy'] . "') + 365
			GROUP BY
			  TO_CHAR(byb_created_date, 'YYYYWW')
			ORDER BY
			  TO_CHAR(byb_created_date, 'YYYYWW') ASC
		";
		return $this->getData($sql);
	
	}
	
	public function getStatisticAverageOfQuoteRate()
	{
		// getting the stats of the forwarded RFQ
		$forwarded = $this->getStatisticOfTotalForwardedRfq();
		
		// getting stats of quote received
		$quoted = $this->getStatisticOfTotalQuoteReceived();
		
		// processing it
		foreach ($forwarded as $buyerId => $d)
		{
			foreach( $d as $week => $total )
			{
				$x['forwarded'][$week] += $total;
			}
		}
		foreach ($quoted as $buyerId => $d)
		{
			foreach( $d as $week => $total )
			{
				$x['quoted'][$week] += $total;
			}
		}
		$output = array();
		foreach($x['forwarded'] as $week => $total)
		{
			if( $total == null || $x['quoted'][$week] == null )
			{
				$output[$week] = null;
			}
			else
			{
				$output[$week] =
				($total==0 || $x['quoted'][$week] == 0 )
				? 0: round($x['quoted'][$week]/$total * 100);
				
			}
		}
		
		return $output;
	}
}