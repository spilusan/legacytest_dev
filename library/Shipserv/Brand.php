<?php
class Shipserv_Brand extends Shipserv_Object
{
    const
        TABLE_NAME    = 'BRAND',
        COL_ID        = 'ID',
        COL_NAME      = 'NAME',
        COL_PAGE_NAME = 'BROWSE_PAGE_NAME'
    ;

    const
        SYNONYM_TABLE_NAME = 'BRAND_SYNONYM',
        SYNONYM_COL_ID     = 'BRA_SYN_BRAND_ID',
        SYNONYM_COL_VALUE  = 'BRA_SYN_BRAND_SYNONYM'
    ;

    protected static $synonyms = null;

    /**
     * @var int
     */
    public $id = null;

    /**
     * Legacy name field that was formed depending on whether page name was available
     * @todo: should be replaced every with the function
     *
     * @var string
     */
    public $name = null;

    /**
     * @var string
     */
    public $country = null;

    /**
     * @var string
     */
    protected $shortName = null;

    /**
     * @var string
     */
    protected $pageName = null;

    /**
     * Refactored by Yuriy Akopov on 2014-09-02
     *
     * @param $id
     * @param $name
     * @param null $country
     * @return Shipserv_Brand
     */
    public static function getInstanceByIdNameCountry($id, $name, $country = null) {
		$object = new self;
		$object->id = $id;
		$object->name = $name;
		$object->country = $country;
		
		return $object;
	}
	
	public static function getInstanceByIdCountryCode( $id, $countryCode )
	{
		$brandAdapter 	= new Shipserv_Oracle_Brands(parent::getDb());
		$countryAdapter = new Shipserv_Oracle_Countries($db);
		$brand        	= $brandAdapter->fetchBrand($id);
		$country 		= $countryAdapter->fetchCountryByCode($countryCode);

		$object 				= self::getInstanceById($brand['ID'], ($brand['BROWSE_PAGE_NAME']!="")?$brand['BROWSE_PAGE_NAME']:$brand['NAME']);
		$object->country 		= strtolower($country[0]['CNT_NAME']);
		$object->countryCode 	= $countryCode;

		return $object;
	}

    /**
     * Refactored by Yuriy Akopov on 2014-09-02
     *
     * @param   int $id
     * @return  Shipserv_Brand
     */
    public static function getInstanceById($id) {
        // load brand fields as a simple array
		$brandAdapter 	= new Shipserv_Oracle_Brands(parent::getDb());
		$brand        	= $brandAdapter->fetchBrand($id);

        // form an object
        // legacy name field is formed from display name, if available, or from the short name otherwise
        $legacyName = (strlen($brand[self::COL_PAGE_NAME]) > 0) ? $brand[self::COL_PAGE_NAME] : $brand[self::COL_NAME];
		// $object = self::getInstanceByIdNameCountry($brand[self::COL_ID], $legacyName);

		$object = new self;
		$object->id = $id;
		$object->name = $legacyName;
        $object->shortName = $brand[self::COL_NAME];
        $object->pageName  = $brand[self::COL_PAGE_NAME];

		return $object;
	}

    /**
     * @author  Yuriy Akopov
     * @date    2014-09-02
     * @story   S11292
     *
     * @return string
     */
    public function getName() {
        return $this->shortName;
    }

    /**
     * @author  Yuriy Akopov
     * @date    2014-09-02
     * @story   S11292
     *
     * @return string
     */
    public function getPageName() {
        return $this->pageName;
    }

	public function getUrl( $type = null )
	{
		if( $type == 'browse-by-country' )
		{
			$url = 'https://'. $_SERVER['HTTP_HOST'] . '/supplier/brand/browse-by-country/' . strtolower(preg_replace('/(\W){1,}/', '-', $this->name)) . '/id/' . $this->id;
		}
		else if( $type == 'brand-without-country' )
		{
			$url = 'https://'. $_SERVER['HTTP_HOST'] .'/brand/' . strtolower(preg_replace('/(\W){1,}/', '-', $this->name)) . '/' . $this->id;
		}
		else
		{
			if( $this->countryCode != "" )
			{
				$url = 'https://'. $_SERVER['HTTP_HOST'] .'/brand/' . strtolower(preg_replace('/(\W){1,}/', '-', $this->name)) . '/' . ( ($this->country != null)?strtolower(preg_replace('/(\W){1,}/', '-', $this->country)) . '/' . $this->countryCode . "/":''  ) . $this->id;
			}
			else
			{
				$url = 'https://'. $_SERVER['HTTP_HOST'] .'/brand/' . strtolower(preg_replace('/(\W){1,}/', '-', $this->name)) . '/' . ( ($this->country != null)?strtolower(preg_replace('/(\W){1,}/', '-', $this->country)) . '/':''  ) . $this->id;
			}
		}
		return $url;
	}

	public function getUrlForProduct($id, $name)
	{
		return 'https://'. $_SERVER['HTTP_HOST'] .'/product/' . strtolower(preg_replace('/(\W){1,}/', '-', $name)) . '/' . $id;
	}

    /**
     * Loads from the database and organised a list of brand synonyms
     *
     * @author  Yuriy Akopov
     * @date    2014-07-23
     * @story   S10773
     *
     * @param   int|null    $brandId
     * @param   bool        $noMatchStopWords
     *
     * @return array
     */
    public static function getAllSynonyms($brandId = null, $noMatchStopWords = true) {
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
                        array('b' => self::TABLE_NAME),
                        'b.' . self::COL_ID . ' = syn.' . self::SYNONYM_COL_ID,
                        array()
                    )
                    ->order('syn.' . self::SYNONYM_COL_ID)
                    ->order('syn.' . self::SYNONYM_COL_VALUE);

                if ($noMatchStopWords) {
                    $select
                        ->joinLeft(
                            array('msw' => 'match_stopwords'),
                            'LOWER(syn.' . self::SYNONYM_COL_VALUE . ') = LOWER(msw.msw_word)',
                            array()
                        )
                        ->where('msw.msw_word IS NULL');
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

        if (is_null($brandId)) {
            return self::$synonyms;
        }

        if (!array_key_exists($brandId, self::$synonyms)) {
            return array();
        }

        return self::$synonyms[$brandId];
    }

    /**
     * @author  Yuriy Akopov
     * @date    2014-07-23
     * @story   S7903
     *
     * @param   bool    $noMatchStopWords
     *
     * @return  array
     */
    public function getSynonyms($noMatchStopWords = true) {
        self::getAllSynonyms($this->id, $noMatchStopWords);
    }
}

;