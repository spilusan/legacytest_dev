<?php
/**
 * Class to handle communication between SF and Pages in relation to
 * Uploading Monthly VBP Billing report so Finance can bill suppliers
 * from Salesforce.
 */
// commented out by Yuriy Akopov on 2016-06-08, S16162 as Salesforce library is moved to composer
// require_once ('/var/www/libraries/SalesForce/SforceEnterpriseClient.php');

class Myshipserv_Salesforce_ValueBasedPricing_Rate extends Myshipserv_Salesforce_Base
{
	const
		MIN_SUPPLIER_ID = 30000
	;

	/**
	 * @var null|resource
	 */
	protected $errorCsv = null;

	/**
	 * @var Myshipserv_Logger_Base
	 */
	protected $logger = null;

	/**
	 * @return array
	 */
	protected $syncErrorCount = 0;

    /**
     * @return int
     */
	public function getSyncErrorCount()
    {
		return $this->syncErrorCount;
	}

	/**
	 * Saves sync error information to the log CSV
	 *
	 * @param   Exception   $error
	 */
	public function addErrorInfo(Exception $error)
    {
		$this->syncErrorCount++;

		$message = $error->getMessage();

		if ($error instanceof Shipserv_Supplier_Rate_Exception) {
			$supplierId     = $error->getSupplierId();
			$rateSource     = $error->getRateSource();
			$sfAccountId    = $error->getSfAccountId();
			$sfAccountName  = $error->getSfAccountName();
			$sfContractId   = $error->getSfContractId();
		} else {
			$supplierId     = null;
			$rateSource     = null;
			$sfAccountId    = null;
			$sfAccountName  = null;
			$sfContractId   = null;
		}

		if ($this->errorCsv) {
			fputcsv(
			    $this->errorCsv,
                array(
                    $supplierId,
                    get_class($error),
                    $message,
                    $rateSource,
                    $sfAccountId,
                    $sfAccountName,
                    $sfContractId
    			)
            );
		}
	}

	/**
	 * Myshipserv_Salesforce_ValueBasedPricing_Rate constructor.
	 *
	 * @param   resource  $csv
	 */
	function __construct($csv = null)
    {
		parent::initialiseConnection();

		if ($csv) {
			$this->errorCsv = $csv;

			fputcsv(
			    $this->errorCsv,
                array(
                    'Supplier TNID',
                    'Exception Type',
                    'Error Description',
                    'Rate Source',
                    'SalesForce Account ID',
                    'SalesForce Account Name',
                    'SalesForce Contract ID'
    			)
            );
		}
	}

