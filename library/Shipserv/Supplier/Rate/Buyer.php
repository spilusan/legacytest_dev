<?php
/**
 * A class representing buyer-supplier relationship and how their transactions are charged
 *
 * @author  Yuriy Akopov
 * @date    2016-02-09
 * @story   S15735
 */
class Shipserv_Supplier_Rate_Buyer
{
	const
		TABLE_NAME = 'BUYER_SUPPLIER_RATE',

		COL_ID       = 'BSR_ID',
		COL_BUYER    = 'BSR_BYB_BRANCH_CODE',
		COL_SUPPLIER = 'BSR_SPB_BRANCH_CODE',
		COL_STATUS   = 'BSR_STATUS',
		COL_ORDER    = 'BSR_LOCKED_ORD_INTERNAL_REF_NO',
		COL_RATE     = 'BSR_SBR_ID',
		COL_USER     = 'BSR_PSU_ID',
		COL_VALID_FROM = 'BSR_VALID_FROM',
		COL_VALID_TILL = 'BSR_VALID_TILL',
		COL_LAST_NOTIFIED = 'BSR_LAST_NOTIFIED',

		SEQUENCE_ID = 'SQ_BUYER_SUPPLIER_RATE_ID'
	;

	const
		REL_STATUS_TARGETED = 'targeted',
		REL_STATUS_EXCLUDED = 'excluded'
	;

	const
        CRON_JOB_NO_USER_ID = 0
    ;

	/**
	 * @var Shipserv_Supplier
	 */
	protected $supplier = null;

	/**
	 * @var Shipserv_User
	 */
	protected $user = null;

	/**
	 * Initialises the instance for the current supplier and Pages user (is null is provided, currently logged in user is used)
	 *
	 * @param   int $supplierId
	 * @param   int $userId
	 *
	 * @throws Shipserv_Supplier_Rate_Buyer_Exception
	 */
	public function __construct($supplierId, $userId = null)
    {
		$supplier = Shipserv_Supplier::getInstanceById($supplierId, null, true);
		if (strlen($supplier->tnid) === 0) {
			throw new Shipserv_Supplier_Rate_Buyer_Exception("Supplier " . $supplierId . " is not valid", $supplierId);
		}

		$this->supplier = $supplier;

		if (is_null($userId)) {
			$user = Shipserv_User::isLoggedIn();
			if ($user === false) {
				throw new Shipserv_Supplier_Rate_Buyer_Exception("User is not logged in to operate pricing models");
			}
		} else if ($userId === self::CRON_JOB_NO_USER_ID) {
			// cron job context
			$user = null;
		} else {
			$user = Shipserv_User::getInstanceById($userId);
		}

		$this->user = $user;
	}

	/**
	 * @return int
	 */
	public function getSupplierId()
    {
		return (int) $this->supplier->tnid;
	}

	/**
	 * @return int|null
	 */
	public function getUserId()
    {
		if (is_null($this->user)) {
			return null;
		}

		return (int) $this->user->userId;
	}

	/**
	 * @return Shipserv_Supplier_Rate
	 */
	public function getRateObj()
    {
		return new Shipserv_Supplier_Rate($this->getSupplierId());
	}

	/**
	 * @param   string  $status
	 *
	 * @return  bool
	 * @throws  Shipserv_Supplier_Rate_Buyer_Exception
	 */
	protected function _validateStatus($status)
    {
		if (!in_array(
		    $status,
            array(
			    self::REL_STATUS_EXCLUDED,
			    self::REL_STATUS_TARGETED
		    )
        )) {
			throw new Shipserv_Supplier_Rate_Buyer_Exception("Invalid status " . $status . " provided", $this->getSupplierId());
		}

		return true;
	}

