<?php

/**
 * Serves XML to charts implemented with the Maani library.
 */
class StatsController extends Myshipserv_Controller_Action
{
    public function init()
    {		
    	parent::init();
		$this->_helper->contextSwitch()
			->setActionContext('transbymonth', 'xml')
			->setActionContext('transbylevel', 'xml')
			->initContext();
    }
	
	/**
	 * Fetch XML for graph of transaction value by month.
	 */
	public function transbymonthAction ()
	{
		$this->view->chartData = $this->getChartData('mapDate');
	}
	
	/**
	 * Fetch XML for graph of transaction value by supplier level.
	 */
	public function transbylevelAction ()
	{
		$this->view->chartData = $this->getChartData('mapLevel');
	}
	
	/**
	 * Form data for XML view, applying specified mapping functions.
	 */
	private function getChartData ($labelMap = 'nullMap', $valMap = 'nullMap')
	{
		$chartData = array();
		
		$maxVal = 0;
		foreach (unserialize(base64_decode($this->_getParam('cd'))) as $row)
		{
			$chartData[] = array('label' => $this->$labelMap($row[0]), 'value' => $this->$valMap($row[1]));
			if ($row[1] > $maxVal) $maxVal = $row[1];
		}
		
		return $chartData;
	}
	
	/**
	 * Mapping function: placebo
	 */
	private function nullMap ($x)
	{
		return $x;
	}
	
	/**
	 * Mapping function: turn supplier level key into presentable phrase.
	 */
	private function mapLevel ($level)
	{
		static $map = array('BASIC' => 'Basic', 'PREMIUM' => Premium);
		return @$map[$level];
	}
	
	/**
	 * Mapping function: turn date key into presentable date.
	 */
	private function mapDate ($d)
	{
		return date("M 'y", strtotime($d));
	}
}