	/**
	 * By Yuriy Akopov: This function seems a simplified version of pullVBPAndPOPackPercentage() which is called before
	 * value events upload. Not sure if it should remain like this or be replaced / merged with pullVBPAndPOPackPercentage()
	 *
	 * It has been modified and refactored, but not yet completely reworked or joined with pullVPBbla-bla because I am short on time
	 * and also for the first release want to limit the number of changes
	 *
	 * @param   Myshipserv_Logger_Base  $logger
	 * @param   array                   $supplierIds
	 *
	 * @return  mixed
	 * @throws  Exception
	 */
	public function pullVBPSupplier(Myshipserv_Logger_Base $logger, array $supplierIds = null)
    {
		$this->logger = $logger;

		if (!is_null($supplierIds) and !empty($supplierIds)) {
			// this is not a safe input, but this function is only called from the command line by the operator who know what
			// they are doing, but be an acceptable risk
			$supplierConstraint = "AND TNID__c IN (" . implode(", ", $supplierIds) . ")";
		} else {
			$supplierConstraint = "";
		}

		$soql = "
			SELECT
				Id
				, TNID__c
				, Name
				, Contracted_under__r.Id
				, Contracted_under__r.Type_of_agreement__c
				, Contracted_under__r.Status
				, Contracted_under__r.StartDate
			FROM
				Account
			WHERE
				TNID__c != null
				AND TNID__c > 30000
				AND " . self::_getValueBasedStatusConstraint('Contracted_under__r.Type_of_agreement__c'). "
				AND Contracted_under__r.Status = 'Active'
				" . $supplierConstraint . "
			ORDER BY
				TNID__c ASC NULLS LAST
		";

		try {
			$response = $this->querySalesforce($soql, true);

		} catch (Exception $e) {
			$this->logger->log(
			    "[SF] Exception raised: " . $e->faultstring  . $e->getMessage() . " - Last SF request: ",
                print_r($this->sfConnection->getLastRequest(), true)
            );
			throw $e;
		}

		$logger->log("Found " . $response->size . " contracted suppliers in SalesForce");

		if ($response->size == 0 ) {
			// nothing in SalesForce
			return array();
		}

		$iteration = 0;
		$data = array();

		while (true) {
			++$iteration;
			foreach ($response->records as $r) {
				if (!isset($r->Contracted_under__r->Id)) {
					continue;
				}

				$tnid = $r->TNID__c;
				$transitionDate = $this->convertSFDate($r->Contracted_under__r->StartDate);

				$accountDetails = array(
					'accountId'       => $r->Id,
					'contractId'      => $r->Contracted_under__r->Id,
					'contractedUnder' => $r->Contracted_under__r->Id,
					'transitionDate'  => (($transitionDate !== false) ? $transitionDate : ''),
					'tnid'            => $tnid,
					'rateId'          => null
				);

				try {
					$sfData = $this->getRateSetFromContractIdAndTnid($tnid, $r->Id, $r->Contracted_under__r->Id, true);
					$accountDetails['rateId'] = $sfData['rateId'];

				} catch (Myshipserv_Salesforce_Exception $e) {
					//DE6477: skip broken ratesets - they should appear with 0 values in the CSV
                    $e->sendNotification("SalesForce sync error in pullVBPSupplier()");
				}

				$data[$tnid] = $accountDetails;
			}

			if ($response->done) {
				break;
			}

			$this->logger->log("[SF] querying SF for next batch (Job #" . $iteration . ")");

			try {
				$response = $this->sfConnection->queryMore($response->queryLocator);

			} catch (Exception $e) {
				$this->logger->log(
				    "[SF] Exception raised: " . $e->faultstring  . $e->getMessage() . " -  Last SF request: ",
                    print_r($this->sfConnection->getLastRequest(), true)
                );
				throw $e;
			}
		}

		$this->logger->log("Total accounts that have VBP: " . count($data));

		return $data;
	}

	/**
	 * A helper function by Yuriy Akopov to assist in debugging legacy functions without changing them much
	 *
	 * @param   string  $message
	 * @param   mixed   $data
	 * @param   bool    $noPrint
	 */
	protected function logAndPrint($message, $data = null, $noPrint = true)
    {
		if ($this->logger) {
			$this->logger->log($message);
		}

		$now = new DateTime();

		if (!$noPrint) {
			print($now->format('[Y-m-d H:i:s]') . " " . $message . PHP_EOL);
			if (!is_null($data)) {
				print_r($data);
				print(PHP_EOL);
			}
		}
	}

