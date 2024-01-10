<?php
/**
 * Class for dealing with IMPA Catalogue from Oracle
 * 
 * @copyright Copyright (c) 2009, ShipServ
 */
class Shipserv_Oracle_ImpaCatalogue extends Shipserv_Oracle
{
    const
        TABLE_NAME = 'PAGES_CATALOGUE_IMPA',

        COL_ID          = 'PCI_PART_NO',
        COL_DESC        = 'PCI_DESCRIPTION',
        COL_UOM         = 'PCI_UOM',
        COL_UOM_MTML    = 'PCI_MTML_UOM',
        COL_EXPLANATION = 'PCI_EXPLANATION',
        COL_IMAGE       = 'PCI_IMAGE_NAME'
    ;

    const
        TOKEN_CATALOGUE_ID = 'impa',

        TOKEN_TABLE_NAME  = 'PAGES_CATALOGUE_PART_TOKEN',
        TOKEN_COL_CAT_ID  = 'PRT_CATALOGUE_ID',
        TOKEN_COL_PART_NO = 'PRT_PART_NO',
        TOKEN_COL_TOKEN   = 'PRT_TOKEN'
    ;

	public function __construct (&$db)
	{
		parent::__construct($db);
	}

	/**
	 * Refactored and updated by Yuriy Akopov on 2016-06-27, DE6606
	 *
	 * @param   string  $searchTerm
	 *
	 * @return  array|bool
	 * @throws  Exception
	 */
	public function search ($searchTerm)
	{
		$select = new Zend_Db_Select($this->db);
		$select
			->from(
				array('pci' => self::TABLE_NAME),
				array(
					'pci.' . self::COL_ID,
					'pci.' . self::COL_DESC
				)
			)
			->where('rownum <= ?', 50)
			->order('pci.' . self::COL_ID . ' asc')
		;

		$searchTerms = explode(' ', trim(str_replace('-', ' ', $searchTerm)));
		$constraints = array();

		foreach ($searchTerms as $term) {
			if (strlen($term) >= 3) {
				$constraintRow = array();

				if (ctype_digit($term)) {
					$constraintRow[] = 'pci.' . self::COL_ID . Shipserv_Helper_Database::escapeLike(
						$select->getAdapter(),
						$term,
						Shipserv_Helper_Database::ESCAPE_LIKE_BOTH
					);
				}

				$constraintRow[] = 'LOWER(pci.' . self::COL_DESC . ') ' . Shipserv_Helper_Database::escapeLike(
					$select->getAdapter(),
					strtolower($term),
					Shipserv_Helper_Database::ESCAPE_LIKE_RIGHT
				);
				$constraintRow[] = 'LOWER(pci.' . self::COL_DESC . ') ' . Shipserv_Helper_Database::escapeLike(
					$select->getAdapter(),
					strtolower(' ' . $term),
					Shipserv_Helper_Database::ESCAPE_LIKE_BOTH
				);
				$constraintRow[] = 'LOWER(pci.' . self::COL_DESC . ') ' . Shipserv_Helper_Database::escapeLike(
					$select->getAdapter(),
					strtolower('-' . $term),
					Shipserv_Helper_Database::ESCAPE_LIKE_BOTH
				);

				if (!empty($constraintRow)) {
					$constraints[] = implode(' OR ', $constraintRow);
				}
			}
		}

		if (empty($constraints)) {
			return false;   // term not qualifies for autocomplete
		}

		$select->where('(' . implode(') AND (', $constraints) . ')');

		$cacheKey = Myshipserv_Config::decorateMemcacheKey(implode(
			'_',
			array(
				__FUNCTION__,
				md5($searchTerm)
			)
		));

		$rows = $this->fetchCachedQuery($select->assemble(), array(), $cacheKey, self::MEMCACHE_TTL);

		return $rows;
	}

    /**
     * Unlike search() function doesn't tranform and analyse the given example returning only items containing the given substring
     *
     * @author  Yuriy Akopov
     * @date    2014-06-18
     * @story   S10527
     *
     * @param   array   $words
     * @param   int     $pageNo         if null, $pageSize is ignored and all the data is returned without pagination
     * @param   int     $pageSize
     * @param   bool    $cached
     * @param   int     $cacheTtl
     *
     * @return  array
     */
    public static function completeDescription(array $words, $pageNo = 1, $pageSize = 10, &$cached = null, $cacheTtl = 3600) {
        $memcache = new Shipserv_Memcache();
        $cache = $memcache::getMemcache();
        $config = Myshipserv_Config::getIni();

        $cacheKey = implode('_', array(
            $config->memcache->client->keyPrefix,
            __METHOD__,
            md5(
                serialize($words) . '_' . $pageNo . '_' . $pageSize
            ),
            $config->memcache->client->keySuffix
        ));

        $cached = false;

        if ($cache instanceof Memcache) {
            if (($products = $cache->get($cacheKey)) !== false) {
                $cached = true;
                return $products;
            }
        }

        $db = Shipserv_Helper_Database::getDb();
        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('pci' => self::TABLE_NAME),
                array(
                    self::COL_ID,
                    self::COL_DESC
                )
            )
            ->order(self::COL_DESC)
        ;

