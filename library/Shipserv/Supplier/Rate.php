<?php
/**
 * Represents supplier's rate records, but not on a per-record ActiveRecord-like base for the lack of a decent
 * boilerplate for that in the project.
 *
 * This is a manager class which instance represents a supplier with an API to manager their rates
 *
 * @author  Yuriy Akopov
 * @date    2016-02-05
 * @story   S15735
 */
class Shipserv_Supplier_Rate
{
	const
		TABLE_NAME = 'SUPPLIER_BRANCH_RATE',

		COL_ID = 'SBR_ID',
		COL_SUPPLIER = 'SBR_SPB_BRANCH_CODE',

		COL_RATE_STANDARD = 'SBR_RATE_STANDARD',
		COL_RATE_TARGET = 'SBR_RATE_TARGET',
		COL_LOCK_TARGET = 'SBR_LOCK_TARGET',

		COL_SF_SRC_TYPE = 'SBR_SF_SOURCE_TYPE',
		COL_SF_SRC_ID = 'SBR_SF_SOURCE_ID',

		COL_VALID_FROM = 'SBR_VALID_FROM',
		COL_VALID_TILL = 'SBR_VALID_TILL',

		SEQUENCE_ID = 'SQ_SUPPLIER_BRANCH_RATE_ID'
	;

	const
		RATE_PRECISION = 4
	;

	const
		SF_SRC_TYPE_CONTRACT = 'contract',
		SF_SRC_TYPE_ACCOUNT  = 'account',
		SF_SRC_TYPE_CONSTANT = 'constant',
		SF_SRC_TYPE_RATESET  = 'rateset'
	;

	const
        TABLE_NAME_BACKUP = 'SUPPLIER_BRANCH_RATE_EDIT',

        EDIT_TABLE_COL_DATE = 'EDIT_DATE'
    ;

	/**
	 * @var Shipserv_Supplier
	 */
	protected $supplier = null;

	// protected $rollBackTo = null;

	/**
	 * Is set to true when a new record is added or the current one modified
	 *
	 * @var bool
	 */
	protected $modified = false;

	/**
	 * @var bool|DateTime
	 */
	protected static $activePromotionStartDate = null;

	/**
	 * Returns rate source types which indicate a rate was found in an active VBP contract and its rateset
	 *
	 * @date    2016-09-22
	 * @story   S18134
	 *
	 * @return array
	 */
	public static function getContractRateSourceTypes()
	{
		return array(
			self::SF_SRC_TYPE_CONTRACT, // contract rates synced before 2016-10 (S18134)
			self::SF_SRC_TYPE_RATESET   // contract rates synced after 2016-10 (S18134)
		);
	}
	/**
	 * Returns the date on which the earliest sync of multi-tiered rates happened
	 *
	 * @return null|DateTime
	 */
	public static function getActivePromotionStartDate()
	{
		if (!is_null(self::$activePromotionStartDate)) {
			return self::$activePromotionStartDate;
		}

		$select = new Zend_Db_Select(Shipserv_Helper_Database::getDb());
		$select
			->from(
				array('sbr' => self::TABLE_NAME),
				new Zend_Db_Expr('TO_CHAR(MIN(sbr.' . self::COL_VALID_FROM . "), 'YYYY-MM-DD HH24:MI:SS')")
			)
		;

		$strDate = $select->getAdapter()->fetchOne($select);

		if (strlen($strDate)) {
			self::$activePromotionStartDate = new DateTime($strDate);
		} else {
			self::$activePromotionStartDate = false;
		}

		return self::$activePromotionStartDate;
	}


	/**
	 * Initialises the instance for the current supplier and Pages user (is null is provided, currently logged in user is used)
	 *
	 * @param   int     $supplierId
	 *
	 * @throws Shipserv_Supplier_Rate_Exception
	 */
	public function __construct($supplierId)
	{
		$supplier = Shipserv_Supplier::getInstanceById($supplierId, null, true);
		if (strlen($supplier->tnid) === 0) {
			throw new Shipserv_Supplier_Rate_Exception("Supplier " . $supplierId . " not found in the database", $supplierId);
		}

		$this->supplier = $supplier;
	}

	/**
	 * @return int
	 */
	public function getSupplierId()
	{
		return (int) $this->supplier->tnid;
	}

