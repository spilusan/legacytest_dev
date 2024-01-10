<?php
class Shipserv_User_AccountManager extends Shipserv_Object{
	
	public function __construct( $userId, $period )
	{
		$this->user = Shipserv_User::getInstanceById($userId);
		$this->period = $period;
		$this->tnids = $this->getAccounts();
		$this->dataForRFQInbox = $this->getDataForRFQInbox();
	}
	
	public function getAccounts()
	{
		$sql = "SELECT spb_branch_code FROM supplier_branch WHERE spb_acct_mngr_email=:email";
		$this->getDb()->setFetchMode(Zend_Db::FETCH_NUM);
		$rows = $this->getDb()->fetchAll($sql, array('email' => $this->user->email));
		foreach( $rows as $row )
		{
			$data[] = $row[0];
		}
		return $data;
	}

	public function getDataForRFQInbox()
	{
		$sql = "
    SELECT 
      result.*,
      result.sent - result.read - result.declined - result.replied NOT_CLICKED,
      
      CASE 
        WHEN ( result.read > 0 OR result.declined > 0 OR result.replied > 0 ) AND result.sent > 0 THEN
        ROUND((result.read + result.declined + result.replied ) / result.sent * 100)
      WHEN result.read = 0 OR result.sent = 0 THEN
        0
        END AS OPEN_RATE,
      CASE 
        WHEN result.read > 0 AND result.sent > 0 THEN
              ROUND((result.sent - result.read - result.declined - result.replied) / result.sent * 100)
          WHEN result.read = 0 OR result.sent = 0 THEN
              0
      END AS IGNORED_RATE
              
    FROM
    (

      SELECT
        assigned_supplier.spb_branch_code tnid,
        assigned_supplier.spb_name name,
        (
        SELECT
          COUNT(*)
        FROM
          PAGES_INQUIRY,
          PAGES_INQUIRY_RECIPIENT
        WHERE
          PIR_SPB_BRANCH_CODE = assigned_supplier.spb_branch_code 
          AND PIR_PIN_ID = PIN_ID
          AND PIR_RELEASED_DATE BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate) + 0.99999
          AND not exists (
            select 1 from pages_statistics_email
            where lower(pin_email) like  '%' || lower(pse_email_phrase) || '%'
          )
          
        ) SENT,
        
        (
        SELECT
          COUNT(*)
        FROM
          PAGES_INQUIRY,
          PAGES_INQUIRY_RECIPIENT
        WHERE
          PIR_SPB_BRANCH_CODE = assigned_supplier.spb_branch_code 
          AND PIR_PIN_ID = PIN_ID
          AND PIR_RELEASED_DATE BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate) + 0.99999
          AND PIR_IS_READ IS NOT NULL
          AND PIR_IS_REPLIED IS NULL
          AND PIR_IS_DECLINED IS NULL
          AND not exists (
            select 1 from pages_statistics_email
            where lower(pin_email) like  '%' || lower(pse_email_phrase) || '%'
          )
          
        ) READ,
        
