<?php
/**
 * Handle the creation of summary worksheet
 * for the documentation please refer to TargetedSearch.php class located on the same folder
 * @author Elvir <eleonard@shipserv.com>
 */

class Shipserv_Enquiry_Excel_Detail extends Shipserv_Enquiry_Excel
{
	protected $style = array();
	
	function __construct($objWriter, $objPHPExcel, $report)
	{
		parent::__construct($objPHPExcel, $objWriter, $report);

		// define excel style
		$this->style["header"]	 		= array ( "fill" => array("type" => PHPExcel_Style_Fill::FILL_SOLID, "color" => array( "argb" => $this->colors["worksheets"]["Pages enquiry"])), "font" => array( "bold" => true, "size" => 14 ), "alignment" => array( "horizontal" => PHPExcel_Style_Alignment::HORIZONTAL_CENTER) );
		
		$this->style["bold"] 			= array ( "font" => array("bold" => true)  );	
		$this->style["center"] 			= array ( "alignment" => array( "horizontal" => PHPExcel_Style_Alignment::HORIZONTAL_CENTER) );
				
	}
	
	public function create()
	{
		// generate tabId		
		parent::$tabId = 1;
			
		// creating sheet
		$objWorksheet = $this->objPHPExcel->createSheet();
		$this->objPHPExcel->setActiveSheetIndex( parent::$tabId );
		$objWorksheet = $this->objPHPExcel->getActiveSheet();
		$objWorksheet->getTabColor()->setRGB( $this->colors["worksheets"]["Pages enquiry"] );
		$objWorksheet->setTitle("Pages RFQs");
	
		// create drill down for category
		$startRow = $this->createDetail(7);		
	}
	
	private function createDetail( $startRow )
	{
		// initialise grid
		$xGrid = array();
		
		$style = $this->style;
		
		$row = 1;

		$this->write( array('A', $row), null, $style["header"], 'ID' );
		$this->write( array('B', $row), null, $style["header"], 'STATUS' );
		$this->write( array('C', $row), null, $style["header"], 'SUBJECT' );
		$this->write( array('D', $row), null, $style["header"], 'NAME' );
		$this->write( array('E', $row), null, $style["header"], 'COMPANY' );
		$this->write( array('F', $row), null, $style["header"], 'EMAIL' );
		$this->write( array('G', $row), null, $style["header"], 'PHONE' );
		$this->write( array('H', $row), null, $style["header"], 'RFQ_TEXT' );
		$this->write( array('I', $row), null, $style["header"], 'HAS ATTACHMENTS' );
		$this->write( array('J', $row), null, $style["header"], 'VESSEL NAME' );
		$this->write( array('K', $row), null, $style["header"], 'IMO Number' );
		$this->write( array('L', $row), null, $style["header"], 'DELIVERY LOCATION' );
		$this->write( array('M', $row), null, $style["header"], 'DELIVERY DATE' );
		$this->write( array('N', $row), null, $style["header"], 'COUNTRY' );
		$this->write( array('O', $row), null, $style["header"], 'SENT DATE' );
		$this->write( array('P', $row), null, $style["header"], 'CLICKED TO VIEW DETAILS' );
		$this->write( array('Q', $row), null, $style["header"], 'CLICKED NOT INTERESTED' );
				
		foreach ((Array) $this->report->enquiries as $enquiry) {	
			$row++;
			$this->write( array('A', $row), null, null, $enquiry->pirId );
			$this->write( array('B', $row), null, null, $enquiry->getStatus(true) );
			$this->write( array('C', $row), null, null, $enquiry->pinSubject );
			$this->write( array('D', $row), null, null, $enquiry->pinName );
			$this->write( array('E', $row), null, null, !empty($enquiry->pinCompany)		?	$enquiry->pinCompany:"-" );
			$this->write( array('F', $row), null, null, !empty($enquiry->pinEmail)			?	$enquiry->pinEmail:"-" );
			$this->write( array('G', $row), null, null, !empty($enquiry->pinPhone)			?	$enquiry->pinPhone:"-" );
			$this->write( array('H', $row), null, null, !empty($enquiry->pinInquiryText)	?	$enquiry->pinInquiryText:"-" );
			$this->write( array('I', $row), null, null, !empty($enquiry->pinHasAttachment)	?	$enquiry->pinHasAttachment:"-" );
			$this->write( array('J', $row), null, null, !empty($enquiry->pinVesselName)		?	$enquiry->pinVesselName:"-" );
			$this->write( array('K', $row), null, null, !empty($enquiry->pinImo)			?	$enquiry->pinImo:"-" );
			$this->write( array('L', $row), null, null, !empty($enquiry->pinDeliveryLocation)	?	$enquiry->pinDeliveryLocation:"-");
			$this->write( array('M', $row), null, null, !empty($enquiry->pinDeliveryDate)	?	$enquiry->pinDeliveryDate:"-" );
			$this->write( array('N', $row), null, null, !empty($enquiry->pinCountry)		?	$enquiry->pinCountry:"-" );
			$this->write( array('O', $row), null, null, !empty($enquiry->pirReleasedDate)	?	$enquiry->pirReleasedDate:"-" );
			$this->write( array('P', $row), null, null, !empty($enquiry->pirReadDate)		?	$enquiry->pirReadDate:"-" );
			$this->write( array('Q', $row), null, null, !empty($enquiry->pirDeclinedDate)	?	$enquiry->pirDeclinedDate:"-" );
				
		}
						
		return 11;
	}
	
}