	/**
	 * Adds a new rate for a supplied synced from a contract and its rateset (as opposed to an account or a constant)
	 *
	 * @param   string      $rateSetId
	 * @param   float|null  $rateStandard
	 * @param   float|null  $rateTarget
	 * @param   int|null    $lockTarget
     * @param   DateTime    $validFrom
	 *
	 * @return  int|null
	 * @throws Myshipserv_Salesforce_Exception
	 */
	public function addNewContractRateSetRate($rateSetId, $rateStandard = null, $rateTarget = null, $lockTarget = null, DateTime $validFrom = null)
	{
		if (strlen($rateSetId) === 0) {
			throw new Myshipserv_Salesforce_Exception("Contract rate for supplier " . $this->getSupplierId() . " should come from a valid rateset");
		}

		return $this->addNewRate(self::SF_SRC_TYPE_RATESET, $rateSetId, $rateStandard, $rateTarget, $lockTarget, $validFrom);
	}

	/**
	 * Adds a rate acquired from supplier's account PO Fee when there wasn't an active contract
	 *
	 * @param   string      $accountId
	 * @param   float|null  $rateStandard
	 * @param   float|null  $rateTarget
	 *
	 * @return  int|null
	 * @throws  Myshipserv_Salesforce_Exception
	 */
	public function addNewAccountRate($accountId, $rateStandard = null, $rateTarget = null)
	{
		if (strlen($accountId) === 0) {
			throw new Myshipserv_Salesforce_Exception("Account rate for supplier " . $this->getSupplierId() . " should come from an account");
		}

		return $this->addNewRate(self::SF_SRC_TYPE_ACCOUNT, $accountId, $rateStandard, $rateTarget, null);
	}

	/**
	 * Adds a constant rate which is needed when we know supplier is a paying customer but has no account or contract rates
	 *
	 * @param   float|null  $rateStandard
	 *
	 * @return  int|null
	 */
    public function addConstantRate($rateStandard = null)
	{
		return $this->addNewRate(self::SF_SRC_TYPE_CONSTANT, null, $rateStandard, null, null);
	}

	/**
	 * Adds an all-NULL rate record which is needed when supplier is not in SalesForce
	 *
	 * @return  int|null
	 */
	public function addNullRate()
	{
		return $this->addNewRate(self::SF_SRC_TYPE_CONSTANT, null, null, null, null);
	}

	/**
	 * @param   array $record
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
						$value = (int) $value;
						break;

					case self::COL_RATE_STANDARD:
					case self::COL_RATE_TARGET:
						$value = (float) $value;
						break;

					case self::COL_VALID_FROM:
					case self::COL_VALID_TILL:
						$value = new DateTime($value);
						break;
						
					default:
						// same value assigned
						break;
				}
			}

			$prepared[$key] = $value;
		}

		return $prepared;
	}

	/**
	 * Returns currently active rate settings for the supplier or a specific historical record if an ID is specified
	 *
	 * @param   int $rateId
	 *
	 * @return  array
	 * @throws  Shipserv_Supplier_Rate_Exception
	 */
	public function getRate($rateId = null)
	{
		$db = Shipserv_Helper_Database::getDb();
		$select = new Zend_Db_Select($db);

		$select
			->from(
				array('sbr' => self::TABLE_NAME),
				array(
					self::COL_ID            => 'sbr.' . self::COL_ID,
					self::COL_SUPPLIER      => 'sbr.' . self::COL_SUPPLIER,
					self::COL_SF_SRC_TYPE   => 'sbr.' . self::COL_SF_SRC_TYPE,
					self::COL_SF_SRC_ID     => 'sbr.' . self::COL_SF_SRC_ID,
					self::COL_RATE_STANDARD => 'sbr.' . self::COL_RATE_STANDARD,
					self::COL_RATE_TARGET   => 'sbr.' . self::COL_RATE_TARGET,
					self::COL_LOCK_TARGET   => 'sbr.' . self::COL_LOCK_TARGET,
					self::COL_VALID_FROM    => new Zend_Db_Expr('TO_CHAR(sbr.' . self::COL_VALID_FROM . ", 'YYYY-MM-DD HH24:MI:SS')"),
					self::COL_VALID_TILL    => new Zend_Db_Expr('TO_CHAR(sbr.' . self::COL_VALID_TILL . ", 'YYYY-MM-DD HH24:MI:SS')")
				)
			)
			->where('sbr.' . self::COL_SUPPLIER . ' = ?', $this->getSupplierId())
		;

		if (is_null($rateId)) {
			// active rate returned
			$select->where('sbr.' . self::COL_VALID_TILL . ' IS NULL');
		} else {
			$select->where('sbr.' . self::COL_ID . ' = ?', $rateId);
		}

		$row = $select->getAdapter()->fetchRow($select);
		if ($row === false) {
			throw new Shipserv_Supplier_Rate_Exception("No requested rate for supplier " . $this->getSupplierId(), $this->getSupplierId());
		}

		return self::prepareRecord($row);
	}

