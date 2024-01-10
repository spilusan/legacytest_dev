<?php
/**
 * An entry point to Neo4j graph database
 *
 * @author  Yuriy Akopov
 * @date    2015-04-08
 * @story   S13156
 */

// commented out by Yuriy Akopov on 2016-06-08, S16162 as autoloader is now initialised in index.php
// not sure how it used to work before though, composer/autoload.php file doesn't seem to exist (yet it works)
// require_once('composer/autoload.php');

use Everyman\Neo4j\Cypher\Query;
use Everyman\Neo4j\Client;

class Shipserv_Adapters_Neo4j {
    const
        DEFAULT_CACHE_TTL = 21600 // 6 hours timeout for queries
    ;

    /**
     * @var null
     */
    protected $client = null;

    /**
     * @var Memcache
     */
    protected $memcache = null;

    /**
     * @var bool
     */
    protected $lastQueryFromCache = null;

    /**
     * @return bool
     */
    public function wasLastQueryCached() {
        return $this->lastQueryFromCache;
    }

    public function __construct() {
        $config = Myshipserv_Config::getIni()->shipserv->services->neo4j;

        $this->client = new Client($config->host, $config->port);
        $this->client->getTransport()->setAuth($config->login, $config->password);

        $this->memcache = Shipserv_Memcache::getMemcache();
    }

    /**
     * Reimplementing something from Shipserv_Memcache because it is protected there and we cannot inherit
     *
     * @param   string  $query
     * @param   array   $params
     *
     * @return  string
     */
    protected function makeCacheKey($query, array $params = null) {
        $config = Myshipserv_Config::getIni();

        $querySerialised = $query;
        if (!is_null($params)) {
            $querySerialised .= '_' . serialize($params);
        }

        $key = implode('_', array(
            $config->memcache->client->keyPrefix,
            get_called_class(),
            md5($querySerialised),
            $config->memcache->client->keySuffix
        ));

        return $key;
    }

    /**
     * Takes IMPA codes and check if they have been ordered
     *
     * @param   string|array                              $impaCodes
     * @param   Shipserv_Buyer|Shipserv_Buyer_Branch|null $buyer
     * @param   DateTime                                  $dateFrom
     * @param   int                                       $maxSuggestions
     *
     * @return  array
     * @throws  Shipserv_Adapters_Neo4j_Exception#
     */
    public function checkIfOrdered($impaCodes, $buyer, DateTime $dateFrom = null, $maxSuggestions = null) {
        $queryBits = array(
            'MATCH',
            '(b:BUYER_BRANCH)-[ou:ORDERED_USING]->(p:PRODUCT)',
        );

        $params = array();

        $buyerConstraint = true;

        if ($buyer instanceof Shipserv_Buyer) {
            $queryBits[] = ', (b:BUYER_BRANCH)-[:BELONGS]->(o:BUYER_ORG)';
            $queryBits[] = 'WHERE';
            $queryBits[] = 'o.id = {buyerId}';

            $params['buyerId'] = (int) $buyer->id;

        } else if ($buyer instanceof Shipserv_Buyer_Branch) {
            $queryBits[] = 'WHERE';
            $queryBits[] = 'b.id = {buyerId}';

            $params['buyerId'] = (int) $buyer->id;

        } else if (is_null($buyer)) {
            $queryBits[] = 'WHERE';
            $buyerConstraint = false;

        } else {
            throw new Shipserv_Adapters_Neo4j_Exception("Invalid buyer supplied");
        }

        if (!is_array($impaCodes)) {
            $impaCodes = array($impaCodes);
        }

        $productWhereBits = array();
        foreach (array_values($impaCodes) as $index => $code) {
            $paramName = 'code' . $index;
            $params[$paramName] = (string) $code;
            $productWhereBits[] = 'p.impa = {' . $paramName . '}';
        }

        if ($buyerConstraint) {
            $queryBits[] = 'AND';
        }
        $queryBits[] = "(" . implode(' OR ', $productWhereBits) . ")";

        if ($dateFrom) {
            $queryBits[] = "AND ou.last_order_date > {dateFrom}";
            $params['dateFrom'] = $dateFrom->format('Y-m-d H:i:s');
        }

        $queryBits[] = "RETURN DISTINCT p.impa";
        // $queryBits[] = "ORDER BY p.desc";

        if (!is_null($maxSuggestions)) {
            $params['suggestions'] = $maxSuggestions;
            $queryBits[] = "LIMIT {suggestions}";
        }

        $query = implode(' ', $queryBits);
        // print $query; print '<hr/>'; print_r($params); die;
        $results = $this->getResults($query, $params);

        $boughtProducts = array();
        if (!empty($results)) {
            foreach ($results as $row) {
                $boughtProducts[] = $row['p.impa'];
            }
        }

        return $boughtProducts;
    }