	/**
	 * The key procedure that pulls rates from SalesForce - modified by Yuriy Akopov in order to preserve the existing
	 * logic but also to populate new multi-tired rate structures
	 *
	 * Originally pulled VBP and PO Pack Rate only, now also pulls target rate and lock period for contracted suppliers
	 *
	 * @author  Yuriy Akopov
	 * @date    2016-02-11
	 * @story   S15735
	 *
	 * @param   int|null    $supplierToPull
	 * @param   bool        $checkSupplierType
	 *
	 * @return  array|null
	 *
	 * @throws  Exception
	 * @throws  Myshipserv_Salesforce_Exception
	 */
	public function pullVBPAndPOPackPercentage($supplierToPull = null, $checkSupplierType = false)
    {
		$this->syncErrorCount = 0;
		$this->logger = new Myshipserv_Logger_File('salesforce-pull-vbp-popack-rate');

		try {
			$response = $this->querySalesforce(
				"
				SELECT
					Id
					, TNID__c
					, Name
					, Contracted_under__r.Id
					, Contracted_under__r.Type_of_agreement__c
					, Contracted_under__r.Status
					, Contracted_under__r.StartDate
					, PO_Pack__c
					, Paying_Supplier__c					
					, Active_Promotion_Loss_Leader__c
					, PO_Rate_del__c
					, PO_Rate_Two__c
				FROM
					Account
				WHERE
					TNID__c != null
                    AND TNID__c > " . self::MIN_SUPPLIER_ID . "
			    " . (is_null($supplierToPull) ? "" : "AND TNID__c = " . $supplierToPull) . "
				ORDER BY
					TNID__c ASC NULLS LAST
				", true
			);

		} catch (SoapFault $e) {
			$this->logAndPrint(
				"[SF] " . get_class($e) . " raised: " .	(isset($e->faultstring) ? $e->faultstring : "") . $e->getMessage() .
				" -  Last SF request: ", print_r($this->sfConnection->getLastRequest(), true)
			);
			throw new Myshipserv_Salesforce_Exception($e->getMessage());
		}

		$totalFound = $response->size;
		$this->logAndPrint("Checking all suppliers in SalesForce: " . $totalFound . " records found");

		if ($totalFound == 0) {
			// most likely indicates a problem in SalesForce rather than a legitimate situation
			$errorMessage = "No account records found in SalesForce";
			$this->logAndPrint($errorMessage);
			throw new Myshipserv_Salesforce_Exception($errorMessage);
		}

		$data = array();
		$receivedSupplierIds = array();

		// legacy process - clearing 'simple' rates for all suppliers
		// $this->removeExistingPOPackPercentage();
		// $this->removeExistingVBPPOPercentage();
		// $this->removeExistingMonetisationPercentage();

		//  loop through all the account records obtained from SalesForce
		$iteration = 0;
		$totalProcessed = 0;

		$db = $this->getDb();

		while (true) {
			++$iteration;

			foreach ($response->records as $r) {
				++$totalProcessed;

				$tnid = (int) $r->TNID__c;
				$receivedSupplierIds[] = $tnid;
				$this->logAndPrint("- " . $totalProcessed . "/" . $totalFound . " Checking TNID: " . $tnid);

				$rateSource = null;

				// wrapping every supplier rate update to a transaction to be able to roll back in case of problems.
				$db->beginTransaction();

				try {
					// initialise rate object so we can roll back if required
					$rateObj = new Shipserv_Supplier_Rate($tnid);
					// reset current legacy rates
					$this->removeExistingPOPackPercentage($tnid);
					$this->removeExistingVBPPOPercentage($tnid);
					$this->removeExistingMonetisationPercentage($tnid);

					$multiTieredRateId = false;

					if (
						isset($r->Contracted_under__r->Id) and
						($r->Contracted_under__r->Status === 'Active') and
						in_array($r->Contracted_under__r->Type_of_agreement__c, self::getContractStatusSpellings())
					) {
						$transitionDate = $this->convertSFDate($r->Contracted_under__r->StartDate);

						// changed by Yuriy Akopov on 2016-02-08, S15735 - update function below now updates multi-tiered prices
						$rateSource = Shipserv_Supplier_Rate::SF_SRC_TYPE_RATESET;

						$sfData = $this->getRateSetFromContractIdAndTnid($tnid, $r->Id, $r->Contracted_under__r->Id);
						$multiTieredRateId = $sfData['multiTieredRateId'];

						$data[$tnid] = array(
							'accountId'         => $sfData['accountId'],
							'contractId'        => $sfData['contractId'],
							'rateId'            => $sfData['rateId'],
							'multiTieredRateId' => (bool) $sfData['multiTieredRateId'],
							'tnid'              => $tnid,
							'contractedUnder'   => $r->Contracted_under__r->Id,
							'transitionDate'    => (($transitionDate !== false) ? $transitionDate : '')
						);

						$this->updateVBPTransitionTable($data);

					} else if ($r->Active_Promotion_Loss_Leader__c) {
						// a special case for specifying a target rate without a contract, typically for switching Start Suppliers into Active Promotion

						if ($r->PO_Pack__c !== null) {
							$defaultRate = $r->PO_Pack__c;
						} else {
							// if there is no PO_Pack which usually defines account-level standard rate, use the new field for this use case specifically
							$defaultRate = $r->PO_Rate_del__c;
						}

						// this source of the rate didn't exist in the legacy mode so there is no legacy supplier_branch field to populate with the rate value
						// I believe the use case closest to it is the case of PO_Pack which also comes from the account object, so this is why I am using this one
						$this->updatePOPackPercentage($tnid, $defaultRate);

						$rateSource = Shipserv_Supplier_Rate::SF_SRC_TYPE_ACCOUNT;
						$multiTieredRateId = $rateObj->addNewAccountRate($r->Id, $defaultRate, $r->PO_Rate_Two__c);

						$this->logAndPrint("- Account target rate: " . $defaultRate . "%, standard rate " . $r->PO_Rate_del__c . "%");

					} else if ($r->Paying_Supplier__c === true) {
						if ($r->PO_Pack__c != null) {
							$defaultRate = $r->PO_Pack__c;

							$this->updatePOPackPercentage($tnid, $defaultRate);
							$rateSource = Shipserv_Supplier_Rate::SF_SRC_TYPE_ACCOUNT;
							$multiTieredRateId = $rateObj->addNewAccountRate($r->Id, $defaultRate);

							$this->logAndPrint("- PO Pack: " . $defaultRate . "%");
						} else {
							$defaultRate = Myshipserv_Config::getSalesForceDefaultPayingCustomerRate();

							$this->updateMonetisationPercentage($tnid, $defaultRate);
							$rateSource = Shipserv_Supplier_Rate::SF_SRC_TYPE_CONSTANT;
							$multiTieredRateId = $rateObj->addConstantRate($defaultRate);

							$this->logAndPrint("- No PO Pack or Contract, applying the constant rate of " . $defaultRate);
						}
					}

					if (!$multiTieredRateId) {
						$rateSource = Shipserv_Supplier_Rate::SF_SRC_TYPE_CONSTANT;
						$rateObj->addNullRate();

						$this->logAndPrint("- Nullifying a rate for SalesForce supplier as it has no rates");
					}

					$rateObj->validateRateAgainstMonetisation();

					if ($checkSupplierType) {
						$rateObj->validateRateAgainstSupplierStatus();
					}

					$db->commit();

					// S18756: if a new contract rate was assigned, we need to recalculate order rates
                    // because it might have been a retrospective rate
                    if (in_array($rateSource, Shipserv_Supplier_Rate::getContractRateSourceTypes())) {
                        if (!is_null($multiTieredRateId)) {
                            $this->logAndPrint("Recalculating order rates for supplier " . $rateObj->getSupplierId() . " new rate " . $multiTieredRateId);

                            $rateRow = $rateObj->getRate($multiTieredRateId);
                            $rateObj->recalculateOrderRates($rateRow[Shipserv_Supplier_Rate::COL_VALID_FROM]);
                        }
                    }

				} catch (Shipserv_Supplier_Rate_Exception $e) {
					$db->rollBack();

					// add more information about the account record processed
					$e->setRateSource($rateSource);
					$e->setSfAccountId($r->Id);
					$e->setSfAccountName($r->Name);
					$e->setSfContractId($r->Contracted_under__r->Id);

					$this->addErrorInfo($e);
					$this->logAndPrint("- ERROR " . get_class($e) . ": " . $e->getMessage());

                } catch (Myshipserv_Salesforce_Exception $e) {
                    //DE7384: skip broken ratesets
                    $e->sendNotification("SalesForce sync error in pullVBPAndPOPackPercentage()");
                    $this->addErrorInfo($e);
                    $this->logAndPrint(" - ERROR " . get_class($e) . ": " . $e->getMessage());
                }
			}

			// load the next chunk of records from SalesForce
			if (!$response->done) {
				$this->logAndPrint("[SF] querying SF for next batch (Job #" . $iteration . ")");

				try {
					$response = $this->sfConnection->queryMore($response->queryLocator);

				} catch (SoapFault $e) {
					$this->logAndPrint(
						"[SF] " . get_class($e) . " raised: " .	(isset($e->faultstring) ? $e->faultstring : "") . $e->getMessage() .
						" -  Last SF request: ", print_r($this->sfConnection->getLastRequest(), true)
					);

					// @todo: this is risky, continuing here with next page or to abort in the middle of the sync - neither seems right as we cannot roll back...
					throw $e;
				}

			} else {
				// no more SalesForce records to process
				break;
			}
		}

		$this->logAndPrint("Total number of accounts that have VBP: " . count($data));

		// nullify rates for suppliers that were not downloaded
		if (is_null($supplierToPull)) {
			$nullifiedSupplierIds = $this->nullifyRatesNotInSalesForce($receivedSupplierIds, $checkSupplierType);
			$this->logAndPrint("Suppliers not downloaded and rates nullified: " . count($nullifiedSupplierIds));
		}

		return $data;
	}