	/**
	 * Throws an exception when the rate would not fit in our database field without losing some meaningful digits
	 *
	 * @param   float|null  $rate
	 *
	 * @return  bool
	 * @throws  Shipserv_Supplier_Rate_Exception_Invalid
	 */
	protected function validateRateValue($rate)
	{
		$rate = (float) $rate;
		$roundedRate = round($rate, self::RATE_PRECISION);

		if ($roundedRate !== $rate) {
			throw new Shipserv_Supplier_Rate_Exception_Invalid("Rate " . $rate . " for supplier " . $this->getSupplierId() . " is too precise", $this->getSupplierId());
		}

		return true;
	}

    /**
     * Copies given rate into a backup table
     *
     * @date    2016-12-08
     * @story   S18756
     *
     * @param   int $rateId
     */
	protected static function backupRate($rateId)
    {
        $db = Shipserv_Helper_Database::getDb();

	    $sql = "
            INSERT INTO " . self::TABLE_NAME_BACKUP . "
            SELECT
                sbr.*,
                SYSDATE AS " . self::EDIT_TABLE_COL_DATE . "
            FROM
                " . self::TABLE_NAME . " sbr
            WHERE
                sbr." . $db->quoteInto(self::COL_ID . ' = ?', $rateId)
        ;

	    $db->query($sql);
    }

    /**
     * Removes and updated existing rates to free the given interval in which a new rate will be inserted later
     * Returns the IDs of affected rate records
     *
     * We only support start date going in the past now, the new rate always stretches into the future
     *
     * @param   DateTime    $validFrom
     *
     * @return  array
     * @throws  Shipserv_Supplier_Rate_Exception_Invalid
     */
	protected function accommodateNewRate(DateTime $validFrom)
    {
        $relationshipObj = new Shipserv_Supplier_Rate_Buyer($this->getSupplierId(), Shipserv_Supplier_Rate_Buyer::CRON_JOB_NO_USER_ID);
	    $lockedRelationships = $relationshipObj->getLockedRelationshipsByLockDate($validFrom);

	    if (!empty($lockedRelationships)) {
	        throw new Shipserv_Supplier_Rate_Exception_Invalid(
	            "A new rate for supplier " . $this->getSupplierId() . " starting in " . $validFrom->format('Y-m-d H:i:s') .
                " overlaps with relationships locked after it",
                $this->getSupplierId()
            );
        }

        // if we are here, that means that this supplier has no relationships locked in the period the new rate is going
        // to replace

        $db = Shipserv_Helper_Database::getDb();
        $affectedRateIds = array();

        $overlappingRates = $this->getRatesInTheInterval($validFrom, null, false, false);

        foreach ($overlappingRates as $rateRow) {
            $affectedRateIds[] = $rateRow[self::COL_ID];

            if ($rateRow[self::COL_VALID_FROM] < $validFrom) {
                // a rate like this would only be returned if it is the 'leftmost' one
                // it has to be truncated from the right

                self::backupRate($rateRow[self::COL_ID]);

                $db->update(
                    self::TABLE_NAME,
                    array(
                        self::COL_VALID_TILL => new Zend_Db_Expr(Shipserv_Helper_Database::getOracleDateExpr($validFrom))
                    ),
                    $db->quoteInto(self::COL_ID . ' = ?', $rateRow[self::COL_ID])
                );

                continue;
            }

            if ($rateRow[self::COL_VALID_FROM] >= $validFrom) {
                // this rate interval is fully included in the interval of the new rate and needs to be deleted
                self::backupRate($rateRow[self::COL_ID]);

                $db->delete(
                    self::TABLE_NAME,
                    $db->quoteInto(self::COL_ID . ' = ?', $rateRow[self::COL_ID])
                );

                continue;
            }

            throw new Shipserv_Supplier_Rate_Exception_Invalid("Unexpected rate accommodation situation for rate ID " . $rateRow[self::COL_ID]);
        }

        return $affectedRateIds;
    }

