<?php
/**
 * Base class of the excel
 * @author Elvir <eleonard@shipserv.com>
 */
abstract class Myshipserv_Excel extends Shipserv_Object
{

	/**
	 * Function to draw/write the dataGrid (xGrid) to excel
	 * @param array $grid
	 * @param char $c
	 * @param int $r
	 */
	protected function drawXGrid( $grid, $c, $r, $debug = false )
	{
		// if no data then show message
		if( count( $grid ) == 0 )
		{
			// $this->objPHPExcel->getActiveSheet()->setCellValue( $c.$r, 'No data is available to display');
			return $r;
		}
		$numberOfRows = array();
		// parse the grid
		foreach( $grid as $column => $data )
		{
			$row = $r;
			
			// re-order the array
			ksort($data);
			
			if( $debug === true )
			{
				//print_r( $data );
			}
			
			// parse the data within the grid
			foreach( $data as $ro => $content )
			{
				$row = $ro + $r;
				// initialise style for each cell
				$style = null;
				
				// concat the column and row to make a cell on excel
				$cell = $column . $row;
				
				// if content contains style information, then separate them into two different variables
				if( is_array( $content ) )
				{
					$tmp = $content;
					$content = $tmp['content'];
					$style = $tmp['style'];
				}
				
				// apply style for each cell (if defined)
				if( $style !== null )
				{
					$this->objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray( $style );
					
				}
				
				// check if content is numeric, if so, apply numeric format to the cell and write to it
				if( $content != "" && is_numeric( $content ) )
				{
					$this->objPHPExcel->getActiveSheet()->getCell($cell)->setValueExplicit($content, PHPExcel_Cell_DataType::TYPE_NUMERIC);
				}
				// write to cell
				else 
				{
					$this->objPHPExcel->getActiveSheet()->setCellValue( $cell, $content);
				}
				$row++;
			}
			if( $numberOfRows[ count($numberOfRows)-1 ] != $row )
			$numberOfRows[] = $row;
		}

		if( count( $numberOfRows ) > 1 )
		{
			return ( $numberOfRows[0] > $numberOfRows[1] ) ? $numberOfRows[0] : $numberOfRows[1];
		}
		else
		{
			return $numberOfRows[0];
		}
		//return $row;			
	}
	
	/**
	 * Search needle on array and return id 
	 * @param string $haystack
	 * @param int $needle
	 */
	protected function searchExistingValueOnColumnName( $haystack, $needle )
	{
		$needle = strtolower( trim( $needle ) );
		foreach( (array) $haystack as $id => $content )
		{
			$content = strtolower( trim( $content ) );
			if( $content == $needle )
			{
				return $id;
			}
		}
		return false;
	}
	
	/**
	 * Convert 20110101 to DateTime object
	 *
	 * @param string $string
	 * @param string $format
	 */
	protected function convertDate( $string, $format )
	{
		$tmp = explode( " ", $string );
		if( count( $tmp ) > 1 )
		{
			foreach( $tmp as $d )
			{
				$date = new DateTime();
				$date->setDate( substr($d, 0, 4), substr($d, 4, 2), substr($d, 6, 2) );	
				$output[] = $date->format( $format );
			}
			
			return implode(" to ", $output);
		}
		else
		{
			$date = new DateTime();
			$date->setDate( substr($string, 0, 4), substr($string, 4, 2), substr($string, 6, 2) );	
			return $date->format( $format );			
		}
	}	
	
	/**
	 * Get next column in excel
	 *
	 * @param char $currentCol
	 * @param int $num offset
	 */
	protected function getNextColumn( $currentCol, $num = 1 )
	{
		return chr( ord( $currentCol ) + $num );		
	}
	
	/**
	 * Get the coordinate of a cell with the offsets supplied.
	 * @param unknown_type $currentRow
	 * @param unknown_type $currentColumn
	 * @param unknown_type $offsetRow
	 * @param unknown_type $offsetColumn
	 */
	protected function getCell( $currentRow, $currentColumn, $offsetRow = 1, $offsetColumn = 1 )
	{
		$column = chr( ord( $currentColumn ) + offsetColumn);
		$row = $currentRow + $offsetColumn;
		return $column . $row;
	}
	
