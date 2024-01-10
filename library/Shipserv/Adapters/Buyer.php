<?php
/**
 * Adapter for Buyer Creation Service
 *
 * @author uladzimirmaroz
 */
class Shipserv_Adapters_Buyer {

    private $buyerParams;
	private $config;
	
	public function  __construct($buyerParams)
	{
		$this->buyerParams = $buyerParams;
		$this->config  = Zend_Registry::get('config');
	}

	public function create()
	{
		$client = new Zend_Http_Client();
		$client->setUri($this->config->shipserv->services->buyercreate->url);
		$client->setConfig(array(
			'maxredirects' => 0,
			'timeout'      => 30
		));
		
		$client->setMethod(Zend_Http_Client::POST);
		$client->setParameterPost($this->buyerParams);

		$response = $client->request();

		$result = simplexml_load_string($response->getBody());
		
		if (isset($result->error)) {
			throw new Exception($result->error->msg);
		}
		else
		{
			return (int)$result->ok->attributes()->orgCode;
		}
		
	}
	
}
?>
