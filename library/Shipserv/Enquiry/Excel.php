<?php
/**
 * Excel for Enquiry
 * @author Elvir <eleonard@shipserv.com>
 */
abstract class Shipserv_Enquiry_Excel extends Myshipserv_Excel
{
	const TMP_DIR = '/tmp/';
	const TMP_DIR_EXCEL = '/tmp/enquiry/excel/';
	const NO_DATA = 'No data is available to display.';
	
	protected $report;
	protected $period = array();
	
	// data holder for the report itself
	protected $data;
	protected $dataForGlobal;
	
	// data holder for chart
	protected $dataForChart;
	protected $dataDescriptionForChart;
	
	// variable to hold the writer
	protected static $objPHPExcel;
	protected static $objWriter;
	protected static $objReader;
	
	protected $colors;
	
	// keep track of tabId for worksheet creation
	protected static $tabId = 0;

	// store filename of the charts from different worksheets
	protected static $charts = array();
	
	/**
	 * Initialise writer and create an empty phpExcel object and initialising all necessary 
	 * style library required
	 * @throws Myshipserv_Exception_MessagedException
	 */
	public function __construct($objPHPExcel, $objWriter, $report)
	{
		// Create new PHPExcel object
		$this->objPHPExcel = $objPHPExcel;

		// create writer
		$this->objWriter = $objWriter;
		
		// store report
		$this->report = $report;
		
		// init colours
		$this->colors["period1"] = "";
		$this->colors["period2"] = "";
		$this->colors["periodChange"] = "";
		$this->colors["worksheets"] = array(
			'Summary' => '5BBEEB',
			'Pages enquiry' => 'bfce75'
		);

		// check temporary table structures
		if( is_dir( self::TMP_DIR ) == false )
		{
			throw new Myshipserv_Exception_MessagedException("System error. Temporary folder doesn't exist.", 500);
		}
		
		// make sure that all folders are available if not, create them.
		else
		{
			// check folder for google dfp
			if( is_dir( self::TMP_DIR . 'enquiry' ) == false )
			{
				mkdir( self::TMP_DIR . 'enquiry', 0777 );
			}
			else 
			{
				//chmod( self::TMP_DIR, 0777 );
			}
			
			// check folder structure within google dfp for CSV
			if( is_dir( self::TMP_DIR . 'enquiry/excel' ) == false )
			{
				mkdir( self::TMP_DIR . 'enquiry/excel', 0777 );
			}
			else 
			{
				//chmod( self::TMP_DIR . 'svr/excel', 0777 );
			}			
		}
		
		// set the meta data of the excel file
		$this->setMetaData();
		
	}
		
	/**
	 * Setting meta tag for the excel file. This function is called on initialisation of this class.
	 * @return void
	 */
	protected function setMetaData()
	{
		// get the company for this report
		$company = $this->report->getCompany();
		
		$this->objPHPExcel->getProperties()->setCreator("Shipserv - Pages");
		$this->objPHPExcel->getProperties()->setLastModifiedBy("Shipserv - Pages");
		$this->objPHPExcel->getProperties()->setCompany("Shipserv");
		$this->objPHPExcel->getProperties()->setTitle("ShipServ Pages RFQs for " . trim( $company->name ) . " (TradeNet ID: " . $company->tnid . ")");
	}
	
}