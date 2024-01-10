<?php

class Shipserv_Oracle_Enquiry extends Shipserv_Oracle
{
	protected $db;
	
	public function __construct ($db = null)
	{
		if( $db === null )
		{
			$this->db = $this->getDb();
		}
		else 
		{
			$db = parent::__construct($db);
		}
	}
	
	public function storeBlockedBuyer($userId, $tnid, $status = "Y")
	{
		if( $userId == "" || $tnid == "" || $status == "" )
		{
			throw new Exception("Please check your parameter", 404);
		}
		$sql = "
			MERGE INTO pages_inquiry_banned_user USING DUAL ON (PBL_PSU_ID = :userId AND PBL_SPB_BRANCH_CODE = :tnid)
			  WHEN MATCHED THEN
			    UPDATE SET PBL_STATUS = :status, PBL_DATE_UPDATED=SYSDATE
			  WHEN NOT MATCHED THEN
			    INSERT (PBL_SPB_BRANCH_CODE, PBL_PSU_ID, PBL_STATUS, PBL_DATE_UPDATED, PBL_DATE_CREATED) VALUES(:tnid, :userId, :status, SYSDATE, SYSDATE)		
		";
		
		$sqlData = array(	"tnid" => $tnid,
							"userId" => $userId,
							"status" => $status );
		
		$this->db->query( $sql, $sqlData );
	}
	
	public function deleteBlockedUser( $tnid, $userId )
	{

		$db = $this->db;
		$sql = "UPDATE pages_inquiry_banned_user set PBL_STATUS = 'N', PBL_DATE_UPDATED=SYSDATE WHERE pbl_spb_branch_code=:tnid AND pbl_psu_id=:userId";
		//$sql = "DELETE FROM pages_inquiry_banned_user WHERE pbl_spb_branch_code=:tnid AND pbl_psu_id=:userId";
		
		$db->beginTransaction();
		try{	
			$db->query( $sql, array('tnid' => $tnid, 'userId' => $userId));
			$db->commit();
  			return true;
		}
		catch( ControlPanel_Exception $e )
		{
			throw $e;
			//$db->rollback();
			return false;
		}
		
	}
	
	public function getSenderUserIdByInquiryId( $inquiryId )
	{
		$sql = "SELECT PIN_USR_USER_CODE FROM pages_inquiry WHERE pin_id=:inquiryId";
		return $this->db->fetchAll($sql, array("inquiryId" => $inquiryId));
	}
	
	public function getBlockedUserBySupplierId( $tnid )
	{
		$sql = "
			SELECT
				pbl_id,
				pbl_psu_id,
				pbl_spb_branch_code,
				pbl_status,
				Coalesce(psu_firstname, 'N/A') psu_firstname,
				Coalesce(psu_lastname,'N/A') psu_lastname,
				psu_email,
				Coalesce(psu_company, 'N/A') psu_company,
				LOWER(TO_CHAR(PBL_DATE_UPDATED, 'MM/DD/YY')) PBL_DATE_CREATED
			FROM
				pages_inquiry_banned_user
				JOIN pages_user ON pbl_psu_id = psu_id
			WHERE
				pbl_spb_branch_code=:tnid
				AND 
				pbl_status = 'Y'
		";
		$results = $this->db->fetchAll($sql, array("tnid" => $tnid));
		return $results;
	}
	
	public function fetchByTnid ($tnid, $start, $total, &$totalFound, $period = null)
	{
		$start--;
		
		$sql = "
			SELECT 
				pages_inquiry.*,
        		pages_inquiry_recipient.*,
        		TO_CHAR(pin_creation_date, 'YYYY/MM/DD HH24:MI:SS') pir_creation_date_full,
        		ROUND(SYSDATE - pin_creation_date) date_diff_from_today
			FROM
				pages_inquiry, 
				pages_inquiry_recipient
			WHERE
				pin_id=pir_pin_id
				AND PIR_SPB_BRANCH_CODE=:tnid
						
		";

			$sql .= "
		        AND not exists (
		              select 1 from pages_statistics_email
		              where lower(pin_email) like  '%' || lower(pse_email_phrase) || '%'
		            )
			
			";
		
		if( $period !== null )
		{
			$sql .= "
				AND PIR_RELEASED_DATE BETWEEN
				TO_DATE('" . $period['start']->format('d-M-Y') . "') AND 
				TO_DATE('" . $period['end']->format('d-M-Y') . "') + 1 
			";	
		}
		
		$sql .= "
			ORDER BY
				PIR_RELEASED_DATE DESC
		";
		
		$sql = "
		SELECT * FROM
		(
			SELECT ROWNUM rn, x.* 
			FROM ( $sql ) x
		)
		";

		$totalData = $this->db->fetchAll("SELECT COUNT(*) TOTAL FROM ($sql)", array('tnid'=>$tnid));
		$totalFound = (int) $totalData[0]['TOTAL'];
		
		if( $start !== null && $total !== null )
		{
			if( $start == 0 )
			{
				$sql .= "
						WHERE rn > " . $start * $total . " AND rn <= "  . ( ( ( $start + 1 ) * $total) ). "
				";
			}
			else 
			{
				$sql .= "
						WHERE rn >= " . (($start * $total) + 1) . " AND rn <= "  . ( ( ( $start + 1 ) * $total)  ). "
				";
			}
		}
		
		$res = $this->db->fetchAll($sql, array('tnid'=>$tnid));
		return $res;
	}
	
