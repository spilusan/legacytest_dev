<?php
// comment by Yuriy Akopov, 2013-09-11
// @todo: this class should be refactored or turned into an ActiveRecord
// at the moment it's a mixture of different approaches (legacy methods and ones added by Yuriy Akopov)
class Shipserv_Category extends Shipserv_Object
{
    const
        TABLE_NAME    = 'PRODUCT_CATEGORY',

        COL_ID        = 'ID',
        COL_PARENT_ID = 'PARENT_ID'
    ;

    const
        SYNONYM_TABLE_NAME  = 'CATEGORY_SYNONYM',
        SYNONYM_COL_ID      = 'CAT_SYN_CATEGORY_ID',
        SYNONYM_COL_VALUE   = 'CAT_SYN_CATEGORY_SYNONYM'
    ;

    /**
     * @var array
     */
    protected static $synonyms = null;

	public $id = null;
	public $name = null;
	public $country = null;
	
	public static function getInstanceByParams($params)
	{
		if( $params['categoryId'] == "" ) throw new Exception("Invalid category id specified.");
		
		$categoryAdapter = new Shipserv_Oracle_Categories();
		$categoryInfo = $categoryAdapter->fetchCategory($params['categoryId']);
		
		if( $params['portName'] != "" )
		{
			$countryAdapter = new Shipserv_Oracle_Countries();	
			$tmp = explode("-", $params['searchWhere']);
			$countryInfo = $countryAdapter->fetchCountryByCode($tmp[0]);
			$that = self::getInstanceByCountryPort($params['categoryId'], $categoryInfo['NAME'], $countryInfo[0]['CNT_NAME'], $params['portName'], $tmp[0], $params['searchWhere'] );
		}
		else if( $params['portName'] == "" )
		{
			$countryAdapter = new Shipserv_Oracle_Countries();
			$countryInfo = $countryAdapter->fetchCountryByCode($params['searchWhere']);
			$that = self::getInstanceByIdNameCountry($params['categoryId'], $categoryInfo['NAME'], $countryInfo[0]['CNT_NAME'], $countryInfo[0]['CNT_COUNTRY_CODE'] );
		}	
		else
		{
			$that = self::getInstanceById($params['categoryId']);
				
		}
		return $that;
	}
	
	public static function getInstanceByCountryPort($id, $name, $country = null, $port = null, $countryCode = null, $portCode = null)
	{
		$object = new self;

		$object->id = $id;
		$object->name = $name;
		$object->country = $country;
		$object->countryCode = $countryCode;
		$object->port = $port;
		$object->portCode = $portCode;
		
		return $object;		
	}
	
	public static function getInstanceByIdNameCountry($id, $name, $country = null, $countryCode = null)
	{
		$object = new self;
		$object->id = $id;
		$object->name = $name;
		$object->country = $country;
		$object->countryCode = $countryCode;
		
		return $object;
	}

	public static function getInstanceById( $id )
	{
		$categoryAdapter = new Shipserv_Oracle_Categories(parent::getDb());
		$category = $categoryAdapter->fetchCategory($id);
		return self::getInstanceByIdNameCountry($category['ID'], (($category['DISPLAYNAME']!="")?$category['DISPLAYNAME']:$category['NAME']));
	}

	public static function getInstanceByIdCountryCode( $id, $countryCode )
	{
		$categoryAdapter 		= new Shipserv_Oracle_Categories(parent::getDb());
		$countryAdapter 		= new Shipserv_Oracle_Countries($db);

		$category 				= $categoryAdapter->fetchCategory($id);
		$country 				= $countryAdapter->fetchCountryByCode($countryCode);

		$object 				= self::getInstanceByIdNameCountry($category['ID'], (($category['DISPLAYNAME']!="")?$category['DISPLAYNAME']:$category['NAME']));
		$object->country 		= strtolower($country[0]['CNT_NAME']);
		$object->countryCode 	= $countryCode;

		return $object;
	}

	public function getUrl( $type = null )
	{
		if( $type == 'browse-by-country' )
		{
			$url = 'https://'. $_SERVER['HTTP_HOST'] .'/supplier/category/browse-by-country/' . strtolower(preg_replace('/(\W){1,}/', '-', $this->name)) . '/' . ( ($this->country != null)?strtolower(preg_replace('/(\W){1,}/', '-', $this->country)) . '/':''  ) . 'id/' . $this->id;
		}
		else if( $type == 'browse-by-port-list' )
		{
			$url = 'https://'. $_SERVER['HTTP_HOST'] .'/supplier/category/browse-by-port/1/cntcode/' . $this->countryCode . '/id/' . $this->id;
		}
		else if( $type == 'category-by-country')
		{
			//category/agency-services/algeria/DZ/53/
			$part[] = 'https://'. $_SERVER['HTTP_HOST'];
			$part[] = 'category';
			$part[] = strtolower(preg_replace('/(\W){1,}/', '-', $this->name));
			$part[] = strtolower(preg_replace('/(\W){1,}/', '-', $this->country));
			$part[] = (preg_replace('/(\W){1,}/', '-', $this->countryCode));
			$part[] = $this->id;
			return implode("/", $part);
		}
		else if( $type == 'category-without-country' )
		{
			$url = 'https://'. $_SERVER['HTTP_HOST'] .
			'/category/' . 
			strtolower(preg_replace('/(\W){1,}/', '-', $this->name)) . 
			'/' . $this->id;
			
			$part[] = 'https://'. $_SERVER['HTTP_HOST'];
			$part[] = 'category';
			$part[] = strtolower(preg_replace('/(\W){1,}/', '-', $this->name));
			$part[] = $this->id;
			return implode("/", $part);
				
		}
		else if( ($type==null && $this->country != "" && $this->portCode != "" ) || $type == "browse-by-port")
		{
			$part[] = 'https://'. $_SERVER['HTTP_HOST'];
			$part[] = 'category';
			$part[] = strtolower(preg_replace('/(\W){1,}/', '-', $this->name));
			$part[] = strtolower(preg_replace('/(\W){1,}/', '-', $this->country));
			$part[] = strtolower(preg_replace('/(\W){1,}/', '-', $this->port));
			$part[] = (preg_replace('/(\W){1,}/', '-', $this->portCode));
			$part[] = $this->id;
			return implode("/", $part);
				
		}
		else
		{
			if( $this->countryCode != "" )
			{			
				
				$part[] = 'https://'. $_SERVER['HTTP_HOST'];
				$part[] = 'category';
				$part[] = strtolower(preg_replace('/(\W){1,}/', '-', $this->name));
				$part[] = strtolower(preg_replace('/(\W){1,}/', '-', $this->country));
				$part[] = $this->countryCode;
				$part[] = $this->id;
				return implode("/", $part);
			}
			else
			{
				$part[] = 'https://'. $_SERVER['HTTP_HOST'];
				$part[] = 'category';
				$part[] = strtolower(preg_replace('/(\W){1,}/', '-', $this->name));
				$part[] = $this->id;
				return implode("/", $part);
			}
		}

		return $url;
	}