	/**
	 * Function to interface PHPExcel writing function.
	 * 
	 * @param array $cell array( char column_letter, int row)
	 * @param array $merge array( char column_letter, int row)
	 * @param array $style
	 * @param string $content
	 * @param int $columnOffset
	 * @param int $rowOffset
	 * @throws Exception
	 */
	protected function write( $cell, $merge, $style, $content, $columnOffset = null, $rowOffset = null )
	{
		if( $content[0] == "=") $content[0] = "";
		
		if( $merge != null )
		{
			if( $columnOffset != null )
			{
				$merge[0][0] = $this->getNextColumn( $merge[0][0], $columnOffset );
				$merge[1][0] = $this->getNextColumn( $merge[1][0], $columnOffset );
			}
			if( $merge[0] != "" && $merge[1] != "" )
			{
				$this->objPHPExcel->getActiveSheet()->mergeCells( implode("",$merge[0]) . ':' . implode("",$merge[1]) );
			}
			
		}
		
		if ( is_array( $cell ) )
		{
			if( $columnOffset != null )
			{
				$cell[0] = $this->getNextColumn( $cell[0], $columnOffset );
			}
			$c = $cell[0] . $cell[1];
		} 
		else 
		{
			throw new Exception("Error");
		}
		
		// if content contains style information, then separate them into two different variables
		if( is_array( $content ) )
		{
			$tmp = $content;
			$content = $tmp["content"];
			$style = $tmp['style'];
		}
	
		
		if( $style !== null )
		$this->objPHPExcel->getActiveSheet()->getStyle( $c )->applyFromArray( $style );
		
		if( $content !== null && $content != "" )
		{		
			$this->objPHPExcel->getActiveSheet()->setCellValue( $c, $content);
		}
		else
		{
			$this->objPHPExcel->getActiveSheet()->getCell($c)->setValueExplicit("", PHPExcel_Cell_DataType::TYPE_NUMERIC);
		}
		
		// set auto width on each column
		$this->objPHPExcel->getActiveSheet()->getColumnDimension($cell[0])->setAutoSize(true);
		
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
		
		if(debug)
		{
			//var_dump( $period1 );
			//var_dump( $period2 );
			//echo "<hr />";
		}
		if( is_array( $period1 ) || is_array( $period2 ) )
		{
			return array( "content" => '0%', "style" => $greenStyle);			
		} 
		if( $period2 === null || $period2 === '' )
		{
			return '';
		}
		if( $period1 === null || $period1 === '' )
		{
			return '';
		}
		
		if( $period1 > 0 && $period2 > 0 )
		{
			$change = ( $period2 - $period1 ) / $period2 * 100;
			$change = abs($change);	
		}
		else if( $period1 > 0 && $period2 == 0 )
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
		if( $period2 > $period1 )
		{
			$style = $greenStyle;
			if( $change < 0 )
			{
				$change = $change * -1;
			}
		}
		else 
		{
			$style = $redStyle;
			if( floatval($change) == 0 )
			{
				$style = $greenStyle;
			}
		}
		
		$data = array( "content" => round($change) . '%', "style" => $style);
		return $data;
	}
	
	/**
	 * Create supplier information on the top of the report
	 * @return void
	 */
	protected function createSupplierInformation( )
	{
		// get the company for this report
		$company = $this->report->getCompany(true);
		
		// create TOP header and style it		
		$this->objPHPExcel->getActiveSheet()->getStyle("A1")->applyFromArray( array( 
			"font" => array( "bold" => true, "size" => 14 )
			, "alignment" => array( "horizontal" => PHPExcel_Style_Alignment::HORIZONTAL_LEFT)
		));
		
		// merge the cell 
		$this->objPHPExcel->getActiveSheet()->mergeCells('A1:Z1');
		
		// write it
		$this->objPHPExcel->getActiveSheet()->setCellValue('A1', "" . trim( $company->name ) . " (TradeNet ID: " . $company->tnid . ")");
		
	}
		
	/**
	 * Mimic same NVL function on oracle.
	 * Replace null value with subtitution
	 * @param int|null|string $value
	 * @param int|string $subtitute
	 */
	protected function nvl( $value, $subtitute = 0 )
	{
		if( isset( $value ) === false || $value === null || $value == "" || $value == '0' || $value == 0 )
			return $subtitute;
	}
	
	public function normaliseData( $data )
	{
		
		$object = new Shipserv_Report_Excel_Data( $data, $this->report );		
		return $object->getData();
	}
}