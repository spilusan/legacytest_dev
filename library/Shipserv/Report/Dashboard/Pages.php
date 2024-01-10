<?php
/**
 *
 */
class Shipserv_Report_Dashboard_Pages extends Shipserv_Report_Dashboard
{		
	public function getData( $rolledUp = false )
	{
			$data = $this->getStatisticFromSSReport2Db();
		
		foreach($data as $row)
		{
			$new[$row['TXN_TYPE']][$row['GROUPING_STRING']] = $row['TOTAL'];
		}
				

		$data = $this->getStatisticFromSservdbaDb();
		
		foreach($data as $row)
		{
			$new[$row['TXN_TYPE']][$row['GROUPING_STRING']] = $row['TOTAL'];
		}
				
		return $new;
		
	}
	
	protected function getStatisticFromSSReport2Db()
	{
		$sql = "
		
WITH base_data AS
(
	SELECT
	  'Supplier Page Impressions' txn_type
	  , TO_CHAR(TO_DATE(TO_CHAR(dpi_view_date), 'YYYYMMDD'), 'YYYY-MON') grouping_string
	  , SUM(dpi_impression_count) TOTAL
	FROM
	  pages_impression_daily_stats
	WHERE
	  dpi_view_date BETWEEN TO_NUMBER(TO_CHAR(TO_DATE('01-JAN-" . $this->params['yyyy'] . "'),'YYYYMMDD'))
	    AND TO_NUMBER(TO_CHAR(TO_DATE('01-JAN-" . $this->params['yyyy'] . "') + 365,'YYYYMMDD'))
	GROUP BY
	  TO_CHAR(TO_DATE(TO_CHAR(dpi_view_date), 'YYYYMMDD'), 'YYYY-MON')
	 
	UNION ALL
		
	SELECT
	  'Contact Page Views' txn_type
	  , TO_CHAR(pss_view_date, 'YYYY-MON') grouping_string
	  , COUNT(*)
	FROM
	  pages_impression_stats
	WHERE
	  pss_view_date BETWEEN TO_DATE('01-JAN-" . $this->params['yyyy'] . "') AND TO_DATE('01-JAN-" . $this->params['yyyy'] . "') + 365
	  AND pss_contact_viewed=1
	GROUP BY
	  TO_CHAR(pss_view_date, 'YYYY-MON')
			
)
SELECT
  *
FROM
  base_data
		";
		$db = $this->getDbByType('ssreport2');
		$key = __CLASS__ . md5($sql) . print_r($this->params, true);
		$data = $this->fetchCachedQuery ($sql, $params, $key, (60*60*2), 'ssreport2');
		
		return $data;
	}

	protected function getStatisticFromSServdbaDb()
	{
		$sql = "
	
WITH base_data AS
(
SELECT
  'TradeNet ID requests' txn_type
  , TO_CHAR(pss_view_date, 'YYYY-MON') grouping_string
  , COUNT(*) total
FROM
  pages_statistics_supplier
WHERE
  pss_view_date BETWEEN TO_DATE('01-JAN-" . $this->params['yyyy'] . "') AND TO_DATE('01-JAN-" . $this->params['yyyy'] . "') + 365
  AND PSS_TNID_VIEWED=1
GROUP BY
  TO_CHAR(pss_view_date, 'YYYY-MON')


UNION ALL


SELECT
  'New Registrations' txn_type
  , TO_CHAR(psu_creation_date, 'YYYY-MON') grouping_string
  , COUNT(*) total
FROM
  pages_user
WHERE
  psu_creation_date BETWEEN TO_DATE('01-JAN-" . $this->params['yyyy'] . "') AND TO_DATE('01-JAN-" . $this->params['yyyy'] . "') + 365
GROUP BY
  TO_CHAR(psu_creation_date, 'YYYY-MON')


UNION ALL

  
  
SELECT
  'Review Request Sent' txn_type
  , TO_CHAR(pue_requested_date, 'YYYY-MON') grouping_string
  , COUNT(DISTINCT PUE_ID) total
FROM
  pages_user_endorsement
WHERE
  pue_requested_date BETWEEN TO_DATE('01-JAN-" . $this->params['yyyy'] . "') AND TO_DATE('01-JAN-" . $this->params['yyyy'] . "') + 365
GROUP BY
  TO_CHAR(pue_requested_date, 'YYYY-MON')

UNION ALL

SELECT
  'Reviews Submitted' txn_type
  , TO_CHAR(pue_created_date, 'YYYY-MON') grouping_string
  , COUNT(DISTINCT PUE_ID) total
FROM
  pages_user_endorsement
WHERE
  pue_created_date BETWEEN TO_DATE('01-JAN-" . $this->params['yyyy'] . "') AND TO_DATE('01-JAN-" . $this->params['yyyy'] . "') + 365
GROUP BY
  TO_CHAR(pue_created_date, 'YYYY-MON')
  
UNION ALL
  
SELECT
  'RFQ Events (Blocked)' txn_type
  , TO_CHAR(pin_creation_date, 'YYYY-MON') grouping_string
  , COUNT(DISTINCT PIN_ID) total
FROM
  pages_inquiry
WHERE
  pin_creation_date BETWEEN TO_DATE('01-JAN-" . $this->params['yyyy'] . "') AND TO_DATE('01-JAN-" . $this->params['yyyy'] . "') + 365
  AND pin_status='BLOCKED'
GROUP BY
  TO_CHAR(pin_creation_date, 'YYYY-MON')

UNION ALL

SELECT
  'RFQ Events (Released)' txn_type
  , TO_CHAR(pin_creation_date, 'YYYY-MON') grouping_string
  , COUNT(DISTINCT PIN_ID) total
FROM
  pages_inquiry
WHERE
  pin_creation_date BETWEEN TO_DATE('01-JAN-" . $this->params['yyyy'] . "') AND TO_DATE('01-JAN-" . $this->params['yyyy'] . "') + 365
  AND pin_status='RELEASED'
GROUP BY
  TO_CHAR(pin_creation_date, 'YYYY-MON')
						
)
SELECT
  *
FROM
  base_data
		";
		$db = $this->getDb();
		$key = __CLASS__ . md5($sql) . print_r($this->params, true);
		$data = $this->fetchCachedQuery ($sql, $params, $key, (60*60*2));
		return $data;
	}
	
}
