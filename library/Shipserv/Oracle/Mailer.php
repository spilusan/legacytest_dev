<?php
/**
 * This class handles all survey that is available on pages
 * @author Elvir <eleonard@shipserv.com>
 *
 */
class Shipserv_Oracle_Mailer extends Shipserv_Oracle
{
	protected $db;
	
	function __construct( $db )
	{
		$this->db = $db;
	}
	
	public function fetchById ($id)
	{
		$sql = "SELECT * FROM pages_mailer_campaign WHERE pmc_id=:id";
		$sqlData = array('id' => $id);
		return $this->db->fetchAll($sql, $sqlData);
	}
	
	public function getNextRecipient($rownum, $campaignId = null, $error = false, $checkOptedOutOnSalesforce = true)
	{
	    $sql = '';
	    $params = array();
		if ($campaignId != null) {
			$sql = "SELECT pmr_email, pmr_tnid, pmr_data_source, pmr_pmc_id, pmr_psu_id, pmr_is_trusted FROM (SELECT * FROM pages_mailer_recipient WHERE pmr_pmc_id=" . $campaignId . " ORDER BY dbms_random.value) WHERE rownum<=" . $rownum . " AND pmr_pmc_id=" . $campaignId;
			if( $checkOptedOutOnSalesforce === true )
			{
				$sql .= " AND pmr_email NOT IN(SELECT SEO_EMAIL FROM SALESFORCE_EMAIL_OPTOUT)";
			}
		} else  {
			$sql = "SELECT pmr_email, pmr_tnid, pmr_data_source, pmr_pmc_id, pmr_psu_id, pmr_is_trusted FROM pages_mailer_recipient WHERE rownum<=" . $rownum . "";
			if( $checkOptedOutOnSalesforce === true )
			{
				$sql .= " AND pmr_email NOT IN(SELECT SEO_EMAIL FROM SALESFORCE_EMAIL_OPTOUT)";
			}
		}
		
		if ($error == true) {
			$sql .= " AND pmr_error_message IS NOT null";
		} else {
			$sql .= " AND pmr_date_sent IS null";
		}
		
		if (isset($_GET['testing'])) {
			// testing pages user
			if ($_GET['testing'] == 'pages') {
                $sql .= " AND pmr_psu_id IS NOT NULL";
			
			// testing pages enquiry
            } else if( $_GET['testing'] == 'enquiry') {
                $sql .= " AND pmr_data_source='ENQUIRY'";
			
			// testing salesforce not trusted
            } else if ($_GET['testing'] == 'salesforce-not-trusted') {
			    $sql .= " AND pmr_data_source='SALESFORCE' AND pmr_is_trusted IS NULL";
			
			// testing salesforce not trusted
		    } else if ($_GET['testing'] == 'salesforce-trusted') {
                $sql .= " AND pmr_data_source='SALESFORCE' AND pmr_is_trusted=1";
			
			} else if ($_GET['TESTING'] != '') {
			    $params[] = $_GET['TESTING'];
                $sql .= " AND pmr_data_source = ? ";
			}
			
		}
		
		if (isset($_GET['tnid'])) {
		    $params[] = $_GET['tnid'];
			$sql .= " AND pmr_tnid = ?";
		}
	
		return $this->db->fetchAll($sql, $params);
	}
		
		
	public function setTimestamp($type, $key, $campaignId = null, $tnid = null)
	{
		$sql = "UPDATE pages_mailer_recipient SET ";
		if ($type == "sent") {
			$sql .= " pmr_date_sent=SYSDATE ";	
		} else if ($type == "read") {
			$sql .= " pmr_date_read=SYSDATE ";
		} else if ($type == "error") {
			$sql .= " pmr_error_message=null ";
		} else  {
			throw new Exception("Mailer error: Incorrect type when updating the timestamp", 500);
		}
		
		if (ctype_digit($key)) {
			$sql .= " WHERE pmr_id=:key";
		} else  {
			$sql .= " WHERE pmr_email=:key";
		}

		if ($campaignId != null) {
			$sql .= " AND pmr_pmc_id=" . $campaignId;	
		}
		
		if ($tnid != null) {
			$sql .= " AND pmr_tnid=" . $tnid;	
		}
		
		$sqlData = array( "key" => $key );
		
		if (isset( $_GET['do-not-mark-as-sent'] ) && $_GET['do-not-mark-as-sent'] == 1) {
			return;
		}
		
		return $this->db->query($sql, $sqlData);
	}
	
	public function setError($error, $email, $campaignId)
	{
		$sql = "UPDATE pages_mailer_recipient SET ";
		$sql .= " pmr_error_message=:error ";
		$sql .= " WHERE pmr_email=:email AND pmr_pmc_id=:campaignId";

		$sqlData = array( "error" => $error, "email" => $email, "campaignId" => $campaignId );
		return $this->db->query($sql, $sqlData);
	}

}
