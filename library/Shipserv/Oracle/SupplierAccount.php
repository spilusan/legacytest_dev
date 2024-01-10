<?php

/**
 * Class for reading supplier account data from Oracle
 * 
 * @package Shipserv
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
class Shipserv_Oracle_SupplierAccount extends Shipserv_Oracle
{
	public function __construct (&$db)
	{
		parent::__construct($db);
	}
	
	/**
	 * Fetches account data for a specific supplier
	 *
	 * @access public
	 * @param int $tnid The TradeNet ID of the supplier branch for which account data should be fetched
	 * @return array
	 */
	public function fetch ($tnid)
	{
		$sql = 'SELECT *';
		$sql.= '  FROM pages_enquiry_account';
		$sql.= ' WHERE pea_spb_branch_code = :tnid';
		
		$sqlData = array('tnid' => $tnid);
		
		$accountData = $this->db->fetchRow($sql, $sqlData);
		
		// calculate remaining balances, credits, etc.
		if ($accountData['PEA_ACCOUNT_TYPE'] == 'PREPAY')
		{
			$accountData['remainingCredits'] = (int) $accountData['PEA_ENQ_PAID_COUNT'] - (int) $accountData['PEA_ENQ_OPENED_COUNT'];
			$accountData['remainingBalance'] = $accountData['PEA_ENQ_PRICE'] * $accountData['remainingCredit'];
		}
		elseif ($accountData['PEA_ACCOUNT_TYPE'] == 'PAYASYOUGO')
		{
			$accountData['unpaidBalance'] = $accountData['PEA_ENQ_PRICE'] * ( (int) $accountData['PEA_ENQ_OPENED_COUNT'] - (int) $accountData['PEA_ENQ_PAID_COUNT']);
		}
		
		return $accountData;
	}
}