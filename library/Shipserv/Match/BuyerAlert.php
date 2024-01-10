<?php
/**
 * Class that handles the processing of Match Quotes, so that buyer is alerted when quote is received. Cron scripts run every 10 minutes call this.
 * @package myshipserv
 * @author Shane O'Connor <soconnor@shipserv.com>
 * @copyright Copyright (c) 2012, ShipServ
 */
class Shipserv_Match_BuyerAlert 
{
    // added by Yuriy Akopov on 2014-06-19
    const
        TABLE_NAME = 'BUYER_MATCH_ALERT',

        COL_QUOTE_ID = 'BMA_QUOTE_ID',
        COL_DATE     = 'BMA_CREATED_DATE',
        COL_SILENCED = 'BMA_SILENCED'
    ;

    public function __construct() 
    {
    	
    }

	public static function processWaitingMatchQuotes() 
    {
        //The query looks for any quotes sent into the system since the script went live.
        $config = Zend_Registry::get('config');

        $buyerId = (Int) $config->shipserv->match->buyerId;

		$sql = "
            SELECT
                QOT_INTERNAL_REF_NO, QOT_RFQ_INTERNAL_REF_NO 
            FROM
                quote LEFT JOIN Buyer_Match_Alert ON qot_internal_ref_no=bma_quote_id
            WHERE
                qot_byb_branch_code = $buyerId
                AND qot_quote_sts = 'SUB'
                AND qot_total_cost > 0
                AND qot_updated_date > (sysdate - 1)
                AND bma_quote_id IS NULL
            ORDER BY
                qot_internal_ref_no
        ";
        $db = $GLOBALS['application']->getBootstrap()->getResource('db');
        $results = $db->fetchAll($sql);
        $logger = new Myshipserv_Logger_File('sending-match-quote-to-buyer');
        
        try {
            $nm = new Myshipserv_NotificationManager($db);
            $nm->startSending();
			
            if( count($results) > 0 )
            {
				$logger->log("----------------------------------------------------------------------------------");
				$logger->log(count($results) . " unprocessed match quotes found");
            }

            // changed by Yuriy Akopov on 2014-10-23, S11338
            $sentCount = 0;
            foreach ($results as $result) {
            	$logger->log("Processing match quote " . $result['QOT_INTERNAL_REF_NO'] . "...");

            	//S16121: do not send email if Keppel deadline conditions are satisfied
            	$rfq = Shipserv_Rfq::getInstanceById($result['QOT_RFQ_INTERNAL_REF_NO']);
            	if ($rfq && $rfq->shouldHideQuotePrice()) {
            	    $logger->log(sprintf('Not sending email for quote="%s", rfq="%s" because Keppel deadline conditionsare satisfied', $result['QOT_INTERNAL_REF_NO'], $result['QOT_RFQ_INTERNAL_REF_NO']));
            	    
            	//Send email    
            	} else {
            	    try {
            	    
            	        $nm->sendMatchQuoteToBuyer($result['QOT_INTERNAL_REF_NO']);
            	        $logger->log("Notification sent out");
            	    
            	        $sentCount++;
            	    
            	    } catch (Myshipserv_NotificationManager_Email_Exception $e) {
            	        // added by Yuriy Akopov on 2014-04-07
            	        $logger->log("Notification not sent, match quote discarded as a duplicate");
            	        continue;
            	    
            	    } catch (Exception $e) {
            	        $logger->log("Notification not sent, " . get_class($e) . ": " . $e->getMessage());
            	        continue;
            	    }
            	}            	

                // match quote notification has been sent which means the quote must be imported as well without waiting for the cron job to fetch it
                $quote = Shipserv_Quote::getInstanceById($result['QOT_INTERNAL_REF_NO']);

                try {
                    if ($quote->prepareForAutoImport()) {
                        $logger->log("Quote prepared for the import process");
                    } else {
                        $logger->log("Match quote import step skipped");
                    }
                } catch (Exception $e) {
                    // DE6561: added by Yuriy Akopov for one failed quote not to block the import of others waiting in the queue
                    $logger->log("Failed to auto-import quote " . $quote->qotInternalRefNo . ": " . get_class($e) . ": " . $e->getMessage());

                    // notify the code monkey
                    $failMail = new Myshipserv_SimpleEmail();
                    $failMail->setSubjectAndBody(
                        "Failed to auto-import quote " . $quote->qotInternalRefNo,
                        get_class($e) . ": " . $e->getMessage()
                    );
                    $failMail->send(Myshipserv_Config::getMatchQuoteImportBccRecipients());

                    continue;
                }
            }
            
            return "Completed with " . $sentCount . " emails sent out";

        } catch (Exception $ex) {
            $message = "Failed, " . get_class($ex) . ": " . $ex->getMessage();
            $logger->log($message);
            return $message;
        }
    }

    /**
     * Returns datetime of the notification email sending date for the given quote or null if it hasn't been sent
     *
     * @author  Yuriy Akopov
     * @date    2014-06-19
     * @story   S10311
     *
     * @param   Shipserv_Quote|int  $quote
     *
     * @return  DateTime|null
     */
    public static function getQuoteAlertDate($quote) {
        if ($quote instanceof Shipserv_Quote) {
            $quoteId = $quote->qotInternalRefNo;
        } else {
            $quoteId = $quote;
        }

        $db = Shipserv_Helper_Database::getDb();
        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('bma' => self::TABLE_NAME),
                new Zend_Db_Expr("TO_CHAR(MAX(bma." . self::COL_DATE . "), 'YYYY-MM-DD HH24:MI:SS')")
            )
            ->where('bma.' . self::COL_QUOTE_ID . ' = ?', $quoteId);
        ;

        $dateStr = $db->fetchOne($select);
        if (strlen($dateStr) === 0) {
            return null;
        }

        return new DateTime($dateStr);
    }

    /**
     * Adds a record that an email has been sent for the given quote
     *
     * @author  Yuriy Akopov
     * @date    2014-06-19
     * @story   S10311
     *
     * @param   Shipserv_Quote|int  $quote
     * @param   bool                $silenced
     *
     * @return  int
     */
    public static function saveAlert($quote, $silenced = false) {
        if ($quote instanceof Shipserv_Quote) {
            $quoteId = $quote->qotInternalRefNo;
        } else {
            $quoteId = $quote;
        }

        $db = Shipserv_Helper_Database::getDb();
        return $db->insert(self::TABLE_NAME, array(
            self::COL_QUOTE_ID => $quoteId,
            self::COL_DATE     => new Zend_Db_Expr('SYSDATE'),
            self::COL_SILENCED => ($silenced ? 1 : 0)
        ));
    }
}