        (
        SELECT
          COUNT(*)
        FROM
          PAGES_INQUIRY,
          PAGES_INQUIRY_RECIPIENT
        WHERE
          PIR_SPB_BRANCH_CODE = assigned_supplier.spb_branch_code 
          AND PIR_PIN_ID = PIN_ID
          AND PIR_RELEASED_DATE BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate) + 0.99999
          AND PIR_IS_REPLIED IS NOT NULL
          AND PIR_IS_READ IS NOT NULL
          AND not exists (
            select 1 from pages_statistics_email
            where lower(pin_email) like  '%' || lower(pse_email_phrase) || '%'
          )
          
        ) REPLIED,
        
        (
          SELECT
            COUNT(*)
          FROM
            PAGES_INQUIRY,
            PAGES_INQUIRY_RECIPIENT
          WHERE
            PIR_SPB_BRANCH_CODE = assigned_supplier.spb_branch_code 
            AND PIR_PIN_ID = PIN_ID
            AND PIR_RELEASED_DATE BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate) + 0.99999
            AND PIR_IS_DECLINED IS NOT NULL
            AND not exists (
              select 1 from pages_statistics_email
              where lower(pin_email) like  '%' || lower(pse_email_phrase) || '%'
          )
        ) DECLINED
      
      FROM 
      
        (
          SELECT 
            spb_branch_code 
            , spb_name
          FROM 
            supplier_branch 
          WHERE 
            spb_acct_mngr_email=:email
            AND spb_test_account = 'N'
            AND spb_account_deleted = 'N'
            AND spb_branch_code <= 999999
            AND spb_acct_mngr_email IS NOT null
            AND directory_listing_level_id=4
        ) assigned_supplier

    ) result
		";

		if( $rowNum != null )
		{
			$sql = "SELECT * FROM ($sql) WHERE ROWNUM <= " . $rowNum;
		}
		
		$this->getDb()->setFetchMode(Zend_Db::FETCH_ASSOC);
		$rows = $this->getDb()->fetchAll($sql, array(	'email' => $this->user->email
														, 'endDate' => $this->period['start']->format('d M Y') 
														, 'startDate' => $this->period['end']->format('d M Y')));
		return $rows;		
	}
	
	public function getWorstRFQOrderBy( $type )
	{
		if( !in_array($type, array('NOT_CLICKED', 'SENT', 'READ', 'REPLIED', 'DECLINED', 'OPEN_RATE', 'IGNORED_RATE')) )
		{
			throw new Myshipserv_Exception_MessagedException( $type . " is not supported");	
		}
		$data = $this->dataForRFQInbox;
		self::aasort( $data, $type );
		return $data;
	}

	public function getWorstPCS( $rowNum = null )
	{
		$sql = "
		SELECT
			tmp.*
	        , (
	        SELECT
	          COUNT(*)
	        FROM
	          PAGES_INQUIRY,
	          PAGES_INQUIRY_RECIPIENT
	        WHERE
	          PIR_SPB_BRANCH_CODE = tmp.TNID 
	          AND PIR_PIN_ID = PIN_ID
	          AND PIR_RELEASED_DATE BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate) + 0.99999
	          AND not exists (
	            select 1 from pages_statistics_email
	            where lower(pin_email) like  '%' || lower(pse_email_phrase) || '%'
	          )
	        ) SENT
			
		FROM
		(
          SELECT 
            spb_branch_code TNID
            , spb_name NAME
            , spb_pcs_score SCORE            
          FROM 
            supplier_branch 
          WHERE 
            spb_acct_mngr_email=:email
            AND spb_test_account = 'N'
            AND spb_account_deleted = 'N'
            AND spb_branch_code <= 999999
            AND spb_acct_mngr_email IS NOT null
            AND spb_pcs_score IS NOT null
            AND directory_listing_level_id=4
		  ORDER BY
		  	spb_pcs_score ASC
		) tmp
		";	
		
		if( $rowNum != null )
		{
			$sql = "SELECT * FROM ($sql) WHERE ROWNUM <= " . $rowNum;
		}
		
		$params = array(	'email' => $this->user->email
							, 'endDate' => $this->period['start']->format('d M Y') 
							, 'startDate' => $this->period['end']->format('d M Y'));
							
		$rows = $this->getDb()->fetchAll($sql, $params);
		return $rows;		
		
	}
	
	public static function aasort (&$array, $key) 
	{
	    $sorter=array();
	    $ret=array();
	    reset($array);
	    foreach ($array as $ii => $va) {
	        $sorter[$ii]=$va[$key];
	    }
	    arsort($sorter);
	    foreach ($sorter as $ii => $va) {
	        $ret[$ii]=$array[$ii];
	    }
	    $array=$ret;
	}
	
}
