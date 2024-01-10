<?php

class Shipserv_Adapters_TradeNet
{
	private $rfqToXml;
	private $config;
	
	public function __construct ()
	{
		$this->rfqToXml = new Shipserv_TnMsg_Xml_Rfq();
		$this->config  = Zend_Registry::get('config');
	}
	
	/**
	 * @return string TN control reference for submitted RFQ.
	 */
	public function sendRfq (Shipserv_TnMsg_Xml_RfqHelper $rfq)
	{
		$client = new Zend_Http_Client();
		//echo $rfq->getSender();exit;
		$client->setUri($this->makeSendRfqUrl($rfq)); // todo: check it is indeed the buyer id that's required
		$client->setConfig(array(
			'maxredirects' => 0,
			'timeout'      => 30
		));
		$client->setMethod(Zend_Http_Client::POST);
		$client->setRawData($this->rfqToXml->rfqToMtml($rfq), 'text/xml');
		$response = $client->request();
		
		return $this->parseSendRfqResponse($response);
	}
	
	/**
	 * @return string TN control reference for submitted RFQ.
	 */
	private function parseSendRfqResponse ($response)
	{
		// todo: check headers?
		// good e.g. <document><control-reference>RequestForQuote:5147876</control-reference></document>
		$xResponse = new SimpleXMLElement($response->getBody());
		if ($xResponse->getName() == 'document')
		{
			$cr = $xResponse->{'control-reference'}[0];
			$prefix = 'RequestForQuote:';
			$prefixLen = strlen($prefix);
			if (substr($cr, 0, $prefixLen) == $prefix && strlen($cr) > $prefixLen)
			{
				return trim(substr($cr, $prefixLen));
			}
		}
		//<error><message>Error:The client is neither the buyer nor the vendor or inactive buyer/vendor</message></error>
		//<error><message>Recipient is either an invalid TradeNet ID or no longer active on TradeNet</message></error>
		throw new Exception("Bad response: " . $response->getBody());
	}
	
	private function makeSendRfqUrl (Shipserv_TnMsg_Xml_RfqHelper $rfq)
	{
		return $this->config->shipserv->services->{'mtml-rest'}->url . '/docs/' . $rfq->getSender() . '/outbox';
	}
}