	/**
	 * Terminates any currently active relationship between a buyer and supplier
	 * Returns time and date when it was terminated at or null if there were no existing relationship
	 *
	 * @param   int $buyerId
	 *
	 * @return DateTime|null
	 * @throws Shipserv_Supplier_Rate_Buyer_Exception
	 */
	protected function terminateCurrentRelationship($buyerId)
    {
		$db = Shipserv_Helper_Database::getDb();
		$now = new DateTime();

		// check if it is not an ongoing locked targeted relationship terminated
		$select = new Zend_Db_Select($db);
		$select
			->from(
				array('bsr' => self::TABLE_NAME),
				'bsr.' . self::COL_ID
			)
			->join(
				array('sbr' => Shipserv_Supplier_Rate::TABLE_NAME),
				'sbr.' . Shipserv_Supplier_Rate::COL_ID . ' = bsr.' . self::COL_RATE,
				array()
			)
			->join(
				array('ord' => Shipserv_PurchaseOrder::TABLE_NAME),
				'ord.' . Shipserv_PurchaseOrder::COL_ID . ' = bsr.' . self::COL_ORDER,
				array()
			)
			->where('bsr.' . self::COL_SUPPLIER . ' = ?', $this->getSupplierId())
			->where('bsr.' . self::COL_BUYER . ' = ?', $buyerId)
			->where('bsr.' . self::COL_STATUS . ' = ?', self::REL_STATUS_TARGETED)

			// relationships with open date can be terminated - when supplier no longer has a target rate contract
			// ->where('bsr.' . self::COL_VALID_TILL . ' IS NULL')

			// no need to check the order date - when it's locked, valid_till date will be populated for us
			/*
			->where(implode(' OR ', array(
				'(ord.' . Shipserv_PurchaseOrder::COL_DATE_SUB . ' + sbr.' . Shipserv_Supplier_Rate::COL_LOCK_TARGET . ') > ' . Shipserv_Helper_Database::getOracleDateExpr($now),
				'sbr.' . Shipserv_Supplier_Rate::COL_LOCK_TARGET . ' IS NULL'
			)))
			*/

			// so it is enough to check this
			->where('bsr.' . self::COL_VALID_FROM . ' >= ' . Shipserv_Helper_Database::getOracleDateExpr($now))
			->where('bsr.' . self::COL_VALID_TILL . ' < ' . Shipserv_Helper_Database::getOracleDateExpr($now))
		;

		$lockedId = $db->fetchOne($select);
		if (strlen($lockedId)) {
			throw new Shipserv_Supplier_Rate_Buyer_Exception("An ongoing locked relationship " . $lockedId . " with a no open date cannot be terminated!", $this->getSupplierId());
		}

		$updated = $db->update(
		    self::TABLE_NAME,
			array(
				self::COL_VALID_TILL => new Zend_Db_Expr(Shipserv_Helper_Database::getOracleDateExpr($now))
			),
			implode(
			    ' AND ',
                array(
				    $db->quoteInto(self::COL_BUYER . ' = ?', $buyerId),
				    $db->quoteInto(self::COL_SUPPLIER . ' = ?', $this->getSupplierId()),
				    self::COL_VALID_TILL . ' IS NULL'
			    )
            )
		);

		if ($updated > 0) {
			return $now;
		}

		return null;
	}

	/**
	 * Creates a TARGETED relationship with the buyer using the currently active rate
	 *
	 * @param   int $buyerId
	 *
	 * @return  int
	 * @throws  Shipserv_Supplier_Rate_Buyer_Exception
	 */
	public function addTargetedBuyer($buyerId)
    {
		$rateObj = $this->getRateObj();

		if (!$rateObj->canTargetNewBuyers()) {
			throw new Shipserv_Supplier_Rate_Buyer_Exception(
				"Supplier " . $this->getSupplierId() . " rate settings don't allow targeting new buyers",
				$this->getSupplierId()
			);
		}

		return $this->addRelationship($buyerId, self::REL_STATUS_TARGETED);
	}

	/**
	 * Moves buyer to the EXCLUDED group
	 *
	 * @param   int $buyerId
	 *
	 * @return  int
	 * @throws  Exception
	 */
	public function excludeBuyer($buyerId)
    {
		return $this->addRelationship($buyerId, self::REL_STATUS_EXCLUDED);
	}

    /**
     * @param   int $buyerBranchId
     *
     * @return  array
     * @throws  Shipserv_Supplier_Rate_Buyer_Exception
     */
    public function getOngoingRelationshipByBuyer($buyerBranchId)
    {
        $select = new Zend_Db_Select(Shipserv_Helper_Database::getDb());
        $select
            ->from(
                array('bsr' => self::TABLE_NAME),
                self::_getRecordSelectFields()
            )
            ->where('bsr.' . self::COL_SUPPLIER . ' = ?', $this->getSupplierId())
            ->where('bsr.' . self::COL_BUYER . ' = ?', $buyerBranchId)
            ->where(
                implode(
                    ' OR ',
                    array(
                        'bsr.' . self::COL_VALID_TILL . ' IS NULL',
                        'bsr.' . self::COL_VALID_TILL . ' > SYSDATE'
                    )
                )
            )
        ;

        $row = $select->getAdapter()->fetchRow($select);

        if (empty($row)) {
            throw new Shipserv_Supplier_Rate_Buyer_Exception(
                "No relationship with buyer " . $buyerBranchId . " for supplier " . $this->getSupplierId(), $this->getSupplierId()
            );
        }

        return self::_expandRecord($row);
    }


