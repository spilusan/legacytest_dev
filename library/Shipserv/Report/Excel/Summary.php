<?php
/**
 * Handle the creation of summary worksheet
 * for the documentation please refer to TargetedSearch.php class located on the same folder
 * @author Elvir <eleonard@shipserv.com>
 */

class Shipserv_Report_Excel_Summary extends Shipserv_Report_Excel
{
	protected $style = array();
	
	function __construct($objWriter, $objPHPExcel, $report)
	{
		parent::__construct($objPHPExcel, $objWriter, $report);

		// define excel style
		$this->style["top-level-profile-view"] 		= array ( "fill" => array("type" => PHPExcel_Style_Fill::FILL_SOLID, "color" => array( "argb" => $this->colors["worksheets"]["Profile views"])), "font" => array( "bold" => true, "size" => 14 ), "alignment" => array( "horizontal" => PHPExcel_Style_Alignment::HORIZONTAL_CENTER) );
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
		if( parent::$tabId > 0 ){
			parent::$tabId++;
			
			// creating sheet
			$objWorksheet = $this->objPHPExcel->createSheet();
		}
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
		$this->objPHPExcel->getActiveSheet()->setCellValue('A2', "Supplier insight report summary");
		
		// create drill down for category
		$startRow = $this->createImpressionSummaryForProfilePage(7);		
		$startRow = $this->createImpressionSummaryForContactPage($startRow + 3);		
		$startRow = $this->createImpressionSummaryForEnquiry($startRow + 3);		
		$startRow = $this->createImpressionSummaryForBanner($startRow + 7);		
	}
	
	
	private function createImpressionSummaryForProfilePage( $startRow )
	{
		// initialise grid
		$xGrid = array();
		
		$style = $this->style;
		
		$row = $startRow;

		$this->write(
			array( 'B', $row ),
			array( array('B', $row), array($this->getNextColumn('B', ($this->multiplePeriods)?5:1), $row) ),
			$style["top-level-profile-view"],
			"Profile views"
		);
		
		// header for top brands
		//$this->write( array('A', $row+2), null, $style["bold"], "Total & Unique" );
		
		// header for period 1
		$this->write(
			array('B', $row+1),
			array( array('B', $row+1), array($this->getNextColumn('B', 1), $row+1) ),
			$this->style["periods"],
			$this->convertDate( $this->periods[0], 'd-m-Y')
		);
		
		$this->write( array('B', $row+2), null, $this->style["third-level"], 'You' );
		$this->write( array('C', $row+2), null, $this->style["third-level"], 'Market' );
		$this->write( array('A', $row+3), null, $style["bold"], "Total number" );
		$this->write( array('A', $row+4), null, $style["bold"], "Unique users" );

		// total
		$this->write( array('B', $row+3), null, null, $this->roundZeroUp($this->data[0]["impression-summary"]["impression"]["count"]) );
		$this->write( array('C', $row+3), null, null, $this->roundZeroUp($this->dataForGlobal[0]["impression"]["count"]) );

		// unique
		$this->write( array('B', $row+4), null, null, $this->roundZeroUp($this->data[0]["impression-summary"]["impression-by-unique-user"]["count"]) );
		$this->write( array('C', $row+4), null, null, $this->roundZeroUp($this->dataForGlobal[0]["impression-by-unique-user"]["count"]) );
		
		if( $this->multiplePeriods == true )
		{
			// header for period 2
			$this->write(
				array('D', $row+1),
				array( array('D', $row+1), array('E', $row+1) ),
				$this->style["periods2"],
				$this->convertDate( $this->periods[1], 'd-m-Y')
			);
				
			$this->write( array('D', $row+2), null, $this->style["third-level"], 'You' );
			$this->write( array('E', $row+2), null, $this->style["third-level"], 'Market' );

			// total
			$this->write( array('D', $row+3), null, null, $this->roundZeroUp($this->data[1]["impression-summary"]["impression"]["count"]) );
			$this->write( array('E', $row+3), null, null, $this->roundZeroUp($this->dataForGlobal[1]["impression"]["count"]) );

			// unique
			$this->write( array('D', $row+4), null, null, $this->roundZeroUp($this->data[1]["impression-summary"]["impression-by-unique-user"]["count"]) );
			$this->write( array('E', $row+4), null, null, $this->roundZeroUp($this->dataForGlobal[1]["impression-by-unique-user"]["count"]) );
			
			// header for period change
			$this->write(
				array('F', $row+1),
				array( array('F', $row+1), array('G', $row+1) ),
				$this->style["periods-change"],
				"% Period change"
			);
			
			$this->write( array('F', $row+2), null, $this->style["third-level"], 'You' );
			$this->write( array('G', $row+2), null, $this->style["third-level"], 'Market' );
			
			// total
			$this->write( array('F', $row+3), null, null, $this->getPercentageChange($this->data[0]["impression-summary"]["impression"]["count"],$this->data[1]["impression-summary"]["impression"]["count"]) );
			$this->write( array('G', $row+3), null, null, $this->getPercentageChange($this->dataForGlobal[0]["impression"]["count"],$this->dataForGlobal[1]["impression"]["count"]) );
			
			// unique
			$this->write( array('F', $row+4), null, null, $this->getPercentageChange($this->data[0]["impression-summary"]["impression-by-unique-user"]["count"],$this->data[1]["impression-summary"]["impression-by-unique-user"]["count"]) );
			$this->write( array('G', $row+4), null, null, $this->getPercentageChange($this->dataForGlobal[0]["impression-by-unique-user"]["count"],$this->dataForGlobal[1]["impression-by-unique-user"]["count"]) );

		}
		
		return 11;
	}
	
	
	private function createImpressionSummaryForContactPage( $startRow )
	{
		// initialise grid
		$xGrid = array();
		
		$style = $this->style;
		
		$row = $startRow;

		$this->write(
			array( 'B', $row ),
			array( array('B', $row), array($this->getNextColumn('B', ($this->multiplePeriods)?5:1), $row) ),
			$style["top-level-contact-view"],
			"Contact views"
		);
		
		// header for top brands
		//$this->write( array('A', $row+2), null, $style["bold"], "Total & Unique" );
		
		// header for period 1
		$this->write(
			array('B', $row+1),
			array( array('B', $row+1), array($this->getNextColumn('B', 1), $row+1) ),
			$this->style["periods"],
			$this->convertDate( $this->periods[0], 'd-m-Y')
		);
		
		$this->write( array('B', $row+2), null, $this->style["third-level"], 'You' );
		$this->write( array('C', $row+2), null, $this->style["third-level"], 'Market' );
		$this->write( array('A', $row+3), null, $style["bold"], "Total number" );
		$this->write( array('A', $row+4), null, $style["bold"], "Unique users" );
		
		// total
		$this->write( array('B', $row+3), null, null, $this->roundZeroUp($this->data[0]["impression-summary"]["contact-view"]["count"]) );
		$this->write( array('C', $row+3), null, null, $this->roundZeroUp($this->dataForGlobal[0]["contact-view"]["count"]) );

		// unique
		$this->write( array('B', $row+4), null, null, $this->roundZeroUp($this->data[0]["impression-summary"]["contact-view-by-unique-user"]["count"]) );
		$this->write( array('C', $row+4), null, null, $this->roundZeroUp($this->dataForGlobal[0]["contact-view-by-unique-user"]["count"]) );
		
		if( $this->multiplePeriods == true )
		{
			// header for period 2
			$this->write(
				array('D', $row+1),
				array( array('D', $row+1), array('E', $row+1) ),
				$this->style["periods2"],
				$this->convertDate( $this->periods[1], 'd-m-Y')
			);
				
			$this->write( array('D', $row+2), null, $this->style["third-level"], 'You' );
			$this->write( array('E', $row+2), null, $this->style["third-level"], 'Market' );

			// total
			$this->write( array('D', $row+3), null, null, $this->roundZeroUp($this->data[1]["impression-summary"]["contact-view"]["count"]) );
			$this->write( array('E', $row+3), null, null, $this->roundZeroUp($this->dataForGlobal[1]["contact-view"]["count"]) );

			// unique
			$this->write( array('D', $row+4), null, null, $this->roundZeroUp($this->data[1]["impression-summary"]["contact-view-by-unique-user"]["count"]) );
			$this->write( array('E', $row+4), null, null, $this->roundZeroUp($this->dataForGlobal[1]["contact-view-by-unique-user"]["count"]) );
			
			// header for period change
			$this->write(
				array('F', $row+1),
				array( array('F', $row+1), array('G', $row+1) ),
				$this->style["periods-change"],
				"% Period change"
			);
			
			$this->write( array('F', $row+2), null, $this->style["third-level"], 'You' );
			$this->write( array('G', $row+2), null, $this->style["third-level"], 'Market' );
			
			// total
			$this->write( array('F', $row+3), null, null, $this->getPercentageChange($this->data[0]["impression-summary"]["contact-view"]["count"],$this->data[1]["impression-summary"]["contact-view"]["count"]) );
			$this->write( array('G', $row+3), null, null, $this->getPercentageChange($this->dataForGlobal[0]["contact-view"]["count"],$this->dataForGlobal[1]["contact-view"]["count"]) );
			
			// unique
			$this->write( array('F', $row+4), null, null, $this->getPercentageChange($this->data[0]["impression-summary"]["contact-view-by-unique-user"]["count"],$this->data[1]["impression-summary"]["contact-view-by-unique-user"]["count"]) );
			$this->write( array('G', $row+4), null, null, $this->getPercentageChange($this->dataForGlobal[0]["contact-view-by-unique-user"]["count"],$this->dataForGlobal[1]["contact-view-by-unique-user"]["count"]) );

		}
		
		return 18;
	}
	
