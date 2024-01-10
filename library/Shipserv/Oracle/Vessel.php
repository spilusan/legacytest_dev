<?php
class Shipserv_Oracle_Vessel extends Shipserv_Oracle
{
	const ALERTS_IMMEDIATELY = 'I';
	const ALERTS_WEEKLY = 'W';
	const ALERTS_NEVER = 'N';
	
	protected $db;
	
	function __construct($db = null)
	{
		if( $db == null )
		{
			$this->db = $this->getDb();
		}
		else 
		{
			$this->db = $db;
		}
	}
	
	public function getTypes($dataType = null)
	{
		$sql = "SELECT * FROM PAGES_VESSEL_TYPE ORDER BY PVT_NAME ASC";
		$db = $this->db;

		$vesselTypes = array();

		foreach($db->fetchAll($sql) as $row) {
			if ($dataType == null) {
				$vesselTypes[] = array('id' => $row['PVT_ID'], 'name' => $row['PVT_NAME']);
			} else if( $dataType == 'Zend_Form') {
				$vesselTypes[] = array($row['PVT_ID'] => $row['PVT_NAME']);
			}
		}

		if (empty($vesselTypes)) {
			return null; // stupid, but this is to keep the legacy behaviour (Yuriy Akopov on 2016-06-27, S16901)
		}

		return $vesselTypes;
	}
	
	public function getSelectedVesselTypeByUser($userId)
	{
		$sql = "SELECT puv_vessel_type FROM pages_user_vessel_type WHERE puv_psu_id=:userId";
		$data = array();
		foreach ($this->db->fetchAll($sql, array("userId" => $userId)) as $row) {
			$data[] = $row['PUV_VESSEL_TYPE'];
		}

		return $data;
	}

	/**
	 * Loads vessel names mentioned in buyer's RFQs
	 *
	 * @author  Yuriy Akopov
	 * @date    2016-06-27
	 * @story   S16901
	 *
	 * @param   int         $buyerOrgId
	 * @param   int|null    $buyerBranchId
	 * @param   DateTime    $dateFrom
	 *
	 * @return  array
	 * @throws  Myshipserv_Exception_MessagedException
	 */
	public function getVesselsFromRfqsSelect($buyerOrgId, $buyerBranchId = null, DateTime $dateFrom = null)
	{
		if (is_null($buyerBranchId)) {
			$buyers = Shipserv_Report_Buyer_Match_BuyerBranches::getInstance();
			$data  = $buyers->getBuyerBranches(Shipserv_User::BRANCH_FILTER_BUY);

			$buyerBranchIds = array();
			foreach ($data as $branchItem) {
				$buyerBranchIds[] = $branchItem['id'];
			}
		} else {
			$buyerBranchIds = array($buyerBranchId);
		}

		$select = new Zend_Db_Select(Shipserv_Helper_Database::getSsreport2Db());
		$select
			->from(
				array('rfq' => 'rfq'),
				array(
					'IMO'   => 'rfq.rfq_imo_no',
					'NAME'  => 'rfq.rfq_vessel_name',
					'BUYER' => 'rfq.byb_branch_code'
				)
			)
			->where('rfq.rfq_submitted_date >= ' . Shipserv_Helper_Database::getOracleDateExpr($dateFrom, true))
			// ->where('rfq.rfq_is_valid_imo_no = ?', 1)
			->where('rfq.rfq_valid_vessel_name <> ?', 'NO VESSEL NAME')
			->order('NAME')
			->distinct()
		;

		if ($buyerBranchId == Myshipserv_Config::getProxyPagesBuyer()) {
			$select
				->join(
					array('pir' => new Zend_Db_Expr('pages_inquiry_recipient@livedb_link.shipserv.com')),
					'pir.pir_rfq_internal_ref_no = rfq.rfq_internal_ref_no',
					array()
				)
				->join(
					array('pin' => new Zend_Db_Expr('pages_inquiry@livedb_link.shipserv.com')),
					'pin.pin_id = pir.pir_pin_id',
					array()
				)
				->where('pin.pin_puc_company_type = ?', Shipserv_Rfq::SENDER_TYPE_BUYER)
				->where('pin.pin_puc_company_id = ?', $buyerOrgId)
			;
		} else if (!is_null($buyerBranchId)) {
			$select
				// ->where('rfq.byb_byo_org_code = ?', $buyerOrgId)
				->where('rfq.byb_branch_code IN (?)', $buyerBranchIds)
			;
		} else {
			$select
				->joinLeft(
					array('pir' => new Zend_Db_Expr('pages_inquiry_recipient@livedb_link.shipserv.com')),
					'pir.pir_rfq_internal_ref_no = rfq.' . Shipserv_Rfq::COL_ID,
					array()
				)
				->joinLeft(
					array('pin' => new Zend_Db_Expr('pages_inquiry@livedb_link.shipserv.com')),
					implode(
						' AND ',
						array(
							'pin.pin_id = pir.pir_pin_id',
							$select->getAdapter()->quoteInto('pin.pin_puc_company_type = ?', Shipserv_Rfq::SENDER_TYPE_BUYER)
						)
					),
					array()
				)
				->where(implode(
					' OR ',
					array(
						// $select->getAdapter()->quoteInto('rfq.byb_byo_org_code = ?', $buyerOrgId),
						$select->getAdapter()->quoteInto('rfq.byb_branch_code IN (?)', $buyerBranchIds),
						$select->getAdapter()->quoteInto('pin.pin_puc_company_id = ?', $buyerOrgId)
					)
				))
			;
		}

		// print $select->assemble(); die;
		
		$cacheKey = Myshipserv_Config::decorateMemcacheKey(implode(
			'_',
			array(
				__FUNCTION__,
				md5($select->assemble())
			)
		));
		
		$rows = $this->fetchCachedQuery($select->assemble(), array(), $cacheKey, self::MEMCACHE_TTL, self::SSREPORT2);
		
		return $rows;
	}
}