	/**
	 * @param   int|null    $relationshipId
	 *
	 * @return  array
	 * @throws  Shipserv_Supplier_Rate_Buyer_Exception
	 */
	public function getRelationshipById($relationshipId = null)
    {
		$select = new Zend_Db_Select(Shipserv_Helper_Database::getDb());
		$select
			->from(
				array('bsr' => self::TABLE_NAME),
				self::_getRecordSelectFields()
			)
			->where('bsr.' . self::COL_SUPPLIER . ' = ?', $this->getSupplierId())
            ->where('bsr.' . self::COL_ID . ' = ?', $relationshipId)
		;

		$row = $select->getAdapter()->fetchRow($select);

		if (empty($row)) {
			throw new Shipserv_Supplier_Rate_Buyer_Exception(
			    "No relationship " . $relationshipId . " for supplier " . $this->getSupplierId(), $this->getSupplierId()
            );
		}

		return self::_expandRecord($row);
	}

	/**
	 * Creates a new relationship between a buyer and supplier
	 *
	 * @param   int     $buyerId
	 * @param   string  $status
	 * @param   int     $rateId
	 *
	 * @return  int
	 * @throws Exception
	 */
	protected function addRelationship($buyerId, $status, $rateId = null)
    {
		// check if status is something we know about
		self::_validateStatus($status);
		// check if buyer branch is eligible to be in a relationship with supplier
		$this->_validateBuyerForNewRelationship($buyerId);

		// check if the status to be assigned is different from it currently may be
		try {
			$relationship = $this->getOngoingRelationshipByBuyer($buyerId);
		} catch (Shipserv_Supplier_Rate_Buyer_Exception $e) {
			// no ongoing relationship
			$relationship = null;
		}

		if ($relationship) {
			if ($relationship[self::COL_STATUS] === $status) {
				throw new Shipserv_Supplier_Rate_Buyer_Exception(
					"Cannot put a buyer " . $buyerId . " in status " . $status . " with supplier " . $this->getSupplierId() . " they are already in",
					$this->getSupplierId()
				);
			}

			if (strlen($relationship[self::COL_ORDER]) > 0) {
			    throw new Shipserv_Supplier_Rate_Buyer_Exception(
                    "Cannot put a buyer " . $buyerId . " in a new relationship " . $status . " with supplier " . $this->getSupplierId() .
                    " because their existing relationship is locked",
                    $this->getSupplierId()
                );
            }
		}

		$db = Shipserv_Helper_Database::getDb();
		$db->beginTransaction();

		try {
			$validFrom = $this->terminateCurrentRelationship($buyerId);
			if (is_null($validFrom)) {
				$validFrom = new DateTime();
			}

			$db->insert(
			    self::TABLE_NAME,
                array(
                    self::COL_SUPPLIER => $this->getSupplierId(),
                    self::COL_BUYER    => $buyerId,
                    self::COL_STATUS   => $status,
                    self::COL_RATE     => $rateId,
                    self::COL_USER     => $this->getUserId(),
                    self::COL_VALID_FROM => new Zend_Db_Expr(Shipserv_Helper_Database::getOracleDateExpr($validFrom))
    			)
            );

			$id = $db->lastSequenceId(self::SEQUENCE_ID);
			$db->commit();

		} catch (Exception $e) {
			$db->rollBack();
			throw new Shipserv_Supplier_Rate_Buyer_Exception(
				"Failed to update relationship for supplier " . $this->getSupplierId() . ": " . $e->getMessage(),
				$this->getSupplierId()
			);
		}

		return $id;
	}

	/**
	 * @param   array   $record
	 *
	 * @return  array
	 */
	public static function prepareRecord(array $record)
    {
		$prepared = array();
		foreach ($record as $key => $value) {
			if (!is_null($value)) {
				switch ($key) {
					case self::COL_ID:
					case self::COL_SUPPLIER:
					case self::COL_BUYER:
					case self::COL_ORDER:
					case self::COL_RATE:
					case self::COL_USER:
						$value = (int) $value;
						break;

					case self::COL_VALID_FROM:
					case self::COL_VALID_TILL:
					case self::COL_LAST_NOTIFIED:
					case 'LOCKED_AT':
						$value = new DateTime($value);
						break;

					case 'LOCKED_FOR':
						$value = (float) $value;
						break;

					case Shipserv_Buyer_Branch::COL_PROMOTE_CHILDREN:
					case 'IS_TOP':
						$value = (bool) $value;
						break;

					default:
						// same value assigned
				}
			}

			$prepared[$key] = $value;
		}

		return $prepared;
	}

	/**
	 * Adds objects which IDs are provided in the given record
	 *
	 * @param   array $row
	 *
	 * @return  array
	 */
	protected function _expandRecord(array $row)
    {
		$prepared = self::prepareRecord($row);

		$prepared['_expanded'] = array(
			'rate'  => $this->getRateObj()->getRate($row[self::COL_RATE]),
			'buyer' => Shipserv_Buyer_Branch::getInstanceById($row[self::COL_BUYER]),
			'user'  => (strlen($row[self::COL_USER]) === 0) ? null : Shipserv_User::getInstanceById($row[self::COL_USER])
		);

		if (strlen($prepared[self::COL_ORDER])) {
			$prepared['_expanded']['order'] = Shipserv_PurchaseOrder::getInstanceById($prepared[self::COL_ORDER]);
		}

		return $prepared;
	}