	/**
	 * Some suppliers might not be listed in SalesForce which means their rate should be set to 0. But this action still
	 * needs to be validated and possibly reverted, if not applicable. Previously we used to nullify rates for all suppliers
	 * before stating the sync, and now here is this slower per-row approach which allows rolling back on per supplier basis
	 *
	 * @author  Yuriy Akopov
	 * @date    2016-02-15
	 * @story   S15735
	 *
	 * @param   array       $processedSupplierIds
	 * @param   bool        $checkSupplierType
	 *
	 * @return  array
	 * @throws  Zend_Paginator_Exception
	 */
	protected function nullifyRatesNotInSalesForce(array $processedSupplierIds, $checkSupplierType = false)
    {
		$this->logAndPrint("Resetting rates suppliers not found among " . count($processedSupplierIds) . " downloaded from SalesForce");

		$db = Shipserv_Helper_Database::getDb();
		$select = new Zend_Db_Select($db);

		$select
			->from(
				array('spb' => Shipserv_Supplier::TABLE_NAME),
				'*'
			)
			->where('spb.' . Shipserv_Supplier::COL_ID . ' > ?', self::MIN_SUPPLIER_ID)
		;

		$paginator = Zend_Paginator::factory($select);
		$pageSize = 1000;
		$paginator->setItemCountPerPage($pageSize);

		$nullifiedSuppliers = array();
		while (true) {
			$rows = $paginator->getCurrentItems();

			foreach ($rows as $supplierRow) {
				$supplierId = $supplierRow[Shipserv_Supplier::COL_ID];
				$nullifiedSuppliers[] = $supplierId;

				$db->beginTransaction();
				try {
					if (in_array($supplierId, $processedSupplierIds)) {
						continue;   // this supplier has been downloaded from SalesForce and processed already
					}

					$rateObj = new Shipserv_Supplier_Rate($supplierId);

					// erasing legacy rates
					$this->removeExistingPOPackPercentage($supplierId);
					$this->removeExistingVBPPOPercentage($supplierId);
					$this->removeExistingMonetisationPercentage($supplierId);

					// erasing multi-tiered rate
					$multiTieredRateId = $rateObj->addNullRate();

					$rateObj->validateRateAgainstMonetisation();
					if ($checkSupplierType) {
						$rateObj->validateRateAgainstSupplierStatus();
					}

					$db->commit();

				} catch (Shipserv_Supplier_Rate_Exception $e) {
					$db->rollBack();

					$e->setRateSource('NULLIFICATION');
					$this->addErrorInfo($e);
				}
			}

			if (count($rows) < $pageSize) {
				break; // last page
			}

			$paginator->setCurrentPageNumber($paginator->getCurrentPageNumber() + 1);
		}

		return $nullifiedSuppliers;
	}