	/**
	 * Adds a new rate record for a supplier
	 *
	 * @param   string      $sourceType
	 * @param   string      $sourceId
	 * @param   float|null  $rateStandard
	 * @param   float|null  $rateTarget
	 * @param   int|null    $lockTarget
     * @param   DateTime    $validFrom
	 *
	 * @return  int|null
	 * @throws  Shipserv_Helper_Database_Exception
	 * @throws  Myshipserv_Salesforce_Exception
	 * @throws  Shipserv_Supplier_Rate_Exception_Invalid
	 * @throws  Exception
	 */
	protected function addNewRate($sourceType, $sourceId, $rateStandard = null, $rateTarget = null, $lockTarget = null, DateTime $validFrom = null)
	{
		if ($this->modified) {
			// a new rate has already been added in this session, not expected to happen again
			throw new Shipserv_Supplier_Rate_Exception(
				"A new rate has already been added for supplier " . $this->getSupplierId() . " in this session",
				$this->getSupplierId(),
				$sourceType
			);
		}

		if (is_null($validFrom)) {
		    $validFrom = new DateTime();
        } else {
            $now = new DateTime();
            if ($now < $validFrom) {
                // rates starting in the future are not yet supported
                $validFrom = $now;
            }
        }

		// safety conversion added on 2016-04-22 to maintain the distinction between NULLs and 0 (and for possible FALSEs to be treated as NULLs)
		if (strlen($rateStandard) === 0) {
			$rateStandard = null;
		}

		if (strlen($rateTarget) === 0) {
			$rateTarget = null;
		}

		$this->validateRateValue($rateStandard);
		$this->validateRateValue($rateTarget);

		// verify that the target rate, if it is over zero, is not lower than the standard rate
		if ($rateTarget > 0) {
			if ($rateTarget < $rateStandard) {
				throw new Shipserv_Supplier_Rate_Exception_Invalid(
					"Supplier " . $this->getSupplierId() . " has a target rate of " . $rateTarget . " which is lower than its standard rate of " . $rateStandard,
					$this->getSupplierId(),
					$sourceType
				);
			}
		}

		$db = Shipserv_Helper_Database::getDb();

		// check if the same rate is already effective
		$select = new Zend_Db_Select($db);
		$select
			->from(
				array('sbr' => self::TABLE_NAME),
				array(
					'sbr.' . self::COL_ID,
					'sbr.' . self::COL_RATE_STANDARD,
					'sbr.' . self::COL_RATE_TARGET,
					'sbr.' . self::COL_LOCK_TARGET
				)
			)
			->where('sbr.' . self::COL_SUPPLIER . ' = ?', $this->getSupplierId())
			->where('sbr.' . self::COL_SF_SRC_TYPE . ' = ?', $sourceType)
			->where('sbr.' . self::COL_VALID_TILL . ' IS NULL')
		;

		if (!is_null($sourceId)) {
			$select->where('sbr.' . self::COL_SF_SRC_ID . ' = ?', $sourceId);
		} else {
			$select->where('sbr.' . self::COL_SF_SRC_ID . ' IS NULL');
		}

		if (!is_null($rateStandard)) {
			$select->where('sbr.' . self::COL_RATE_STANDARD . ' = ?', $rateStandard);
		} else {
			$select->where('sbr.' . self::COL_RATE_STANDARD . ' IS NULL');
		}

		if (!is_null($rateTarget)) {
			$select->where('sbr.' . self::COL_RATE_TARGET . ' = ?', $rateTarget);
		} else {
			$select->where('sbr.' . self::COL_RATE_TARGET . ' IS NULL');
		}

		if (!is_null($lockTarget)) {
			$select->where('sbr.' . self::COL_LOCK_TARGET . ' = ?', $lockTarget);
		} else {
			$select->where('sbr.' . self::COL_LOCK_TARGET . ' IS NULL');
		}

		$existingRateRecord = $db->fetchRow($select);
		if (!empty($existingRateRecord)) {
			// there is no need to update the rate record if it still the same as during the last sync
			return (int) $existingRateRecord[self::COL_ID];
		}

		try {
		    // make space for the new rate
			$affectedRateIds = $this->accommodateNewRate($validFrom);

			// add new rate condition
			$db->insert(
			    self::TABLE_NAME,
                array(
                    self::COL_SUPPLIER      => $this->getSupplierId(),
                    self::COL_RATE_STANDARD => $rateStandard,
                    self::COL_RATE_TARGET   => $rateTarget,
                    self::COL_LOCK_TARGET   => $lockTarget,
                    self::COL_SF_SRC_TYPE   => $sourceType,
                    self::COL_SF_SRC_ID     => $sourceId,
                    self::COL_VALID_FROM    => new Zend_Db_Expr(Shipserv_Helper_Database::getOracleDateExpr($validFrom))
                )
            );

			$recordId = $db->lastSequenceId(self::SEQUENCE_ID);

		} catch (Exception $e) {
			throw new Shipserv_Supplier_Rate_Exception(
				"Failed to update rates for supplier " . $this->getSupplierId() . ": " . $e->getMessage(),
				$this->getSupplierId(),
				$sourceType
			);
		}

		// if no target rate was applied, terminate all ongoing targeted relationships with an unlimited locked period
		// because they are only active as long as we remain in a contract with a target rate (even if the rate is different)

        $activePromotionStatus = $this->canTargetNewBuyers();
		if (!$activePromotionStatus) {
            $relationshipObj = new Shipserv_Supplier_Rate_Buyer($this->getSupplierId(), 0);
            $relationshipObj->backToStandardPricingModel($validFrom);
        }

        // added on 2017-10-23, BUY-1231 to keep AutoSource participating status in sync with Active Promotion access
        Shipserv_Match_Auto_Manager::setSupplierParticipant($this->getSupplierId(), $activePromotionStatus);

        // we cannot recalculate now because the procedure is in another DB and the changes are not yet committed
		/*
        if (!empty($affectedRateIds)) {
            $this->recalculateOrderRates($validFrom);
        }
		*/

		$this->modified = true;

		return $recordId;
	}

