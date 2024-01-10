<?php
/**
 * Adapter class to do search on SOLR
 */
class Shipserv_Adapters_SolrSearch
{	
	/**
	 * Permitted value is between 0 and 1, with a value closer to 1 only 
	 * terms with a higher similarity will be matched
	 * @var double
	 */
	const FUZZY_MATCH_SENSITIVITY = 0.8;
	
	/**
	 * Flag if to tell SOLR that this is a fuzzy match mode
	 * @var boolean
	 */
	public $fuzzyMatchMode = false;
	
	/**
	 * All available URL on SOLR server
	 * @var unknown_type
	 */
	public $availableCallUrl = array( 	"category" => "preProcess",
										"brand" => "preProcess",
										"country" => "preProcess",
										"port" => "preProcess",
										"supplier" => "supplier",
										"company" => "companyName",
										"SPB" => "companyName",
										"BYO" => "companyName" );
	
	/**
	 * Store type of object
	 * @var string
	 */
	public $type;
	
	public function __construct ()
	{
		// Do nothing
	}
	
	public function makeFuzzyMatchSearch()
	{
		$this->fuzzyMatchMode = true;	
	}
	
	public function makeHttpClient ()
	{
		$config  = Zend_Registry::get('config');
		$x = $this->availableCallUrl[$this->type];
		$baseUrl = $config->shipserv->services->solr->$x->url;
		$selectUrl = $baseUrl . "/select";

		$client = new Zend_Http_Client();
		$client->setUri($selectUrl);
		$client->setConfig(array(
			'maxredirects' => 0,
			'timeout'      => 5));
		
		return $client;
	}
	
	public function tokenize ($str)
	{
		// Turn non-alphanum characters into space
		$regex1 = '/[^A-Za-z0-9]/';
		$normed1 = preg_replace($regex1, ' ', $str);
		
		// Turn consecutive spaces into 1 space
		$regex2 = '/ +/';
		$normed2 = preg_replace($regex2, ' ', $normed1);
		
		// Explode by space
		$toks = explode(' ', $normed2);
		
		foreach ($toks as $i => $v)
		{
			$toks[$i] = strtolower($v);
		}
		
		return $toks;
	}
	
	public function makeSolrQuery ($searchStr, $type)
	{
		
		$toks = $this->tokenize($searchStr);
		foreach ($toks as $i => $t)
		{
			if( $this->fuzzyMatchMode === true )
			{
				$toks[$i] = "name: ($t OR $t* OR $t~" . self::FUZZY_MATCH_SENSITIVITY . ")";
			}
			else 
			{
				$toks[$i] = "name: ($t OR $t*)";
			}
		}
		
		$returnString = join(' AND ', $toks);

		// Validate company type
		if (array_key_exists($type, $this->availableCallUrl))
		{
			// supplier
			if( $type == "SPB" )
				$returnString .= " AND type:S";
			// buyer
			else if( $type == "BYO" )
				$returnString .= " AND type:B";
			// search for supplier 
			else if( $type == "supplier" || $type == "company" )
				$returnString .= "";
			// other
			else
				$returnString .= " AND type:$type";
		}

		return $returnString;
	}
	
	public function makeSolrProximityQuery($searchStr, $distance = 1){
		//return 'description:"' . $searchStr . '" orders:"' . $searchStr . '"~' . $distance . ' catalog:"' . $searchStr . '"~' . $distance; 
		return 'name:"' . str_replace('"', '\"', $searchStr)  . '" description:"' . str_replace('"', '\"', $searchStr)  . '" orders:"' . str_replace('"', '\"', $searchStr) . '" catalog:"' . str_replace('"', '\"', $searchStr) . '"'; 
	}
	
	
	public function doProximitySearch ($type, $searchTerm, $distance = 1)
	{
		$client = $this->makeHttpClient();
		
		// Build search string
		if($distance > 0){
			$qStr = $this->makeSolrProximityQuery($searchTerm, $distance);
		}else{
			$qStr = $this->makeSolrQuery($searchTerm, $type);
		}
		
		// Specify request parameters
		$client->setParameterGet(
			array(
				'q'		=> $qStr,				
				'start'	=> (int) 0,
				'rows'	=> (int) 100,
				'fl'	=> 'id,tnid,score,name,country,ispremiumlisting,continent,type',
				'sort'	=> 'score desc',
			)
		);
		//echo '..'.$qStr.'...';
		// Make request
		$client->setMethod(Zend_Http_Client::GET);
		$response = $client->request();
		$lastResponse = $client->getLastRequest();
		
		
		// Catch errors
		if ($response->getStatus() != 200)
		{
			throw new Exception("Failed to contact Solr server");
		}
		
		// Buyild array from result
		$res = array();
		$xml = new SimpleXMLElement($response->getBody());
		foreach ($xml->result as $nResult)
		{
			if ($nResult['name'] == 'response')
			{
				foreach ($nResult as $nResponse)
				{
					if ($nResponse->getName() == 'doc')
					{
						$resRow = array();
						foreach ($nResponse as $nDocEl)
						{
							$resRow[(string) $nDocEl['name']] = (string) $nDocEl;
						}
						$res[$resRow['id']] = $resRow;
					}
				}
			}
		}
		
		

		
		return $res;
	}
	
	
	