	/**
	 * Returns currently active targeted buyers or ones that were targeted before but are still in the locked state
	 *
	 * @return  array
	 */
	public function getTargetedBuyerList()
    {
		$select = new Zend_Db_Select(Shipserv_Helper_Database::getDb());
		$select
			->from(
				array('bsr' => self::TABLE_NAME),
				self::_getRecordSelectFields()
			)
			->join(
				array('byb' => Shipserv_Buyer_Branch::TABLE_NAME),
				'byb.' . Shipserv_Buyer_Branch::COL_ID . ' = bsr.' . self::COL_BUYER,
				array(
					Shipserv_Buyer_Branch::COL_NAME => 'byb.' . Shipserv_Buyer_Branch::COL_NAME,
					Shipserv_Buyer_Branch::COL_PROMOTE_CHILDREN => 'byb.' . Shipserv_Buyer_Branch::COL_PROMOTE_CHILDREN
				)
			)
			->join(
				array('branches' => new Zend_Db_Expr('(' . Shipserv_Buyer_Branch::getTopBranchIdQuery() . ')')),
				'branches.byb_branch_code = byb.' . Shipserv_Buyer_Branch::COL_ID,
				array(
					'IS_TOP' => new Zend_Db_Expr(
					    'CASE
						    WHEN branches.byb_branch_code = branches.top_branch_code THEN 1
						    ELSE 0
					    END'
                    )
				)
			)
			->where('bsr.' . self::COL_SUPPLIER . ' = ?', $this->getSupplierId())
			->where('bsr.' . self::COL_STATUS . ' = ?', self::REL_STATUS_TARGETED)
			->joinLeft(
				array('sbr' => Shipserv_Supplier_Rate::TABLE_NAME),
				'sbr.' . Shipserv_Supplier_Rate::COL_ID . ' = bsr.' . self::COL_RATE,
				array()
			)
			->joinLeft(
				array('ord' => Shipserv_PurchaseOrder::TABLE_NAME),
				'ord.' . Shipserv_PurchaseOrder::COL_ID . ' = bsr.' . self::COL_ORDER,
				array(
					'LOCKED_AT'  => new Zend_Db_Expr('TO_CHAR(ord.' . Shipserv_PurchaseOrder::COL_DATE_SUB . ", 'YYYY-MM-DD HH24:MI:SS')"),
					'LOCKED_FOR' => new Zend_Db_Expr(
					    'CASE
						    WHEN sbr.' . Shipserv_Supplier_Rate::COL_LOCK_TARGET . ' IS NULL THEN NULL
						    ELSE sbr.' . Shipserv_Supplier_Rate::COL_LOCK_TARGET . ' - (SYSDATE - ord.' . Shipserv_PurchaseOrder::COL_DATE_SUB . ')
					    END'
                    )
				)
			)
			->where(
			    implode(
			        ' OR ',
                    array(
                        'bsr.' . self::COL_VALID_TILL . ' IS NULL',
                        'bsr.' . self::COL_VALID_TILL . ' > SYSDATE'
                        // 'ord.' . Shipserv_PurchaseOrder::COL_DATE_SUB . ' + sbr.' . Shipserv_Supplier_Rate::COL_LOCK_TARGET . ') > SYSDATE'
        			)
                )
            )
			->order(Shipserv_Buyer_Branch::COL_NAME)
			->distinct()
		;

		// print $select->assemble(); die;

		$rows = $select->getAdapter()->fetchAll($select);

		$data = array();
		foreach ($rows as $row) {
			$data[] = $this->_expandRecord($row);
		}

		return $data;
	}

	/**
	 * @return array
	 */
	protected static function _getRecordSelectFields()
    {
		return array(
			self::COL_ID        => 'bsr.' . self::COL_ID,
			self::COL_SUPPLIER  => 'bsr.' . self::COL_SUPPLIER,
			self::COL_BUYER     => 'bsr.' . self::COL_BUYER,
			self::COL_STATUS    => 'bsr.' . self::COL_STATUS,
			self::COL_ORDER     => 'bsr.' . self::COL_ORDER,
			self::COL_RATE      => 'bsr.' . self::COL_RATE,
			self::COL_USER      => 'bsr.' . self::COL_USER,
			self::COL_VALID_FROM    => new Zend_Db_Expr('TO_CHAR(bsr.' . self::COL_VALID_FROM . ", 'YYYY-MM-DD HH24:MI:SS')"),
			self::COL_VALID_TILL    => new Zend_Db_Expr('TO_CHAR(bsr.' . self::COL_VALID_TILL . ", 'YYYY-MM-DD HH24:MI:SS')"),
			self::COL_LAST_NOTIFIED => new Zend_Db_Expr('TO_CHAR(bsr.' . self::COL_LAST_NOTIFIED . ", 'YYYY-MM-DD HH24:MI:SS')")
		);
	}