    /**
     * @param   string  $sfDate
     * @return  bool|false|string
     */
	public function convertSFDate($sfDate)
	{
		if ($sfDate != "") {
			$tmp = explode("-", $sfDate);
			$dateObject = mktime(0, 0, 0, $tmp [1], $tmp [2], $tmp [0]);
			return date('d-M-Y', $dateObject);
		}

		return false;
	}

	/**
	 * Reworked by Yuriy Akopov on 2016-02-11
	 *
	 * @param   int     $tnid
	 * @param   string  $sfAccountId
	 * @param   string  $sfContractId
	 * @param   bool    $skipMultiTiered    @todo: this is a dodgy crutch for value events upload script not yet modified
	 *
	 * @return  array
	 * @throws  Myshipserv_Salesforce_Exception
	 */
	public function getRateSetFromContractIdAndTnid($tnid, $sfAccountId, $sfContractId, $skipMultiTiered = false)
    {
		$response = $this->querySalesforce(
			"
            SELECT
				Id,
				PO_percentage_fee__c,
				Target_PO_Fee__c,
				Target_PO_Fee_Lock_Period_Days__c,
				Valid_from__c
			FROM
				Rate__c
			WHERE
				Contract__c = '" . $sfContractId . "'
				AND Active_Rates__c = true
			",
            true
		);

		if ($response->size == 0) {
			throw new Myshipserv_Salesforce_Exception("Unable to retrieve contracted supplier's " . $tnid . " rate for contract " . $sfContractId);
		}

		$rate = $response->records[0];

		// update the PO% on
		if ($rate->PO_percentage_fee__c != "") {
			$this->updateVBPPOPercentage($tnid, $rate->PO_percentage_fee__c);
		}
		$this->logAndPrint(
			"- VBP Contract found: rateId: " . $rate->Id . ", PO%: " .
			(($rate->PO_percentage_fee__c != "") ? $rate->PO_percentage_fee__c : "Not specified") . " for: " . $tnid
		);

		// added by Yuriy Akopov on 2016-02-05, S15735
		$recordId = null;

		// added on 2016-12-08, S18756
		$rateValidFromDate = null;
		if (strlen($rate->Valid_from__c)) {
            $rateValidFromDate = new DateTime($rate->Valid_from__c);
        }

		if (!$skipMultiTiered) {
			$rateObj = new Shipserv_Supplier_Rate($tnid);
			$recordId = $rateObj->addNewContractRateSetRate(
			    $rate->Id,
                $rate->PO_percentage_fee__c,
                $rate->Target_PO_Fee__c,
                $rate->Target_PO_Fee_Lock_Period_Days__c,
                $rateValidFromDate
            );

			$this->logAndPrint("- Updated multi-tiered rates, record " . $recordId . " added / updated");
		}

		return array(
			'rateId'            => $rate->Id,
			'accountId'         => $sfAccountId,
			'contractId'        => $sfContractId,
			'multiTieredRateId' => $recordId,
            'rateValidFromDate' => $rateValidFromDate
		);
	}

