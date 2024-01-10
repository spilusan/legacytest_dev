<?php

/**
 * Search domain object
 *
 * @author Anthony Powell <apowell@shipserv.com>
 */
class Myshipserv_Search_Search
{
	const START_DEFAULT = 0;
	const ROWS_DEFAULT = 15;
	const CATEGORY_ROWS_DEFAULT = 500;
	const MEMBERSHIP_ROWS_DEFAULT = 500;
	const CERTIFICATION_ROWS_DEFAULT = 500;
	const BRAND_ROWS_DEFAULT = 500;

	private $what = '';
	private $text = '';
	private $where = '';
	private $type = 'product';
	private $country = '';
	private $port = '';
	private $start = self::START_DEFAULT;
	private $rows = self::ROWS_DEFAULT;
	private $categoryRows = self::CATEGORY_ROWS_DEFAULT;
	private $membershipRows = self::MEMBERSHIP_ROWS_DEFAULT;
	private $certificationRows = self::CERTIFICATION_ROWS_DEFAULT;
	private $brandRows = self::BRAND_ROWS_DEFAULT;
	private $filters = array();
	private $orFilters = array();
	private $facets = array();

	private $refinedBrandId = null;

	public function __get ($name)
	{
		$arr = get_object_vars($this);
		if (array_key_exists($name, $arr)) return $this->$name;
		throw new Exception("Undefined attribute: $name");
	}

	/**
	 * @param string $what Search string
	 */
	public function setWhat ($what)
	{
		$this->what = trim($what);
	}

	/**
	 * @param string $text Raw search location
	 */
	public function setText ($text)
	{
		$this->text = trim($text);
	}

	/**
	 * @param string $where Processed search location (country or port code)
	 */
	public function setWhere ($where)
	{
		$this->where = trim($where);
	}

	/**
	 * @param string $type Search type
	 */
	public function setType ($type)
	{
		$type = trim($type);
		if ($type == '') $type = 'product';
		$this->type = $type;
	}

	/**
	 *
	 * @param integer $refinedBrandId
	 */
	public function setRefinedBrandId ($refinedBrandId)
	{
		$this->refinedBrandId = $refinedBrandId;
	}


	/**
	 * @param string $country Country code
	 */
	public function setCountry ($country)
	{
		$this->country = trim($country);
	}

	/**
	 * @param string $port Port code
	 */
	public function setPort ($port)
	{
		$this->port = $port;
	}

	/**
	 * @param int $start Pagination offset (starts from 0)
	 */
	public function setStart ($start)
	{
		if (is_null($start))
		{
			$this->start = self::START_DEFAULT;
		}
		else
		{
			$start = (int) $start;
			if ($start < 0) $start = 0;
			$this->start = $start;
		}
	}

	/**
	 * @param int $rows Number of rows to return
	 */
	public function setRows ($rows)
	{
		if (is_null($rows))
		{
			$this->rows = self::ROWS_DEFAULT;
		}
		else
		{
			$rows = (int) $rows;
			if ($rows < 1) $rows = self::ROWS_DEFAULT;
			$this->rows = $rows;
		}
	}

	/**
	 * @param int $categoryRows Number of category rows to return
	 */
	public function setCategoryRows ($categoryRows)
	{
		if (is_null($categoryRows))
		{
			$this->categoryRows = self::CATEGORY_ROWS_DEFAULT;
		}
		else
		{
			$categoryRows = (int) $categoryRows;
			if ($categoryRows < 0) $categoryRows = 0;
			$this->categoryRows = $categoryRows;
		}
	}

	/**
	 * @param int $membershipRows Number of membership rows to return
	 */
	public function setMembershipRows ($membershipRows)
	{
		if (is_null($membershipRows))
		{
			$this->membershipRows = self::MEMBERSHIP_ROWS_DEFAULT;
		}
		else
		{
			$membershipRows = (int) $membershipRows;
			if ($membershipRows < 0) $membershipRows = 0;
			$this->membershipRows = $membershipRows;
		}
	}

	/**
	 * @param int $certificationRows Number of certification rows to return
	 */
	public function setCertificationRows ($certificationRows)
	{
		if (is_null($certificationRows))
		{
			$this->certificationRows = self::CERTIFICATION_ROWS_DEFAULT;
		}
		else
		{
			$certificationRows = (int) $certificationRows;
			if ($certificationRows < 0) $certificationRows = 0;
			$this->certificationRows = $certificationRows;
		}
	}

	/**
	 * @param string $name Filter name
	 * @param int $value Filter value
	 */
	public function addFilter ($name, $value)
	{
		$this->filters[$name][$value] = true;
	}

	/**
	 * @param string $name Filter name
	 * @param int $value Filter value
	 */
	public function addOrFilter ($name, $value)
	{
		$this->orFilters[$name][$value] = true;
	}