    /**
     * Returns measuring units used by the given buyer when ordering the given product
     *
     * @param   array|string  $impaCodes
     * @param   Shipserv_Buyer|Shipserv_Buyer_Branch    $buyer
     * @param   DateTime|null   $dateFrom
     *
     * @return array
     * @throws Shipserv_Adapters_Neo4j_Exception
     */
    public function getProductUnits($impaCodes, $buyer, DateTime $dateFrom = null) {
        $queryBits = array(
            'MATCH',
            '(b:BUYER_BRANCH)-[ou:ORDERED_USING]->(p:PRODUCT)',
        );

        $params = array();

        $buyerConstraint = true;

        if ($buyer instanceof Shipserv_Buyer) {
            $queryBits[] = ', (b:BUYER_BRANCH)-[:BELONGS]->(o:BUYER_ORG)';
            $queryBits[] = 'WHERE';
            $queryBits[] = 'o.id = {buyerId}';

            $params['buyerId'] = (int) $buyer->id;

        } else if ($buyer instanceof Shipserv_Buyer_Branch) {
            $queryBits[] = 'WHERE';
            $queryBits[] = 'b.id = {buyerId}';

            $params['buyerId'] = (int) $buyer->id;

        } else if (is_null($buyer)) {
            $queryBits[] = 'WHERE';
            $buyerConstraint = false;

        } else {
            throw new Shipserv_Adapters_Neo4j_Exception("Invalid buyer supplied");
        }

        if (!is_array($impaCodes)) {
            $impaCodes = array($impaCodes);
        }

        $productWhereBits = array();
        foreach (array_values($impaCodes) as $index => $code) {
            $paramName = 'code' . $index;
            $params[$paramName] = (string) $code;
            $productWhereBits[] = 'p.impa = {' . $paramName . '}';
        }

        if ($buyerConstraint) {
            $queryBits[] = 'AND';
        }
        $queryBits[] = "(" . implode(' OR ', $productWhereBits) . ")";

        if ($dateFrom) {
            $queryBits[] = "AND ou.last_order_date > {dateFrom}";
            $params['dateFrom'] = $dateFrom->format('Y-m-d 00:00:00');
        }

        $queryBits[] = "RETURN DISTINCT p.impa, ou.unit";

        // print_r($params); print $query; die;

        $query = implode(' ', $queryBits);
        $results = $this->getResults($query, $params);

        $productUnits = array();
        if (!empty($results)) {
            foreach ($results as $row) {
                $impaCode = $row['p.impa'];

                if (!array_key_exists($impaCode, $productUnits)) {
                    $productUnits[$impaCode] = array();
                }

                $productUnits[$impaCode][] = $row['ou.unit'];
            }
        }

        return $productUnits;
    }

    /**
     * @param   string  $query
     * @param   array   $params
     * @param   bool    $loadFromCache
     * @param   bool    $saveToCache
     * @param   int     $cacheTtl
     *
     * @return \Everyman\Neo4j\Query\ResultSet
     */
    protected function getResults($query, array $params = null, $loadFromCache = true, $saveToCache = true, $cacheTtl = self::DEFAULT_CACHE_TTL) {
        if (!($this->memcache instanceof Memcache)) {
            $loadFromCache = false;
            $saveToCache = false;
        }

        $neo4j = $this->client;

        $resultObj2Data = function() use ($query, $params, $neo4j) {
            $cypher = new Query($neo4j, $query, $params);
            $results = $cypher->getResultSet();

            $rows = array();
            foreach ($results as $resRow) {
                $row = array();
                foreach ($resRow as $field => $value) {
                    $row[$field] = (string) $value;
                }
                $rows[] = $row;
            }

            return $rows;
        };

        $this->lastQueryFromCache = false;

        if (!$loadFromCache) {
            // no need to check the cache, run the query
            $results = $resultObj2Data();

        } else {
            $cacheKey = $this->makeCacheKey($query, $params);
            if (($results = $this->memcache->get($cacheKey)) === false) {
                // failed to find results of this query in cache
                $results = $resultObj2Data();

                if ($saveToCache) {
                    if (!$this->memcache->set($cacheKey, $results, null, $cacheTtl)) {
                        $fail = true;
                    }
                }
            } else {
                $this->lastQueryFromCache = true;
            }
        }

        return $results;
    }

