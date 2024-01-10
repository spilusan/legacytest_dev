<?php
/**
 * Excel for SIR/Report
 * @author Elvir <eleonard@shipserv.com>
 */
abstract class Shipserv_Report_Excel extends Myshipserv_Excel
{
	const TMP_DIR = '/tmp/';
	const TMP_DIR_EXCEL = '/tmp/svr/excel/';
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
			'Banner impressions' => 'bfce75',
			'Banner clicks' => '758719',
			'Search Impressions' => '78c346',
			'Category searches' => '78c346',
			'Category clicks' => '007100',
			'Profile views' => 'fecb38',
			'Contact views' => 'fb830f',
			'Enquiries'=>'f14331'
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
			if( is_dir( self::TMP_DIR . 'svr' ) == false )
			{
				mkdir( self::TMP_DIR . 'svr', 0777 );
			}
			else 
			{
				//chmod( self::TMP_DIR, 0777 );
			}
			
			// check folder structure within google dfp for CSV
			if( is_dir( self::TMP_DIR . 'svr/excel' ) == false )
			{
				mkdir( self::TMP_DIR . 'svr/excel', 0777 );
			}
			else 
			{
				//chmod( self::TMP_DIR . 'svr/excel', 0777 );
			}			
		}
		
		// check if report has 2 periods
		$this->multiplePeriods = ( count( $this->report->data["supplier"] ) == 2 );
	
		// check if it's multiple periods
		if( $this->multiplePeriods )
		{
			// get all the available periods
			$this->periods = array_keys($this->report->data["supplier"]);
			
			// split the data from different periods into an array
			$this->data[] = $this->report->data["supplier"][ $this->periods[0] ];
			$this->data[] = $this->report->data["supplier"][ $this->periods[1] ];
			
			// split the data for global report
			$this->dataForGlobal[] = $this->report->data["general"][ $this->periods[0] ];
			$this->dataForGlobal[] = $this->report->data["general"][ $this->periods[1] ];
		}
		else
		{
			$this->data[] = $this->report->data["supplier"];
			$this->dataForGlobal[] = $this->report->data["general"];
			$this->periods = array( $this->report->data["supplier"]["period"]["start"] . " " . $this->report->data["supplier"]["period"]["end"] ); 
		}
		
		if( $this->data[1] !== null )
		{
			$this->mergeArray($this->data[0]['search-summary']['category-searches-local'], $this->data[1]['search-summary']['category-searches-local']);
			$this->mergeArray($this->data[0]['search-summary']['category-searches-global'], $this->data[1]['search-summary']['category-searches-global']);
			$this->mergeArray($this->data[0]['search-summary']['category-searches-global'], $this->data[0]['search-summary']['category-searches-local']);
			$this->mergeArray($this->data[1]['search-summary']['category-searches-global'], $this->data[1]['search-summary']['category-searches-local']);
	
			$this->mergeArray($this->data[0]['search-summary']['brand-searches-local'], $this->data[1]['search-summary']['brand-searches-local']);
			$this->mergeArray($this->data[0]['search-summary']['brand-searches-global'], $this->data[1]['search-summary']['brand-searches-global']);
			$this->mergeArray($this->data[0]['search-summary']['brand-searches-global'], $this->data[0]['search-summary']['brand-searches-local']);
			$this->mergeArray($this->data[1]['search-summary']['brand-searches-global'], $this->data[1]['search-summary']['brand-searches-local']);
			
			$this->mergeArray($this->data[0]['impression-summary']['impression-by-user-type'], $this->data[1]['impression-summary']['impression-by-user-type']);
			$this->mergeArray($this->data[0]['impression-summary']['impression-by-search-keywords'], $this->data[1]['impression-summary']['impression-by-search-keywords']);
			$this->mergeArray($this->data[0]['enquiry-summary']['enquiries-sent-by-user-type'], $this->data[1]['enquiry-summary']['enquiries-sent-by-user-type']);
			$this->mergeArray($this->data[0]['enquiry-summary']['enquiries-sent-by-search-keywords'], $this->data[1]['enquiry-summary']['enquiries-sent-by-search-keywords']);
		}

		//print_r($this->data);
		
		// set the meta data of the excel file
		$this->setMetaData();
		
	}
	
	/**
	 * When we have multiple periods, this function is used to get the difference between periods
	 * @param int $period1
	 * @param int $period2
	 * @return int %change
	 */
	protected function getPercentageChange( $period1, $period2, $debug = false )
	{
		$redStyle 	= array( "font" => array( "bold" => true, "color" => array( "argb" => "45A411" ) ) );
		$greenStyle = array( "font" => array( "bold" => true, "color" => array( "argb" => "FF00000" ) ) );
		$blackStyle = array( "font" => array( "bold" => true, "color" => array( "argb" => "0000000" ) ) );
		$debug=false;
		if($debug)
		{
			var_dump( $period1 );
			var_dump( $period2 );
			echo "<hr />";
		}
		

		if( is_array( $period1 ) || is_array( $period2 ) )
		{
			return array( "content" => '0%', "style" => $greenStyle);			
		} 
		
		
		if( $period1 > 0 && $period1 < 1)
		{
			$period1 = 1;
		}
		
		if( $period2 > 0 && $period2 < 1)
		{
			$period2 = 1;
		}
		
		if( $period1 > 0 && $period2 > 0 )
		{
			$change = ( $period1 - $period2 ) / $period2 * 100;
			$change = abs($change);	
		}
		else if( ($period1 > 0 && $period2 == 0) || $period2 > 0 && $period1 == 0 )
		{
			$change = 100;
		}
		
		if( $debug )
		{
			echo $period1;
			echo " **** ";
			echo $period2;
			echo " = ";
			echo $change;
			echo "<br /><br /><br />";
		}
		
		// apply colors according to the change
		if( $period2 < $period1 )
		{
			$style = $redStyle;
			$showNegative = false;
			if( $change < 0 )
			{
				$change = $change * -1;
			}
		}
		else 
		{
			$style = $greenStyle;
			$showNegative = true;
			if( floatval($change) == 0 )
			{
				$style = $blackStyle;
				$showNegative = true;
			}
		}
		
		$data = array( "content" => (($showNegative==true && round($change)!=0)?"-":"") . round($change) . '%', "style" => $style);
		return $data;
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
		$this->objPHPExcel->getProperties()->setTitle("Supplier insight report for " . trim( $company->name ) . " (TradeNet ID: " . $company->tnid . ")");
	}
	
	
	/**
	 * Function to create the chart/graph based.
	 * Information about $data and $dataDescription can be found on: http://pchart.sourceforge.net/documentation.php?topic=pChart 
	 * @param mixed $data
	 * @param mixed $dataDescription meta data explaining the structure of the data
	 * @param string $fileName PNG output
	 */
	public function createChart( $data, $dataDescription, $fileName = "", $title = "")
	{
		if( count( $data ) == 0 ) return $data;

		// group data by month
		$data = $this->normaliseData( $data );
		
		include_once("pChart/pData.class");   
		include_once("pChart/pChart.class");   
		  
		// Initialise the graph
		$Test = new pChart(1600,900);
		$Test->setFontProperties("/var/www/libraries_b_svr/pChart/Fonts/tahoma.ttf",10);
		$Test->setGraphArea(140,30,1500,800);
		$Test->drawGraphArea(252,252,252,TRUE);
		$Test->drawScale($data, $dataDescription,SCALE_NORMAL,150,150,150,TRUE,0,2);
		$Test->drawGrid(4,TRUE,230,230,230,70);
		
		// Draw the line graph
		$Test->drawLineGraph($data, $dataDescription);
		$Test->drawPlotGraph($data, $dataDescription,1,1,255,255,255);
		
		// Finish the graph
		$Test->setFontProperties("/var/www/libraries_b_svr/pChart/Fonts/tahoma.ttf",8);
		$Test->drawLegend(145,35,$dataDescription,255,255,255);
		$Test->setFontProperties("/var/www/libraries_b_svr/pChart/Fonts/tahoma.ttf",10);
		
		if( $title != "" )
		$Test->drawTitle(160,22,$title,50,50,50,585);
		
		// save the chart
		$Test->Render( $fileName);

		return $fileName;
	}
	
	protected function mergeArray(&$array1, &$array2)
	{
	
		if( $array1 === null )
		{
			$array1 = array();
		}
	
		if( $array2 === null )
		{
			$array2 = array();
		}
	
		$newData = array();
	
		foreach($array1 as $row)
		{
			$newData[$row['name']] = $row;
		}
		 
		foreach($array2 as $row)
		{
			if( $newData[$row['name']] == null )
			{
				$newData[$row['name']] = array('id' => $row['id'], 'name' => $row['name'], 'search' => 0, 'click' => 0);
			}
		}
		 
		ksort($newData);
		$array1 = $newData;
		 
		$d = array();
		foreach($array1 as $x)
		{
			$d[] = $x;
		}
		$array1 = $d;
		 
		 
		$newData = array();
		 
		foreach($array2 as $row)
		{
			$newData[$row['name']] = $row;
		}
	
		foreach($array1 as $row)
		{
			if( $newData[$row['name']] == null )
			{
				$newData[$row['name']] = array('id' => $row['id'], 'name' => $row['name'], 'search' => 0, 'click' => 0);
			}
		}
		ksort($newData);
		$array2 = $newData;
		$d = array();
		foreach($array2 as $x)
		{
			$d[] = $x;
		}
		$array2 = $d;
	
	}
}