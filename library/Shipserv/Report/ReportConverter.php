<?php

/**
 * Converter for SVR (supplier value report) at the moment, only excel is 
 * supported, we may extend this to support CSV 
 * 
 * @package ShipServ
 * @author Elvir <eleonard@shipserv.com>
 * @copyright Copyright (c) 2011, ShipServ
 */
class Shipserv_Report_ReportConverter extends Shipserv_Object implements Myshipserv_Converter
{
	const TMP_DIR = '/tmp/';
	
	/**
	 * Interface function to convert a given SVR report to another format
	 * @param Shipserv_Report $report
	 * @param string $format only xlsx is supported for now
	 */
	public static function convert( Shipserv_Report $report, $format = "xlsx" )
	{
		$object = new self();
		
		if( $format == 'xlsx' )
		{
			//try
			//{
				return $object->toExcel( $report );
			//}
			//catch( Exception $e )
			//{
			//	throw new Myshipserv_Exception_MessagedException("Problem with SVR to excel conversion: " . $e->getMessage(), 500);	 
			//}
		}		
	}

	/**
	 * Export to excel
	 * @param Shipserv_Report $report
	 */
	private function toExcel( Shipserv_Report $report )
	{
		ini_set("memory_limit","128M");
		
		// create the object
		$objPHPExcel = new PHPExcel();
		
		PHPExcel_Shared_Font::setAutoSizeMethod(PHPExcel_Shared_Font::AUTOSIZE_METHOD_EXACT);
		
		// create writer and reader
		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
		
		// prepare report's data 
		$this->report = $report;
				
		$company = $this->report->getCompany(true); //Skipcheck for unpublished supplier
		
		$response = new Myshipserv_Response("200", "OK", $this->report->toArray() );
		$json = json_encode( $response->toArray() );
        
		$fileName = self::TMP_DIR . 'svr/excel/' . 'report_for_TNID_' . $company->tnid . '.xlsx';
		
		// remove the old file
		if( file_exists( $fileName ) )
		{
			unlink( $fileName );
		}
		
		
		// debug the report
		file_put_contents(self::TMP_DIR . 'svr/excel/report_for_TNID_' . $company->tnid . '.json', $json);
		
		// create first tab
		$summary = new Shipserv_Report_Excel_Summary($objWriter, $objPHPExcel, $this->report);
		$a = $summary->create();
		
		$bannerStatistic = new Shipserv_Report_Excel_BannerStatistic($objWriter, $objPHPExcel, $this->report);
		$a = $bannerStatistic->create();
		
		$targetedSearch = new Shipserv_Report_Excel_TargetedSearch($objWriter, $objPHPExcel, $this->report);
		$a = $targetedSearch->create();
		
		$profileView = new Shipserv_Report_Excel_ProfileView($objWriter, $objPHPExcel, $this->report);
		$a = $profileView->create();

		$contactView = new Shipserv_Report_Excel_ContactView($objWriter, $objPHPExcel, $this->report);
		$a = $contactView->create();
		
		$enquiry = new Shipserv_Report_Excel_EnquiryStatistic($objWriter, $objPHPExcel, $this->report);
		$a = $enquiry->create();

		// no longer needed, chart are compiled in worksheet level
//		$chart = new Shipserv_Report_Excel_Chart($objWriter, $objPHPExcel, $this->report);
//		$a = $chart->create();
		
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