    /**
     * @author  Yuriy Akopov
     * @date    2013-09-10
     * @story   S7903
     *
     * @return  Shipserv_Category
     * @throws  Exception
     */
    public function getParent() {
        $categoryAdapter = new Shipserv_Oracle_Categories(self::getDb());
        $category = $categoryAdapter->fetchCategory($this->id);

        if (empty($category)) {
            throw new Exception('Unable to get parent category - category not found or not initialised properly');
        }

        if (strlen($category['PARENT_ID']) === 0) {
            throw new Exception('No parent found - top level category');
        }

        $parent = $categoryAdapter->fetchCategory($category['PARENT_ID']);
        if (empty($parent)) {
            throw new Exception('Unable to get parent category - invalid parent category ID');
        }

        return self::getInstanceByIdNameCountry($parent['ID'], $parent['NAME']);
    }

    /**
     * Loads from the database and organised a list of category synonyms
     *
     * @author  Yuriy Akopov
     * @date    2014-07-22
     * @story   S10773
     *
     * @param   int|null    $id
     * @param   bool        $noMatchStopwords
     *
     * @return array
     */
    public static function getAllSynonyms($id = null, $noMatchStopWords = true) {
        if (is_null(self::$synonyms)) {
            $db = Shipserv_Helper_Database::getDb();
            $cache = new Shipserv_Match_MatchCache($db);

            $synonyms = $cache->memcacheGet(__CLASS__, __FUNCTION__, 'allSynonyms');
            if ($synonyms !== false) {
                self::$synonyms = $synonyms;
            } else {
                $select = new Zend_Db_Select($db);
                $select
                    ->from(
                        array('syn' => self::SYNONYM_TABLE_NAME),
                        array(
                            'syn.' . self::SYNONYM_COL_ID,
                            'syn.' . self::SYNONYM_COL_VALUE
                        )
                    )
                    ->join(
                        array('pc' => self::TABLE_NAME),
                        'pc.' . self::COL_ID . ' = syn.' . self::SYNONYM_COL_ID,
                        array()
                    )
                    ->order('syn.' . self::SYNONYM_COL_ID)
                    ->order('syn.' . self::SYNONYM_COL_VALUE)
                ;

                if ($noMatchStopWords) {
                    $select
                        ->joinLeft(
                            array('msw' => 'match_stopwords'),
                            'LOWER(syn.' . self::SYNONYM_COL_VALUE . ') = LOWER(msw.msw_word)',
                            array()
                        )
                        ->where('msw.msw_word IS NULL')
                    ;
                }

                $rows = $db->fetchAll($select);

                self::$synonyms = array();
                foreach ($rows as $row) {
                    $id = $row[self::SYNONYM_COL_ID];

                    if (!array_key_exists($id, self::$synonyms)) {
                        self::$synonyms[$id] = array();
                    }

                    self::$synonyms[$id][] = Shipserv_Helper_Pattern::singularise(strtolower(trim($row[self::SYNONYM_COL_VALUE])));
                }

                $cache->memcacheSet(__CLASS__, __FUNCTION__, 'allSynonyms', self::$synonyms);
            }
        }

        if (is_null($id)) {
            return self::$synonyms;
        }

        if (!array_key_exists($id, self::$synonyms)) {
            return array();
        }

        return self::$synonyms[$id];
    }

    /**
     * Reworked on 2014-07-22 to base on synonyms table rather that on category table field
     *
     * @author  Yuriy Akopov
     * @date    2013-09-11
     * @story   S7903
     *
     * @param   bool    $noMatchStopWords
     *
     * @return  array
     */
    public function getSynonyms($noMatchStopWords = true) {
        self::getAllSynonyms($this->id, $noMatchStopWords);
    }

    /**
     * Returns IDs of the parent categories for the given one
     *
     * @author  Yuriy Akopov
     * @date    2013-09-11
     * @story   S7903
     *
     * @param   int     $id
     *
     * @return  array
     */
    public static function getAllParents($id) {
        $db = Shipserv_Helper_Database::getDb();

        $sql = "
            SELECT
                id
            FROM
                " . self::TABLE_NAME . "
            START WITH " . self::COL_ID . " = :parentId
            CONNECT BY Prior " . self::COL_PARENT_ID . " = " . self::COL_ID . "
        ";

        $ids = $db->fetchCol($sql, array('parentId' => $id));

        return $ids;
    }
}