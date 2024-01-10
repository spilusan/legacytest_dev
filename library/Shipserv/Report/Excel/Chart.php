<?php
class Shipserv_Report_Excel_Chart extends Shipserv_Report_Excel
{
	function __construct($objWriter, $objPHPExcel, $report)
	{
		parent::__construct($objPHPExcel, $objWriter, $report);
	
	}
	
	public function create()
	{
		// set summary worksheet as active tab
		$this->objPHPExcel->setActiveSheetIndex(0);
		$no = 0;
		foreach( parent::$charts as $path )
		{
			$objDrawing = new PHPExcel_Worksheet_Drawing();
			$objDrawing->setName('chart');
			$objDrawing->setPath($path);
			$objDrawing->setCoordinates('E' . ( ($no * 50) + 6) );
			$objDrawing->setWorksheet($this->objPHPExcel->getActiveSheet());

			$no++;
		}
	}
}