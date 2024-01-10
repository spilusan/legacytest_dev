<?php

/**
 * Converter for SVR (supplier value report) at the moment, only excel is 
 * supported, we may extend this to support CSV 
 * 
 * @package ShipServ
 * @author Elvir <eleonard@shipserv.com>
 * @copyright Copyright (c) 2011, ShipServ
 */
class Shipserv_Enquiry_ReportConverter extends Shipserv_Object implements Myshipserv_Converter
{
	const TMP_DIR = '/tmp/';
	
	/**
	 * Interface function to convert a given SVR report to another format
	 * @param Shipserv_Report $report
	 * @param string $format only xlsx is supported for now
	 */
	public static function convert( $enquiries, $format = "xlsx" )
	{
		$object = new self();
		
		if( $format == 'xlsx' )
		{
			return $object->toExcel( $enquiries );
		}		
	}

	/**
	 * Export to excel
	 * @param Shipserv_Report $report
	 */
	private function toExcel( $report )
	{
		ini_set("memory_limit","128M");
		
		// create the object
		$objPHPExcel = new PHPExcel();
		
		PHPExcel_Shared_Font::setAutoSizeMethod(PHPExcel_Shared_Font::AUTOSIZE_METHOD_EXACT);
		
		// create writer and reader
		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
		
		// prepare report's data 
		$this->report = $report;
						
		$fileName = self::TMP_DIR . 'enquiry/excel/' . 'enquiries_for_TNID_' . $report->supplier->tnid . '.xlsx';
		
		// remove the old file
		if( file_exists( $fileName ) )
		{
			unlink( $fileName );
		}
		
		// create first tab
		$summary = new Shipserv_Enquiry_Excel_Summary($objWriter, $objPHPExcel, $this->report);
		$a = $summary->create();
		$detail = new Shipserv_Enquiry_Excel_Detail($objWriter, $objPHPExcel, $this->report);
		$a = $detail->create();
		
		// activate summary worksheet as default
		$objPHPExcel->setActiveSheetIndex(0);
		$objWriter->save( $fileName );
		
		return basename( $fileName );
		
		// send the binary to the browser and force download the file
		ob_end_clean();
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . basename( $fileName ). '"');

        ob_end_clean();
		
        $objWriter->save( 'php://output');
		
		return basename( $fileName );
	}		
	
	private function debug()
	{
		
	}
}