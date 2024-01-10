<?php
/**
 * Thrown when something is wrong with buyer rates (typically when the data is inconsistent etc.)
 *
 * @author  Yuriy Akopov
 * @date    2016-02-10
 * @story   S15735
 */
class Shipserv_Supplier_Rate_Exception extends Exception {
	/**
	 * @var null|int
	 */
	protected $supplierId = null;

	/**
	 * @var string|null
	 */
	protected $rateSource = null;

	/**
	 * @var string|null
	 */
	protected $sfAccountId = null;

	/**
	 * @var string|null
	 */
	protected $sfAccountName = null;

	/**
	 * @var string|null
	 */
	protected $sfContractId = null;

	/**
	 * @return null|int
	 */
	public function getSupplierId() {
		return $this->supplierId;
	}

	/**
	 * @return null|string
	 */
	public function getRateSource() {
		return $this->rateSource;
	}

	/**
	 * @param   string  $rateSource
	 */
	public function setRateSource($rateSource) {
		$this->rateSource = $rateSource;
	}

	/**
	 * @return null|string
	 */
	public function getSfAccountId() {
		return $this->sfAccountId;
	}

	/**
	 * @param   string  $sfAccountId
	 */
	public function setSfAccountId($sfAccountId) {
		$this->sfAccountId = $sfAccountId;
	}

	/**
	 * @return null|string
	 */
	public function getSfAccountName() {
		return $this->sfAccountName;
	}

	/**
	 * @param   string  $sfAccountName
	 */
	public function setSfAccountName($sfAccountName) {
		$this->sfAccountName = $sfAccountName;
	}

	/**
	 * @return null|string
	 */
	public function getSfContractId() {
		return $this->sfContractId;
	}

	/**
	 * @param   string  $sfContractId
	 */
	public function setSfContractId($sfContractId) {
		$this->sfContractId = $sfContractId;
	}

	/**
	 * Shipserv_Supplier_Rate_Exception constructor.
	 * @param   string      $message
	 * @param   int|null    $supplierId
	 * @param   string|null $rateSource
	 * @param   string|null $sfAccountId
	 * @param   string|null $sfAccountName
	 * @param   string|null $sfContractId
	 */
	public function __construct($message = null, $supplierId = null, $rateSource = null, $sfAccountId = null, $sfAccountName = null, $sfContractId = null) {
		$this->supplierId = $supplierId;

		$this->rateSource    = $rateSource;
		$this->sfAccountId   = $sfAccountId;
		$this->sfAccountName = $sfAccountName;
		$this->sfContractId  = $sfContractId;

		parent::__construct($message);
	}
}