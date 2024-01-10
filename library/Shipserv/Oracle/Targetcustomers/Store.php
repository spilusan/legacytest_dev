<?php
/**
* Return the aditionl buyer related columns for a row
*/

class Shipserv_Oracle_Targetcustomers_Store {

	/**
    * @var Singleton The reference to *Singleton* instance of this class
    */
    private static $instance;
    protected $db;
    protected $spbBranchCode;


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

    /**
    * Store the satus of Max Quotes per Buyer
    *
	*
	*/
    public function storeMaxQuotesPerBuyer( $spbBranchCode,  $params )
    {

    	$this->spbBranchCode = (int)$spbBranchCode;

        if (array_key_exists('max', $params) && array_key_exists('status', $params)) {
    		$this->storeMax((int)$params['max'], $params['status']);
    	}

		return true;
    }


    protected function storeMax( $max, $status )
    {
        $maxQot = ($max === 0) ? null : $max;

        $statusFlag = (strtolower($status) === 'true' || $status === '1') ? 1 : 0;

        $sql = "
            UPDATE 
                supplier_branch
            SET 
                spb_promotion_max_quotes = :maxQot
                ,spb_promotion_check_quotes =:status

            WHERE
                spb_branch_code = :spbBranchCode
            ";

		$params = array(
				  'spbBranchCode' => $this->spbBranchCode
				, 'maxQot' => $maxQot
                , 'status' => $statusFlag
			);

		$this->db->query( $sql, $params);
    }



    /**
    * Store the targeting states
    * @param array $param 
    * @return result ok, or error, for the JSON will be returned
    */
     public function storeUserTargetInfo( $users, $spbBranchCode, $params )
     {

        $hasNotification = array_key_exists('notification', $params);
        $hasAllowtarget = array_key_exists('allowtarget', $params);

        foreach ($users['approved'] as $userId => $user) {
            if ($user['roles']['administrator'] == true)  {
                $notifications = ($hasNotification) ? (int)in_array($userId, $params['notification']) : 0;
                $canTarget = ($hasAllowtarget) ? (int)in_array($userId, $params['allowtarget']) : 0;
                $this->storeUserTargetInfoPerUser($userId, $spbBranchCode, $notifications, $canTarget);
            }
        }

     	return 'ok';

     }

     /**
     * Store the status for a specific user, notifiation, cantarget have to contain 0 or 1
     * @param int $userId
     * @param int $notification
     * @param int $canTarget
     */
     public function storeUserTargetInfoPerUser( $userId, $spbBranchCode, $notification , $canTarget )
     {

     	$sql = "
     		MERGE INTO 
     			pages_user_target USING DUAL ON (PUT_PSU_ID=:psuId AND PUT_SPB_BRANCH_CODE = :spbBranchCode)
     		WHEN NOT MATCHED THEN
     			INSERT (
 					  put_psu_id
                    , put_spb_branch_code
 					, put_target_notification
 					, put_target_can_target
     				)
				VALUES (
						  :psuId
                        , :spbBranchCode
						, :notification
						, :canTarget
					)
			WHEN MATCHED THEN
				UPDATE SET
					  put_target_notification = :notification
 					, put_target_can_target = :canTarget
     	";

     	$params = array(
     			'psuId'  => (int)$userId,
                'spbBranchCode'  => (int)$spbBranchCode,
     			'notification'  => $notification,
     			'canTarget'  => $canTarget,

     		);

        $this->db->query( $sql, $params);
     	
     }

   /**
   * Store the status for a specific user, notifiation, have to contain 0 or 1
   * @param int $userId
   * @param int $notification
   */

   public function storeUserNotificationInfoPerUser( $userId, $spbBranchCode, $notification )
   {
    $sql = "
            MERGE INTO 
                pages_user_target USING DUAL ON (PUT_PSU_ID=:psuId AND PUT_SPB_BRANCH_CODE = :spbBranchCode)
            WHEN NOT MATCHED THEN
                INSERT (
                      put_psu_id
                    , put_spb_branch_code
                    , put_target_notification
                    )
                VALUES (
                          :psuId
                        , :spbBranchCode  
                        , :notification
                    )
            WHEN MATCHED THEN
                UPDATE SET
                      put_target_notification = :notification
        ";

        $params = array(
                'psuId'  => (int)$userId,
                'spbBranchCode'  => (int)$spbBranchCode,
                'notification'  => $notification,
            );

        $this->db->query( $sql, $params);
   }

   /**
   * Store the status for a specific user, cantarget have to contain 0 or 1
   * @param int $userId
   * @param int $canTarget
   */
   public function storeUserCanTargetPerUser( $userId, $spbBranchCode, $canTarget )
   {
    $sql = "
            MERGE INTO 
                pages_user_target USING DUAL ON (PUT_PSU_ID=:psuId AND PUT_SPB_BRANCH_CODE = :spbBranchCode)
            WHEN NOT MATCHED THEN
                INSERT (
                      put_psu_id
                    , put_spb_branch_code
                    , put_target_can_target
                    )
                VALUES (
                          :psuId
                        , :spbBranchCode
                        , :canTarget
                    )
            WHEN MATCHED THEN
                UPDATE SET
                    put_target_can_target = :canTarget
        ";

        $params = array(
                'psuId'  => (int)$userId,
                'spbBranchCode'  => (int)$spbBranchCode,
                'canTarget'  => $canTarget,
            );

        $this->db->query( $sql, $params);
   }

}