	private function createImpressionSummaryForEnquiry( $startRow )
	{
		// initialise grid
		$xGrid = array();
		
		$style = $this->style;
		
		$row = $startRow;

		$this->write(
			array( 'B', $row ),
			array( array('B', $row), array($this->getNextColumn('B', ($this->multiplePeriods)?5:1), $row) ),
			$style["top-level-enquiries"],
			"Pages RFQs"
		);
		
		// header for top brands
		//$this->write( array('A', $row+2), null, $style["bold"], "Total & Unique" );
		
		// header for period 1
		$this->write(
			array('B', $row+1),
			array( array('B', $row+1), array($this->getNextColumn('B', 1), $row+1) ),
			$this->style["periods"],
			$this->convertDate( $this->periods[0], 'd-m-Y')
		);
		
		$this->write( array('B', $row+2), null, $this->style["third-level"], 'You' );
		$this->write( array('C', $row+2), null, $this->style["third-level"], 'Market' );
		$this->write( array('A', $row+3), null, $style["bold"], "Total number" );
		$this->write( array('A', $row+4), null, $style["bold"], "Unique users" );
		
		// total
		$this->write( array('B', $row+3), null, null, $this->roundZeroUp($this->data[0]["enquiry-summary"]["enquiry-sent"]["count"]) );
		$this->write( array('C', $row+3), null, null, $this->roundZeroUp($this->dataForGlobal[0]["enquiry-sent"]["count"]) );

		// unique
		$this->write( array('B', $row+4), null, null, $this->roundZeroUp($this->data[0]["enquiry-summary"]["enquiries-sent-by-unique-user"]["count"]) );
		$this->write( array('C', $row+4), null, null, $this->roundZeroUp($this->dataForGlobal[0]["enquiry-sent-by-unique-user"]["count"]) );
		
		if( $this->multiplePeriods == true )
		{
			// header for period 2
			$this->write(
				array('D', $row+1),
				array( array('D', $row+1), array('E', $row+1) ),
				$this->style["periods2"],
				$this->convertDate( $this->periods[1], 'd-m-Y')
			);
				
			$this->write( array('D', $row+2), null, $this->style["third-level"], 'You' );
			$this->write( array('E', $row+2), null, $this->style["third-level"], 'Market' );

			// total
			$this->write( array('D', $row+3), null, null, $this->roundZeroUp($this->data[1]["enquiry-summary"]["enquiry-sent"]["count"]) );
			$this->write( array('E', $row+3), null, null, $this->roundZeroUp($this->dataForGlobal[1]["enquiry-sent"]["count"]) );

			// unique
			$this->write( array('D', $row+4), null, null, $this->roundZeroUp($this->data[1]["enquiry-summary"]["enquiries-sent-by-unique-user"]["count"]) );
			$this->write( array('E', $row+4), null, null, $this->roundZeroUp($this->dataForGlobal[1]["enquiry-sent-by-unique-user"]["count"]) );
			
			// header for period change
			$this->write(
				array('F', $row+1),
				array( array('F', $row+1), array('G', $row+1) ),
				$this->style["periods-change"],
				"% Period change"
			);
			
			$this->write( array('F', $row+2), null, $this->style["third-level"], 'You' );
			$this->write( array('G', $row+2), null, $this->style["third-level"], 'Market' );
			
			// total
			$this->write( array('F', $row+3), null, null, $this->getPercentageChange($this->data[0]["enquiry-summary"]["enquiry-sent"]["count"],$this->data[1]["enquiry-summary"]["enquiry-sent"]["count"]) );
			$this->write( array('G', $row+3), null, null, $this->getPercentageChange($this->dataForGlobal[0]["enquiry-sent"]["count"],$this->dataForGlobal[1]["enquiry-sent"]["count"]) );
			
			// unique
			$this->write( array('F', $row+4), null, null, $this->getPercentageChange($this->data[0]["enquiry-summary"]["enquiries-sent-by-unique-user"]["count"],$this->data[1]["enquiry-summary"]["enquiries-sent-by-unique-user"]["count"]) );
			$this->write( array('G', $row+4), null, null, $this->getPercentageChange($this->dataForGlobal[0]["enquiry-sent-by-unique-user"]["count"],$this->dataForGlobal[1]["enquiry-sent-by-unique-user"]["count"]) );

		}
		
		return $startRow;
	}
	