        if (!empty($words)) {
            $where = array();
            foreach ($words as $word) {
                $whereBits = array();

                if (is_numeric($word)) {
                    if (strlen($word) === 6) {
                        $whereBits[] = 'pci.' . self::COL_ID . ' = ' . $word;
                    } else if (strlen($word) < 6) {
                        $likeStatement = Shipserv_Helper_Database::escapeLike($db, $word, Shipserv_Helper_Database::ESCAPE_LIKE_RIGHT);
                        $whereBits[] = 'pci.' . self::COL_ID . ' ' . $likeStatement;
                    }
                }

                $likeStatement = Shipserv_Helper_Database::escapeLike($db, $word, Shipserv_Helper_Database::ESCAPE_LIKE_BOTH);
                $whereBits[] = 'UPPER(pci.' . self::COL_DESC . ') ' . $likeStatement;

                $where[] = '(' . implode(' OR ', $whereBits) . ')';
            }

            $select->where(implode(' AND ', $where));
        }

        if (!is_null($pageNo)) {
            $paginator = Zend_Paginator::factory($select);

            $total = $paginator->getTotalItemCount();
            if ((($pageNo - 1) * $pageSize) > $total) {
                $rows = array();
            } else {
                $paginator
                    ->setItemCountPerPage($pageSize)
                    ->setCurrentPageNumber($pageNo)
                ;

                $rows = $paginator->getCurrentItems();
            }

        } else {
            $rows = $db->fetchAll($select);
            $total = count($rows);
        }

        $response = array();
        if ($total > 0) {
            foreach ($rows as $row) {
                $response[$row[self::COL_ID]] = $row[self::COL_DESC];
            };
        }

        if ($cache instanceof Memcache) {
            if (!$cache->set($cacheKey, $response, null, $cacheTtl)) {
                $fail = true;
            }
        }

        return $response;
    }

    /**
     * Returns product row by given IMPA code
     *
     * @author  Yuriy Akopov
     * @date    2015-06-04
     * @story   S13143
     *
     * @param   int  $impaCode
     * @return  array
     *
     * @throws  Exception
     */
    public static function getRowByCode($impaCode) {
        $db = Shipserv_Helper_Database::getDb();

        $select = new Zend_Db_Select($db);
        $select
            ->from(
                self::TABLE_NAME,
                '*'
            )
            ->where(self::COL_ID . ' = ?', $impaCode)
        ;

        $row = $db->fetchRow($select);
        if (!is_array($row)) {
            throw new Exception("No product found for IMPA code " . $impaCode);
        }

        return $row;
    }

    /**
     * Loads tokens defined for the given IMPA code to use in Solr query when searching for product mentions
     *
     * @author  Yuriy Akopov
     * @date    2015-08-06
     * @story   S14313
     *
     * @param   string|int  $impaCode
     *
     * @return  array
     */
    public static function getTokensByCode($impaCode) {
        $memcache = new Shipserv_Memcache();
        $cache = $memcache::getMemcache();
        $config = Myshipserv_Config::getIni();

        $cacheKey = implode('_', array(
            $config->memcache->client->keyPrefix,
            __METHOD__,
            $impaCode,
            $config->memcache->client->keySuffix
        ));

        $cached = false;
        if ($cache instanceof Memcache) {
            if (($tokens = $cache->get($cacheKey)) !== false) {
                $cached = true;
                return $tokens;
            }
        }

        $db = Shipserv_Helper_Database::getDb();
        $select = new Zend_Db_Select($db);
        $select
            ->from(
                self::TOKEN_TABLE_NAME,
                self::TOKEN_COL_TOKEN
            )
            ->where(self::TOKEN_COL_CAT_ID . ' = ?', self::TOKEN_CATALOGUE_ID)
            ->where(self::TOKEN_COL_PART_NO . ' = ?', $impaCode)
        ;

        $tokens = $db->fetchCol($select);

        if ($cache instanceof Memcache) {
            if (!$cache->set($cacheKey, $tokens, null, 3600)) {
                $fail = true;
            }
        }

        return $tokens;
    }
}
