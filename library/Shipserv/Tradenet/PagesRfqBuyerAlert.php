<?php


/**
 * 
 * @package myshipserv
 * @author Shane O'Connor <soconnor@shipserv.com>
 * @copyright Copyright (c) 2012, ShipServ
 */
class Shipserv_Tradenet_PagesRfqBuyerAlert 
{
	public function __construct()
	{
	}
	
	public static function processWaitingQuotes()
	{
		$logger = new Myshipserv_Logger_File('sending-email-quote-to-pages-buyer');
		
		//The query looks for any quotes sent into the system since the script went live.
		$config  = Zend_Registry::get('config');
		
		$buyerId = (Int) $config->shipserv->pagesrfq->buyerId;
                
        //Find quotes from the last 20 minutes (20/1440)
		$sql = "Select qot_internal_ref_no from quote where qot_byb_branch_code = $buyerId and qot_quote_sts = 'SUB'  and qot_internal_ref_no not in (Select pba_quote_id from pages_rfq_buyer_alerted where pba_quote_id is not null) and quote.qot_submitted_date > sysdate - 1";
		
		$db =  $GLOBALS['application']->getBootstrap()->getResource('db');
		$results = $db->fetchAll($sql);
		
		try
		{
			$nm = new Myshipserv_NotificationManager($db);
			$nm->startSending();

			if (count($results) > 0) {
			    $logger->log('Trying to send ' . count($result) . " email notification(s).");
			}
			foreach ($results as $result) {
				if( count($results) > 0 ) $logger->log('qotId: ' . $result['QOT_INTERNAL_REF_NO']);
				$nm->sendPagesQuoteToBuyer($result['QOT_INTERNAL_REF_NO']);
				
			}
			if (count($results) > 0) {
			    $logger->log('Completed');
			}
			return "Process complete. ". count($results) . " complete.";
		} 
		catch (Exception $ex)
		{
			$logger->log('Failed: ' . $ex->getMessage());
			return "Fail with ex: " . $ex->getMessage();
		}
	}
	
}

