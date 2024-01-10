<?php

/**
 * Functionality for calling Solr company name index.
 * @deprecated use Shipserv_Adapters_SolrSearch class instead
 */
class Shipserv_Adapters_CompanyNameSearch
{	
	private $fuzzyMatchMode = false;
	
	public function __construct ()
	{
		// Do nothing
	}
	
	public function makeFuzzyMatchSearch()
	{
		$this->fuzzyMatchMode = true;	
	}
	
	private function makeHttpClient ()
	{
		$config  = Zend_Registry::get('config');		
		$baseUrl = $config->shipserv->services->solr->companyName->url;
		$selectUrl = $baseUrl . "/select";
		
		$client = new Zend_Http_Client();
		$client->setUri($selectUrl);
		$client->setConfig(array(
			'maxredirects' => 0,
			'timeout'      => 5));
		
		return $client;
	}
	
	private function tokenize ($str)
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
	
	private function makeSolrQuery ($searchStr, $companyType)
	{
		
		$toks = $this->tokenize($searchStr);
		foreach ($toks as $i => $t)
		{
			if( $this->fuzzyMatchMode === true )
			{
				$toks[$i] = "($t OR $t* OR $t~0.6)";
			}
			else 
			{
				$toks[$i] = "($t OR $t*)";
			}
		}
		
		// I don't understand the '*' before the AND: removed
		//return join(' AND ', $toks) . "* AND type:$companyType";

		$returnString = join(' AND ', $toks);

		// Validate company type
		if (in_array($companyType, array('S', 'B')))
		{
			$returnString .= " AND type:$companyType";
		}

		return $returnString;
	}
	
	private function doSearch ($companyType, $searchTerm)
	{
		$client = $this->makeHttpClient();
		
		// Build search string
		$qStr = $this->makeSolrQuery($searchTerm, $companyType);
		
		// Specify request parameters
		$client->setParameterGet(
			array(
				'q'		=> $qStr,
				'fq'	=> '',
				'start'	=> 0,
				'rows'	=> 100,
				'fl'	=> 'id,tnid,name,type,score',
				'hl'	=> 'on',
				'hl.fl'	=> 'name',
				'sort'	=> 'score desc',
			)
		);
		/*
		print_r( array(
				'q'		=> $qStr,
				'fq'	=> '',
				'start'	=> 0,
				'rows'	=> 100,
				'fl'	=> 'id,tnid,name,type,score',
				'hl'	=> 'on',
				'hl.fl'	=> 'name',
				'sort'	=> 'score desc',
			));
		*/
		// Make request
		$client->setMethod(Zend_Http_Client::GET);
		$response = $client->request();
		
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
	
	private function highlight ($haystack, array $needleArr, &$nMatched)
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
	public function search ($companyType, $nameFragment)
	{
		static $cTypeTrans = array('SPB' => 'S', 'BYO' => 'B');
		$sRes = $this->doSearch(@$cTypeTrans[$companyType], $nameFragment);
		return $sRes;
	}
}