    /**
     * Starts recalculation of supplier's orders following changes in supplier's rates
     *
     * @date    2016-12-07
     * @story   S18756
     *
     * @param DateTime $dateFrom
     */
	public function recalculateOrderRates(DateTime $dateFrom)
    {
        $db = Shipserv_Helper_Database::getSsreport2Db();

        $cmd = $db->quoteInto(
            'spb_txn_rates.process(' . Shipserv_Helper_Database::getOracleDateExpr($dateFrom) . ', ?)',
            $this->getSupplierId()
        );

        $db->query("BEGIN " . $cmd . "; END;");
    }

	/**
	 * Validates supplier's rate record so it doesn't contradict legacy monetisation percent field
	 *
	 * @return  true
	 * @throws  Shipserv_Supplier_Rate_Exception
	 */
	public function validateRateAgainstMonetisation()
	{
		$select = new Zend_Db_Select(Shipserv_Helper_Database::getDb());
		$select
			->from(
				array('spb' => Shipserv_Supplier::TABLE_NAME),
                'spb.spb_monetization_percent'
			)
			->where('spb.' . Shipserv_Supplier::COL_ID . ' = ?', $this->getSupplierId())
		;

		$row = $select->getAdapter()->fetchRow($select);
		$rate = $this->getRate();

		// if the multi-tiered rate (if any) corresponds with the legacy one
		if ((float) $rate[self::COL_RATE_STANDARD] !== (float) $row['SPB_MONETIZATION_PERCENT']) {
			throw new Shipserv_Supplier_Rate_Exception(
				"Multi-tiered standard rate " . $rate[self::COL_RATE_STANDARD] .
				" record is different from the legacy rate " . $row['SPB_MONETIZATION_PERCENT'] .
				" for supplier " . $this->getSupplierId(),
				$this->getSupplierId()
			);
		}

		return true;
	}

	/**
	 * Throws an exception when the currently active rate is obviously wrong contradicting supplier's status
	 *
	 * @return  true
	 * @throws  Shipserv_Supplier_Rate_Exception
	 */
	public function validateRateAgainstSupplierStatus()
	{
		$select = new Zend_Db_Select(Shipserv_Helper_Database::getDb());
		$select
			->from(
				array('spb' => Shipserv_Supplier::TABLE_NAME),
				array(
					'spb.spb_connect_type',
					'spb.spb_smart_product_name',
					'spb.spb_monetization_percent'
				)
			)
			->where('spb.' . Shipserv_Supplier::COL_ID . ' = ?', $this->getSupplierId())
		;

		$row = $select->getAdapter()->fetchRow($select);

		$isNullOrZero = function ($value) {
			return ((strlen($value) === 0) or ((float) $value === 0.0));
		};

		if ($row['SPB_CONNECT_TYPE'] === Shipserv_Supplier::PRODUCT_START_SUPPLIER) {
			// start suppliers are not supposed to have any rates
			if (!$isNullOrZero($row['SPB_MONETIZATION_PERCENT'])) {
				throw new Shipserv_Supplier_Rate_Exception_StartSupplierRateNotZero(
					"StartSupplier " . $this->getSupplierId() . " was assigned non-zero rate of " . $row['SPB_MONETIZATION_PERCENT'],
					$this->getSupplierId()
				);
			}
		}

		if ($row['SPB_SMART_PRODUCT_NAME'] === Shipserv_Supplier::PRODUCT_SMART_SUPPLIER) {
			// smart suppliers are not supposed to have zero rates
			if ($isNullOrZero($row['SPB_MONETIZATION_PERCENT'])) {
				throw new Shipserv_Supplier_Rate_Exception_SmartSupplierRateZero(
					"SmartSupplier " . $this->getSupplierId() . " was assigned zero rate of " .
					(is_null($row['SPB_MONETIZATION_PERCENT']) ? "NULL" : $row['SPB_MONETIZATION_PERCENT']),
					$this->getSupplierId()
				);
			}
		}

		return true;
	}

