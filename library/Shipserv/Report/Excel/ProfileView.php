<?php
/**
 * Handle the creation of profile worksheet
 * for the documentation please refer to TargetedSearch.php class located on the same folder
 * @author Elvir <eleonard@shipserv.com>
 */

class Shipserv_Report_Excel_ProfileView extends Shipserv_Report_Excel
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
		$objWorksheet->getTabColor()->setRGB( $this->colors["worksheets"]["Profile views"] );
		$objWorksheet->setTitle("Profile views");
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
					, "color" => array( "argb" => $this->colors["worksheets"]["Profile views"] )
				)
			)
		);
		$this->objPHPExcel->getActiveSheet()->setCellValue('A2', "Supplier profile page views statistics");
		
		// create drill down for category
		$startRow = $this->createTableGroupByUserType(4);		

		// create local and global brand search
		$startRow = $this->createTopSearchesLeadToImpressionTableGroupByKeyword( $startRow + 5 );		
		
	}
	
	private function createTableGroupByUserType( $startRow )
	{
		// initialise grid
		$xGrid = array();
		
		$style = $this->style;
		
		$row = $startRow;
		
		// header for top brands
		$this->write( array('A', $row+1), null, $style["bold"], "Types of user that view this supplier" );
		
		// header for period 1
		$this->write(
			array('B', $row+1),
			null,
			$this->style["periods"],
			$this->convertDate( $this->periods[0], 'd-m-Y')
		);
		
		
		if( $this->multiplePeriods == true )
		{
			// header for period 2
			$this->write(
				array('C', $row+1),
				null,
				$this->style["periods2"],
				$this->convertDate( $this->periods[1], 'd-m-Y')
			);
				
			
			// header for period change
			$this->write(
				array('D', $row+1),
				null,
				$this->style["periods-change"],
				"% Period change"
			);
			
		}
			
		// period 1
		$intCol = 0;
		$data = $this->data[0];
		foreach( (array)$data["impression-summary"]["impression-by-user-type"] as $row )
		{
			$xGrid[$this->getNextColumn('A', $intCol)][] = $row['name'];
			$xGrid[$this->getNextColumn('B', $intCol)][] = intval($row['count']);
		}
				
		// period 2
		if( isset( $this->data[1] ) )
		{
			$data = $this->data[1];
			foreach( (array)$data["impression-summary"]["impression-by-user-type"] as $row )
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
				
				$xGrid[$this->getNextColumn('C', $intCol)][$id] = intval($row['count']);
				
			}
		}

		// if there is a second period, prepare the data for the period to period comparison
		if( isset( $this->data[1] ) )
		{
			foreach( (array) $xGrid["A"] as $id => $name)
			{
				$xGrid['D'][$id] = $this->getPercentageChange( $xGrid['B'][$id], $xGrid['C'][$id] );//@( ( $xGrid['B'][$id] - $xGrid['D'][$id] ) / $xGrid['D'][$id] * 100  );
			}
		}
		
		// draw the grid and get the last position of current
		$row = $this->drawXGrid( $xGrid, "A", $startRow + 2 );
		
		// check if there's any data printed
		if( $row == $startRow + 2 )
		{
			$this->write( array('B', $startRow + 2), null, null, self::NO_DATA);
		}
		
		return $row;
	}
	
	
	private function createTopSearchesLeadToImpressionTableGroupByKeyword( $startRow )
	{
		// initialise grid
		$xGrid = array();
		
		$style = $this->style;
		
		$row = $startRow;
		
		// header for top brands
		$this->write( array('A', $row+1), null, $style["bold"], "Top searches that lead to impressions" );
		
		// header for period 1
		$this->write(
			array('B', $row+1),
			null,
			$this->style["periods"],
			$this->convertDate( $this->periods[0], 'd-m-Y')
		);
		
		
		if( $this->multiplePeriods == true )
		{
			// header for period 2
			$this->write(
				array('C', $row+1),
				null,
				$this->style["periods2"],
				$this->convertDate( $this->periods[1], 'd-m-Y')
			);
				
			
			// header for period change
			$this->write(
				array('D', $row+1),
				null,
				$this->style["periods-change"],
				"% Period change"
			);
			
		}
			
		// period 1
		$intCol = 0;
		$data = $this->data[0];
		foreach( (array)$data["impression-summary"]["impression-by-search-keywords"] as $row )
		{
			$xGrid[$this->getNextColumn('A', $intCol)][] = $row['name'];
			$xGrid[$this->getNextColumn('B', $intCol)][] = intval($row['count']);
		}
		
		// period 2
		if( isset( $this->data[1] ) )
		{
			$data = $this->data[1];
			foreach( (array)$data["impression-summary"]["impression-by-search-keywords"] as $row )
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
				
				$xGrid[$this->getNextColumn('C', $intCol)][$id] = intval($row['count']);
			}
		}

		// if there is a second period, prepare the data for the period to period comparison
		if( isset( $this->data[1] ) )
		{
			foreach( (array)$xGrid["A"] as $id => $name)
			{
				$xGrid['D'][$id] = $this->getPercentageChange( $xGrid['B'][$id], $xGrid['C'][$id] );//@( ( $xGrid['B'][$id] - $xGrid['D'][$id] ) / $xGrid['D'][$id] * 100  );
			}
		}
		
		// draw the grid and get the last position of current
		$row = $this->drawXGrid( $xGrid, "A", $startRow + 2 );
		
		// check if there's any data printed
		if( $row == $startRow + 2 )
		{
			$this->write( array('B', $startRow + 2), null, null, self::NO_DATA);
		}
		
		return $row;
	}

}