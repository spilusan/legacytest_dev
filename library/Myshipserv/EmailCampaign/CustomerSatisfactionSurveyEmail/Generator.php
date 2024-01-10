<?php
/**
 * This class handles the email reminder to unverified supplier
 * 
 * @author elvir <eleonard@shipserv.com>
 *
 */
class Myshipserv_EmailCampaign_CustomerSatisfactionSurveyEmail_Generator {

	private $db;
	
	public $suppliers = array();
	
	public $debug = false;
	
	const TMP_DIR = '/tmp/';
	const NO_EMAIL_PER_BATCH_LIVE_MODE = 10;
	const NO_EMAIL_PER_BATCH_DEBUG_MODE = 10;
	/**
	 * Sending an invitation email to all users of unverified supplier
	 */
	public function generate()
	{
		$this->useDb();
		
	}	

	/**
	 * Sending an invitation email to all users of unverified supplier
	 */
	public function useDb()
	{
		$arguments = getopt('d:');
		
		if( isset( $arguments['d'] ) && $arguments['d'] == 'true' )
		{
			$this->debug = false;
		} 
		
		
		// No max execution time
		//ini_set('max_execution_time', 0);

		// No upper memory limit
		//ini_set("memory_limit","512M");
		
				
		$notificationManager = new Myshipserv_NotificationManager( $this->getDb());
		
		if( $this->debug == false )
		{
			$numberOfEmailPerBatch = self::NO_EMAIL_PER_BATCH_LIVE_MODE;
		}
		else
		{
			$numberOfEmailPerBatch = self::NO_EMAIL_PER_BATCH_DEBUG_MODE;
		}
		
		$totalEmailSent = 0;
		$adapter = new Shipserv_Oracle_Survey();
		
		$rows = $adapter->getNextBatchForInvitation( $numberOfEmailPerBatch );
		foreach( $rows  as $row )
		{
			$email = $row["PYU_EMAIL"];
			
			if( $this->debug )
			{
				echo "Sending email to: " . $email . "<br />";
			}

			$notificationManager->sendCustomerSatisfactionSurveyInvitation( $email );
			$a = new Shipserv_Oracle_Survey();
			$a->setTimestampOnInvitationByEmail( $email );
			$totalEmailSent++;
		}
				
		echo "\n" . $totalEmailSent . " email(s) sent.";

	}	
	
	/**
	 * Sending an invitation email to all users of unverified supplier
	 */
	public function generateExcel()
	{
		$arguments = getopt('d:');
		
		if (isset($arguments['d']) && $arguments['d'] == 'true') {
			$this->debug = true;
		} 
		
		// check if /tmp/customerSatisfactionSurvey exists
		if (is_dir(self::TMP_DIR . 'customerSatisfactionSurvey') == false) {
			throw new Exception("Missing excel file containing all users from Salesforce on " . self::TMP_DIR . 'customerSatisfactionSurvey/. Please ask Elvir/Shane for this file and run this script again.' );
		}
		
		// 
		$fileReadOnly = self::TMP_DIR . 'customerSatisfactionSurvey/users.xlsx';
		$fileWritable = self::TMP_DIR . 'customerSatisfactionSurvey/users.xlsx';
		$fileOnTmp = self::TMP_DIR . 'customerSatisfactionSurvey/output.xlsx';
		
		// No max execution time
		ini_set('max_execution_time', 0);

		// No upper memory limit
		ini_set("memory_limit","512M");
		
		
		// prepare the Excel reader
		$objPHPExcelWritable = PHPExcel_IOFactory::load($fileWritable);
		$writableWorksheet = $objPHPExcelWritable->getActiveSheet();
		
		$objReader = PHPExcel_IOFactory::createReader('Excel2007');
		$objPHPExcel = $objReader->load($fileReadOnly);
		$objWorksheet = $objPHPExcel->getActiveSheet();
		$highestRow = $objWorksheet->getHighestRow();
		$highestColumn = $objWorksheet->getHighestColumn();
		$highestColumnIndex = PHPExcel_Cell::columnIndexFromString( $highestColumn);
		
		$notificationManager = new Myshipserv_NotificationManager( $this->getDb());
		
		if( $this->debug == false )
		{
			$numberOfEmailPerBatch = self::NO_EMAIL_PER_BATCH_LIVE_MODE;
		}
		else
		{
			$numberOfEmailPerBatch = self::NO_EMAIL_PER_BATCH_DEBUG_MODE;
		}
		
		$totalEmailSent = 0;
		
		for( $row = 1; $row <= $highestRow; ++$row)
		{
			// check if it's already been sent
			if( $objWorksheet->getCellByColumnAndRow( 9, $row )->getValue() != "SENT" )
			{
				if( $totalEmailSent < $numberOfEmailPerBatch )
				{
					$tnid 		= $objWorksheet->getCellByColumnAndRow( 1, $row )->getValue();
					$accountId 	= $objWorksheet->getCellByColumnAndRow( 3, $row )->getValue();
					$firstName 	= $objWorksheet->getCellByColumnAndRow( 4, $row )->getValue();
					$lastName 	= $objWorksheet->getCellByColumnAndRow( 5, $row )->getValue();
					$contactId 	= $objWorksheet->getCellByColumnAndRow( 6, $row )->getValue();
					$email 		= $objWorksheet->getCellByColumnAndRow( 7, $row )->getValue();
					
					// please change this with Shane's new structure
					$recordId	= "a0Z00000000EHMjEAO"; //$objWorksheet->getCellByColumnAndRow( 3, $row )->getValue();
					
					if( $this->debug ) echo "Sending email to: " . $email . "<br />";
					$notificationManager->sendCustomerSatisfactionSurveyInvitation($tnid, $accountId, $firstName, $lastName, $contactId, $email, $recordId);
					$writableWorksheet->getCell("J".$row)->setValue("SENT");
					$totalEmailSent++;
				}
				else
				{
					break;
				}
			}
		}
		
		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcelWritable, 'Excel2007');
		$objWriter->save($fileOnTmp);
		
		// move tmp file to SS_content
		if( !copy($fileOnTmp, $fileWritable) )
		{
			echo "Error when updating the excel file.";
		}
		
		echo "\n" . $totalEmailSent . " email(s) sent.";

	}	
	
	private static function getStandByDb()
	{
		$resource = $GLOBALS["application"]->getBootstrap()->getPluginResource('multidb');
		return $resource->getDb('standbydb');
	}

	private static function getDb()
	{
		return $GLOBALS["application"]->getBootstrap()->getResource('db');
	}
	
}
?>