	/**
	 * Changed by Yuriy Akopov to nullify one supplier's rate only
	 *
	 * @param   int $tnid
	 */
	public function removeExistingMonetisationPercentage($tnid = null)
    {
		$constraints = array(
			'spb_monetization_percent IS NOT NULL'
		);

		if ($tnid) {
			$constraints[] = $this->db->quoteInto(Shipserv_Supplier::COL_ID . ' = ?', $tnid);
		}

		$this->db->update(
			Shipserv_Supplier::TABLE_NAME,
			array(
				'spb_monetization_percent' => null
			),
			implode(' AND ', $constraints)
		);
	}

	/**
	 * Changed by Yuriy Akopov to nullify one supplier's rate only
	 *
	 * @param   int $tnid
	 */
	public function removeExistingVBPPOPercentage($tnid = null)
    {
		$constraints = array(
			'spb_vbp_percentage IS NOT NULL'
		);

		if ($tnid) {
			$constraints[] = $this->db->quoteInto(Shipserv_Supplier::COL_ID . ' = ?', $tnid);
		}

		$this->db->update(
			Shipserv_Supplier::TABLE_NAME,
			array(
				'spb_vbp_percentage' => null
			),
			implode(' AND ', $constraints)
		);
	}

	/**
	 * Changed by Yuriy Akopov to nullify one supplier's rate only
	 *
	 * @param   int $tnid
	 */
	public function removeExistingPOPackPercentage($tnid = null)
    {
		$constraints = array(
			'spb_po_pack_percentage IS NOT NULL'
		);

		if ($tnid) {
			$constraints[] = $this->db->quoteInto(Shipserv_Supplier::COL_ID . ' = ?', $tnid);
		}

		$this->db->update(
			Shipserv_Supplier::TABLE_NAME,
			array(
				'spb_po_pack_percentage' => null
			),
			implode(' AND ', $constraints)
		);
	}