	/**
	 * @return array
	 */
	public function getExcludedSupplierList()
    {
		$select = new Zend_Db_Select(Shipserv_Helper_Database::getDb());
		$select
			->from(
				array('bsr' => self::TABLE_NAME),
				self::_getRecordSelectFields()
			)
			->join(
				array('byb' => Shipserv_Buyer_Branch::TABLE_NAME),
				'byb.' . Shipserv_Buyer_Branch::COL_ID . ' = bsr.' . self::COL_BUYER,
				array(
					Shipserv_Buyer_Branch::COL_NAME => 'byb.' . Shipserv_Buyer_Branch::COL_NAME,
					Shipserv_Buyer_Branch::COL_PROMOTE_CHILDREN => 'byb.' . Shipserv_Buyer_Branch::COL_PROMOTE_CHILDREN
				)
			)
			->join(
				array('branches' => new Zend_Db_Expr('(' . Shipserv_Buyer_Branch::getTopBranchIdQuery() . ')')),
				'branches.byb_branch_code = byb.' . Shipserv_Buyer_Branch::COL_ID,
				array(
					'IS_TOP' => new Zend_Db_Expr(
					    'CASE
						    WHEN branches.byb_branch_code = branches.top_branch_code THEN 1
						    ELSE 0
					    END'
                    )
				)
			)
			->where('bsr.' . self::COL_SUPPLIER . ' = ?', $this->getSupplierId())
			->where('bsr.' . self::COL_STATUS . ' = ?', self::REL_STATUS_EXCLUDED)
			->where(
			    implode(
                    ' OR ',
                    array(
                        'bsr.' . self::COL_VALID_TILL . ' IS NULL',
                        'bsr.' . self::COL_VALID_TILL . ' > SYSDATE'
                    )
                )
            )
			->order(Shipserv_Buyer_Branch::COL_NAME)
		;

		$rows = $select->getAdapter()->fetchAll($select);

		$data = array();
		foreach ($rows as $row) {
			$data[] = $this->_expandRecord($row);
		}

		return $data;
	}

	/**
	 * Returns the list of buyer branches available for excluding or targeting
	 *
	 * @return  array
	 */
	public function getPendingSupplierList()
    {
		$select = $this->_getBuyerQuery();
		$select->order('byb.' . Shipserv_Buyer_Branch::COL_NAME);

		$rows = $select->getAdapter()->fetchAll($select);
		return $rows;
	}

