<?php
/**
* Return the user settins
*/

class Shipserv_Oracle_Targetcustomers_Targetinfo {

	/**
    * @var Singleton The reference to *Singleton* instance of this class
    */
    private static $instance;
    protected $db;

    /**
    * Returns the *Singleton* instance of this class.
    *
    * @return Singleton The *Singleton* instance.
    */
    public static function getInstance()
    {
        if (null === static::$instance) {
            static::$instance = new static();
        }
        
        return static::$instance;
    }

    /**
    * Protected what we have to hide
    */
    protected function __construct() {
    	$this->db = Shipserv_Helper_Database::getDb();
    }
    private function __clone()  {}


    /*
    * Get details for target list, need record id (target ID)
    */
	public function getTargetingDetails( $id )
	{
		$sql = "
			SELECT
			    bsr_byb_branch_code
			  , bsr_spb_branch_code
			  , TO_CHAR(bsr_valid_from, 'DD MON YYYY') bsr_valid_from
			  , LOWER(TO_CHAR(bsr_valid_from, 'HH12:MI AM')) bsr_valid_from_time
			  , TO_CHAR(bsr_valid_till, 'DD MON YYYY') bsr_valid_till
			  , LOWER(TO_CHAR(bsr_valid_till, 'HH24:MI:SS')) bsr_valid_till_time
			  , bsr_psu_id
			  , bsr_locked_ord_internal_ref_no
			  , bsr_status
			  , TO_CHAR(ord_created_date, 'DD MON YYYY') ord_created_date
			FROM
				buyer_supplier_rate bsr
				LEFT JOIN
				purchase_order po
				ON (po.ord_internal_ref_no = bsr.bsr_locked_ord_internal_ref_no)
			WHERE 
				bsr_id=:id
		";
		$params = array(
				'id' => (int)$id
				);

		return $this->db->fetchAll($sql, $params);
	}

	/*
	* Get email count assiciated with this supplier
	*/
	public function hasEmail( $spbBranchCode )
	{

		$params = array(
		'spbBranchCode' => (int)$spbBranchCode
		);

		$sql = "
		  SELECT
			  COUNT(put_psu_id) hasEmail
			FROM
			  pages_user_target
			WHERE
			  PUT_SPB_BRANCH_CODE = :spbBranchCode
		";

		$hasRecord = $this->db->fetchOne($sql, $params);

		if ($hasRecord == 0) {
			return 1;
		} else {
			$sql = "
			  SELECT
				  COUNT(put_psu_id) hasEmail
				FROM
				  pages_user_target
				WHERE
				  PUT_SPB_BRANCH_CODE = :spbBranchCode
				  AND put_target_notification = 1
			";

			return $this->db->fetchOne($sql, $params);
		}

	}

}