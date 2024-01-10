<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
class Shipserv_Match_Search extends Shipserv_Adapters_SolrSearch{
    const
        SCORE       = 'score',

        SUPPLIER    = 'supplier',

        SUPPLIER_ID = 'id',
        COUNTRY     = 'country'
    ;

public function doBrandSearch($brand){
                
                $this->type = 'brand';

                $db =  $GLOBALS['application']->getBootstrap()->getResource('db');
                $sql = "Select Id from Brand where name = :brand";
                $params = array('brand' => $brand);
                $result = $db->fetchOne($sql, $params);
                if(!empty($result)){
                    $brandId = $result;
                }else{
                    return false;
                }
                
                unset($db);
                unset($sql);
                unset($params);
                
                
		$client = $this->makeHttpClient();
		
                $qStr = $this->makeSolrQuery($brand, 'brand');
		
                // Specify request parameters
		$client->setParameterGet(
			array(
				'q'		=> $qStr,				
				'start'	=> 0,
				'rows'	=> 50,
				'fl'	=> 'id,tnid,score,name,type',				
				'sort'	=> 'score desc',
                                'supName' => $brand,
                                'brandIdAuthVer' => $brandId
                                
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
    
            
}