	public function fetchByEnquiryRecipientId($id)
	{
		$sql = "
		SELECT
			TO_CHAR(pin_creation_date, 'YYYY/MM/DD HH24:MI:SS') pir_creation_date_full,
			ROUND(SYSDATE - pin_creation_date) date_diff_from_today,
			pages_inquiry.*,
			pages_inquiry_recipient.*
		FROM
			pages_inquiry, 
			pages_inquiry_recipient
		WHERE
			pir_id=:id
			AND pir_pin_id=pin_id
		";
		
		return $this->db->fetchAll($sql, array('id'=>$id));
	}
	
	public function fetchByEnquiryIdAndTnid($id, $tnid)
	{
		$sql = "
			SELECT 
				TO_CHAR(pin_creation_date, 'YYYY/MM/DD HH24:MI:SS') pir_creation_date_full,
				ROUND(SYSDATE - pin_creation_date) date_diff_from_today,
				pages_inquiry.*,
				pages_inquiry_recipient.*
			FROM
				pages_inquiry, 
				pages_inquiry_recipient
			WHERE
				pin_id=pir_pin_id
				AND pin_id=:id
				AND PIR_SPB_BRANCH_CODE=:tnid
		";
				
		return $this->db->fetchAll($sql, array('id'=>$id, 'tnid' => $tnid));
		
	}
	
	public function fetchById ($id)
	{
		$sql = "
			SELECT 
				TO_CHAR(pin_creation_date, 'YYYY/MM/DD HH24:MI:SS') pir_creation_date_full,
				ROUND(SYSDATE - pin_creation_date) date_diff_from_today,
				pages_inquiry.*,
				pages_inquiry_recipient.*
			FROM
				pages_inquiry, 
				pages_inquiry_recipient
			WHERE
				pin_id=pir_pin_id
				AND pir_pin_id=:id
		";
				
		return $this->db->fetchAll($sql, array('id'=>$id));
	}
	
	public function setViewedDate($enquiryId, $tnid, $userId)
	{
		$sql = "
			UPDATE
				pages_inquiry_recipient
			SET
				pir_is_read = 1,
				pir_read_date = SYSDATE
			WHERE
			 	pir_pin_id=:enquiryId
				AND PIR_SPB_BRANCH_CODE=:tnid
		";
		$params = array('tnid' => $tnid, 'enquiryId' => $enquiryId );
		$result = $this->db->query( $sql, $params );
		
		return $result;
	}
	
	public function setDeclinedDate($enquiryId, $tnid, $userId)
	{
		$sql = "
			UPDATE
				pages_inquiry_recipient
			SET
				PIR_DECLINED_DATE = SYSDATE,
				PIR_IS_DECLINED = 1,
				PIR_DECLINE_SOURCE = 'VIEW'
			WHERE
			 	pir_pin_id=:enquiryId
				AND PIR_SPB_BRANCH_CODE=:tnid
				";
		$params = array('tnid' => $tnid, 'enquiryId' => $enquiryId );
		return $this->db->query( $sql, $params );
	}
	
	public function setRepliedDate($enquiryId, $tnid, $userId)
	{
		$sql = "
			UPDATE
				pages_inquiry_recipient
			SET
				PIR_DECLINED_DATE = null,
				PIR_IS_DECLINED = null,
				PIR_DECLINE_SOURCE = null,
				
				pir_is_replied = 1,
				pir_replied_date = SYSDATE,
				pir_replied_by = :userId
			WHERE
			 	pir_pin_id=:enquiryId
				AND PIR_SPB_BRANCH_CODE=:tnid
		
		";
		$params = array('tnid' => $tnid, 'userId' => $userId, 'enquiryId' => $enquiryId );
		return $this->db->query( $sql, $params );
	}
	
	public function getAttachments($enquiryId)
	{
		$sql = "SELECT 
				  ATTACHMENT_FILE.*
				FROM
				  ATTACHMENT_TXN,
				  ATTACHMENT_FILE
				WHERE
				  ATT_TRANSACTION_TYPE='PIN'
				  AND ATT_TRANSACTION_ID=:enquiryId
				  AND ATT_ATF_ID=ATF_ID		";
		return $this->db->fetchAll($sql, array('enquiryId'=>$enquiryId));
	}

	public function getRecipient( $enquiryId )
	{
		$sql = "
			SELECT
				pir_spb_branch_code
			FROM
				pages_inquiry_recipient
			WHERE
				pir_pin_id=:enquiryId
		";
		return $this->db->fetchAll($sql, array('enquiryId'=>$enquiryId));		
	}
}

