<?php

class Myshipserv_View_Helper_Stats extends Zend_View_Helper_Abstract
{
	
	/**
	 *
	 * @access public
	 * @return Myshipserv_View_Helper_Stats
	 */
	public function stats ()
	{
		return $this;
	}
	
	public function init ()
	{
		
	}
	
	public function resultPage (MyShipserv_SupplierTransactionStats_ApiResult $data)
	{
		$params = array();
		
		$params['byMonth'] = array();
		$params['byMonth']['xmlUrl'] = "/stats/transbymonth/format/xml/cd/" . base64_encode(serialize($data->byMonthAsArr())) . "?unique_id=" . uniqid();
		$params['byMonth']['total'] = self::formatNumberAsStr($data->getMonthResult()->getTotal());
		
		$params['byLevel'] = array();
		$params['byLevel']['xmlUrl'] = "/stats/transbylevel/format/xml/cd/" . base64_encode(serialize($data->byLevelAsArr())) . "?unique_id=" . uniqid();
		
		$res = $this->view->partial('transactionstats.phtml', $params);
		return $res;
	}
	
	public static function formatNumberAsStr ($v)
	{
		$arr = self::formatNumber($v);
		$res = $arr['v'];
		if ($arr['scale'])
		{
			$res .= ' ' . $arr['scale'];
		}
		return $res;
	}
	
	public static function formatNumber ($v)
	{
		if ($v >= 10000 && $v < 1000000)
		{
			$res = array('v' => number_format($v / 1000, 1), 'scale' => 'k', 'div' => 1000);
		}
		else if ($v >= 1000000 && $v < 1000000000)
		{
			$res = array('v' => number_format($v / 1000000, 1), 'scale' => 'm', 'div' => 1000000);
		}
		else if ($v >= 1000000000)
		{
			$res = array('v' => number_format($v / 1000000000, 1), 'scale' => 'bn', 'div' => 1000000000);
		}
		else
		{
			$res = array('v' => number_format($v, 0), 'scale' => '', 'div' => 1);
		}
		return $res;
	}
}