	/**
	 * Returns a query draft for a buyer to be added in a relationship
	 *
	 * @param   string|array    $allowedRelationships
	 *
	 * @return  Zend_Db_Select
	 */
	protected function _getBuyerQuery($allowedRelationships = array())
    {
		if (!is_array($allowedRelationships)) {
			$allowedRelationships = array($allowedRelationships);
		}

		$db = Shipserv_Helper_Database::getDb();

		$select = new Zend_Db_Select($db);
		$select
			->from(
				array('byb' => Shipserv_Buyer_Branch::TABLE_NAME),
				'byb.*'
			)
			->join(
				array('buyer' => new Zend_Db_Expr('(' . Shipserv_Buyer_Branch::getTopBranchIdQuery() . ')')),
				'buyer.byb_branch_code = byb.' . Shipserv_Buyer_Branch::COL_ID,
				array()
			)
			->join(
				array('parent_byb' => Shipserv_Buyer_Branch::TABLE_NAME),
				'parent_byb.' . Shipserv_Buyer_Branch::COL_ID . ' = buyer.top_branch_code',
				array(
					'IS_TOP' => new Zend_Db_Expr(
					    'CASE
						    WHEN byb.' . Shipserv_Buyer_Branch::COL_ID . ' = parent_byb.' . Shipserv_Buyer_Branch::COL_ID . ' THEN 1
						    ELSE 0
					    END'
                    )
				)
			)
			->where(
			    implode(
			        ' OR ',
                    array(
                        // parent branches
                        'parent_byb.' . Shipserv_Buyer_Branch::COL_ID . ' = byb.' . Shipserv_Buyer_Branch::COL_ID,
                        // child branches where parent branch allows them to be promoted individually
                        $db->quoteInto('parent_byb.' . Shipserv_Buyer_Branch::COL_PROMOTE_CHILDREN . ' = ?', 1)
                    )
                )
            )
			// no POM buyers linked to supplier accounts
			->joinLeft(
				array('spb' => Shipserv_Supplier::TABLE_NAME),
				'spb.' . Shipserv_Supplier::COL_POM_BUYER . ' = byb.' . Shipserv_Buyer_Branch::COL_ID,
				array()
			)
			->where('spb.' . Shipserv_Supplier::COL_ID . ' IS NULL')
			// only specific contracts
			->where(
			    'byb.' . Shipserv_Buyer_Branch::COL_CONTRACT_TYPE . ' IN (?)',
                array(
                    Shipserv_Buyer_Branch::CONTRACT_TYPE_CN3,
                    Shipserv_Buyer_Branch::CONTRACT_TYPE_CCP,
                    Shipserv_Buyer_Branch::CONTRACT_TYPE_TRIAL,
                    Shipserv_Buyer_Branch::CONTRACT_TYPE_STANDARD
                )
            )
			// no proxy branches
			->where(
			    'byb.' . Shipserv_Buyer_Branch::COL_ID . ' NOT IN (?)',
                array(
				    Myshipserv_Config::getProxyMatchBuyer(),
				    Myshipserv_Config::getProxyPagesBuyer()
			    )
            )
			// only active buyers
			->where('byb.' . Shipserv_Buyer_Branch::COL_STATUS . ' = ?', Shipserv_Buyer_Branch::STATUS_ACTIVE)
			// no test branches
			->where('byb.' . Shipserv_Buyer_Branch::COL_TEST . ' = ?', Shipserv_Buyer_Branch::TEST_NO)
			->joinLeft(
				array('bsr' => self::TABLE_NAME),
				implode(
				    ' AND ',
                    array(
                        $db->quoteInto('bsr.' . self::COL_SUPPLIER . ' = ?', $this->getSupplierId()),
                        'bsr.' . self::COL_BUYER . ' = byb.' . Shipserv_Buyer_Branch::COL_ID,
                        '(' . implode(
                            ' OR ',
                            array(
                                'bsr.' . self::COL_VALID_TILL . ' IS NULL',
                                'bsr.' . self::COL_VALID_TILL . ' > SYSDATE'
                            )
                        ) . ')'
    				)
                ),
				array()
			)
			->distinct()
		;

		if (empty($allowedRelationships)) {
			// buyer should not be in an ongoing relationship with the supplier
			$select->where('bsr.' . self::COL_ID . ' IS NULL');
		} else {
			// buyer is allowed to be in certain types of relationship with the buyer
			// so we allow either no relationships or the relationships of the allowed types
			$select
				->where(
				    implode(
                        ' OR ',
                        array(
                            'bsr.' . self::COL_ID . ' IS NULL',
                            $db->quoteInto('bsr.' . self::COL_STATUS . ' IN (?)', $allowedRelationships)
                        )
                    )
                )
			;
		}

		// print $select->assemble(); die;

		return $select;
	}

	/**
	 * Checks if the given buyer is fine for a new relationship to be created with it
	 * Basically this prevents adding a buyer branch not pending, or not already excluded or targeted
	 *
	 * IMPORTANT: won't throw and exception when the buyer is in an ongoing locked targeted relationship, this has to
	 * be checked separately, if needed
	 *
	 * @param   int     $buyerBranchId
	 *
	 * @return  bool
	 * @throws  Shipserv_Supplier_Rate_Buyer_Exception
	 */
	protected function _validateBuyerForNewRelationship($buyerBranchId)
    {
		$select = $this->_getBuyerQuery(
		    array(
                self::REL_STATUS_TARGETED,
                self::REL_STATUS_EXCLUDED
            )
        );
		$select
			->where('byb.' . Shipserv_Buyer_Branch::COL_ID . ' = ?', $buyerBranchId)
		;

		$row = $select->getAdapter()->fetchRow($select);
		if ($row === false) {
			throw new Shipserv_Supplier_Rate_Buyer_Exception(
			    "Buyer " . $buyerBranchId . " cannot get in relationship with supplier " . $this->getSupplierId(), $this->getSupplierId()
            );
		}

		return true;
	}