	/**
	 * Updating PO Percentage for VBP customer
	 *
	 * @param   int     $tnid
	 * @param   float   $percentage
	 *
	 * @return  bool
	 */
	public function updateVBPPOPercentage($tnid, $percentage)
	{
		$sql = "UPDATE supplier_branch SET spb_monetization_percent=:pc, spb_vbp_percentage = :pc WHERE spb_branch_code = :tnid";
		$this->db->query($sql, array('pc' => $percentage, 'tnid' => $tnid));

		return true;
	}

	/**
	 * Updating PO Pack percentage
	 *
	 * @param   int     $tnid
	 * @param   float   $percentage
	 *
	 * @return  bool
	 */
	public function updatePOPackPercentage($tnid, $percentage)
	{
		$sql = "UPDATE supplier_branch SET spb_monetization_percent = :pc, spb_po_pack_percentage = :pc WHERE spb_branch_code = :tnid";
		$this->db->query($sql, array('pc' => $percentage, 'tnid' => $tnid));

		return true;
	}

	/**
	 * @param   int     $tnid
	 * @param   float   $percentage
	 *
	 * @return  bool
	 */
	public function updateMonetisationPercentage($tnid, $percentage)
	{
		$sql = "UPDATE supplier_branch SET spb_monetization_percent = :pc WHERE spb_branch_code = :tnid";
		$this->db->query($sql, array('pc' => $percentage, 'tnid' => $tnid));

		return true;
	}

	/**
	 * Updating VBP Transition table which is used by other parts of system
	 *
	 * @param   array   $row
	 *
	 * @return  boolean
	 */
	public function updateVBPTransitionTable(array $row)
    {
		$db = $this->db;

		try {
			$sql = "
					MERGE INTO vbp_transition_date USING DUAL ON (vst_spb_branch_code = :tnid)
						WHEN MATCHED THEN
							UPDATE SET
								vst_transition_date=TO_DATE(:transitionDate, 'DD-MON-YYYY'),
								vst_contracted_under=:contractedUnder,
								vst_sf_rate_id=:rateId,
								vst_sf_contract_id=:contractId,
								vst_sf_account_id=:accountId,
								vst_date_updated_from_sf=SYSDATE
						WHEN NOT MATCHED THEN
							INSERT
								(
									vst_spb_branch_code,
									vst_transition_date,
									vst_date_updated_from_sf,
									vst_date_created,
									vst_contracted_under,
									vst_sf_rate_id,
									vst_sf_contract_id,
									vst_sf_account_id
								)
							VALUES
								(
									:tnid,
									TO_DATE(:transitionDate, 'DD-MON-YYYY'),
									SYSDATE,
									SYSDATE,
									:contractedUnder,
									:rateId,
									:contractId,
									:accountId
								)
				";
			if ($row['tnid'] != "") {
				$db->query($sql, $row);
			}
		} catch (Exception $e) {
			$this->logAndPrint(
			    $e->getMessage(),
                array(
				    'tnid' => $row['tnid'],
				    'transitionDate' => $row['transitionDate']
			    )
            );
		}

		return true;
	}
}