	private function createImpressionSummaryForBanner( $startRow )
	{
		$style = $this->style;
		
		$row = $startRow;

		$this->write(
			array( 'B', $row ),
			array( array('B', $row), array($this->getNextColumn('B', ($this->multiplePeriods)?5:1), $row) ),
			$style["top-level-banners"],
			"Banners"
		);
		
		// header for period 1
		$this->write(
			array('B', $row+1),
			array( array('B', $row+1), array($this->getNextColumn('B', 1), $row+1) ),
			$this->style["periods"],
			$this->convertDate( $this->periods[0], 'd-m-Y')
		);
		
		$this->write( array('B', $row+2), array( array('B', $row+2), array('C', $row+2) ), $this->style["third-level"], 'Impressions' );
		//DISABLING-BANNER-CLICKS//$this->write( array('C', $row+2), null, $this->style["third-level"], 'Clicks' );
		
		// total
		$this->write( array('B', $row+3), array( array('B', $row+3), array('C', $row+3) ), null, $this->roundZeroUp($this->data[0]["banner-summary"]["impression"]["count"]) );
		//DISABLING-BANNER-CLICKS//$this->write( array('C', $row+3), null, null, $this->data[0]["banner-summary"]["click"]["count"] );

		if( $this->multiplePeriods == true )
		{
			// header for period 2
			$this->write(
				array('D', $row+1),
				array( array('D', $row+1), array('E', $row+1) ),
				$this->style["periods2"],
				$this->convertDate( $this->periods[1], 'd-m-Y')
			);
				
			$this->write( array('D', $row+2), array( array('D', $row+2), array('E', $row+2) ), $this->style["third-level"], 'Impressions' );
			//DISABLING-BANNER-CLICKS//$this->write( array('E', $row+2), null, $this->style["third-level"], 'Clicks' );

			// total
			$this->write( array('D', $row+3), array( array('D', $row+3), array('E', $row+3) ), null, $this->roundZeroUp($this->data[1]["banner-summary"]["impression"]["count"]) );
			//DISABLING-BANNER-CLICKS//$this->write( array('E', $row+3), null, null, $this->data[1]["banner-summary"]["click"]["count"] );
			
			// header for period change
			$this->write(
				array('F', $row+1),
				array( array('F', $row+1), array('G', $row+1) ),
				$this->style["periods-change"],
				"% Period change"
			);
			
			$this->write( array('F', $row+2), array( array('F', $row+2), array('G', $row+2) ), $this->style["third-level"], 'Impressions' );
			//DISABLING-BANNER-CLICKS//$this->write( array('G', $row+2), null, $this->style["third-level"], 'Clicks' );
			
			// total
			$this->write( array('F', $row+3), array( array('F', $row+3), array('G', $row+3) ), null, $this->getPercentageChange($this->data[0]["banner-summary"]["impression"]["count"], $this->data[1]["banner-summary"]["impression"]["count"]) );
			//DISABLING-BANNER-CLICKS//$this->write( array('G', $row+3), null, null, $this->getPercentageChange($this->data[0]["banner-summary"]["click"]["count"], $this->data[1]["banner-summary"]["click"]["count"]) );

		}
		
		return $startRow;
	}	
	
	
	private function roundZeroUp($data)
	{
		if( $data > 0 && $data < 1 ) return 1;
		else return $data;
	}
	
}