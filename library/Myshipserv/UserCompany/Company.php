<?php

class Myshipserv_UserCompany_Company
{
	const TYPE_SPB = 'SPB';
	const TYPE_BYO = 'BYO';
	const TYPE_BYB = 'BYB';
    const TYPE_CON = 'CON';

	
	const STATUS_ACTIVE = 'ACT';
	const STATUS_INACTIVE = 'INA';
	const STATUS_DELETED = 'DEL';
	const STATUS_PENDING = 'PEN';
	
	const LEVEL_USER = 'USR';
	const LEVEL_ADMIN = 'ADM';
	
	private $type;
	private $id;
	private $status;
	private $level;
	private $isDefault;
	

	private $canAccessTxnMon;
	private $canAccessWebReporter;
	private $canAccessMatch;
	private $canAccessBuyTab;
	private $canAccessApprovedSupplier;

	private $canAccessTransactionMonitorAdm;
	private $canAccessAutmaticReminder;
	private $isDeadlineManager;

	
	public function __construct($type, $id, $status, $level, $isDefault=0, $canAccessTxnMon=0, $canAccessWebReporter=0, $canAccessMatch = 0, $canAccessBuyTab = 0, $canAccessApprovedSupplier = 0 , $canAccessTransactionMonitorAdm = 0, $canAccessAutmaticReminder = 0, $isDeadlineManager = 0)
	{
		$this->type = (string) $type;
		$this->id = (int) $id;
		$this->status = (string) $status;
		$this->level = (string) $level;
		$this->isDefault = ($isDefault==1);
		$this->canAccessTxnMon = ($canAccessTxnMon==1);
		$this->canAccessWebReporter = ($canAccessWebReporter==1);
		$this->canAccessMatch = ($canAccessMatch==1);
		$this->canAccessBuyTab = ($canAccessBuyTab==1);
		$this->canAccessApprovedSupplier = ($canAccessApprovedSupplier==1);
		$this->canAccessTransactionMonitorAdm = ($canAccessTransactionMonitorAdm==1);
		$this->canAccessAutmaticReminder = ($canAccessAutmaticReminder==1);
		$this->isDeadlineManager = ($isDeadlineManager==1);
	}
	
	public static function isTypeValid($type)
	{
		return in_array($type, array(
			self::TYPE_BYO,
			self::TYPE_BYB,
			self::TYPE_SPB,
            self::TYPE_CON,
		));
	}
	
	public static function isStatusValid($status)
	{
		return in_array($status, array(
			self::STATUS_ACTIVE,
			self::STATUS_INACTIVE,
			self::STATUS_DELETED,
			self::STATUS_PENDING,
		));
	}
	
	public static function isLevelValid($level)
	{
		return in_array($level, array(
			self::LEVEL_USER,
			self::LEVEL_ADMIN,
		));
	}
	
	public function getType()
	{
		return $this->type;
	}
	
	public function getId()
	{
		return $this->id;
	}
	
	public function getStatus()
	{
		return $this->status;
	}
	
	public function getLevel()
	{
		return $this->level;
	}
	

	/**
	 * @param string $checkingStatus
	 * @return boolean
	 */
	public function canAccessWebReporter($checkingStatus = true)
	{
	    if ($checkingStatus) {
	        return ($this->canAccessWebReporter && $this->getStatus() == self::STATUS_ACTIVE );
	    } else {
	        return $this->canAccessWebReporter;
	    }
		
	}
	
	/**
	 * @param string $checkingStatus
	 * @return boolean
	 */	
	public function canAccessTxnMon($checkingStatus = true)
	{
	    if ($checkingStatus) {
	        return ($this->canAccessTxnMon && $this->getStatus() == self::STATUS_ACTIVE);
	    } else {
	        return $this->canAccessTxnMon;
	    }
		
	}

	/**
	 * @param string $checkingStatus
	 * @return boolean
	 */
	public function canAccessMatch($checkingStatus = true)
	{
	    if ($checkingStatus) {
	        return ($this->canAccessMatch && $this->getStatus() == self::STATUS_ACTIVE);
	    } else {
	        return $this->canAccessMatch;
	    }
	}

	/**
	 * @param string $checkingStatus
	 * @return boolean
	 */
	public function canAccessBuyTab($checkingStatus = true)
	{
	    if ($checkingStatus) {
	        return ($this->canAccessBuyTab && $this->getStatus() == self::STATUS_ACTIVE);
	    } else{
	        return $this->canAccessBuyTab;
	    }
	}
	
	/**
	 * @param string $checkingStatus
	 * @return boolean
	 */
	public function canAccessApprovedSupplier($checkingStatus = true)
	{
	    if ($checkingStatus) {
	        return ($this->canAccessApprovedSupplier && $this->getStatus() == self::STATUS_ACTIVE);
	    } else {
	        return $this->canAccessApprovedSupplier;
	    }
		
	}

	public function canAccessTransactionMonitorAdm()
	{
		return ($this->canAccessTransactionMonitorAdm && $this->getStatus() == self::STATUS_ACTIVE);
	}

	public function canAccessAutmaticReminder()
	{
		return ($this->canAccessAutmaticReminder && $this->getStatus() == self::STATUS_ACTIVE);
	}

	public function isDeadlineManager()
	{
		return ($this->isDeadlineManager && $this->getStatus() == self::STATUS_ACTIVE);
	}

	public function isDefault()
	{
		return $this->isDefault;
	}
}
