<?php

/**
 * Provides search functionality to support company name searches.
 */
class Myshipserv_Controller_Action_Helper_CompanyNameSearch extends Zend_Controller_Action_Helper_Abstract
{
	/**
	 * Look up suppliers by branch codes, keeping them in the same order
	 * as they are requested in.
	 *
	 * @return array
	 */
	public function getSuppliersByIds (array $ids)
	{
		// Look up suppliers by branch codes
		$sDao = new Shipserv_Oracle_Suppliers($this->getDb());
		$sRows = $sDao->fetchSuppliersByIds($ids);
		
		// Index rows by ID
		$sRowsIdx = array();
		foreach ($sRows as $i => $r)
		{
			$sRowsIdx[$r['SPB_BRANCH_CODE']] = $r;
		}
		
		// Result array
		$cRes = array();
		
		// Abstract useful details
		foreach ($ids as $id)
		{
			$r = @$sRowsIdx[$id];
			if ($r)
			{
				$cRes[] = array(
					'COMP_TYPE' 	=> 'SPB',
					'COMP_ID' 		=> $r['SPB_BRANCH_CODE'],
					'COMP_NAME' 	=> $r['SPB_NAME'],
					'COMP_CITY' 	=> $r['SPB_CITY'],
					'COMP_STATE' 	=> $r['SPB_STATE_PROVINCE'],
					'COMP_COUNTRY' 	=> $r['SPB_COUNTRY']
				);
			}
		}
		
		return $cRes;
	}
	
	/**
	 * Look up buyers by org codes, keeping them in the same order as they are
	 * requested in.
	 *
	 * @return array
	 */
	public function getBuyersByIds (array $ids)
	{
		// Look up buyers by org codes
		$bDao = new Shipserv_Oracle_BuyerOrganisations($this->getDb());
		$bRows = $bDao->fetchBuyerOrganisationsByIds($ids);

		// Index rows by ID
		$bRowsIdx = array();
		foreach ($bRows as $i => $r)
		{
			$bRowsIdx[$r['BYO_ORG_CODE']] = $r;
		}
		
		// Result array
		$cRes = array();
		
		// Abstract useful details
		foreach ($ids as $id)
		{
			$r = @$bRowsIdx[$id];
			if ($r)
			{
				$cRes[] = array(
					'COMP_TYPE' 	=> 'BYO',
					'COMP_ID' 		=> $r['BYO_ORG_CODE'],
					'COMP_NAME' 	=> $r['BYO_NAME'],
					'COMP_CITY' 	=> $r['BYO_CONTACT_CITY'],
					'COMP_STATE' 	=> $r['BYO_CONTACT_STATE'],
					'COMP_COUNTRY' 	=> $r['BYO_COUNTRY']
				);
			}
		}
		
		return $cRes;
	}
	
	private function getDb ()
	{
		return $GLOBALS['application']->getBootstrap()->getResource('db');
	}
}