	/**
	 * Checks if rate exists, belong to the same supplier and fits the specific criteria
	 *
	 * @param   int     $rateId
	 * @param   bool    $mustBeActive
	 * @param   bool    $mustBeAbleToTarget
	 *
	 * @return  bool
	 * @throws  Shipserv_Supplier_Rate_Exception
	 */
	public function validateRate($rateId, $mustBeActive = false, $mustBeAbleToTarget = false)
	{
		$db = Shipserv_Helper_Database::getDb();

		$select = new Zend_Db_Select($db);
		$select
			->from(
				array('sbr' => self::TABLE_NAME),
				array(
					'sbr.' . self::COL_SUPPLIER,
					'sbr.' . self::COL_VALID_TILL,
					'sbr.' . self::COL_SF_SRC_TYPE,
					'sbr.' . self::COL_RATE_TARGET,
					'sbr.' . self::COL_RATE_STANDARD
				)
			)
			->where('sbr.' . self::COL_ID . ' = ?', $rateId)
		;

		$row = $select->getAdapter()->fetchRow($select);

		if ($row === false) {
			throw new Shipserv_Supplier_Rate_Exception("Rate " . $rateId . " does not exist", $this->getSupplierId());
		}

		if (((int) $row[self::COL_SUPPLIER]) !== $this->getSupplierId()) {
			throw new Shipserv_Supplier_Rate_Exception("Rate " . $rateId . " does not belong to supplier " . $this->getSupplierId(), $this->getSupplierId());
		}

		if ($mustBeActive) {
			// rate record must be the most recent one with an open expiration date
			if (strlen($row[self::COL_VALID_TILL])) {
				throw new Shipserv_Supplier_Rate_Exception("Rate " . $rateId . " is not currently active", $this->getSupplierId());
			}
		}

		if ($mustBeAbleToTarget) {
			// rate record must allow the supplier to participate in Active Promotion
			if (strlen($row[self::COL_RATE_TARGET]) === 0) {
				throw new Shipserv_Supplier_Rate_Exception("Rate " . $rateId . " has an undefined target rate so Active Promotion is not enabled");
			}

			if (strlen($row[self::COL_RATE_STANDARD]) === 0) {
				throw new Shipserv_Supplier_Rate_Exception("Rate " . $rateId . " has an undefined standard rate so Active Promotion is not enabled as it is not possible to compare it to the target rate");
			}

			if (((float) $row[self::COL_RATE_STANDARD]) > ((float) $row[self::COL_RATE_TARGET])) {
				throw new Shipserv_Supplier_Rate_Exception("Rate " . $rateId . " has a target rate lower than the standard rate value so Active Promotion is not enabled");
			}
		}

		return true;
	}

	/**
	 * Returns true if the rate was received from a contract
	 *
	 * @param   int $rateId
	 *
	 * @return bool
	 */
	public function isUnderContract($rateId = null)
	{
		try {
			$rate = $this->getRate($rateId);
		} catch (Shipserv_Supplier_Rate_Exception $e) {
			// no active rate
			return false;
		}

		return in_array($rate[self::COL_SF_SRC_TYPE], self::getContractRateSourceTypes());
	}

	/**
	 * Returns true if the current rate settings allow supplier to target new buyers
	 * (i.e. that supplier is under a contract with a target rate)
	 *
	 * @param   int $rateId
	 *
	 * @return  bool
	 */
	public function canTargetNewBuyers($rateId = null)
	{
		try {
			$rate = $this->getRate($rateId);
		} catch (Shipserv_Supplier_Rate_Exception $e) {
			// no active rate
			return false;
		}

		try {
			return $this->validateRate($rate[self::COL_ID], true, true);

		} catch (Shipserv_Supplier_Rate_Exception $e) {
			return false;
		}
	}