	/**
	 * Is called when no target rate is synced for a supplier and terminates ongoing targeted relationships which are:
	 * a) not locked
	 * b) locked without a lock period
	 *
	 * @param   DateTime    $newRateValidFrom
	 *
	 * @return  int
	 * @throws  Shipserv_Supplier_Rate_Buyer_Exception
	 */
	public function backToStandardPricingModel(DateTime $newRateValidFrom = null)
    {
		if (is_null($newRateValidFrom)) {
			$newRateValidFrom = new DateTime();
		}

		$rateObj = $this->getRateObj();
		if ($rateObj->canTargetNewBuyers()) {
			// cannot get back to standard rate mode because it is already standard
			throw new Shipserv_Supplier_Rate_Buyer_Exception(
				"Supplier " . $this->getSupplierId() . " is not on a standard pricing model!",
				$this->getSupplierId()
			);
		}

		$db = Shipserv_Helper_Database::getDb();

		// delete non-locked target relationships started after the new rate was applied
        $deletedNonLocked = $db->delete(
            self::TABLE_NAME,
            implode(
                ' AND ',
                array(
                    $db->quoteInto(self::COL_SUPPLIER . ' = ?', $this->getSupplierId()),
                    $db->quoteInto(self::COL_STATUS . ' = ?', self::REL_STATUS_TARGETED),
                    self::COL_ORDER . ' IS NULL',
                    self::COL_VALID_FROM . ' >= ' . Shipserv_Helper_Database::getOracleDateExpr($newRateValidFrom)
                )
            )
        );

		// terminate non-locked ongoing target relationships started before the new rate was applied
		$updatedNonLocked = $db->update(
            self::TABLE_NAME,
            array(
                self::COL_VALID_TILL => new Zend_Db_Expr(Shipserv_Helper_Database::getOracleDateExpr($newRateValidFrom))
            ),
            implode(
                ' AND ',
                array(
                    $db->quoteInto(self::COL_SUPPLIER . ' = ?', $this->getSupplierId()),
                    $db->quoteInto(self::COL_STATUS . ' = ?', self::REL_STATUS_TARGETED),
                    self::COL_ORDER . ' IS NULL',
                    self::COL_VALID_FROM . ' <= ' . Shipserv_Helper_Database::getOracleDateExpr($newRateValidFrom),
                    '(' . implode(
                        ' OR ',
                        array(
                            self::COL_VALID_TILL . ' IS NULL',
                            // this is never going to be the case (yet) for non-locked relationships, but still would be logical to include
                            self::COL_VALID_TILL . ' >= ' . Shipserv_Helper_Database::getOracleDateExpr($newRateValidFrom)
                        )
                    ) . ')'
                )
            )
        );

		// terminate all locked relationships with no lock period
		$updatedLocked = $db->update(
			self::TABLE_NAME,
			array(
				self::COL_VALID_TILL => new Zend_Db_Expr(Shipserv_Helper_Database::getOracleDateExpr($newRateValidFrom))
			),
			implode(
			    ' AND ',
                array(
				    $db->quoteInto(self::COL_SUPPLIER . ' = ?', $this->getSupplierId()),
				    $db->quoteInto(self::COL_STATUS . ' = ?', self::REL_STATUS_TARGETED),
				    self::COL_ORDER . ' IS NOT NULL',
				    self::COL_VALID_TILL . ' IS NULL',
			    )
            )
		);

		return ($deletedNonLocked + $updatedNonLocked + $updatedLocked);
	}

	/**
	 * Returns time intervals within the given one where the supplier had locked relationships so target rate would be charged
	 *
	 * @date    2016-03-15
	 * @story   S15989
	 *
	 * @param   DateTime    $dateStart
	 * @param   DateTime    $dateEnd
	 *
	 * @return  array
	 */
	public function getTargetRateContractIntervals(DateTime $dateStart, DateTime $dateEnd = null)
    {
        if (is_null($dateEnd)) {
            $dateEnd = new DateTime();
        }

		$strDateStart = Shipserv_Helper_Database::getOracleDateExpr($dateStart);
		$strDateEnd   = Shipserv_Helper_Database::getOracleDateExpr($dateEnd);

		$select = new Zend_Db_Select(Shipserv_Helper_Database::getDb());
		$select
			->from(
				array('bsr' => self::TABLE_NAME),
				'bsr.' . self::COL_ID
			)
			->where('bsr.' . self::COL_SUPPLIER . ' = ?', $this->getSupplierId())
			->where('bsr.' . self::COL_STATUS . ' = ?', self::REL_STATUS_TARGETED)
			->where('bsr.' . self::COL_ORDER . ' IS NOT NULL')
			// starts in the past or in the interval, ends in the future or in the interval
			->where(
			    implode(
			        ' AND ',
                    array(
                        // start date in the past or within the interval
                        'bsr.' . self::COL_VALID_FROM . ' <= ' . $strDateEnd,
                        // end date in the interval or in the future
                        '(' . implode(
                            ' OR ',
                            array(
                                'bsr.' . self::COL_VALID_TILL . ' > ' . $strDateStart,
                                'bsr.' . self::COL_VALID_TILL . ' IS NULL'
                            )
                        ) . ')'
                    )
                )
            )
			->order('bsr.' . self::COL_VALID_FROM)
		;

		$relationshipIds = $select->getAdapter()->fetchCol($select);

		$intervals = array();
		foreach ($relationshipIds as $relId) {
			$relationship = $this->getRelationshipById($relId);

            $interval = array();
			$interval[] = max($dateStart, $relationship[self::COL_VALID_FROM]);

			if ($relationship[self::COL_VALID_TILL]) {
				$interval[] = min($dateEnd, $relationship[self::COL_VALID_TILL]);
			} else {
				$interval[] = $dateEnd;
			}

			if (empty($intervals)) {
				$intervals[] = $interval;
			} else {

			}
		}

		return $intervals;
	}

