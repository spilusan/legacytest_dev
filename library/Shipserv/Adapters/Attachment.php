<?php

/**
 * Adapter to IXP Attachment Service.
 * todo
 */
class Shipserv_Adapters_Attachment
{
	private $config;
	
	public function __construct ()
	{
		$this->config  = Zend_Registry::get('config');
	}
	
	/**
	 * @return array
	 */
	public function putFile ($filePath, $basename, $createdBy)
	{
		// I tried to do a raw binary POST using cURL, but failed, so using shell for now.
		// NB cURL attempt in usingCurl() below.
		// todo: at some point, re-write without shell
		$quoted = array();
		$quoted['url'] = escapeshellarg($this->makePutFileUrl($basename, $createdBy));
		$quoted['filePath'] = escapeshellarg($filePath);
		$res = shell_exec("curl --data-binary {$quoted['filePath']} {$quoted['url']}");
		
		// Parse response
		// Constructor issues warning & throws exception on parse fail (including empty string)
		$x = new SimpleXMLElement($res);
		if ($x->ok->getName() != 'ok')
		{
			// Return not OK
			throw new Exception($res);
		}
		
		// Success, extract atfId
		return array('atfId' => (string) $x->atfId);
	}
	
	private function makePutFileUrl ($basename, $createdBy)
	{
		return $this->config->shipserv->services->attachment->url . "/putFile?basename=" . urlencode($basename) . "&createdBy=" . urlencode($createdBy);
	}
	
	//function usingCurl ()
	//{
	//	//$fc = file_get_contents($filePath);
	//	//if ($fc === false)
	//	//{
	//	//	throw new Exception("Failed to read file");
	//	//}
	//	
	//	$ch = curl_init();
	//	curl_setopt($ch, CURLOPT_URL, $this->svcUrl . "/putFile?basename=" . urlencode($basename) . "&createdBy=&" . urlencode($createdBy));
	//	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	//	
	//	curl_setopt($ch, CURLOPT_POST, 1);
	//	
	//	
	//	//curl_setopt($ch, CURLOPT_POST, 1);
	//	//curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
	//	//curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/octet-stream', 'Content-Transfer-Encoding: base64'));
	//	//curl_setopt($ch, CURLOPT_POSTFIELDS, base64_encode(file_get_contents($filePath)));
	//	curl_setopt($ch, CURLOPT_POSTFIELDS, base64_encode(file_get_contents($filePath))); 
	//	
	//	$res = curl_exec($ch);
	//	if ($res === false)
	//	{
	//		throw new Exception("File POST failed");
	//	}
	//	var_dump($res);
	//	curl_close($ch);
	//	exit;
	//}
}
