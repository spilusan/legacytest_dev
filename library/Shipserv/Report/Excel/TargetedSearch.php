<?php
/**
 * Class to handle worksheet for targeted search
 * @author Elvir <eleonard@shipserv.com>
 */
class Shipserv_Report_Excel_TargetedSearch extends Shipserv_Report_Excel
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
	
	/**
	 * Parent/main function which gets called from ReportConverter
	 * @return boolean result of the process
	 */
	public function create()
	{
		// generate tabId
		parent::$tabId++;
		
		// creating sheet
		$objWorksheet = $this->objPHPExcel->createSheet();
		$objWorksheet->getTabColor()->setRGB( $this->colors["worksheets"]["Search Impressions"] );
		$objWorksheet->setTitle("Search Impressions");
		$this->objPHPExcel->setActiveSheetIndex( parent::$tabId );

		// write supplier information on the top of the worksheet
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
					, "color" => array( "argb" => $this->colors["worksheets"]["Search Impressions"] )
				)
			)
		);
		$this->objPHPExcel->getActiveSheet()->setCellValue('A2', "Search Impressions");
		
		// create drill down for category
		$startRow = $this->createLocalAndGlobalCategorySearches(7);		

		// create local and global brand search
		$startRow = $this->createLocalAndGlobalBrandSearches( $startRow + 5 );		
		
		$rowForChart = $startRow + 5;
		// create category and brand breakdown by date
		$startRow = $this->createBrandAndCategorySearchesGroupedByDate( $startRow + 5 );		
		
		// create chart related to all metrics produced by this worksheet
		//$this->createChart( $rowForChart );
		
	}
	
	/**
	 * Function to populate category searches metrics (both local and global)
	 * @param int $startRow tell the writer where it should start writing the section from
	 * @todo please replace local with the locality of the port of the supplier on this report
	 */
	private function createLocalAndGlobalCategorySearches( $startRow )
	{
		$xGrid = array();
		$style = $this->style;
		
		$row = $startRow;
		
		// Local STATISTIC - Local STATISTIC - Local STATISTIC - Local STATISTIC - Local STATISTIC
		// Local STATISTIC - Local STATISTIC - Local STATISTIC - Local STATISTIC - Local STATISTIC
		// Local STATISTIC - Local STATISTIC - Local STATISTIC - Local STATISTIC - Local STATISTIC
		$this->write(
			array( 'B', $row ),
			array( array('B', $row), array($this->getNextColumn('B', ($this->multiplePeriods)?5:1), $row) ),
			$style["top-level"],
			"Your Country"
		);
		
		// header for top brands
		$this->write( array('A', $row+2), null, $style["bold"], "Top categories" );
		
		// header for period 1
		$this->write(
			array('B', $row+1),
			array( array('B', $row+1), array($this->getNextColumn('B', 1), $row+1) ),
			$this->style["periods"],
			$this->convertDate( $this->periods[0], 'd-m-Y')
		);
		
		$this->write( array('B', $row+2), null, $this->style["third-level"], 'Searches' );
		$this->write( array('C', $row+2), null, $this->style["third-level"], 'Clicks' );
		
		// check if another period is available
		if( $this->multiplePeriods == true )
		{
			// header for period 2
			$this->write(
				array('D', $row+1),
				array( array('D', $row+1), array('E', $row+1) ),
				$this->style["periods2"],
				$this->convertDate( $this->periods[1], 'd-m-Y')
			);
				
			$this->write( array('D', $row+2), null, $this->style["third-level"], 'Searches' );
			$this->write( array('E', $row+2), null, $this->style["third-level"], 'Clicks' );
			
			// header for period change
			$this->write(
				array('F', $row+1),
				array( array('F', $row+1), array('G', $row+1) ),
				$this->style["periods-change"],
				"% Period change"
			);
			
			$this->write( array('F', $row+2), null, $this->style["third-level"], 'Searches' );
			$this->write( array('G', $row+2), null, $this->style["third-level"], 'Clicks' );
		}
		
		/*
		// WORLDWIDE STATISTIC - WORLDWIDE STATISTIC - WORLDWIDE STATISTIC - WORLDWIDE STATISTIC
		// WORLDWIDE STATISTIC - WORLDWIDE STATISTIC - WORLDWIDE STATISTIC - WORLDWIDE STATISTIC
		// WORLDWIDE STATISTIC - WORLDWIDE STATISTIC - WORLDWIDE STATISTIC - WORLDWIDE STATISTIC
		*/
		if( $this->multiplePeriods == false )
		{
			$columnOffset = -4;
		}
	
		$this->write(
			array( 'H', $row ),
			array( array('H', $row), array($this->getNextColumn('H', ($this->multiplePeriods)?5:1), $row) ),
			$style["top-level2"],
			"Global"
			, $columnOffset
		);
		
		// header for top brands
		$this->write( array('A', $row+2), null, $style["bold"], "Top categories" );
		
		// header for period 1
		$this->write(
			array('H', $row+1),
			array( array('H', $row+1), array($this->getNextColumn('H', 1), $row+1) ),
			$this->style["periods"],
			$this->convertDate( $this->periods[0], 'd-m-Y')
			, $columnOffset
		);
		
		$this->write( array('H', $row+2), null, $this->style["third-level"], 'Searches', $columnOffset );
		$this->write( array('I', $row+2), null, $this->style["third-level"], 'Clicks', $columnOffset );
		
		if( $this->multiplePeriods == true )
		{
			// header for period 2
			$this->write(
				array('J', $row+1),
				array( array('J', $row+1), array($this->getNextColumn('J', 1), $row+1) ),
				$this->style["periods2"],
				$this->convertDate( $this->periods[1], 'd-m-Y')
				, $columnOffset
			);
				
			$this->write( array('J', $row+2), null, $this->style["third-level"], 'Searches', $columnOffset );
			$this->write( array('K', $row+2), null, $this->style["third-level"], 'Clicks', $columnOffset );
			
			// header for period change
			$this->write(
				array('L', $row+1),
				array( array('L', $row+1), array($this->getNextColumn('L', 1), $row+1) ),
				$this->style["periods-change"],
				"% Period change"
				, $columnOffset
			);
			
			$this->write( array('L', $row+2), null, $this->style["third-level"], 'Searches', $columnOffset );
			$this->write( array('M', $row+2), null, $this->style["third-level"], 'Clicks', $columnOffset );
		}		
			
		// Local CATEGORY SEARCHES
		// period 1
		$intCol = 0;
		$data = $this->data[0];
				
		
		if( count( $data["search-summary"]["category-searches-local"] ) > 0 )
		{
			foreach( (array) $data["search-summary"]["category-searches-local"] as $row )
			{
				$xGrid[$this->getNextColumn('A', $intCol)][] = $row['name'];
				$xGrid[$this->getNextColumn('B', $intCol)][] = $row['search'];
				$xGrid[$this->getNextColumn('C', $intCol)][] = $row['click'];
			}
		}
		
		// period 2
		if( isset( $this->data[1] ) )
		{						
			$data = $this->data[1];
			foreach( (array) $data["search-summary"]["category-searches-local"] as $row )
			{
				// check if item is exists initialy
				$id = $this->searchExistingValueOnColumnName( $xGrid['A'], $row['name']);
				
				// if not exist
				if( $id === false )
				{
					// create a new one
					$xGrid['A'][] = $row['name'];
					
					// search the location 
					$id = $this->searchExistingValueOnColumnName( $xGrid['A'], $row['name']);
				}
				
				$xGrid[$this->getNextColumn('D', $intCol)][$id] = $row['search'];
				$xGrid[$this->getNextColumn('E', $intCol)][$id] = $row['click'];
			}
		}

		// if there is a second period, prepare the data for the period to period comparison
		if( isset( $this->data[1] ) )
		{
			foreach( (array) $xGrid["A"] as $id => $name)
			{
				$xGrid[$this->getNextColumn('F', $intCol)][$id] = $this->getPercentageChange( $xGrid[$this->getNextColumn('B', $intCol)][$id], $xGrid[$this->getNextColumn('D', $intCol)][$id] );
				$xGrid[$this->getNextColumn('G', $intCol)][$id] = $this->getPercentageChange( $xGrid[$this->getNextColumn('C', $intCol)][$id], $xGrid[$this->getNextColumn('E', $intCol)][$id] );
			}
		}

		// GLOBAL BRAND SEARCHES
		// period 1
		$intCol = ( $this->multiplePeriods ) ? 7 : 3;
		$data = $this->data[0];
		if( count( $data["search-summary"]["category-searches-global"] ) > 0 )
		{
			foreach( (array) $data["search-summary"]["category-searches-global"] as $row )
			{
				// check if item is exists initialy
				$id = $this->searchExistingValueOnColumnName( $xGrid['A'], $row['name']);
				
				// if not exist
				if( $id === false )
				{
					// create a new one
					$xGrid['A'][] = $row['name'];
					
					// search the location 
					$id = $this->searchExistingValueOnColumnName( $xGrid['A'], $row['name']);
				}
				
				$xGrid[$this->getNextColumn('A', $intCol)][$id] = $row['search'];
				$xGrid[$this->getNextColumn('B', $intCol)][$id] = $row['click'];
			}
		}
		// period 2
		if( isset( $this->data[1] ) )
		{
			$data = $this->data[1];
			foreach( (array) $data["search-summary"]["category-searches-global"] as $row )
			{
//
				// check if item is exists initialy
				$id = $this->searchExistingValueOnColumnName( $xGrid['A'], $row['name']);
				
				// if not exist
				if( $id === false )
				{
					// create a new one
					$xGrid['A'][] = $row['name'];
					
					// search the location 
					$id = $this->searchExistingValueOnColumnName( $xGrid['A'], $row['name']);
				}
				
				$xGrid[$this->getNextColumn('C', $intCol)][$id] = $row['search'];
				$xGrid[$this->getNextColumn('D', $intCol)][$id] = $row['click'];
				
//				
				//$xGrid[$this->getNextColumn('C', $intCol)][] = $row['search'];
				//$xGrid[$this->getNextColumn('D', $intCol)][] = $row['click'];
			}
		}

		if( isset( $this->data[1] ) )
		{
			// period change
			foreach( $xGrid[$this->getNextColumn('A', $intCol)] as $id => $name)
			{
				$xGrid[$this->getNextColumn('E', $intCol)][$id] = $this->getPercentageChange( 
					$xGrid[$this->getNextColumn('B', $intCol-1)][$id], 
					$xGrid[$this->getNextColumn('D', $intCol-1)][$id] );
				$xGrid[$this->getNextColumn('F', $intCol)][$id] = $this->getPercentageChange( 
					$xGrid[$this->getNextColumn('C', $intCol-1)][$id], 
					$xGrid[$this->getNextColumn('E', $intCol-1)][$id] );
			}
		}

		// draw the grid and get the last position of current
		$row = $this->drawXGrid( $xGrid, "A", $startRow + 3 );

		// check if there's any data printed
		if( $row == $startRow + 3 )
		{
			$this->write( array('B', $startRow+3), null, null, self::NO_DATA);
		}
		
		return $row;
	}

	/**
	 * Function to create related brand searches for both local and global search
	 * @param int $startRow tell the writer where it should start writing the section from
	 * @todo please replace local with the locality of the port of the supplier on this report
	 */
	private function createLocalAndGlobalBrandSearches( $startRow )
	{
		$xGrid = array();
		$style = $this->style;
		
		$row = $startRow;
		
		// Local STATISTIC - Local STATISTIC - Local STATISTIC - Local STATISTIC - Local STATISTIC
		// Local STATISTIC - Local STATISTIC - Local STATISTIC - Local STATISTIC - Local STATISTIC
		// Local STATISTIC - Local STATISTIC - Local STATISTIC - Local STATISTIC - Local STATISTIC
	
		$this->write(
			array( 'B', $row ),
			array( array('B', $row), array($this->getNextColumn('B', ($this->multiplePeriods)?5:1), $row) ),
			$style["top-level"],
			"Your Country"
		);
		
		// header for top brands
		$this->write( array('A', $row+2), null, $style["bold"], "Top brands" );
		
		// header for period 1
		$this->write(
			array('B', $row+1),
			array( array('B', $row+1), array($this->getNextColumn('B', 1), $row+1) ),
			$this->style["periods"],
			$this->convertDate( $this->periods[0], 'd-m-Y')
		);
		
		$this->write( array('B', $row+2), null, $this->style["third-level"], 'Searches' );
		$this->write( array('C', $row+2), null, $this->style["third-level"], 'Clicks' );
		
		if( $this->multiplePeriods == true )
		{
			// header for period 2
			$this->write(
				array('D', $row+1),
				array( array('D', $row+1), array('E', $row+1) ),
				$this->style["periods2"],
				$this->convertDate( $this->periods[1], 'd-m-Y')
			);
				
			$this->write( array('D', $row+2), null, $this->style["third-level"], 'Searches' );
			$this->write( array('E', $row+2), null, $this->style["third-level"], 'Clicks' );
			
			// header for period change
			$this->write(
				array('F', $row+1),
				array( array('F', $row+1), array('G', $row+1) ),
				$this->style["periods-change"],
				"% Period change"
			);
			
			$this->write( array('F', $row+2), null, $this->style["third-level"], 'Searches' );
			$this->write( array('G', $row+2), null, $this->style["third-level"], 'Clicks' );
		}
		
		
		
		
		/*
		// WORLDWIDE STATISTIC - WORLDWIDE STATISTIC - WORLDWIDE STATISTIC - WORLDWIDE STATISTIC
		// WORLDWIDE STATISTIC - WORLDWIDE STATISTIC - WORLDWIDE STATISTIC - WORLDWIDE STATISTIC
		// WORLDWIDE STATISTIC - WORLDWIDE STATISTIC - WORLDWIDE STATISTIC - WORLDWIDE STATISTIC
		*/
		if( $this->multiplePeriods == false )
		{
			$columnOffset = -4;
		}
	
		$this->write(
			array( 'H', $row ),
			array( array('H', $row), array($this->getNextColumn('H', ($this->multiplePeriods)?5:1), $row) ),
			$style["top-level2"],
			"Global"
			, $columnOffset
		);
		
		// header for top brands
		$this->write( array('A', $row+2), null, $style["bold"], "Top brands" );
		
		// header for period 1
		$this->write(
			array('H', $row+1),
			array( array('H', $row+1), array($this->getNextColumn('H', 1), $row+1) ),
			$this->style["periods"],
			$this->convertDate( $this->periods[0], 'd-m-Y')
			, $columnOffset
		);
		
		$this->write( array('H', $row+2), null, $this->style["third-level"], 'Searches', $columnOffset );
		$this->write( array('I', $row+2), null, $this->style["third-level"], 'Clicks', $columnOffset );
		
		if( $this->multiplePeriods == true )
		{
			// header for period 2
			$this->write(
				array('J', $row+1),
				array( array('J', $row+1), array($this->getNextColumn('J', 1), $row+1) ),
				$this->style["periods"],
				$this->convertDate( $this->periods[1], 'd-m-Y')
				, $columnOffset
			);
				
			$this->write( array('J', $row+2), null, $this->style["third-level"], 'Searches', $columnOffset );
			$this->write( array('K', $row+2), null, $this->style["third-level"], 'Clicks', $columnOffset );
			
			// header for period change
			$this->write(
				array('L', $row+1),
				array( array('L', $row+1), array($this->getNextColumn('L', 1), $row+1) ),
				$this->style["periods-change"],
				"% Period change"
				, $columnOffset
			);
			
			$this->write( array('L', $row+2), null, $this->style["third-level"], 'Searches', $columnOffset );
			$this->write( array('M', $row+2), null, $this->style["third-level"], 'Clicks', $columnOffset );
		}		
			
		// Local BRAND SEARCHES
		// period 1
		$intCol = 0;
		$data = $this->data[0];
		
		if( count($data["search-summary"]["brand-searches-local"]) > 0 )
		{
			foreach( (array) $data["search-summary"]["brand-searches-local"] as $row )
			{
				$xGrid[$this->getNextColumn('A', $intCol)][] = $row['name'];
				$xGrid[$this->getNextColumn('B', $intCol)][] = intval($row['search']);
				$xGrid[$this->getNextColumn('C', $intCol)][] = intval($row['click']);
			}
		}
		
		// period 2
		if( isset( $this->data[1] ) )
		{
			$data = $this->data[1];
			foreach( (array) $data["search-summary"]["brand-searches-local"] as $row )
			{
				
				// check if item is exists initialy
				$id = $this->searchExistingValueOnColumnName( $xGrid['A'], $row['name']);
				
				// if not exist
				if( $id === false )
				{
					// create a new one
					$xGrid['A'][] = $row['name'];
					
					// search the location 
					$id = $this->searchExistingValueOnColumnName( $xGrid['A'], $row['name']);
				}
				
				$xGrid[$this->getNextColumn('D', $intCol)][$id] = intval($row['search']);
				$xGrid[$this->getNextColumn('E', $intCol)][$id] = intval($row['click']);
				
			}
		}

		// if there is a second period, prepare the data for the period to period comparison
		if( isset( $this->data[1] ) )
		{
			if( count( $xGrid ) > 0 )
			{
				foreach( $xGrid["A"] as $id => $name)
				{
					$xGrid['F'][$id] = $this->getPercentageChange( 
						$xGrid['B'][$id], 
						$xGrid['D'][$id] );
					$xGrid['G'][$id] = $this->getPercentageChange( 
						$xGrid['C'][$id], 
						$xGrid['E'][$id] );
				}
			}
		}
		
		// GLOBAL BRAND SEARCHES
		// period 1
		$intCol = ( $this->multiplePeriods ) ? 7 : 3;
		$data = $this->data[0];
		
		if( count($data["search-summary"]["brand-searches-global"]) > 0 )
		{
			foreach( (array) $data["search-summary"]["brand-searches-global"] as $row )
			{
				// check if item is exists initialy
				$id = $this->searchExistingValueOnColumnName( $xGrid['A'], $row['name']);
				
				// if not exist
				if( $id === false )
				{
					// create a new one
					$xGrid['A'][] = $row['name'];
					
					// search the location 
					$id = $this->searchExistingValueOnColumnName( $xGrid['A'], $row['name']);
				}
				
				$xGrid[$this->getNextColumn('A', $intCol)][$id] = intval($row['search']);
				$xGrid[$this->getNextColumn('B', $intCol)][$id] = intval($row['click']);
			}
		}
		// period 2
		if( isset( $this->data[1] ) )
		{
			$data = $this->data[1];
			foreach( (array)  $data["search-summary"]["brand-searches-global"] as $row )
			{
				$id = $this->searchExistingValueOnColumnName( $xGrid['A'], $row['name']);
				
				// if not exist
				if( $id === false )
				{
					// create a new one
					$xGrid['A'][] = $row['name'];
					
					// search the location 
					$id = $this->searchExistingValueOnColumnName( $xGrid['A'], $row['name']);
				}
				
				$xGrid[$this->getNextColumn('C', $intCol)][$id] = intval($row['search']);
				$xGrid[$this->getNextColumn('D', $intCol)][$id] = intval($row['click']);
			}
		}
		if( isset( $this->data[1] ) )
		{
			if( count( $xGrid ) > 0 )
			{
				// period change
				foreach( (array) $xGrid[$this->getNextColumn('A', $intCol)] as $id => $name)
				{
					$xGrid[$this->getNextColumn('E', $intCol)][$id] = $this->getPercentageChange( 
						$xGrid[$this->getNextColumn('B', $intCol-1)][$id], 
						$xGrid[$this->getNextColumn('D', $intCol-1)][$id] );
					$xGrid[$this->getNextColumn('F', $intCol)][$id] = $this->getPercentageChange( 
						$xGrid[$this->getNextColumn('C', $intCol-1)][$id], 
						$xGrid[$this->getNextColumn('E', $intCol-1)][$id] );
					
				}
			}
		}
		
		// draw the grid and get the last position of current
		$row = $this->drawXGrid( $xGrid, "A", $startRow + 3 );
		
		// check if there's any data printed
		if( $row == $startRow + 3 )
		{
			$this->write( array('B', $startRow+3), null, null, self::NO_DATA);
		}
		
		return $row;
	}
	
	/**
	 * Function to show total number of related searches appear on each day
	 * @param int $startRow
	 * @return int $row
	 */
	private function createBrandAndCategorySearchesGroupedByDate($startRow)
	{
		$this->write( array('A', $startRow + 2), null, $this->style["bold"], 'Search Impressions' );
	
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
			$this->write( array('C', $startRow+2), null, $this->style["third-level"], 'Searches', $offset);
			
			if( count( $data["search-summary"]["brand-and-category-searches"]["days"] ) > 0 )
			{
				// metadata for the structure of the chart
				$this->dataDescriptionForChart["Position"] = 'Name';
				$this->dataDescriptionForChart["Values"][] = $this->periods[$periodNo];
				$this->dataDescriptionForChart['Description'][$this->periods[$periodNo]] = $this->convertDate( $this->periods[$periodNo], 'd-m-Y' ) . ' period';
								
				// draw the daily data
				foreach( (array) $data["search-summary"]["brand-and-category-searches"]["days"] as $row )
				{ 
					$this->dataForChart['targetedSearch'][] = array('Name' =>  $row['date'], $this->periods[$periodNo] => $row['count'] );
					
					$xGrid[$this->getNextColumn('B', $offset)][] = $row['date'];
					$xGrid[$this->getNextColumn('C', $offset)][] = $row['count'];
				}
			}

			$offset += 2;
			$periodNo++;
		}
		
		// reset periodNo
		$periodNo = 0;
		
		// pull data from the batch impression for Market data for the chart
		// working on multiple periods
		foreach( $this->dataForGlobal as $data )
		{
			// if data is available
			if( count( $data["search-summary"]["brand-and-category-searches"]["days"] ) > 0 )
			{
				// meta data for the structure of the chart
				$this->dataDescriptionForChart["Position"] = 'Name';
				$this->dataDescriptionForChart["Values"][] = $this->periods[$periodNo];
				$this->dataDescriptionForChart['Description'][$this->periods[$periodNo]] = "Market for " . $this->periods[$periodNo];				
				
				// draw the daily data
				foreach( (array) $data["search-summary"]["brand-and-category-searches"]["days"] as $row )
				{ 
					$this->dataForChart['targetedSearch'][] = array('Name' =>  $row['date'], $this->periods[$periodNo] => $row['count'] );
					
				}
			}
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
		if( $this->dataForChart['targetedSearch'] !== null && count($this->dataForChart['targetedSearch']) > 0 )
		{
		
	    	$path = Shipserv_Report_Excel::createChart($this->dataForChart['targetedSearch'], $this->dataDescriptionForChart, parent::TMP_DIR_EXCEL . 'targetedSearch_' . $this->report->getCompany()->tnid . '.png', '');
	
	    	$objDrawing = new PHPExcel_Worksheet_Drawing();
			$objDrawing->setName('chart');
			$objDrawing->setPath($path);
			$objDrawing->setCoordinates('F' . $startRow );
			$objDrawing->setWorksheet($this->objPHPExcel->getActiveSheet());
		}
	}
}