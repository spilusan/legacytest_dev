<?php

class Myshipserv_NotificationManager_Email_EmailCampaign_UpsellNonPayingSupplier extends Myshipserv_NotificationManager_Email_Abstract
{
	protected $subject = "";
	protected $isSalesforce = false;
	protected $user = false;
	
	public function __construct ($email, $supplier, $report, $data)
	{	
		if ($_SERVER['APPLICATION_ENV'] == 'production' || $_SERVER['APPLICATION_ENV'] == 'testing' || $_SERVER['APPLICATION_ENV'] == 'development')
		{
			$this->enableSMTPRelay = true;
		}
		
		$this->email = $email;
		$this->supplier = $supplier;
		
		$this->statistic = $report;
		$this->mode = $textMode;
		$this->hash = md5("SIR_INVITE_" . $email );
		$this->db = $db;
		$this->data = $data;
	}
	
	public function getRecipients ()
	{
		$row = array();
		$row['email'] = $this->email;
		return array($row);
	}
	
	private function getTotalRfqReceivedByTnid()
	{
		// use SSREPORT2
		$sql = "SELECT COUNT(*) FROM rfq WHERE spb_branch_code=:tnid AND rfq_submitted_date BETWEEN sysdate-365 AND sysdate";
		return $this->db['ssreport2']->fetchOne($sql, array('tnid' => $this->tnid));
	}

	public function getSegmentId()
	{
		$statistic = $this->statistic;
		$totalProfileView = $statistic->data['supplier']['impression-summary']['impression']['count'];
		$totalRfq = $statistic->data['supplier']['tradenet-summary']['RFQ']['count'];
		if( $totalRfq >= 250 && $totalRfq < 1300 )
			return "A";
		else if( $totalRfq >= 1 && $totalRfq < 250 )
			return "B";
		else
			return "C";
	}
	
	public function getSubject ()
	{
		$statistic = $this->statistic;
		
		$totalProfileView = $statistic->data['supplier']['impression-summary']['impression']['count'];
		$totalRfq = $statistic->data['supplier']['tradenet-summary']['RFQ']['count'];
		
		if( $totalRfq >= 250 && $totalRfq < 1300 )
		{
			$subject = "You've received " . $totalRfq . " RFQ" . (($totalRfq>1)?"s":"") . " via ShipServ in the last 12 months";
		}
		else if( $totalRfq >= 1 && $totalRfq < 250 )
		{
			$subject = "You've received " . $totalRfq . " RFQ" . (($totalRfq>1)?"s":"") . " and " . $totalProfileView . " profile view" . (($totalProfileView>1)?"s":"") . " in the last 12 months";
		}
		else
		{
			$subject = "You've received " . $totalProfileView . " profile view" . (($totalProfileView>1)?"s":"") . " in the last 12 months";
		}
		
		// group name on JANGOSMTP
		if ($this->enableSMTPRelay)
		{
			if ($_SERVER['APPLICATION_ENV'] == 'production')
			{
				$subject .= "{Upsell for non paying supplier - LIVE}";
			}
			else
			{
				$subject .= "{Upsell for non paying supplier - UAT}";
			}
		}
		
		return $subject;
	}
	
	public function getBody ()
	{
		$view = $this->renderView();
		$recipients = $this->getRecipients();
		
		return array($recipients[0]["email"] => $view->render('email/campaign/upsell-non-paying-supplier.phtml'));	
	}

	private function renderView()
	{
		$view = $this->getView();
		$view->segmentId = $this->getSegmentId();
		$view->email = $this->email;
		$view->hostname = $_SERVER['HTTP_HOST'];
		$view->statistic = $this->statistic;
		$view->supplier = $this->supplier;
		return $view;
	}
}


?>