	/**
	 * Returns time intervals within the given one where the supplier was under a contract with a non-zero standard rate
	 *
	 * @date    2016-03-10
	 * @story   S15989
	 *
	 * @param   DateTime    $dateStart
	 * @param   DateTime    $dateEnd
	 *
	 * @return  array
	 */
	public function getStandardRateContractIntervals(DateTime $dateStart, DateTime $dateEnd)
	{
		$strDateStart = Shipserv_Helper_Database::getOracleDateExpr($dateStart);
		$strDateEnd   = Shipserv_Helper_Database::getOracleDateExpr($dateEnd);

		$select = new Zend_Db_Select(Shipserv_Helper_Database::getDb());
		$select
			->from(
				array('sbr' => self::TABLE_NAME),
				'sbr.' . self::COL_ID
			)
			->where('sbr.' . self::COL_SUPPLIER . ' = ?', $this->getSupplierId())
			->where('sbr.' . self::COL_SF_SRC_TYPE . ' IN (?)', self::getContractRateSourceTypes())
			->where('sbr.' . self::COL_RATE_STANDARD . ' > ?', 0)
			// starts in the past or in the interval, ends in the future or in the interval
			->where(
				implode(
					' AND ',
					array(
						// start date in the past or within the interval
						'sbr.' . self::COL_VALID_FROM . ' <= ' . $strDateEnd,
						// end date in the interval or in the future
						'(' .
						implode(
							' OR ',
							array(
								'sbr.' . self::COL_VALID_TILL . ' > ' . $strDateStart,
								'sbr.' . self::COL_VALID_TILL . ' IS NULL'
							)
						) .
						')'
					)
				)
			)
			->order('sbr.' . self::COL_VALID_FROM)
		;

		$rateIds = $select->getAdapter()->fetchCol($select);

		$intervals = array();
		$interval = null;
		foreach ($rateIds as $rateId) {
			$rate = $this->getRate($rateId);

			$interval[] = max($dateStart, $rate[self::COL_VALID_FROM]);

			if ($rate[self::COL_VALID_TILL]) {
				$interval[] = min($dateEnd, $rate[self::COL_VALID_TILL]);
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
	 * Returns a list of supplier IDs which were recorded as under contract in the provided time interval
	 *
	 * @story   S16472
	 * @date    2016-04-29
	 *
	 * @param   DateTime        $dateStart
	 * @param   DateTime|null   $dateEnd
	 *
	 * @return  array
	 */
	public static function getContractedSupplierIds(DateTime $dateStart, DateTime $dateEnd = null)
	{
		$select = new Zend_Db_Select(Shipserv_Helper_Database::getDb());
		$select
			->from(
				array('sbr' => self::TABLE_NAME),
				'sbr.' . self::COL_SUPPLIER
			)
			->where('sbr.' . self::COL_SF_SRC_TYPE . ' IN (?)', self::getContractRateSourceTypes())
			->where('sbr.' . self::COL_RATE_STANDARD . ' >= 0')
			// rate not ended before the given period has started
			->where(
				implode(
					' OR ',
					array(
						'sbr.' . self::COL_VALID_TILL . ' >= ' . Shipserv_Helper_Database::getOracleDateExpr($dateStart),
						'sbr.' . self::COL_VALID_TILL . ' IS NULL'
					)
				)
			)
			->distinct()
		;

		// rate did not begin after the give period has ended
		if ($dateEnd) {
			$select->where('sbr.' . self::COL_VALID_FROM . ' < ' . Shipserv_Helper_Database::getOracleDateExpr($dateEnd));
		}

		return $select->getAdapter()->fetchCol($select);
	}

	/**
	 * Returns rates active for the given supplier in the given time interval
	 *
	 * @param   DateTime        $dateStart
	 * @param   DateTime        $dateEnd
	 * @param   bool            $contractOnly
     * @param   bool            $truncateDates
	 *
	 * @return array
	 */
	public function getRatesInTheInterval(DateTime $dateStart, DateTime $dateEnd = null, $contractOnly = true, $truncateDates = true)
	{
	    if (is_null($dateEnd)) {
	        $dateEnd = new DateTime();
        }

		$select = new Zend_Db_Select(Shipserv_Helper_Database::getDb());
		$select
			->from(
				array('sbr' => self::TABLE_NAME),
				array(
					self::COL_ID            => 'sbr.' . self::COL_ID,
					self::COL_SUPPLIER      => 'sbr.' . self::COL_SUPPLIER,
					self::COL_SF_SRC_TYPE   => 'sbr.' . self::COL_SF_SRC_TYPE,
					self::COL_SF_SRC_ID     => 'sbr.' . self::COL_SF_SRC_ID,
					self::COL_RATE_STANDARD => 'sbr.' . self::COL_RATE_STANDARD,
					self::COL_RATE_TARGET   => 'sbr.' . self::COL_RATE_TARGET,
					self::COL_LOCK_TARGET   => 'sbr.' . self::COL_LOCK_TARGET,
					self::COL_VALID_FROM    => new Zend_Db_Expr('TO_CHAR(sbr.' . self::COL_VALID_FROM . ", 'YYYY-MM-DD HH24:MI:SS')"),
					self::COL_VALID_TILL    => new Zend_Db_Expr('TO_CHAR(sbr.' . self::COL_VALID_TILL . ", 'YYYY-MM-DD HH24:MI:SS')")
				)
			)
			->where('sbr.' . self::COL_SUPPLIER . ' = ?', $this->getSupplierId())
			// rate did not begin after the give period has ended
			->where('sbr.' . self::COL_VALID_FROM . ' < ' . Shipserv_Helper_Database::getOracleDateExpr($dateEnd))
			// rate not ended before the given period has started
			->where(
				implode(
					' OR ',
					array(
						'sbr.' . self::COL_VALID_TILL . ' >= ' . Shipserv_Helper_Database::getOracleDateExpr($dateStart),
						'sbr.' . self::COL_VALID_TILL . ' IS NULL'
					)
				)
			)
			->distinct()
		;

		if ($contractOnly) {
			$select->where('sbr.' . self::COL_SF_SRC_TYPE . ' IN (?)', self::getContractRateSourceTypes());
		}

		$rows = $select->getAdapter()->fetchAll($select);
		$rates = array();

		// align (by truncating) rate validity intervals with the provided time interval
        foreach ($rows as $row) {
            $row = self::prepareRecord($row);

            if ($truncateDates) {
                $row[self::COL_VALID_FROM] = max($row[self::COL_VALID_FROM], $dateStart);

                if (is_null($row[self::COL_VALID_TILL])) {
                    $row[self::COL_VALID_TILL] = $dateEnd;
                } else {
                    $row[self::COL_VALID_TILL] = min($row[self::COL_VALID_TILL], $dateEnd);
                }
            }

            $rates[] = $row;
        }

		return $rates;
	}

	/**
	 * Retrieves the most recent rate set ID synced from SalesForce with the same rate value as specified
	 * Is needed to attribute value events to a rate set. According to James, it's okay if the actual orders
	 * in the value event were attributed to a different rate set as long as the value of the rate is identical
	 *
	 * @date    2016-09-22
	 * @story   S18134
	 *
	 * @param   float|null  $rateValue
	 * @param   bool        $rateStandard
	 * @param   DateTime    $dateFrom
	 * @param   DateTime    $dateTo
	 *
	 * @return  null|string
	 */
	public function findRateSetId($rateValue, $rateStandard, DateTime $dateFrom, DateTime $dateTo)
	{
		$select = new Zend_Db_Select(Shipserv_Helper_Database::getDb());
		$select
			->from(
				array('sbr' => self::TABLE_NAME),
				'sbr.' . self::COL_SF_SRC_ID
			)
			->where('sbr.' . self::COL_SF_SRC_TYPE . ' = ?', self::SF_SRC_TYPE_RATESET)
			->where('sbr.' . self::COL_SUPPLIER . ' = ?', $this->getSupplierId())
			->order('sbr.' . self::COL_VALID_FROM . ' DESC')
		;

		if ($rateStandard) {
			$rateColumn = self::COL_RATE_STANDARD;
		} else {
			$rateColumn = self::COL_RATE_TARGET;
		}

		if (is_null($rateValue)) {
			$select->where($rateColumn . ' IS NULL');
		} else {
			$select->where($rateColumn . ' = ?', $rateValue);
		}

		// first search for rates active in the interval billed
		$selectInInterval = clone($select);
		$selectInInterval
			->where('sbr.' . self::COL_VALID_FROM . ' < ' . Shipserv_Helper_Database::getOracleDateExpr($dateTo))
			->where(
			    implode(
                    ' OR ',
                    array(
                        'sbr.' . self::COL_VALID_TILL . ' IS NULL',
                        'sbr.' . self::COL_VALID_FROM . ' >= ' . Shipserv_Helper_Database::getOracleDateExpr($dateFrom)
                    )
                )
            )
		;

		$rateSetId = $selectInInterval->getAdapter()->fetchOne($selectInInterval);
		if (strlen($rateSetId) === 0) {
			// search again without the interval
			$rateSetId = $select->getAdapter()->fetchOne($select);
		}

		if (strlen($rateSetId) === 0) {
			// supplier has never had a rateset with the rate submitted (may happen when the rate comes from account
			// or was replaced retrospectively
			return null;
		}

		return $rateSetId;
	}
}
