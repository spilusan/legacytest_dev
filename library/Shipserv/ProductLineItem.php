<?php
/**
 * A record representing a connecting between a product (such as IMPA product) and a line item
 *
 * Revisited on 2016-06-28 under S16903
 *
 * @author  Yuriy Akopov
 * @date    2016-01-13
 * @story   S14315
 */
class Shipserv_ProductLineItem extends Shipserv_Object
{
	const
		MEMCACHE_TTL = 3600
	;

	const
		TRANSACTION_TYPE_ORDER = 'ORD',
		TRANSACTION_TYPE_QUOTE = 'QOT',
		TRANSACTION_TYPE_RFQ   = 'RFQ'
	;

	const
		CATALOGUE_ID_IMPA = 'impa'
	;

	const
		TABLE_NAME = 'PAGES_CATALOGUE_PART_LI',

		COL_ID               = 'PCL_ID',
		COL_CATALOGUE_ID     = 'PCL_CATALOGUE_ID',
		COL_PART_NO          = 'PCL_PART_NO',
		COL_TRANSACTION_TYPE = 'PCL_TRANSACTION_TYPE',
		COL_TRANSACTION_DATE = 'PCL_TRANSACTION_DATE',
		COL_TRANSACTION_ID   = 'PCL_TRANSACTION_ID',
		COL_LINE_ITEM_NO     = 'PCL_LINE_ITEM_NO',
		COL_BUYER_ORG_ID     = 'PCL_BYO_ORG_CODE',
		COL_BUYER_BRANCH_ID  = 'PCL_BYB_BRANCH_CODE',
		COL_UNIT             = 'PCL_LI_UNIT',
		COL_QUANTITY         = 'PCL_LI_QUANTITY',
		COL_COST_USD         = 'PCL_LI_TOTAL_COST_USD',
		COL_SUPPLIER_COUNTRY = 'PCL_SPB_COUNTRY'
	;

	/**
	 * Returns units of measurements used in transactions for the given product
	 *
	 * @param   string              $partNo
	 * @param   DateTime|null       $dateFrom
	 * @param   Shipserv_Buyer|null $buyerOrg
	 * @param   string              $catalogueId
	 * @param   string              $transactionType
	 *
	 * @return  array
	 * @throws  Myshipserv_Search_PriceBenchmark_Exception
	 */
	public function getProductUnits($partNo, DateTime $dateFrom = null, Shipserv_Buyer $buyerOrg = null,
                                    $catalogueId = self::CATALOGUE_ID_IMPA,
                                    $transactionType = self::TRANSACTION_TYPE_ORDER
	) {
		$select = new Zend_Db_Select(Shipserv_Helper_Database::getDb());
		$select
			->from(
				array('pcl' => self::TABLE_NAME),
				array(
					'UOM' => 'pcl.' . self::COL_UNIT
				)
			)
			->where('pcl.' . self::COL_CATALOGUE_ID . ' = ?', $catalogueId)
			->where('pcl.' . self::COL_PART_NO . ' = ?', $partNo)
			->where('pcl.' . self::COL_TRANSACTION_TYPE . ' = ?', $transactionType)
			->order('pcl.' . self::COL_UNIT)
			->distinct()
		;
		
		if ($dateFrom) {
			$select
				->where('pcl.' . self::COL_TRANSACTION_DATE . ' >= ' . Shipserv_Helper_Database::getOracleDateExpr($dateFrom, true))
			;
		}

		if ($buyerOrg) {
			// @todo: wouldn't include Pages transactions for the buyer because they're not resolved to the real TNID by the crawler
			$select
				->where('pcl.' . self::COL_BUYER_ORG_ID . ' = ?', $buyerOrg->id)
			;
		}

		$cacheKey = Myshipserv_Config::decorateMemcacheKey(implode('_',
			array(
				__FUNCTION__,
				$select->assemble()
			)
		));

		$rows = $this->fetchCachedQuery($select->assemble(), array(), $cacheKey, self::MEMCACHE_TTL);

		$response = array();
		foreach ($rows as $row) {
			$response[] = $row['UOM'];
		};

		return $response;
	}

