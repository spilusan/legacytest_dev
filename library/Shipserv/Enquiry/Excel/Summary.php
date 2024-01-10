<?php
/**
 * Handle the creation of summary worksheet
 * for the documentation please refer to TargetedSearch.php class located on the same folder
 * @author Elvir <eleonard@shipserv.com>
 */

class Shipserv_Enquiry_Excel_Summary extends Shipserv_Enquiry_Excel
{
	protected $style = array();
	
	function __construct($objWriter, $objPHPExcel, $report)
	{
		parent::__construct($objPHPExcel, $objWriter, $report);

		// define excel style
		$this->style["top-level-profile-view"] 		= array ( "fill" => array("type" => PHPExcel_Style_Fill::FILL_SOLID, "color" => array( "argb" => $this->colors["worksheets"]["Pages enquiry"])), "font" => array( "bold" => true, "size" => 14 ), "alignment" => array( "horizontal" => PHPExcel_Style_Alignment::HORIZONTAL_CENTER) );
		$this->style["top-level-contact-view"] 		= array ( "fill" => array("type" => PHPExcel_Style_Fill::FILL_SOLID, "color" => array( "argb" => $this->colors["worksheets"]["Contact views"])), "font" => array( "bold" => true, "size" => 14 ), "alignment" => array( "horizontal" => PHPExcel_Style_Alignment::HORIZONTAL_CENTER) );
		$this->style["top-level-enquiries"] 		= array ( "fill" => array("type" => PHPExcel_Style_Fill::FILL_SOLID, "color" => array( "argb" => $this->colors["worksheets"]["Enquiries"])), "font" => array( "bold" => true, "size" => 14 ), "alignment" => array( "horizontal" => PHPExcel_Style_Alignment::HORIZONTAL_CENTER) );
		$this->style["top-level-banners"] 			= array ( "fill" => array("type" => PHPExcel_Style_Fill::FILL_SOLID, "color" => array( "argb" => $this->colors["worksheets"]["Banner impressions"])), "font" => array( "bold" => true, "size" => 14 ), "alignment" => array( "horizontal" => PHPExcel_Style_Alignment::HORIZONTAL_CENTER) );
		$this->style["top-level"] 		= array ( "fill" => array("type" => PHPExcel_Style_Fill::FILL_SOLID, "color" => array( "argb" => "53B8EC")), "font" => array( "bold" => true, "size" => 14 ), "alignment" => array( "horizontal" => PHPExcel_Style_Alignment::HORIZONTAL_CENTER) );
		
		$this->style["top-level2"] 		= array ( "fill" => array("type" => PHPExcel_Style_Fill::FILL_SOLID, "color" => array( "argb" => "90D6F7")), "font" => array( "bold" => true, "size" => 14 ), "alignment" => array( "horizontal" => PHPExcel_Style_Alignment::HORIZONTAL_CENTER) );
		$this->style["periods"] 		= array ( "fill" => array("type" => PHPExcel_Style_Fill::FILL_SOLID, "color" => array( "argb" => "D6D6DE")), "font" => array( "bold" => true), "alignment" => array( "horizontal" => PHPExcel_Style_Alignment::HORIZONTAL_LEFT) );
		$this->style["periods2"] 		= array ( "fill" => array("type" => PHPExcel_Style_Fill::FILL_SOLID, "color" => array( "argb" => "E6EAEB")), "font" => array( "bold" => true), "alignment" => array( "horizontal" => PHPExcel_Style_Alignment::HORIZONTAL_LEFT) );
		$this->style["periods-change"] 	= array ( "fill" => array("type" => PHPExcel_Style_Fill::FILL_SOLID, "color" => array( "argb" => "C2E8F9")), "font" => array( "bold" => true), "alignment" => array( "horizontal" => PHPExcel_Style_Alignment::HORIZONTAL_LEFT) );
		$this->style["bold"] 			= array ( "font" => array("bold" => true)  );	
		$this->style["third-level"] 	= array ( "fill" => array("type" => PHPExcel_Style_Fill::FILL_SOLID, "color" => array( "argb" => "F2F4F5")), "font" => array( "bold" => true), "alignment" => array( "horizontal" => PHPExcel_Style_Alignment::HORIZONTAL_RIGHT) );
		$this->style["center"] 			= array ( "alignment" => array( "horizontal" => PHPExcel_Style_Alignment::HORIZONTAL_CENTER) );
				
	}
	
	public function create()
	{
		// generate tabId		
		parent::$tabId = 0;
		
		// creating sheet
		$objWorksheet = $this->objPHPExcel->createSheet();
	
		$this->objPHPExcel->setActiveSheetIndex( parent::$tabId );
		$objWorksheet = $this->objPHPExcel->getActiveSheet();
		$objWorksheet->getTabColor()->setRGB( $this->colors["worksheets"]["Summary"] );
		$objWorksheet->setTitle("Summary");
	
		$this->createSupplierInformation();
		
		// merge cell
		$this->objPHPExcel->getActiveSheet()->mergeCells('A2:Z2');
		
		// create TOP header and style it		
		$this->objPHPExcel->getActiveSheet()->getStyle("A2")->applyFromArray(
			array( 
				"font" => array( "bold" => true, "size" => 22, "color" => array( "argb" => "FFFFFF" ) )
				, "alignment" => array( "horizontal" => PHPExcel_Style_Alignment::HORIZONTAL_LEFT)
				, "fill" => array( 
					"type" => PHPExcel_Style_Fill::FILL_SOLID
					, "color" => array( "argb" => $this->colors["worksheets"]["Summary"] )
				)
			)
		);
		$this->objPHPExcel->getActiveSheet()->setCellValue('A2', "Pages Enquiries report");
		
		// create drill down for category
		$startRow = $this->createSummary(7);		
	}
	
	
	private function createSummary( $startRow )
	{
		// initialise grid
		$xGrid = array();
		
		$style = $this->style;
		
		$row = $startRow;

		$this->write(
			array( 'B', $row ),
			array( array('B', $row), array($this->getNextColumn('B', ($this->multiplePeriods)?5:1), $row) ),
			$style["top-level-profile-view"],
			"Summary"
		);

		// header for period 1
		$this->write(
			array('B', $row+1),
			array( array('B', $row+1), array($this->getNextColumn('B', 1), $row+1) ),
			$this->style["periods"],
			$this->report->period['start']->format('d-m-Y') . " to " . $this->report->period['end']->format('d-m-Y') 
		);
				
		$this->write( array('B', $row+2), null, $this->style["third-level"], 'Action' );
		$this->write( array('C', $row+2), null, $this->style["third-level"], 'Total' );
		
		$this->write( array('B', $row+3), null, null, 'Replied');
		$this->write( array('C', $row+3), null, null, $this->report->statistic['replied']);

		$this->write( array('B', $row+4), null, null, 'Details viewed');
		$this->write( array('C', $row+4), null, null, $this->report->statistic['read']);
		
		$this->write( array('B', $row+5), null, null, 'Not interested');
		$this->write( array('C', $row+5), null, null, $this->report->statistic['declined']);
		
		$this->write( array('B', $row+6), null, null, 'Not clicked');
		$this->write( array('C', $row+6), null, null, $this->report->statistic['notClicked']);
		
		$this->write( array('B', $row+7), null, null, 'Total received');
		$this->write( array('C', $row+7), null, null, $this->report->statistic['sent']);
		
		return 11;
	}
	
}