	public function doSearch ($type, $searchTerm, $distance = 0)
	{
		
		$client = $this->makeHttpClient();
		
		// Build search string
		if($distance > 0){
			$qStr = $this->makeSolrProximityQuery($searchTerm, $distance);
		}else{
			$qStr = $this->makeSolrQuery($searchTerm, $type);
		}
		
		// Specify request parameters
		$client->setParameterGet(
			array(
				'q'		=> $qStr,				
				'start'	=> 0,
				'rows'	=> 50,
				'fl'	=> 'id,tnid,score,name,type',
				'hl'	=> 'on',
				'hl.fl'	=> 'name',
				'sort'	=> 'score desc',
			)
		);
		//echo '..'.$qStr.'...';
		// Make request
		$client->setMethod(Zend_Http_Client::GET);
		$response = $client->request();
		//$lastResponse = $client->getLastRequest();
		
		// Catch errors
		if ($response->getStatus() != 200)
		{
			throw new Exception("Failed to contact Solr server");
		}
		
		// Buyild array from result
		$res = array();
		$xml = new SimpleXMLElement($response->getBody());
		foreach ($xml->result as $nResult)
		{
			if ($nResult['name'] == 'response')
			{
				foreach ($nResult as $nResponse)
				{
					if ($nResponse->getName() == 'doc')
					{
						$resRow = array();
						foreach ($nResponse as $nDocEl)
						{
							$resRow[(string) $nDocEl['name']] = (string) $nDocEl;
						}
						$res[$resRow['id']] = $resRow;
					}
				}
			}
		}
		
		if( $this->fuzzyMatchMode === false )
		{
			// Loop over results, highlighting search sub-string within haystack
			// Uses a custom highlighting method
			foreach ($res as $k => $r)
			{
				$nMatched = 0;
				$hName = $this->highlight($r['name'], $this->tokenize($searchTerm), $nMatched);
				if ($nMatched > 0)
				{
					// Add highlighted name to search return
					$res[$k]['hName'] = $hName;
				}
				else
				{
					// If sub-string is not present, remove this row
					unset($res[$k]);
				}
			}
		}
		// Loop over Solr's own highlighting return and use it where there is
		// no custom highlighting
		//foreach ($xml->lst as $nLst)
		//{
		//	if ($nLst['name'] == 'highlighting')
		//	{
		//		foreach ($nLst as $nLstHl)
		//		{
		//			foreach ($nLstHl as $nLstHlEl)
		//			{
		//				if ($nLstHlEl->getName() == 'arr' && $nLstHlEl['name'] == 'name')
		//				{
		//					foreach ($nLstHlEl as $nLstHlElArrEl)
		//					{
		//						if (array_key_exists($nLstHl['name'], $res) && !array_key_exists('hName', $res[(string) $nLstHl['name']]))
		//						{
		//							$res[(string) $nLstHl['name']]['hName'] = (string) $nLstHlElArrEl;
		//						}
		//					}
		//				}
		//			}
		//		}
		//	}
		//}
		
		return $res;
	}
	
	public function highlight ($haystack, array $needleArr, &$nMatched)
	{
		$nMatched = 0;
		
		$lNeedleArr = $lenNeedleArr = array();
		foreach ($needleArr as $i => $n)
		{
			$lNeedleArr[$i] = strtolower($n);
			$lenNeedleArr[$i] = strlen($n);
		}
		
		$lHaystack = strtolower($haystack);
		$lenHaystack = strlen($haystack);
		$spOffset = 0;
		$strOut = '';
		
		while (true)
		{
			if ($spOffset > $lenHaystack)
			{
				break;
			}
			
			$sp = null;
			$needleIdx = null;
			foreach ($lNeedleArr as $ni => $lNeedle)
			{
				$spCandidate = strpos($lHaystack, $lNeedle, $spOffset);
				if ($spCandidate !== false)
				{
					if ($sp === null || $spCandidate < $sp)
					{
						$sp = $spCandidate;
						$needleIdx = $ni;
					}
				}
			}
			
			if ($sp === null)
			{
				break;
			}
			
			$strOut .= substr($haystack, $spOffset, $sp - $spOffset);
			$strOut .= "<em>";
			$strOut .= substr($haystack, $sp, $lenNeedleArr[$needleIdx]);
			$strOut .= "</em>";
			
			$nMatched++;
			
			$spOffset = $sp + $lenNeedleArr[$needleIdx];
		}
		
		$strOut .= substr($haystack, $spOffset);
		
		return $strOut;
	}
	
	/**
	 * Fetch company IDs for company names matching the given name fragment.
	 * For suppliers, an array of branch codes are returned. For buyers, it's
	 * an array of org codes. Return IDs are canonical.
	 * 
	 * @param string $companyType [SPB|BYO]
	 * @return array
	 */
	public function search ($type, $nameFragment, $distance = 0)
	{
		$this->type = $type;
		if($distance > 0){
			$sRes = $this->doProximitySearch($type, $nameFragment, $distance);
		}else{
			$sRes = $this->doSearch($type, $nameFragment, $distance);
		}
		return $sRes;
	}
}