	/**
	 * @param string $name Facet name
	 * @param int $value Facet value
	 */
	public function addFacet ($name, $value)
	{
		$this->facets[$name][$value] = true;
	}

	/**
	 *
	 * @param string $name Filter name
	 * @param int $value Filter value
	 */
	public function removeFilter ($name,$value)
	{
		if (isset($this->filters[$name][$value]))
		{
			unset ($this->filters[$name][$value]);
		}
	}

	/**
	 *
	 * @param string $name Filter name
	 * @param int $value Filter value
	 */
	public function removeOrFilter ($name,$value)
	{
		if (isset($this->orFilters[$name][$value]))
		{
			unset ($this->orFilters[$name][$value]);
		}
	}

	/**
	 *
	 * @param string $name Filter name
	 * @param int $value Filter value
	 */
	public function removeFacet ($name,$value)
	{
		if (isset($this->facet[$name][$value]))
		{
			unset ($this->facet[$name][$value]);
		}
	}

	/**
	 * Returns associative, ordered array of attributes prepared
	 * for underlying service method call.
	 *
	 * Filters are returned as array of pairs:
	 * 		['filters'][<index>] =
	 * 			array('field' => <field>, 'value' => <value>)
	 *
	 * @return array
	 */
	public function exportSearchServiceParamArr ()
	{
		//we will add facets for authorised brand if only one brand supplied as filter

		if (lg_count($this->filters["brandId"])==1)
		{
			foreach ($this->filters["brandId"] as $value => $rubbish)
			{
				if ((intval($this->refinedBrandId)==0 or intval($this->refinedBrandId)==$value))
				{
					$this->addFacet("AABrandId", $value);
					$this->addFacet("AIRBrandId", $value);
					$this->addFacet("OEMBrandId", $value);
				}
			}
		}		
		if (lg_count($this->filters["brandId"])>1)
		{
			unset($this->filters["AABrandId"]);
			unset($this->filters["AIRBrandId"]);
			unset($this->filters["OEMBrandId"]);
		}

		$searchServiceParamArr = array (
			'searchWhat' => $this->what,
			'searchWhere' => ($this->where!="")?$this->where:$this->text,
			'searchType' => $this->type,
			'searchCountry' => $this->country,
			'searchPort' => $this->port,
			'searchStart' => $this->start,
			'searchRows' => $this->rows,
			'categoryRows' => $this->categoryRows,
			'membershipRows' => $this->membershipRows,
			'certificationRows' => $this->certificationRows,
			'brandRows' => $this->brandRows,
			'filters' => $this->makeFilterPairArr(),
			'orFilters' => $this->makeOrFilterPairArr(),
			'facets' => $this->makeFacetsPairArr()
		);

		return $searchServiceParamArr;
	}

	/**
	 * Returns associative array of all object attributes.
	 *
	 * Filters are returned in easy-to-search format:
	 * 		['filters'][<name>][<value>] = true
	 *
	 * @return array
	 */
	public function exportArr ()
	{
		return array (
			'searchWhat' => $this->what,
			'searchText' => $this->text,
			'searchWhere' => $this->where,
			'searchType' => $this->type,
			'searchCountry' => $this->country,
			'searchPort' => $this->port,
			'searchStart' => $this->start,
			'searchRows' => $this->rows,
			'categoryRows' => $this->categoryRows,
			'membershipRows' => $this->membershipRows,
			'certificationRows' => $this->certificationRows,
			'filters' => $this->filters,
			'orFilters' => $this->orFilters,
			'facets' => $this->facets

		);
	}

	/**
	 * Helper method: transforms internal filter representation into
	 * representation as array of pairs.
	 *
	 * @return array
	 */
	private function makeFilterPairArr ()
	{
		$res = array();
		foreach ($this->filters as $name => $valArr)
		{
			foreach ($valArr as $value => $rubbish)
			{
				// Cast to string is important: prevents Java service throwing type exception
				$res[] = array('field' => $name, 'value' => (string) $value);
			}
		}
		return $res;
	}

	/**
	 * Helper method: transforms internal filter representation into
	 * representation as array of pairs.
	 *
	 * @return array
	 */
	private function makeOrFilterPairArr ()
	{
		$res = array();
		foreach ($this->orFilters as $name => $valArr)
		{
			foreach ($valArr as $value => $rubbish)
			{
				// Cast to string is important: prevents Java service throwing type exception
				$res[] = array('field' => $name, 'value' => (string) $value);
			}
		}
		return $res;
	}

	/**
	 * Helper method: transforms internal filter representation into
	 * representation as array of pairs.
	 *
	 * @return array
	 */
	private function makeFacetsPairArr ()
	{
		$res = array();
		foreach ($this->facets as $name => $valArr)
		{
			foreach ($valArr as $value => $rubbish)
			{
				// Cast to string is important: prevents Java service throwing type exception
				$res[] = array('field' => $name, 'value' => (string) $value);
			}
		}
		return $res;
	}
}
