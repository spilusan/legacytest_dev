<?php

/**
 * Class for reading the various reference data from Oracle
 *
 * @package Shipserv
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, Dave Starling
 */
class Shipserv_Oracle_Reference extends Shipserv_Oracle
{

	CONST
		  REF_SHIPTYPE = 'SHIPTYPE'
		, REF_QUOTEQUALITY = 'QUOTEQUALITY'
		;

	public function __construct (&$db)
	{
		parent::__construct($db);
	}
	
	/**
	 * Fetches all company types and places them in an associative array
	 *
	 * @access public
	 * @param boolean $cache Fetch from the cache if possible, and cache the
	 * 						 result if cache is invalid
	 * @param int $cacheTTL If using the cache, what TTL should be used?
	 * @return array
	 */
	public function fetchCompanyTypes ($useCache = true, $cacheTTL = 86400)
	{
		$sql = 'SELECT PCT_ID, PCT_COMPANY_TYPE';
		$sql.= '  FROM PAGES_USER_COMPANY_TYPE';
		
		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'COMPANYTYPES_'.
			       $this->memcacheConfig->client->keySuffix;
			
			$result = $this->fetchCachedQuery($sql, array(), $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql);
		}
		
		return $result;
	}
	
	/**
	 * Fetches all job functions and places them in an associative array
	 * 
	 * @access public
	 * @param boolean $cache Fetch from the cache if possible, and cache the
	 * 						 result if cache is invalid
	 * @param int $cacheTTL If using the cache, what TTL should be used?
	 * @return array
	 */
	public function fetchJobFunctions ($useCache = true, $cacheTTL = 86400)
	{
		$sql = 'SELECT PJF_ID, PJF_JOB_FUNCTION';
		$sql.= '  FROM PAGES_USER_JOB_FUNCTION';
		
		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'JOBFUNCTIONS_'.
			       $this->memcacheConfig->client->keySuffix;
			
			$result = $this->fetchCachedQuery($sql, array(), $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql);
		}
		
		return $result;
	}

	public function fetchJobFunctionName ($jobFunctionId, $useCache = true, $cacheTTL = 86400)
	{
		$sql = 'SELECT PJF_JOB_FUNCTION';
		$sql.= '  FROM PAGES_USER_JOB_FUNCTION';
		$sql.= '  WHERE PJF_ID = :jobFunctionId';

		$sqlData = array('jobFunctionId' => $jobFunctionId);

		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'JOBFUNCTION_'.$jobFunctionId.'_'.
			       $this->memcacheConfig->client->keySuffix;

			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql);
		}

		return $result[0]["PJF_JOB_FUNCTION"];
	}
	
	public function fetchBadWords ($useCache = true, $cacheTTL = 86400)
	{
		$sql = 'SELECT psw_word';
		$sql.= '  FROM pages_stop_words';
		
		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'BADWORDS_'.
			       $this->memcacheConfig->client->keySuffix;
			
			$result = $this->fetchCachedQuery($sql, array(), $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql);
		}
		
		$badWords = array();
		foreach ($result as $row)
		{
			$badWords[] = $row['PSW_WORD'];
		}
		
		return $badWords;
	}

	/**
	 * Fixed by Yuriy Akopov on 2015-10-16
	 *
	 * @param	int	$refKey
	 * @param	bool|true $useCache
	 * @param	int $cacheTTL
	 *
	 * @return	array
	 */
	public function fetchQuoteQality($refKey, $useCache = true, $cacheTTL = 86400) {
		$sql = "SELECT
					ref_value
			  	FROM
			  		reference 
			  	WHERE
			  		ref_type = :refType
			  		AND ref_is_active = 1
			  		AND ref_id = :refId
			  	";
		$params = array(
			'refType' => self::REF_QUOTEQUALITY,
			'refId' => (int)$refKey,
		);

		if ($useCache) {
			$key = implode('_', array(
				$this->memcacheConfig->client->keyPrefix,
				self::REF_QUOTEQUALITY,
				$refKey,
				$this->memcacheConfig->client->keySuffix
			));

			$result = $this->fetchCachedQuery($sql, $params, $key, $cacheTTL);
		} else {
			$result = $this->db->fetchAll($sql, $params);
		}

		return $result;
	}
	
	/**
	 * Get the list of quote qualities
	 * @return array
	 */
	public function fetchQuoteQualities($useCache = true, $cacheTTL = 86400)
	{
		$sql = "
			SELECT
				ref_id id,
				ref_value quality
			FROM
				reference
			WHERE
				ref_type = :refType
				and ref_is_active = 1
			ORDER BY
				ref_value";
		
		$params = array(
				'refType' => self::REF_QUOTEQUALITY
		);
		
		if ($useCache) {
			$key = md5(__CLASS__ .'_' . __FUNCTION__ . '_' . $sql);
			return $this->fetchCachedQuery($sql, $params);
		} else {
			return $this->db->fetchAll($sql, $params);
		}
	}

}