<?php
/**
* Return the user settins
*/

class Shipserv_Oracle_Targetcustomers_Usersetting {

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


    /**
    * Return the status, The user can get notification, can target/exclude
    * @param int $psuId
    * @return array
    */
    public function getData( $psuId, $spbBranchCode )
    {

        $sql = "
            SELECT
                put_target_notification
              , put_target_can_target
            FROM
              pages_user_target
            WHERE
              put_psu_id = :psuId
              AND put_spb_branch_code = :spbBranchCode 
        ";

        $result = $this->db->fetchAll($sql,array(
                  'psuId' => (int)$psuId
                , 'spbBranchCode' => (int)$spbBranchCode
            ));

        if (count($result) > 0) {
            return array(
                    'notification' => $result[0]['PUT_TARGET_NOTIFICATION'],
                    'canTarget' => $result[0]['PUT_TARGET_CAN_TARGET']
                );
        } else {
            return array(
                    'notification' => '1',
                    'canTarget' => '1'
                );      
        }

    }

}