    /**
     * Returns IMPA codes ordered by given buyer and relevant measuring units optionally filtering by product description
     *
     * @param   Shipserv_Buyer|Shipserv_Buyer_Branch|null    $buyer
     * @param   array           $words
     * @param   DateTime|null   $dateFrom
     * @param   int             $pageNo
     * @param   int             $pageSize
     *
     * @return  array
     * @throws  Shipserv_Adapters_Neo4j_Exception
     */
    public function getBoughtProductMatches(array $words, $buyer, $dateFrom = null, $pageNo = 1, $pageSize = 20) {
        $queryBits = array(
            'MATCH',
            '(b:BUYER_BRANCH)-[ou:ORDERED_USING]->(p:PRODUCT)',
        );

        $queryWhereBits = array();
        $params = array();

        if ($buyer instanceof Shipserv_Buyer) {
            $queryBits[] = ', (b:BUYER_BRANCH)-[:BELONGS]->(o:BUYER_ORG)';
            $queryWhereBits[] = 'o.id = {buyerId}';

            $params['buyerId'] = (int) $buyer->id;

        } else if ($buyer instanceof Shipserv_Buyer_Branch) {
            $queryWhereBits[] = 'b.id = {buyerId}';

            $params['buyerId'] = (int) $buyer->id;

        } else if (is_null($buyer)) {
            // no buyer filter, site-wide query
        } else {
            throw new Shipserv_Adapters_Neo4j_Exception("Invalid buyer supplied");
        }

        if (!empty($words)) {
            $whereBits = array();
            $wordIndex = 1;
            foreach ($words as $word) {
                $paramName = 'regexp' . $wordIndex;
                $params[$paramName] = '(?i).*' . preg_quote($word) . '.*';
                $whereWordStr = '(p.desc =~ {' . $paramName . '}';

                if (is_numeric($word)) {
                    if (strlen($word) === 6) {
                        // full IMPA code
                        $paramNameCode = $paramName . 'code';
                        $params[$paramNameCode] = (string) $word;
                        $whereWordStr .= ' OR p.impa = {' . $paramNameCode . '}';

                    } else if (strlen($word) < 6) {
                        // partial IMPA code
                        $paramNameCode = $paramName . 'code';
                        $params[$paramNameCode] = '(?)' . preg_quote($word) . '\d*';
                        $whereWordStr .= ' OR p.impa =~ {' . $paramNameCode . '}';
                    }
                }

                $whereWordStr .= ')';

                $whereBits[] = $whereWordStr;

                $wordIndex++;
            }

            $queryWhereBits[] = '(' . implode(' AND ', $whereBits) . ')';
        }

        if ($dateFrom) {
            $queryWhereBits[] = 'ou.last_order_date > {dateFrom}';
            $params['dateFrom'] = $dateFrom->format('Y-m-d 00:00:00');
        }

        if (!empty($queryWhereBits)) {
            $queryBits[] = 'WHERE';
            $queryBits[] = implode(' AND ', $queryWhereBits);
        }

        $queryBits[] = "RETURN DISTINCT p.impa, p.desc";
        $queryBits[] = "ORDER BY p.desc";

        if (!is_null($pageNo)) {
            $params['skipSize'] = ($pageNo - 1) * $pageSize;
            $params['pageSize'] = $pageSize;
            $queryBits[] = "SKIP {skipSize} LIMIT {pageSize}";
        }

        $query = implode(' ', $queryBits);
        // print $query; print '<hr/>'; print_r($params); die;
        $results = $this->getResults($query, $params);

        $products = array();
        if (!empty($results)) {
            foreach ($results as $result) {
                $products[$result['p.impa']] = $result['p.desc'];
            }
        }

        return $products;
    }
}