	/**
	 * Returns relationship record with the given buyer, if it exists, or returns null if there is no relationship
	 *
	 * IMPORTANT:
	 *
	 * Will not check work for non-explicitly targeted buyer - e.g. if a parent buyer is targeted will its child branches also
	 * considered, but it's the parent buyer ID in the relationship record, the function would tell that there is no relationship
	 * is given that child buyer ID
	 *
	 * It will still work for child buyer ID if it is targeted explicitly, with its ID in the relationship record.
	 *
	 * @author  Yuriy Akopov
	 * @story   S16251
	 * @date    2016-04-29
	 *
	 * @param   int             $buyerId
	 * @param   DateTime|null   $dateTime
	 *
	 * @return  array|null
	 */
	public function getExplicitRelationshipWithBuyer($buyerId, DateTime $dateTime = null)
    {
		$select = new Zend_Db_Select(Shipserv_Helper_Database::getDb());
		$select
			->from(
				array('bsr' => self::TABLE_NAME),
				self::_getRecordSelectFields()
			)
			->where('bsr.' . self::COL_SUPPLIER . ' = ?', $this->getSupplierId())
			->where('bsr.' . self::COL_BUYER . ' = ?', $buyerId)
		;

		if ($dateTime) {
			$dateConstraint = Shipserv_Helper_Database::getOracleDateExpr($dateTime);
		} else {
			$dateConstraint = 'SYSDATE';
		}

		$select
			->where('bsr.' . self::COL_VALID_FROM . ' <= ' . $dateConstraint)
			->where(
			    implode(
                    ' OR ',
                    array(
                        'bsr.' . self::COL_VALID_TILL . ' IS NULL',
                        'bsr.' . self::COL_VALID_TILL . ' > ' . $dateConstraint
                    )
                )
            )
		;

		$record = $select->getAdapter()->fetchRow($select);

		if ($record === false) {
			return null;
		}

		return self::_expandRecord($record);
	}

	/**
	 * Returns true if the relationship record provided is of the expected type or false if it isn't
	 * If $expectedLock is not null, also checks whether the relationship is locked or not
	 *
	 * @author  Yuriy Akopov
	 * @story   S16251
	 * @date    2016-04-29
	 *
	 * @param   array       $record
	 * @param   string      $expectedStatus
	 * @param   bool|null   $expectedLock
	 *
	 * @return bool
	 * @throws  Shipserv_Supplier_Rate_Buyer_Exception
	 */
	public static function checkRelationship(array $record, $expectedStatus, $expectedLock = null)
    {
		// validate the record structure
		$expectedFields = array_keys(self::_getRecordSelectFields());
		foreach ($expectedFields as $field) {
			if (!array_key_exists($field, $record)) {
				throw new Shipserv_Supplier_Rate_Buyer_Exception("Invalid relationship record provided, field " . $field . " is missing");
			}
		}

		$result = null;

		switch ($record[self::COL_STATUS]) {
			case self::REL_STATUS_EXCLUDED:
			case self::REL_STATUS_TARGETED:
				$result = ($record[self::COL_STATUS] === $expectedStatus);
				break;

			default:
				throw new Shipserv_Supplier_Rate_Buyer_Exception("Invalid relationship status " . $record[self::COL_STATUS] . " detected");
		}

		if ($result and !is_null($expectedLock)) {    // if we also need to check whether the relationship is locked or not
			$result = ((strlen($record[self::COL_ORDER]) > 0) === $expectedLock);   // true
		}

		return $result;
	}

    /**
     * Returns locked relationships that were locked in the specified date interval
     *
     * @param   DateTime    $dateFrom
     * @param   DateTime    $dateTill
     *
     * @return  array
     */
	public function getLockedRelationshipsByLockDate(DateTime $dateFrom, DateTime $dateTill = null)
    {
        $db = Shipserv_Helper_Database::getDb();

        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('bsr' => self::TABLE_NAME),
                self::_getRecordSelectFields()
            )
            ->join(
                array('ord' => Shipserv_PurchaseOrder::TABLE_NAME),
                'ord.' . Shipserv_PurchaseOrder::COL_ID . ' = bsr.' . self::COL_ORDER,
                array()
            )
            ->where('bsr.' . self::COL_STATUS . ' = ?', self::REL_STATUS_TARGETED)
            ->where('bsr.' . self::COL_SUPPLIER . ' = ?', $this->getSupplierId())
            // it's the lock date, not targeting date that we use as an anchor
            ->where('ord.' . Shipserv_PurchaseOrder::COL_DATE_SUB . ' >= ' . Shipserv_Helper_Database::getOracleDateExpr($dateFrom))
        ;

        if ($dateTill) {
            $select
                ->where('ord.' . Shipserv_PurchaseOrder::COL_DATE_SUB . ' < ' . Shipserv_Helper_Database::getOracleDateExpr($dateTill))
            ;
        }

        $rows = $db->fetchAll($select);

        $records = array();
        foreach ($rows as $row) {
            $records[] = self::_expandRecord($row);
        }

        return $records;
    }
}