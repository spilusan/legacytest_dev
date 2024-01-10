<?php

class Shipserv_Report_Excel_BannerStatistic extends Shipserv_Report_Excel
{
	protected $style = array();
	
	function __construct($objWriter, $objPHPExcel, $report)
	{
		parent::__construct($objPHPExcel, $objWriter, $report);

		// define excel style
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
		parent::$tabId++;

		// creating sheet
		$objWorksheet = $this->objPHPExcel->createSheet();
		$objWorksheet->getTabColor()->setRGB( $this->colors["worksheets"]["Banner impressions"] );
		$objWorksheet->setTitle("Banner impressions");
		$this->objPHPExcel->setActiveSheetIndex( parent::$tabId );

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
					, "color" => array( "argb" => $this->colors["worksheets"]["Banner impressions"] )
				)
			)
		);
		$this->objPHPExcel->getActiveSheet()->setCellValue('A2', "Banner impressions statistics");
		
		// create drill down for category
		$startRow = $this->createTableForBannerImpression(3);		
		//$startRow = $this->createTableForBannerClick($startRow+30);		
		//$this->createChart(4);		
		
	}
	
	public function createTableForBannerClick( $startRow )
	{

		// initialise grid
		$xGrid = array();
		
		$this->write( array('A', $startRow + 2), null, $this->style["bold"], 'Clicks' );
	
		$offset = 0;
		$periodNo = 0;
		
		// do multiple period on for this
		foreach( $this->data as $data )
		{
			$this->write( 
				array('B', $startRow +1), 
				array( array('B', $startRow+1), array($this->getNextColumn('B', 1), $startRow+1) ),
			 	$this->style["periods" . ( ( $periodNo == 0 ) ? "" : "2" )], $this->convertDate( $this->periods[$periodNo], 'd-m-Y') 
			 	, $offset
			 );
				
			$this->write( array('B', $startRow+2), null, $this->style["third-level"], 'Date', $offset);
			$this->write( array('C', $startRow+2), null, $this->style["third-level"], 'Total', $offset);
			
			if( count( $data["banner-summary"]["click"]["days"] ) > 0 )
			{
				// metadata for the structure of the chart
				$this->dataDescriptionForChart["Position"] = 'Name';
				$this->dataDescriptionForChart["Values"][] = $this->periods[$periodNo] . '-clicks';
				$this->dataDescriptionForChart['Description'][$this->periods[$periodNo] . '-clicks'] = $this->convertDate( $this->periods[$periodNo], 'd-m-Y' ) . ' period total clicks';
				
				// draw the daily data
				foreach( $data["banner-summary"]["click"]["days"] as $row )
				{
					$this->dataForChart['banner'][] = array('Name' =>  $row['date'], $this->periods[$periodNo] . '-clicks' => $row['count'] );
					
					$xGrid[$this->getNextColumn('B', $offset)][] = $row['date'];
					$xGrid[$this->getNextColumn('C', $offset)][] = $row['count'];
				}
			}
			$offset += 2;
			$periodNo++;
		}
		// draw the grid and get the last position of current
		$row = $this->drawXGrid( $xGrid, $this->getNextColumn('B', $offset), $startRow + 3 );
		
		// check if there's any data printed
		if( $row == $startRow + 3 )
		{
			$this->write( array('B', $startRow+3), null, null, self::NO_DATA);
		}

		return $row;
			
	}

	public function createTableForBannerImpression( $startRow )
	{

		// initialise grid
		$xGrid = array();
		
		$this->write( array('A', $startRow + 2), null, $this->style["bold"], 'Impressions' );
	
		$offset = 0;
		$periodNo = 0;
		
		// do multiple period on for this
		foreach( $this->data as $data )
		{
			$this->write( 
				array('B', $startRow +1), 
				array( array('B', $startRow+1), array($this->getNextColumn('B', 1), $startRow+1) ),
			 	$this->style["periods" . ( ( $periodNo == 0 ) ? "" : "2" )], $this->convertDate( $this->periods[$periodNo], 'd-m-Y') 
			 	, $offset
			 );
				
			$this->write( array('B', $startRow+2), null, $this->style["third-level"], 'Date', $offset);
			$this->write( array('C', $startRow+2), null, $this->style["third-level"], 'Total', $offset);
			
			if( count( $data["banner-summary"]["impression"]["days"] ) > 0 )
			{
				// metadata for the structure of the chart
				$this->dataDescriptionForChart["Position"] = 'Name';
				$this->dataDescriptionForChart["Values"][] = $this->periods[$periodNo];
				$this->dataDescriptionForChart['Description'][$this->periods[$periodNo]] = $this->convertDate( $this->periods[$periodNo], 'd-m-Y' ) . ' period total impression';
				
				// draw the daily data
				foreach( $data["banner-summary"]["impression"]["days"] as $row )
				{
					$this->dataForChart['banner'][] = array('Name' =>  $row['date'], $this->periods[$periodNo] => $row['count'] );
					
					$xGrid[$this->getNextColumn('B', $offset)][] = $row['date'];
					$xGrid[$this->getNextColumn('C', $offset)][] = $row['count'];
				}
			}
			$offset += 2;
			$periodNo++;
		}
		// draw the grid and get the last position of current
		$row = $this->drawXGrid( $xGrid, $this->getNextColumn('B', $offset), $startRow + 3 );
		
		// check if there's any data printed
		if( $row == $startRow + 3 )
		{
			$this->write( array('B', $startRow+3), null, null, self::NO_DATA);
		}

		return $row;
			
	}
	
	/**
	 * Based on the data structure that was set on the previous function
	 * This function will create chart based on that and will store the final png of the chart to $chart variable
	 */
	public function createChart( $startRow )
	{
		if( $this->dataForChart['banner'] !== null && count($this->dataForChart['banner']) > 0 )
		{
			$path = Shipserv_Report_Excel::createChart($this->dataForChart['banner'], $this->dataDescriptionForChart, parent::TMP_DIR_EXCEL . 'banner_' . $this->report->getCompany()->tnid . '.png', '');
	    	
	    	$objDrawing = new PHPExcel_Worksheet_Drawing();
			$objDrawing->setName('chart');
			$objDrawing->setPath($path);
			$objDrawing->setCoordinates('F' . $startRow );
			$objDrawing->setWorksheet($this->objPHPExcel->getActiveSheet());
		}
	}
	

}