	/**
	 * @param   array|string        $words
	 * @param   DateTime|null       $dateFrom
	 * @param   Shipserv_Buyer|null $buyerOrg
	 * @param   int|null            $pageNo
	 * @param   int|null            $pageSize
	 * @param   string              $catalogueId
	 * @param   string              $transactionType
	 *
	 * @return  array
	 * @throws  Myshipserv_Search_PriceBenchmark_Exception
	 */
	public function autocompleteProduct($words, DateTime $dateFrom = null, Shipserv_Buyer $buyerOrg = null,
											   $pageNo = null, $pageSize = null,
	                                           $catalogueId = self::CATALOGUE_ID_IMPA,
	                                           $transactionType = self::TRANSACTION_TYPE_ORDER) {
		if (!is_array($words)) {
			$words = array($words);
		}

		$select = new Zend_Db_Select(Shipserv_Helper_Database::getDb());
		$select
			->from(
				array('pcl' => self::TABLE_NAME),
				array(
					'PART_NO' => 'pcl.' . self::COL_PART_NO
				)
			)
			->where('pcl.' . self::COL_TRANSACTION_TYPE . ' = ?', $transactionType)
			->distinct()
		;

		if ($dateFrom) {
			$select
				->where('pcl.' . self::COL_TRANSACTION_DATE . ' >= ' . Shipserv_Helper_Database::getOracleDateExpr($dateFrom, true))
			;
		}

		if ($buyerOrg) {
			// @todo: wouldn't include Pages transactions for the buyer because they're not resolved to the real TNID by the crawler
			$select
				->where('pcl.' . self::COL_BUYER_ORG_ID . ' = ?', $buyerOrg->id)
			;
		}

		if ($catalogueId === self::CATALOGUE_ID_IMPA) {
			$select
				->where('pcl.' . self::COL_CATALOGUE_ID . ' = ?', $catalogueId)
				->join(
					array('pci' => Shipserv_Oracle_ImpaCatalogue::TABLE_NAME),
					'pci.' . Shipserv_Oracle_ImpaCatalogue::COL_ID . ' = pcl.' . self::COL_PART_NO,
					array(
						'PART_DESC' => 'pci.' . Shipserv_Oracle_ImpaCatalogue::COL_DESC
					)
				)
				->order('pci.' . Shipserv_Oracle_ImpaCatalogue::COL_DESC)
			;
			
			$select = Shipserv_PriceBenchmark::addProductDescFilter($select, $words, 'pci');

			$where = array();
			foreach ($words as $word) {
				$whereBits = array();

				if (is_numeric($word)) {
					if (strlen($word) === 6) {
						// this might to be a full IMPA code
						$whereBits[] = 'pci.' . Shipserv_Oracle_ImpaCatalogue::COL_ID . ' = ' . $word;
					} else if (strlen($word) < 6) {
						// this might be a partial IMPA code
						$likeStatement = Shipserv_Helper_Database::escapeLike($select->getAdapter(), $word, Shipserv_Helper_Database::ESCAPE_LIKE_RIGHT);
						$whereBits[] = 'pci.' . Shipserv_Oracle_ImpaCatalogue::COL_ID . ' ' . $likeStatement;
					}
				}

				$likeStatement = Shipserv_Helper_Database::escapeLike($select->getAdapter(), $word, Shipserv_Helper_Database::ESCAPE_LIKE_BOTH);
				$whereBits[] = 'UPPER(pci.' . Shipserv_Oracle_ImpaCatalogue::COL_DESC . ') ' . $likeStatement;

				$where[] = '(' . implode(' OR ', $whereBits) . ')';
			}

			$select->where(implode(' AND ', $where));

		} else {
			throw new Myshipserv_Search_PriceBenchmark_Exception("Catalogue '" . $catalogueId . "' is not yet supported for autocomplete");
		}

		// print $select->assemble(); exit;

		$cache = $this->getMemcache();
		$cacheKey = Myshipserv_Config::decorateMemcacheKey(implode('_',
			array(
				__FUNCTION__,
				md5($select->assemble()),
				$pageNo,
				$pageSize
			)
		));

		if (($result = $cache->get($cacheKey)) !== false) {
			return $result;
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
			$rows = $select->getAdapter()->fetchAll($select);
		}

		$response = array();
		foreach ($rows as $row) {
			$response[$row['PART_NO']] = $row['PART_DESC'];
		};

		$cache->set($cacheKey, $response, null, self::MEMCACHE_TTL);

		return $response;
	}
}