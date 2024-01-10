<?php

/**
 * Worker class for RFQ Supplier matching based on the content of an RFQ
 *
 * Refactoring by Yuriy Akopov started on 2013-07-30. A quick overview of the legacy state from the functional perspective:
 * https://docs.google.com/a/shipserv.com/presentation/d/1TMZVhGj6ofsg9X9nOMSorpBB1lAkV8hw-O7Q0RE24j8
 *
 * @package myshipserv
 * @author Shane O'Connor <soconnor@shipserv.com>
 * @copyright Copyright (c) 2012, ShipServ
 */
class Shipserv_Match_Match {
    const
        COUNTRY_MAP_BY_COUNTRY      = 'by_country',
        COUNTRY_MAP_BY_CONTINENT    = 'by_continent',

        CACHE_ALL       = 'cache_all',
        CACHE_AT_RISK   = 'at_risk',
        CACHE_FILTERED  = 'filtered'
    ;

    const
        // keys for search terms collection returned by tokenisation mechanism
        TERM_TYPE_BRANDS        = 'brands',
        TERM_TYPE_CATEGORIES    = 'categories',
        TERM_TYPE_TAGS          = 'tags',
        TERM_TYPE_LOCATIONS     = 'locations',

        // keys for search term arrays used internally
        TERM_ID         = 'id',
        TERM_NAME       = 'output_name',
        TERM_SCORE      = 'score',
        TERM_TAG        = 'tag',
        TERM_PARENT_ID  = 'parent_id',
        // keys for search result (matched supplier) array
        RESULT_SUPPLIER     = 'supplierObj',
        RESULT_SUPPLIER_ID  = 'supplier',
        RESULT_SCORE        = 'score',
        RESULT_COMMENT      = 'comment',
        RESULT_COUNTRY      = 'country',
        RESULT_CONTINENT    = 'continent',
        RESULT_TAGS         = 'tags',
        RESULT_CATEGORIES   = 'categories',
        // RESULT_ORIGINAL     = 'original',
        RESULT_AT_RISK      = 'at_risk'
    ;

    const
        DEFAULT_TERM_WEIGHT = 500
    ;

    /**
     * Storing Solr performance data for testing purposes
     *
     * @var null
     */
    protected $solrStats = null;

    /**
     * RFQ object related to the search conducted (the one defined by $rfqId field above)
     *
     * @var Shipserv_Tradenet_RequestForQuote
     */
    protected $rfq = null;

    /**
     * Tags (nouns) extracted from the RFQ during the tokenising process
     *
     * @var array|null
     */
    protected $tags = null;

    /**
     * Categories relevant to the RFQ
     *
     * @var array|null
     */
    protected $categories = null;

    /**
     * Brands relevant to the RFQ
     *
     * @var array|null
     */
    protected $brands = null;

    /**
     * Locations to filter suppliers
     *
     * @var array|null
     */
    protected $locations = null;

    /**
     * @var array
     */
    public $originalSuppliers = null;
    /**
     * @var array
     */
    public $originalSuppliersLocations = null;

    /**
     * @var Shipserv_Match_MatchCache
     */
    protected $cache;

    /**
     * @var Shipserv_Oracle
     */
    protected $db;

    /**
     * Once-per-session loaded map of countries per continent and vice versa
     *
     * @var array
     */
    protected static $countryMap = null;

    /**
     * Expert-defined factors used in
     *
     * @var array
     */
    protected $algorithmValues = array();

    /**
     * Results of the last performed search (so we can access them more than once without re-running the search)
     *
     * @author  Yuriy Akopov
     * @date    2013-08-16
     * @story   S7924
     *
     * @var array
     */
    protected $resultCache = array();

    /**
     * @todo: not in use as per 2013-10-01
     *
     * @param $arrText
     * @return bool|Shipserv_Supplier[]
     */
    public function getMatchedSuppliersFromTextArray($arrText) {
        $tagGenerator = new Shipserv_Match_TagGenerator($this->db);

        $results = $tagGenerator->generateTagsFromText($arrText);

        $this->tags         = $results['tags'];
        $this->brands       = $results['brands'];
        $this->categories   = $results['categories'];    // @todo: shouldn't it be $results['categories']? (Yuriy Akopov)

        $matchedSuppliers = $this->getMatchedSuppliers();

        return $matchedSuppliers;
    }

    /**
     * This method will see if any amendments have been made on TAG interface and apply rthose to the categories array.
     * @param array $arrCats
     */
    public function mergeCategories($arrCats) {
        $tmpCats = $arrCats;

        $sql = "
          SELECT
            MCR_CATEGORY_ID,
            MCR_IS_ADDITION,
            p2.name AS Parent_Name,
            p.*
          FROM
            MATCH_CATEGORY_REPLACEMENT m,
            Product_category p,
            product_category p2
          WHERE
            m.MCR_CATEGORY_ID           = p.ID
            AND p.parent_id             = p2.id (+)
            AND MCR_RFQ_INTERNAL_REF_NO = :rfq
            AND MCR_CATEGORY_ID         IS NOT NULL
        ";
        $updates = $this->db->fetchAll($sql, array('rfq' => $this->rfq->getInternalRefNo()));

        $removeKeys = array();
        foreach ($updates as $update) {
            //This is to be added to the array.
            if ($update['MCR_IS_ADDITION'] == 'Y') {
                if (!empty($update['PARENT_ID'])) {
                    $outputName = $update['NAME'] . ' (' . $update['PARENT_NAME'] . ')';
                } else {
                    $outputName = '';
                }
                $tmpCats[] = array('tag' => $update['NAME'], 'id' => $update['ID'], 'parent_id' => $update['PARENT_ID'], 'output_name' => $outputName);
            } else {
                //Remove from list.
                foreach ($tmpCats as $key => $val) {
                    if ($val['id'] == $update['MCR_CATEGORY_ID']) {
                        $removeKeys[] = $key;
                    }
                }
            }
        }

        if (count($removeKeys)) {
            foreach ($removeKeys as $remove) {
                unset($tmpCats[$remove]);
            }
        }

        return $tmpCats;
    }

    public function mergeBrands($arrBrands) {
        $sql = "
          SELECT
            MBR_BRAND_ID,
	        MBR_IS_ADDITION,
	        b.*
		  FROM
		    Match_Brand_replacement m,
		    Brand b
		  WHERE
		    m.MBR_brand_id = b.id
		    AND MBR_RFQ_INTERNAL_REF_NO = :rfq
		    AND MBR_BRAND_ID IS NOT NULL
        ";
        $updates = $this->db->fetchAll($sql, array('rfq' => $this->rfq->getInternalRefNo()));

        $tmpBrands = $arrBrands;
        $removeKeys = array();
        foreach ($updates as $update) {
            if ($update['MBR_IS_ADDITION'] == 'Y') {
                $tmpBrands[] = array('tag' => $update['NAME'], 'score' => 1);
            } else {
                foreach ($tmpBrands as $key => $val) {
                    if ($val['tag'] == $update['NAME']) {
                        $removeKeys[] = $key;
                    }
                }
            }
        }

        if (count($removeKeys)) {
            foreach ($removeKeys as $remove) {
                unset($tmpBrands[$remove]);
            }
        }

        return $tmpBrands;
    }

    /**
     * As PHP 5.2 doesn't allow lambda functions, this is a substitute to use with usort for tag info arrays sorting
     * @todo: possibly redundant
     *
     * @author  Yuriy Akopov
     * @date    2013-09-20
     *
     * @param   array $a
     * @param   array $b
     *
     * @return  int
     */
    public static function usortTagsByWordNo(array $a, array $b) {
        $wordCountA = substr_count($a['tag'], ' ');
        $wordCountB = substr_count($b['tag'], ' ');

        if ($wordCountA > $wordCountB) {
            return -1;
        }

        if ($wordCountA < $wordCountB) {
            return 1;
        }

        return strcasecmp($a['tag'], $b['tag']);
    }

    /**
     * Initialises the match engine with the RFQ which needs to be processed
     *
     * @param   Shipserv_Tradenet_RequestForQuote|string    $rfq
     * @param   bool                                        $tokeniseRfq
     * @param   array                                       $locations
     */
    public function __construct($rfq = null, $tokeniseRfq = true, array $locations = null) {
        $this->db = Shipserv_Helper_Database::getDb();
        $this->cache = new Shipserv_Match_MatchCache($this->db);

        $this->algorithmValues  = Shipserv_Match_Settings::getAll();

        if (!is_null($locations)) {
            $this->setLocations($locations);
        }

        // loading RFQ data or stopping if no RFQ
        if (is_null($rfq)) {
            // don't proceed with tokenisation as there is no RFQ
            return;

        } else if ($rfq instanceof Shipserv_Tradenet_RequestForQuote) {
            // RFQ object supplied - re-use it
            $this->rfq = $rfq;
        } else if ($rfq instanceof Shipserv_Rfq) {
            $this->rfq = new Shipserv_Tradenet_RequestForQuote($rfq->rfqInternalRefNo, $this->db);
        } else {
            // RFQ ID supplied - instantiate the RFQ
            $this->rfq = new Shipserv_Tradenet_RequestForQuote($rfq, $this->db);
        }

        $this->originalSuppliers = $this->rfq->rfq_suppliers_details;

        if (!$tokeniseRfq) {
            return;
        }

        // extracting searchable information from the RFQ
        $processor = new Shipserv_Match_TagGenerator();
        $results = $processor->generateTagsFromRFQ($this->rfq);

        // sort the recognised tags by number of words in them
        // usort($results['tags'], array(__CLASS__, 'usortTagsByWordNo'));
        // $this->storeMatchKeywords($results['tags']);

        $this->brands       = $results[self::TERM_TYPE_BRANDS]; // $this->mergeBrands($results['brands']);
        $this->categories   = $results[self::TERM_TYPE_CATEGORIES]; // $this->mergeCategories($results['categories']);
        $this->tags         = $results[self::TERM_TYPE_TAGS]; // $this->mergeTags($results['tags']);
        $this->locations    = $results[self::TERM_TYPE_LOCATIONS];

        self::aasort($this->brands, self::TERM_SCORE);
        self::aasort($this->categories, self::TERM_SCORE);
        self::aasort($this->tags, self::TERM_SCORE);
        self::aasort($this->locations, self::TERM_SCORE);
    }

    /**
     * Assigns custom tag search terms (e.g. altered by user)
     *
     * @param   array   $tags
     *
     * @throws  Exception
     */
    public function setCustomTags(array $tags) {
        $this->resultCache = array();

        if (count($tags) === 0) {
            $this->tags = array();
            return;
        }

        $tagTags = array();
        foreach ($tags as $tagInfo) {
            if (!filter_var($tagInfo[self::TERM_SCORE], FILTER_VALIDATE_FLOAT)) {
                throw new Myshipserv_Exception_MessagedException('Tag term validation error: invalid score supplied for tag "' . $tagInfo[self::TERM_TAG] . '": ' . $tagInfo[self::TERM_SCORE]);
            }

            if (strlen($tagInfo[self::TERM_TAG]) === 0) {
                throw new Myshipserv_Exception_MessagedException('Tag term validation error: empty tag string supplied');
            }

            $tagTags[] = array(
                // this is needed to exclude fields from $tagInfo that we won't need in the match engine
                self::TERM_TAG      => $tagInfo[self::TERM_TAG],
                self::TERM_SCORE    => (float) $tagInfo[self::TERM_SCORE]
            );
        }

        // $this->tags = $this->mergeTags($tags);
        $this->tags = $tagTags;
    }

    /**
     * Assigns custom category search terms (e.g. altered by user)
     *
     * @param array $categories
     *
     * @throws Exception
     */
    public function setCustomCategories(array $categories) {
        $this->resultCache = array();

        if (count($categories) === 0) {
            $this->categories = array();
            return;
        }

        $categoryTags = array();
        foreach ($categories as $categoryInfo) {
            if (!filter_var($categoryInfo[self::TERM_SCORE], FILTER_VALIDATE_FLOAT)) {
                throw new Myshipserv_Exception_MessagedException('Category term validation error: invalid score supplied for category "' . $categoryInfo[self::TERM_ID] . '": ' . $categoryInfo[self::TERM_SCORE]);
            }

            try {
                $category = Shipserv_Category::getInstanceById($categoryInfo[self::TERM_ID]);
                if (strlen($category->id) === 0) {
                    throw new Exception();
                }
            } catch (Exception $e) {
                throw new Myshipserv_Exception_MessagedException('Category terms validation error: unknown category specified: ' . $categoryInfo[self::TERM_ID]);
            }

            $categoryTag = Shipserv_Match_TagGenerator::categoryToTag($category);
            $categoryTag[self::TERM_SCORE] = (float) $categoryInfo[self::TERM_SCORE];

            $categoryTags[] = $categoryTag;
        }

        // $this->categories = $this->mergeCategories($categoryTags);
        $this->categories = $categoryTags;
    }

    /**
     * Assigns custom brand search terms (e.g. altered by user)
     *
     * @param array $brands
     *
     * @throws Exception
     */
    public function setCustomBrands(array $brands) {
        $this->resultCache = array();

        if (count($brands) === 0) {
            $this->brands = array();
            return;
        }

        $brandTags = array();
        foreach ($brands as $brandInfo) {
            if (!filter_var($brandInfo[self::TERM_SCORE], FILTER_VALIDATE_FLOAT)) {
                throw new Myshipserv_Exception_MessagedException('Brand term validation error: invalid score supplied for brand "' . $brandInfo[self::TERM_ID] . '":' . $brandInfo[self::TERM_SCORE]);
            }

            try {
                $brand = Shipserv_Brand::getInstanceById($brandInfo[self::TERM_ID]);
                if (strlen($brand->id) === 0) {
                    throw new Exception();
                }
            } catch (Exception $e) {
                throw new Myshipserv_Exception_MessagedException('Brand terms validation error: unknown brand specified: ' . $brandInfo[self::TERM_ID]);
            }

            // re-build brand array structure from an object - not exactly necessary, but this way we are sure all expected fields will be there
            $brandTag = Shipserv_Match_TagGenerator::brandToTag($brand);
            $brandTag[self::TERM_SCORE] = (float) $brandInfo[self::TERM_SCORE];

            $brandTags[] = $brandTag;

        }

        $this->brands = $brandTags;
    }

    /**
     * Returns the list of locations from which suppliers should be boosted, looks into the specified locations or into the RFQ original ones
     *
     * @return  array
     */
    /*
    protected function getLocationsForBoost() {
        // first collect the countries
        $countries = array();
        if (!empty($this->locations)) {
            foreach ($this->locations as $term) {
                $countries[$term[self::TERM_ID]] = $term[self::RESULT_SCORE];
            }
        } else {
            if ($this->originalSuppliers) {
                // if RFQ was sent to other suppliers apart from the match engine use their location for ranking
                foreach ($this->originalSuppliers as $oSupplier) {
                    $oSuppliersLocations[] = array(
                        'country'   => $oSupplier['COUNTRY'],
                        'continent' => $oSupplier['CONTINENT']
                    );
                }

                if (isset($this->rfq->rfq_header->portcountry)) {
                    $oSuppliersLocations[] = array(
                        'country'   => $this->rfq->rfq_header->portcountry,
                        'continent' => $this->rfq->rfq_header->portcontinent
                    );
                }

            } else {
                // user buyer's location data for ranking
                if (isset($this->rfq->rfq_header->portcountry)) {
                    $oSuppliersLocations[] = array(
                        'country'   => $this->rfq->rfq_header->portcountry,
                        'continent' => $this->rfq->rfq_header->portcontinent
                    );
                } else {
                    $oSuppliersLocations[] = array(
                        'country'   => $this->rfq->rfq_header->buyercountry,
                        'continent' => $this->rfq->rfq_header->buyercontinent
                    );
                }

                $buyerLocation = true;
            }
        }
    }
    */

    /**
     * Applies location penalties/boosts to the list of matched suppliers basing on RFQ and its buyer selected supplier locations
     * This is the legacy mechanism which is going to be gradually replaced
     *
     * @param   array   $matchedSuppliers
     *
     * @returns array
     *
     * @throws  Exception
     */
    protected function applyRfqLocationBoost(array &$matchedSuppliers) {
        $buyerLocation = false;

        if ($this->originalSuppliers) {
            // if RFQ was sent to other suppliers apart from the match engine use their location for ranking
            foreach ($this->originalSuppliers as $oSupplier) {
                $oSuppliersLocations[] = array('country' => $oSupplier['COUNTRY'], 'continent' => $oSupplier['CONTINENT']);
            }

            if (isset($this->rfq->rfq_header->portcountry)) {
                $oSuppliersLocations[] = array('country' => $this->rfq->rfq_header->portcountry, 'continent' => $this->rfq->rfq_header->portcontinent);
            }

        } else {
            // user buyer's location data for ranking
            if (isset($this->rfq->rfq_header->portcountry)) {
                $oSuppliersLocations[] = array('country' => $this->rfq->rfq_header->portcountry, 'continent' => $this->rfq->rfq_header->portcontinent);
            } else {
                $oSuppliersLocations[] = array('country' => $this->rfq->rfq_header->buyercountry, 'continent' => $this->rfq->rfq_header->buyercontinent);
            }

            $buyerLocation = true;
        }

        $oSuppliersLocations = $this->organiseLocations($oSuppliersLocations);

        foreach ($matchedSuppliers as &$matchedSupplier) {
            $sCountryMatch = false;
            $sContinentMatch = false;

            foreach ($oSuppliersLocations as $location) {
                if ($matchedSupplier[self::RESULT_CONTINENT] == $location['continent']) {
                    $sContinentMatch = true;
                    $continentPercentage = $location['continentpercentage'];
                }

                if ($matchedSupplier[self::RESULT_COUNTRY] == $location['country']) {
                    $sCountryMatch = true;
                    $countryPercentage = $location['countrypercentage'];
                }
            }

            if ($sContinentMatch) {
                if ($sCountryMatch) {
                    // 50% boost for country Match
                    if ($buyerLocation) {
                        $matchedSupplier[self::RESULT_COMMENT] .= ";Supplier matches country location of buyer or port.";
                    } else {
                        $matchedSupplier[self::RESULT_COMMENT] .= ";Supplier matches country location of buyer-chosen suppliers.";
                    }
                    $matchedSupplier[self::RESULT_COMMENT] .= " Score boosted 50% * " . $countryPercentage . "% (Country ratio)";
                    $multiplier = 1 + ($this->algorithmValues['BUYER_SUPPLIER_COUNTRY_MATCH_BOOST'] - 1) * ( $countryPercentage / 100);
                } else {
                    // 20% boost for Continent Match
                    if ($buyerLocation) {
                        $matchedSupplier[self::RESULT_COMMENT] .= ";Supplier matches continent location of buyer or port.";
                    } else {
                        $matchedSupplier[self::RESULT_COMMENT] .= ";Supplier matches continent location of buyer-chosen suppliers.";
                    }
                    $matchedSupplier[self::RESULT_COMMENT] .= " Score boosted 20%* " . $continentPercentage . "% (Continent ratio)";
                    $multiplier = 1 + ($this->algorithmValues['BUYER_SUPPLIER_CONTINENT_MATCH_BOOST'] - 1) * ($continentPercentage / 100);
                }

                $matchedSupplier[self::RESULT_SCORE] *= $multiplier;
            }
        }
        unset($matchedSupplier);

        return $matchedSuppliers;
    }

    /**
     * Applies location boost to the list of matched suppliers basing on the list of locations specified as search terms
     * Falls back to legacy boost based on RFQ propertoes / buyer selected suppliers if necessary
     *
     * @author  Yuriy Akopov
     * @date    2014-07-15
     * @story   S10773
     *
     * @param array $matchedSuppliers
     *
     * @returns array
     */
    public function applyLocationBoost(array &$matchedSuppliers) {
        // convert the location search terms into a structure more convenient for our purposes
        if (is_null($this->locations) or empty($this->locations)) {
            return $matchedSuppliers;
        }

        $countryMap = $this->getCountryMap();

        $weightsCountry   = array();
        $weightsContinent = array();
        foreach ($this->locations as $term) {
            $country      = $term[self::TERM_ID];
            $continent    = $countryMap[self::COUNTRY_MAP_BY_COUNTRY][$country];
            $countryScore = $term[self::TERM_SCORE];

            // collect boosts for countries
            $weightsCountry[$country] = $countryScore;
            // collect boosts for continents
            $weightsContinent[$continent][] += ceil($countryScore / Shipserv_Match_Settings::get(Shipserv_Match_Settings::LOCATION_CONTINENT_SCORE_DENOMINATOR));
        }

        foreach ($matchedSuppliers as &$supplier) {
            $supplierCountry = $supplier[self::RESULT_COUNTRY];

            if (in_array($supplierCountry, array_keys($weightsCountry))) {
                $boost = $weightsCountry[$supplierCountry] / Shipserv_Match_Settings::get(Shipserv_Match_Settings::LOCATION_SCORE_DENOMINATOR);
                $supplier[self::RESULT_SCORE] *= $boost;
                $supplier[self::RESULT_COMMENT] .= ';Weighted location country match {x' . $boost . '}';
            } else {
                // no country match, let's see if there is a continent match
                $supplierContinent = $countryMap[self::COUNTRY_MAP_BY_COUNTRY][$supplierCountry];
                if (in_array($supplierContinent, $weightsContinent)) {
                    $boost = $weightsContinent[$supplierContinent] / Shipserv_Match_Settings::get(Shipserv_Match_Settings::LOCATION_SCORE_DENOMINATOR);
                    $supplier[self::RESULT_SCORE] *= $boost;
                    $supplier[self::RESULT_COMMENT] .= ';Weighted location continent match {x' . $boost . '}';
                }
            }
        }
        unset($supplier);

        // legacy boost not applied (S11066)
        /*
        if (!$boostApplied) {
            // revert to legacy mechanism
            $this->applyRfqLocationBoost($matchedSuppliers);
        }
        */

        return $matchedSuppliers;
    }

    /**
     * Penalises suppliers for being listed in too many categories
     *
     * @param   array   $matchedSuppliers
     */
    public function applyCategorySpamPenalty(array &$matchedSuppliers) {
        $thresholdBasic = Shipserv_Match_Settings::get(Shipserv_Match_Settings::CATEGORY_SPAM_THRESHOLD_BASIC);
        $penaltyBasic   = Shipserv_Match_Settings::get(Shipserv_Match_Settings::CATEGORY_SPAM_MIN_PENALTY);

        $thresholdPremium   = Shipserv_Match_Settings::get(Shipserv_Match_Settings::CATEGORY_SPAM_THRESHOLD_PREMIUM);
        $penaltyPremium     = Shipserv_Match_Settings::get(Shipserv_Match_Settings::CATEGORY_SPAM_MAX_PENALTY);

        foreach ($matchedSuppliers as &$supplier) {
            $categoryCount = count($supplier[self::RESULT_CATEGORIES]);

            if ($categoryCount <= $thresholdBasic) {
                continue;
            }

            $penalty = ($categoryCount > $thresholdPremium) ? $penaltyPremium : $penaltyBasic;

            $supplier[self::RESULT_SCORE] *= (1 - ((int) $penalty / 100));   // @todo: convertions to int are legacy ones, are they needed?
            $supplier[self::RESULT_COMMENT] .= ";Supplier penalised by {" . $penalty . "}% for Category spam.";
        }
        unset($supplier);
    }

    /**
     * Penalises suppliers for being in Chandlery category
     *
     * @param   array   $matchedSuppliers
     */
    public function applyChandleryPenalty(array &$matchedSuppliers) {
        if (empty($matchedSuppliers)) {
            return;
        }

        $chandleryId    = Shipserv_Match_Settings::get(Shipserv_Match_Settings::CATEGORY_ID_CHANDLERY);
        $penalty        = Shipserv_Match_Settings::get(Shipserv_Match_Settings::CHANDLERY_PENALISATION);

        foreach ($matchedSuppliers as &$supplier) {
            if (empty($supplier[self::RESULT_CATEGORIES])) {
                continue;
            }

            foreach ($supplier[self::RESULT_CATEGORIES] as $category) {
                if ($category['ID'] == $chandleryId) {
                    $supplier[self::RESULT_SCORE] *= $penalty;
                    $supplier[self::RESULT_COMMENT] .= ";Supplier penalised by {100 * " . $penalty . ")}% for Chandlery.";
                    break;
                }
            }
        }
        unset($supplier);
    }

    /**
     * Marks original suppliers in the of matched ones
     *
     * @param   array   $matchedSuppliers
     * @param   string  $supplierIdKey
     */
    public function markOriginalSuppliers(array &$matchedSuppliers, $supplierIdKey = self::RESULT_SUPPLIER_ID) {
        if (empty($matchedSuppliers)) {
            return;
        }

        foreach ($matchedSuppliers as &$supplier) {
            if ($this->supplierInList($supplier[$supplierIdKey], $this->originalSuppliers)) {
                $supplier[self::RESULT_ORIGINAL] = true;
            } else {
                $supplier[self::RESULT_ORIGINAL] = false;
            }
        }
        unset($supplier);
    }

    /**
     * Boosts suppliers in special categories (e.g. 'valves')
     *
     * @param   array   $matchedSuppliers
     */
    protected function applyPremiumBoost(array &$matchedSuppliers) {
        if (empty($matchedSuppliers)) {
            return;
        }

        $isSpecialCase = $this->tagsContainSpecialCategory($this->tags);

        if ($isSpecialCase) {
            foreach ($matchedSuppliers as &$matchedSupplier) {
                if ($this->supplierIsPremium($matchedSupplier[self::RESULT_SUPPLIER_ID])) {   // @todo: this boost had been never applied before refactoring because of the typo in code
                    $boost = $this->algorithmValues['SPECIAL_CATEGORY_PREMIUM_BOOST'];
                    $matchedSupplier[self::RESULT_SCORE] *= $boost;
                    $matchedSupplier[self::RESULT_COMMENT] .= ";Supplier received " . ($boost / 100) . "% boost for special category and Premium Profile";
                }
            }
            unset($matchedSupplier);
        }
    }

    /**
     * If buyer's lists is enabled, leave only suppliers which exist in the whitelist and remove ones from blacklist
     *
     * @author  Yuriy Akopov
     * @date    2014-05-23
     * @story   S10313
     *
     * @param   array   $matchedSuppliers
     *
     * @return  array
     */
    protected function applyBuyerFilters(array &$matchedSuppliers) {
        $buyer = Shipserv_Buyer::getInstanceById($this->rfq->rfq_header->rfqBybByoOrgCode, true);
        $list = new Shipserv_Buyer_SupplierList($buyer);

        if ($list->isEnabled(Shipserv_Buyer_SupplierList::TYPE_WHITELIST)) {
            $whitelistIds = $list->getListedSuppliers(Shipserv_Buyer_SupplierList::TYPE_WHITELIST);
            foreach ($matchedSuppliers as $index => $supplierInfo) {
                if (!in_array($supplierInfo[self::RESULT_SUPPLIER_ID], $whitelistIds)) {
                    unset($matchedSuppliers[$index]);
                }
            }
        }

        if ($list->isEnabled(Shipserv_Buyer_SupplierList::TYPE_BLACKLIST)) {
            $blacklistIds = $list->getListedSuppliers(Shipserv_Buyer_SupplierList::TYPE_BLACKLIST);
            foreach ($matchedSuppliers as $index => $supplierInfo) {
                if (in_array($supplierInfo[self::RESULT_SUPPLIER_ID], $blacklistIds)) {
                    unset($matchedSuppliers[$index]);
                }
            }
        }

        return $matchedSuppliers;
    }

    /**
     * Removes blacklisted suppliers in the list of the matched ones
     * Refactored by Yuriy Akopov on 2013-08-30, S7028
     *
     * @param   array   $matchedSuppliers
     *
     * @return  array
     */
    protected function removeBlacklistedSuppliers(array &$matchedSuppliers) {
        $selectSuppliersToRemove = new Zend_Db_Select($this->db);
        $selectSuppliersToRemove
            ->from(
                array('spb' => 'supplier_branch'),
                'spb.' . Shipserv_Supplier::COL_ID
            )
            ->where(implode(' OR ', array(
                $this->db->quoteInto('spb.' . Shipserv_Supplier::COL_STATUS . ' <> ?', 'ACT'),
                $this->db->quoteInto('spb.' . Shipserv_Supplier::COL_DIR_ENTRY . ' <> ?', Shipserv_Supplier::DIR_STATUS_PUBLISHED),
                $this->db->quoteInto('spb.' . Shipserv_Supplier::COL_DELETED . ' = ?', 'Y'),
                $this->db->quoteInto('spb.' . Shipserv_Supplier::COL_TEST . ' = ?', 'Y'),
                $this->db->quoteInto('spb.' . Shipserv_Supplier::COL_ID . ' > ?', Myshipserv_Config::getProxyMatchSupplier()),
                $this->db->quoteInto('spb.' . Shipserv_Supplier::COL_MATCH_EXCLUDE . ' = ?', 'Y')
            )))
        ;

        // check if any of the suppliers from provided list match this query

        $blacklist = array();
        $start = 0;
        $step = 999; // Oracle limitation for IN (...)
        while (count($supplierSlice = array_slice($matchedSuppliers, $start, $step))) {
            $start += $step;

            // collect supplier codes in our 999 slice
            $supplierCodes = array();
            foreach ($supplierSlice as $supplierInfo) {
                if (filter_var($supplierInfo[self::RESULT_SUPPLIER_ID], FILTER_VALIDATE_INT)) {
                    $supplierCodes[] = $supplierInfo[self::RESULT_SUPPLIER_ID];
                }
            }

            if (empty($supplierCodes)) {
                continue;
            }

            $selectSlice = clone($selectSuppliersToRemove);
            $selectSlice->where('spb.' .  Shipserv_Supplier::COL_ID . ' IN (?)', $supplierCodes);

            $blacklistSlice = $this->db->fetchCol($selectSlice);
            $blacklist = array_merge($blacklist, $blacklistSlice);
        }

        if (!empty($blacklist)) {
            // remove suppliers than matched the blacklist query
            foreach ($matchedSuppliers as $index => $supplierInfo) {
                if (in_array($supplierInfo[self::RESULT_SUPPLIER_ID], $blacklist)) {
                    unset($matchedSuppliers[$index]);
                }
            }
        }

        return $matchedSuppliers;
    }

    /**
     * Adds more information to the matched suppliers data array we might need later
     *
     * @param   array   $matchedSuppliers
     */
    protected function addMetaData(array &$matchedSuppliers) {
        if (empty($matchedSuppliers)) {
            return;
        }

        foreach ($matchedSuppliers as &$supplier) {
            $supplier[self::RESULT_CATEGORIES] = $this->getSupplierCategories($supplier[self::RESULT_SUPPLIER_ID]);
        }
        unset($supplier);
    }

    /**
     * Penalises suppliers for having common categories with the original RFQ suppliers
     *
     * @param   array   $matchedSuppliers
     *
     * @throws  Exception
     */
    protected function applyCommonCategoriesPenalty(array &$matchedSuppliers) {
        if (empty($this->originalSuppliers) or empty($matchedSuppliers)) {
            // no original supplier for the RFQ
            return;
        }

        // collect category IDs of the original suppliers RFQ
        $commonCats = array();
        foreach ($this->originalSuppliers as $oSupplier) {
            $supplierCats[$oSupplier['BRANCHCODE']] = $this->getSupplierCategories($oSupplier['BRANCHCODE']);
        }

        foreach ($supplierCats as $list) {
            foreach ($list as $li) {
                $commonCats[$li['ID']]++;   // how many times category is "mentioned"
            }
        }
        arsort($commonCats);

        if (empty($commonCats)) {
            return;
        }

        $validSuppliers = array();

        foreach ($commonCats as $key => $val) {
            foreach ($matchedSuppliers as $supplier) {
                if (empty($supplier[self::RESULT_CATEGORIES])) {
                    continue;
                }

                foreach ($supplier[self::RESULT_CATEGORIES] as $supplierCategoryLI) {
                    if ($supplierCategoryLI['ID'] == $key) {
                        $validSuppliers[$supplier[self::RESULT_SUPPLIER_ID]]++;
                        continue;
                    }
                }
            }
        }

        if (empty($validSuppliers)) {
            return;
        }

        foreach ($matchedSuppliers as &$supplier) {
            if (!array_key_exists($supplier[self::RESULT_SUPPLIER_ID], $validSuppliers)) {
                // @todo: because of the error in the legacy code this penalty has never been applier before
                $origScore = $supplier[self::RESULT_SCORE];
                $supplier[self::RESULT_SCORE] = round($supplier[self::RESULT_SCORE] * $this->algorithmValues['CATEGORY_SUPPLIER_BUYER_SUPPLIERS_PENALISATION']);
                $supplier[self::RESULT_COMMENT] .=
                    ";Supplier does not have any category in common with buyer chosen suppliers. Score reduced " .
                    (1 - $this->algorithmValues['CATEGORY_SUPPLIER_BUYER_SUPPLIERS_PENALISATION'] ) * 100 .
                    "% ($origScore * " . $this->algorithmValues['CATEGORY_SUPPLIER_BUYER_SUPPLIERS_PENALISATION'] .
                    ")"
                ;
            }
        }
        unset($supplier);
    }

    /**
     * Prepares readable representation of the search term to put into match comments
     *
     * @author  Yuriy Akopov
     * @date    2013-10-01
     *
     * @param   array   $term
     *
     * @return  string
     */
    protected static function _flattenTerm(array $term) {
        $elements = array();
        if (array_key_exists(self::TERM_ID, $term)) {
            $elements[] = 'ID ' . $term[self::TERM_ID];
        }

        if (is_array($term[self::TERM_TAG])) {
            foreach ($term[self::TERM_TAG] as $tag) {
                $elements[] = '"' . $tag . '"';
            }
        } else {
            $elements[] = '"' . $term[self::TERM_TAG] . '"';
        }

        return implode(', ', $elements);
    }

    /**
     * Runs a check for the given list of suppliers to check if they're "at risk"
     *
     * Warning - it marks suppliers as being at risk without checking if they're paid suppliers
     * If that is important that should be checked later
     *
     * @param   array   &$matchedSuppliers
     * @param   string  $supplierIdKey
     * @param   string  $flagKey
     *
     * @return  array
     */
    protected function markSuppliersAtRisk(array &$matchedSuppliers, $supplierIdKey = self::RESULT_SUPPLIER_ID, $flagKey = self::RESULT_AT_RISK) {
        $interval = (-1) * (int) Shipserv_Match_Settings::get(Shipserv_Match_Settings::SUPPLIER_AT_RISK_INTERVAL);

        // function could have been simpler (and probably housed in supplier class) if we check suppliers one by one
        // but we're trying to save time here so run ad-hoc query to check them in bulk

        $db = Shipserv_Helper_Database::getSsreport2Db();

        // due to no indices in SSREPORT2 we use a subquery instead of a left join as it's quicker
        $selectOrders = new Zend_Db_Select($db);
        $selectOrders
            ->from(
                'ord',
                'ord.ord_internal_ref_no'
            )
            ->where('ord.spb_branch_code = qot.spb_branch_code')
            ->where('ord.ord_submitted_date >= ADD_MONTHS(SYSDATE, ' . $interval . ')')
        ;

        $selectQuotes = new Zend_Db_Select($db);
        $selectQuotes
            ->from(
                'qot',
                'spb_branch_code'
            )
            ->where('qot_submitted_date >= ADD_MONTHS(SYSDATE, ' . $interval . ')')
            ->group('spb_branch_code')
            ->having('COUNT(DISTINCT qot.qot_internal_ref_no) < 4')
            ->having('NOT EXISTS(' . $selectOrders->assemble() . ')')
        ;

        $suppliersAtRisk = array();

        $start = 0;
        $step = 999; // Oracle limitation for IN (...)
        while (count($supplierSlice = array_slice($matchedSuppliers, $start, $step))) {
            $start += $step;

            // collect supplier codes in our 999 slice
            $supplierCodes = array();
            foreach ($supplierSlice as $supplierInfo) {
                $supplierCodes[] = $supplierInfo[$supplierIdKey];
            }

            if (empty($supplierCodes)) {
                continue;
            }

            $selectSlice = clone($selectQuotes);
            $selectSlice->where('spb_branch_code IN (' . implode(',', $supplierCodes) . ')');

            $sliceResults = $db->fetchCol($selectSlice);
            $suppliersAtRisk = array_merge($suppliersAtRisk, $sliceResults);
        }

        // assigning 'supplier at risk' flag to given suppliers
        foreach($matchedSuppliers as $index => $supplierInfo) {
            $matchedSuppliers[$index][$flagKey] = in_array($supplierInfo[$supplierIdKey], $suppliersAtRisk);
        }
    }

    /**
     * Returns list of matched suppliers before the list is truncated, score penalties / boosts applier, etc.
     *
     * @param   Shipserv_Helper_Stopwatch   $t
     * @return  array
     */
    protected function getAllMatchedSuppliers(Shipserv_Helper_Stopwatch $t = null) {
        if (is_null($t)) {
            $t = new Shipserv_Helper_Stopwatch();
        }

        if (is_array($this->resultCache[self::CACHE_ALL])) {
            $t->click(); $t->click("Matching suppliers: cached");
            return $this->resultCache[self::CACHE_ALL];
        }

        // save current state of tags because we will be updating the set from brands and categories and the user should not see the changes
        $userTags = $this->tags;

        $tagGenerator = new Shipserv_Match_TagGenerator();

        $matchedSuppliers = array();
        $count = 0;

        // STEP 1: get the brands, find all associated brands in db and add to suppliers list
        $t->click();
        if (count($this->brands)) {
            foreach ($this->brands as $brand) {
                //$brandAgents = $searchAdapter->doBrandSearch($brand['tag'], $brand['id']);
                $brandAgents = $this->getBrandAgents($brand[self::TERM_ID], $this->locations);
                foreach ($brandAgents as $brandAgent) {
                    //BRAN_AUTH_BASE_INTEGER * BRAND_AUTH_TRADERANK_DIVISOR
                    //$score = ($this->algorithmValues['BRAND_AUTH_BASE_INTEGER'] * ((round($brandAgent['SPB_TRADE_RANK'] / $this->algorithmValues['BRAND_AUTH_TRADERANK_DIVISOR'], 2)) * ($brandAgent['AUTHORISED_AGENT'] + 1)));
                    $score = $this->algorithmValues['BRAND_AUTH_BASE_INTEGER'] * ($brandAgent ['AUTHORISED_AGENT'] + 1) * ( $brandAgent['OEM'] + 1);

                    // @todo: match changes - uncomment to restore earlier behaviour
                    // $score *= count($brand[self::TERM_TAG]);

                    $this->supplierListUpdate(
                        $matchedSuppliers,
                        $brandAgent[Shipserv_Supplier::COL_ID],
                        $score,
                        $brandAgent[Shipserv_Oracle_Countries::COL_CODE_COUNTRY],
                        $brandAgent[Shipserv_Oracle_Countries::COL_CODE_CONTINENT],
                        "Brand {" . self::_flattenTerm($brand) . "} " . $score,
                        $brand
                    );
                }
            }

            // adding keywords from brands to the list of tags to search in Solr
            $this->tags = $tagGenerator->mergeToArray($this->tags, $this->brands, 0);
        }
        $count = count($matchedSuppliers) - $count;
        $t->click("Matching suppliers: brands (" . $count . ")");
        $count = count($matchedSuppliers);

        // STEP 2: collect suppliers matching the recognised categories
        $t->click();
        if (count($this->categories)) {
            foreach ($this->categories as $cat) {
                $categoryParticipants = $this->getCategoryParticipants($cat[self::TERM_ID], $this->locations);

                foreach ($categoryParticipants as $p) {
                    $score = empty($this->algorithmValues['CATEGORY_BASE_SCORE']) ? 300 : $this->algorithmValues['CATEGORY_BASE_SCORE'];

                    $score *= (1 + (int) $p['PRIMARY_SUPPLY_CATEGORY']);
                    $score *= (1 + (int) $p['GLOBAL_SUPPLY_CATEGORY']);
                    $score *= (1 + (int) $p['IS_AUTHORISED']);

                    // @todo: match changes - uncomment to restore earlier behaviour
                    // $score *= count($cat[self::TERM_TAG]);

                    $this->supplierListUpdate(
                        $matchedSuppliers,
                        $p[Shipserv_Supplier::COL_ID],
                        $score,
                        $p[Shipserv_Oracle_Countries::COL_CODE_COUNTRY],
                        $p[Shipserv_Oracle_Countries::COL_CODE_CONTINENT],
                        "Category {" . self::_flattenTerm($cat) . "} " . $score,
                        $cat
                    );
                }
            }

            // adding keywords from categories to the list of tags to search in Solr
            $this->tags = $tagGenerator->mergeToArray($this->tags, $this->categories, 0);
        }
        $count = count($matchedSuppliers) - $count;
        $t->click("Matching suppliers: categories (" . $count . ")");
        $count = count($matchedSuppliers);

        // apply buyer filters - for tag searches on step 3 these filter are going to be applied on Solr level
        $matchedSuppliers = $this->applyBuyerFilters($matchedSuppliers);

        // STEP 3: running Solr searches for every tag, fetching top 100 matched results, merging into the global list
        $t->click();
        if (count($this->tags)) {
            $buyerOrg = Shipserv_Buyer::getInstanceById($this->rfq->rfq_header->rfqBybByoOrgCode, true);
            $matchSearch = new Myshipserv_Search_Match($buyerOrg);

            $counter    = 0;
            $tagCount   = count($this->tags);
            $countryMap = $this->getCountryMap();

            foreach ($this->tags as $tag) {
                if (strlen($tag[self::TERM_TAG]) === 0) {
                    $counter++;
                    continue;
                }

                $results = $matchSearch->searchTag($tag, $this->locations);
                $documents = $results->getDocuments(); /** @var $documents Shipserv_Adapters_Solr_Suppliers_Document[] */

                foreach ($documents as $supplierDoc) {
                    /*
                    if ($supplierDoc->getScore() <= 1) {
                        // if we have requested suppliers from specific locations and there are not enough of them,
                        // we want suppliers from another locations also to appear in the list
                        // their score, however, would be quite low, so we're no longer skipping low-level results

                        //continue;   // ignore low score suppliers;
                    }
                    */

                    $score = (($tagCount - $counter) * $this->algorithmValues['TAG_COUNTER_MULTIPLIER'] ) + (int) $tag[self::TERM_SCORE];

                    // if result relevancy is higher than the defined threshold
                    if ($supplierDoc->getScore() > $this->algorithmValues['TAG_MAX_SCORE_VALUE']) {
                        $score += $this->algorithmValues['TAG_MAX_SCORE_VALUE'];
                    } else {
                        $score += (int) $supplierDoc->getScore();
                    }

                    $this->supplierListUpdate(
                        $matchedSuppliers,
                        $supplierDoc->getId(),
                        $score,
                        $supplierDoc->getCountry(),
                        $countryMap[self::COUNTRY_MAP_BY_COUNTRY][$supplierDoc->getCountry()],
                        "{" . self::_flattenTerm($tag) . "} " . $score,
                        $tag
                    );
                }

                $counter++;
            }
        }

        $count = count($matchedSuppliers) - $count;
        $t->click("Matching suppliers: tags (" . $count . ")");

        $this->resultCache[self::CACHE_ALL] = $matchedSuppliers;

        // restore user set of tags
        $this->tags = $userTags;

        return $matchedSuppliers;
    }

    /**
     * Runs a search and returns matched suppliers which are marked as 'at risk'
     *
     * @param   Shipserv_Helper_Stopwatch   $t
     *
     * @return  array
     */
    public function getMatchedSuppliersAtRisk(Shipserv_Helper_Stopwatch $t = null) {
        if (is_null($t)) {
            $t = new Shipserv_Helper_Stopwatch();
        }

        if (is_array($this->resultCache[self::CACHE_AT_RISK])) {
            $t->click(); $t->click("Suppliers at risk: cached");
            return $this->resultCache[self::CACHE_AT_RISK];
        }

        $matchedSuppliers = $this->getAllMatchedSuppliers($t);
        if (count($matchedSuppliers) === 0) {
            $t->click(); $t->click("Suppliers at risk: empty list");
            $this->resultCache[self::CACHE_FILTERED] = $matchedSuppliers;
            return $matchedSuppliers;
        }

        $t->click();
        $this->removeBlacklistedSuppliers($matchedSuppliers);
        $t->click("Suppliers at risk: blacklisted removed");

        $t->click();
        $this->markSuppliersAtRisk($matchedSuppliers);
        $t->click("Suppliers at risk: marking risk");

        $t->click();
        foreach ($matchedSuppliers as $index => $supplierInfo) {
            if (!$supplierInfo[self::RESULT_AT_RISK]) {
                unset($matchedSuppliers[$index]);
            }
        }
        $t->click("Suppliers at risk: not in risk removed");

        $t->click();
        self::aasort($matchedSuppliers, self::RESULT_SCORE);
        $matchedSuppliers = array_slice($matchedSuppliers, 0, $this->algorithmValues['SUPPLIERS_RETURN_COUNT']);
        $t->click("Suppliers at risk: sorting and truncating");

        $t->click();
        foreach ($matchedSuppliers as $index => $supplierInfo) {
            $supplier = Shipserv_Supplier::getInstanceById($supplierInfo[self::RESULT_SUPPLIER_ID], '' ,true);

            if (($supplier->isPremium() or ($supplier->monetisationPercent > 0))) {
                $matchedSuppliers[$index][self::RESULT_SUPPLIER] = $supplier;
            } else {
                // we don't care much about risk for non-paying suppliers (yet)
                unset($matchedSuppliers[$index]);
            }
        }
        $matchedSuppliers = array_values($matchedSuppliers);
        $t->click("Suppliers at risk: instantiating");

        $this->resultCache[self::CACHE_AT_RISK] = $matchedSuppliers;
        return $matchedSuppliers;
    }

    /**
     * Returns list of matched suppliers ready for user (truncated, filters / boosts / penalties applied)
     *
     * @param   Shipserv_Helper_Stopwatch   $t
     *
     * @return  array
     */
    public function getMatchedSuppliers(Shipserv_Helper_Stopwatch $t = null) {
        if (is_null($t)) {
            $t = new Shipserv_Helper_Stopwatch();
        }

        if (is_array($this->resultCache[self::CACHE_FILTERED])) {
            $t->click(); $t->click("Processing suppliers: cached");
            return $this->resultCache[self::CACHE_FILTERED];
        }

        $matchedSuppliers = $this->getAllMatchedSuppliers($t);
        if (count($matchedSuppliers) === 0) {
            $t->click(); $t->click("Processing suppliers: empty list");
            $this->resultCache[self::CACHE_FILTERED] = $matchedSuppliers;
            return $matchedSuppliers;
        }

        // leaving only pre-defined number of top results out what our searches have returned together
        // resulting list length may vary if more than one supplier share the same minimal score
        $t->click();
        self::aasort($matchedSuppliers, self::RESULT_SCORE);
        $minScore = $matchedSuppliers[$this->algorithmValues['SUPPLIERS_RETURN_COUNT'] + 1][self::RESULT_SCORE] / 4;

        foreach ($matchedSuppliers as $supplierKey => $supplier) {
            if ($supplier[self::RESULT_SCORE] < $minScore) {
                unset($matchedSuppliers[$supplierKey]);
            }
        }
        $t->click("Processing suppliers: sorting and truncating");

        $t->click();
        $this->removeBlacklistedSuppliers($matchedSuppliers);
        $t->click("Processing suppliers: blacklisted removed");

        $t->click();
        $this->addMetaData($matchedSuppliers);
        $t->click("Processing suppliers: metadata loaded");

        $t->click();
        $this->applyCategorySpamPenalty($matchedSuppliers);
        $t->click("Processing suppliers: category spam penalty");

        $t->click();
        $this->applyChandleryPenalty($matchedSuppliers);
        $t->click("Processing suppliers: chandlery penalty");

        /*
        $t->click();
        $this->markOriginalSuppliers($matchedSuppliers);
        $t->click("Processing suppliers: original suppliers marked");
        */

        $t->click();
        $this->applyPremiumBoost($matchedSuppliers);
        $t->click("Processing suppliers: premium listing boost");

        if ($this->algorithmValues['ENABLE_LOCATION_BOOSTING'] == 1) {
            $t->click();
            if (Shipserv_Match_Settings::get(Shipserv_Match_Settings::LOCATION_WEIGHTS_ENABLED)) {
                $this->applyLocationBoost($matchedSuppliers);
            } else {
                $this->applyRfqLocationBoost($matchedSuppliers);
            }
            $t->click("Processing suppliers: location boost");
        }

        $t->click();
        $this->applyCommonCategoriesPenalty($matchedSuppliers);
        $t->click("Processing suppliers: common categories penalty");

        $t->click();
        self::aasort($matchedSuppliers, self::RESULT_SCORE);
        $matchedSuppliers = array_slice($matchedSuppliers, 0, $this->algorithmValues['SUPPLIERS_RETURN_COUNT']);
        $t->click("Processing suppliers: final sorting and truncating");

        $t->click();
        foreach ($matchedSuppliers as &$supplierInfo) {
            $supplierInfo[self::RESULT_SUPPLIER] = Shipserv_Supplier::getInstanceById($supplierInfo[self::RESULT_SUPPLIER_ID], '', true);
        }
        unset($supplierInfo);
        $t->click("Processing suppliers: instantiating");

        $this->resultCache[self::CACHE_FILTERED] = $matchedSuppliers;
        return $matchedSuppliers;
    }

    /**
     * Adds a supplier to the list of the matched ones found previously
     *
     * If supplier is already found in the list, its meta data is updated, otherwise a new list item is added
     *
     * Refactored by Yuriy Akopov on 2013-07-29, S7286
     *
     * @param   array   &$suppliers
     * @param   int     $supplierId
     * @param   float   $score
     * @param   string  $country
     * @param   string  $continent
     * @param   string  $comment
     *
     * @param   array|null   $term
     */
    protected function supplierListUpdate(array &$suppliers, $supplierId, $score, $country, $continent, $comment, array $term = null) {
        foreach ($suppliers as &$item) {
            if ($item[self::RESULT_SUPPLIER_ID] == $supplierId) {
                if (is_null($term) or (!$this->isPreviousPartialMatch($term, $item[self::RESULT_TAGS]))) {

                    $item[self::RESULT_SCORE]   += $score;
                    $item[self::RESULT_COMMENT] .= ";" . $comment;;

                    $item[self::RESULT_COUNTRY]     = $country;
                    $item[self::RESULT_CONTINENT]   = $continent;

                    if (!is_null($term)) {
                        $item[self::RESULT_TAGS][] = $term;
                    }
                }

                return;
            }
        }

        // supplier is a new one (not found in the list of previous results)
        $newItem = array(
            self::RESULT_SUPPLIER_ID  => $supplierId,
            self::RESULT_SCORE     => $score,
            self::RESULT_COUNTRY   => $country,
            self::RESULT_CONTINENT => $continent,
            self::RESULT_COMMENT   => $comment,
            self::RESULT_TAGS      => array()
        );

        if (!is_null($term)) {
            $newItem[self::RESULT_TAGS][] = $term;
        }

        $suppliers[] = $newItem;
    }

    /**
     * Sorts array of array items in  reverse order basing on given key in the item array
     *
     * @param   array   $array
     * @param   string  $key
     */
    static public function aasort(array &$array, $key) {
        $sorter = array();
        $ret = array();

        reset($array);

        foreach ($array as $ii => $va) {
            $sorter[$ii] = $va[$key];
        }

        arsort($sorter);

        foreach ($sorter as $ii => $va) {
            $ret[$ii] = $array[$ii];
        }

        $array = array_values($ret);
    }

    /**
     * Returns categories for the given supplier
     * @todo: shouldn't it be a part of the supplier-centered class?
     *
     * @param   int $tnid
     *
     * @return  array
     */
    public function getSupplierCategories($tnid) {
        $sql = "
          SELECT
            p.id,
            p.name,
            p.browse_page_name,
            decode(s.primary_supply_category, 1, 1, 0) as primary,
            COUNT(pce.pce_category_id) AS ownersCount
            -- (select count(*) from pages_category_editor where pce_category_id = s.product_category_id) ownersCount
          FROM
            product_category p
            JOIN supply_category s ON
              s.product_category_id = p.id
            LEFT JOIN pages_category_editor pce ON
              pce_category_id = s.product_category_id
          WHERE
            s.supplier_branch_code = :tnid
            AND (
              s.is_authorised = 'Y'
              OR s.is_authorised IS NULL
            )
          GROUP BY
            p.id,
            p.name,
            p.browse_page_name,
            decode(s.primary_supply_category, 1, 1, 0)
          ORDER BY
            p.name
        ";

        $categories = $this->cache->fetchData('matchSupplierCategories.' . $tnid, $sql, array('tnid' => $tnid));
        if (!is_array($categories)) {
            return array();
        }

        return $categories;
    }

    private function tagsContainSpecialCategory(array $tags) {
        $specialTags = array('pump', 'valve');

        if (empty($tags) === 0) {
            return false;
        }

        foreach ($tags as $tag) {
            $tagStrings = $tag[self::TERM_TAG];
            if (!is_array($tagStrings)) {
                $tagStrings = array($tagStrings);
            }

            foreach ($tagStrings as $str) {
                if (in_array($str, $specialTags)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function supplierInList($supplierID, $list) {
        $supplierExists = false;
        if (is_array($list)) {

            foreach ($list as $item) {
                if ($item['BRANCHCODE'] == $supplierID) {
                    $supplierExists = true;
                }
            }
        }
        return $supplierExists;
    }

    /**
     * Checks if the given supplier is in the premium listing
     *
     * @param   string  $tnid
     *
     * @return boolean
     */
    private function supplierIsPremium($tnid) {
        $sql = "
          SELECT
            COUNT(*) as count
          FROM
            supplier_branch
          WHERE
            spb_branch_code = :tnid
            AND directory_listing_level_id = :premiumid
        ";

        $results = $this->cache->fetchData('matchSupplierIsPremium.' . $tnid, $sql, array(
            'tnid'      => $tnid,
            'premiumid' => Shipserv_Match_Settings::get(Shipserv_Match_Settings::PREMIUM_DIR_LISTING_ID)
        ));

        return ((int) $results[0]['COUNT'] > 0);
    }

    /**
     * Returns all the country and continent codes mapped against each other for quicker search
     *
     * @return  array
     */
    public function getCountryMap() {
        if (!is_null(self::$countryMap)) {
            return self::$countryMap;
        }

        $sql = "
          SELECT
            cnt_country_code  as country,
            cnt_con_code      as continent
          FROM
            country
        ";

        self::$countryMap = array(
            self::COUNTRY_MAP_BY_COUNTRY    => array(),
            self::COUNTRY_MAP_BY_CONTINENT  => array()
        );

        $rows = $this->cache->fetchData('matchCountryCode', $sql);
        if (count($rows)) {
            foreach($rows as $row) {
                $country    = $row['COUNTRY'];
                $continent  = $row['CONTINENT'];

                self::$countryMap[self::COUNTRY_MAP_BY_COUNTRY][$country] = $continent;

                if (array_key_exists($continent, self::$countryMap[self::COUNTRY_MAP_BY_CONTINENT])) {
                    self::$countryMap[self::COUNTRY_MAP_BY_CONTINENT][$continent][] = $country;
                } else {
                    self::$countryMap[self::COUNTRY_MAP_BY_CONTINENT][$continent] = array($country);
                }

            }
        }

        return self::$countryMap;
    }

    /**
     * Returns suppliers categorised into the given category
     *
     * @param   int     $catId
     * @param   array   $locations
     *
     * @return  array
     */
    protected function getCategoryParticipants($catId, array $locations = null) {
        $select = new Zend_Db_Select($this->db);
        $select
            ->from(
                array('s' => 'supply_category'),
                array(
                    new Zend_Db_Expr("COALESCE(is_authorised,'0') AS is_authorised"),
                    'primary_supply_category',
                    'global_supply_category',
                )
            )
            ->where('s.product_category_id = :cat_id')
        ;

        $this->addMatchedSupplierProperties($select, 's.supplier_branch_code');
        $this->addMatchedSupplierCountry($select, $locations);

        $results = $this->cache->fetchData('catParticipants' . $catId, $select, array('cat_id' => $catId));

        return $results;
    }

    /**
     * Helper function to extend matched supplier query with country data
     * The purpose is to avoid repeating ourselves in similar but different queries
     *
     * @param   Zend_Db_Select  $select
     * @param   array           $locations
     *
     * @return  Zend_Db_Select
     */
    protected function addMatchedSupplierCountry(Zend_Db_Select $select, array $locations = null) {
        $constraints = array(
            'spb.spb_country = c.cnt_country_code',
        );

        if (count($locations)) {
            $countries = array();
            foreach ($locations as $locTerm) {
                $countries[] = $locTerm[self::TERM_ID];
            }

            $constraints[] = $select->getAdapter()->quoteInto('c.cnt_country_code IN (?)', $countries);
        }

        $select->join(
            array('c' => Shipserv_Oracle_Countries::TABLE_NAME),
            implode(' AND ', $constraints),
            array(
                'c.' . Shipserv_Oracle_Countries::COL_CODE_COUNTRY,
                'c.' . Shipserv_Oracle_Countries::COL_CODE_CONTINENT
            )
        );

        return $select;
    }

    /**
     * Helper function to extend matched supplier query with supplier properties
     * The purpose is to avoid repeating ourselves in similar but different queries
     *
     * @param   Zend_Db_Select  $select
     * @param   string          $whereSupplierId
     *
     * @return  Zend_Db_Select
     */
    protected function addMatchedSupplierProperties(Zend_Db_Select $select, $whereSupplierId) {
        $select
            ->join(
                array('spb' => Shipserv_Supplier::TABLE_NAME),
                implode(' AND ' , array(
                    'spb.' . Shipserv_Supplier::COL_ID . ' = ' . $whereSupplierId,
                    "spb.spb_test_account = 'N'",
                    "spb.spb_sts = 'ACT'",
                    "spb.spb_account_deleted = 'N'",
                    "spb.spb_list_in_suppliier_dir = 'Y'",
                    "spb.spb_match_exclude = 'N'",
                    "spb.spb_public_branch_code IS NULL"
                )),
                array(
                    'spb.' . Shipserv_Supplier::COL_ID,
                    new Zend_Db_Expr("
                        CASE
                            WHEN spb.spb_trade_rank = 0 THEN 1
                            ELSE spb.spb_trade_rank
                        END AS spb_trade_rank
                    ")
                )
            )
        ;

        return $select;
    }

    /**
     * Returns suppliers listed as operators of the given brand
     *
     * @param   int     $brandId
     * @param   array   $locations
     *
     * @return  array
     */
    protected function getBrandAgents($brandId, array $locations = null) {
        $select = new Zend_Db_Select($this->db);
        $select
            ->from(
                array('s' => 'supply_brand'),
                array(
                    's.oem',
                    's.authorised_agent',
                    's.authorised_installer_repairer',
                )
            )
            ->where('s.brand_id = :brand_id')
        ;

        $this->addMatchedSupplierProperties($select, 's.supplier_branch_code');
        $this->addMatchedSupplierCountry($select, $locations);

        $results = $this->cache->fetchData('brandAgents.' . $brandId, $select, array('brand_id' => $brandId));

        return $results;
    }

    /**
     * Function to see if a 2 or one word phrase is a part of a larger match. EG If a supplier has
     * already scored for "pressure valve gauge",
     * We want to return true here is $needle is
     * "pressure valve" or "valve gauge" or "pressure" or "valve" etc.
     *
     * @param   array   $newTerm
     * @param   array   $supplierTerms
     *
     * @return bool
     */
    protected function isPreviousPartialMatch(array $newTerm, array $supplierTerms) {
        if (empty($supplierTerms)) {
            return false;
        }

        $newTags = $newTerm[self::TERM_TAG];
        if (!is_array($newTags)) {
            $newTags = array($newTerm[self::TERM_TAG]);
        }

        foreach ($newTags as $newTag) {
            $regex = '/\b' . str_replace(" ", "\W", preg_quote($newTag, '/')) . '\b/i';

            foreach ($supplierTerms as $term) {
                $existingTags = $term[self::TERM_TAG];
                if (!is_array($existingTags)) {
                    $existingTags = array($existingTags);
                }

                foreach($existingTags as $existingTag) {
                    if (preg_match($regex, $existingTag)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Input $array contains countries and continents of locations to be factored into the match process.
     * This method will dedupe, and add frequency counts as %. So if there are 4 countries with US as
     * the country and one with UK, the resultant array will contain only 2 entries, and US will be maked as worth 80% of total, UK with 20%.
     * The percentages are in relation to the original number of array elements/countries, not the final un
     * Similar situation for Continents.
     *
     * @param array $array array containing locations (Country Code and Continent Code)
     *
     * @return array
     */
    private function organiseLocations(array $array) {
        // will hold an array with countryCode as key and value as the count of that country in $array
        $CountryCounter = array();
        // will hold an array with ContinentCode as key and value as the count of that Continent in $array
        $ContinentCounter = array();

        // Deduped version of the $array
        $finalArray = array_unique($array);

        foreach ($array as $item) {
            $CountryCounter[$item['country']]++;
            $ContinentCounter[$item['continent']]++;
        }

        $counter = 0;
        foreach ($finalArray as $item) {
            $finalArray[$counter]['countrypercentage']      = round($CountryCounter [$item['country']] / count($array), 2) * 100;
            $finalArray[$counter]['continentpercentage']    = round($ContinentCounter [$item['continent']] / count($array), 2) * 100;
            $counter++;
        }

        return $finalArray;
    }

    /*
    public function forwardRfqs($rfqId, $arrTnids) {
        if (is_array($arrTnids) && count($arrTnids) > 0) {
            $db = Shipserv_Helper_Database::getStandByDb(true);
            $forwarder = new Shipserv_Match_Forwarder ();
            foreach ($arrTnids as $tnid) {
                //First check if its valid, or needs to be replaced with a PUBLIC_TNID
                //TODO: See if enabled for Match
                $sql = "Select SPB_PUBLIC_BRANCH_CODE, SPB_MATCH_EXCLUDE from Supplier_Branch where SPB_Supplier_branch_code = :tnid";
                $params = array('tnid' => $tnid);
                $row = $db->fetchRow($sql, $params);
                if (!preg_match('/[^0-9]/', $row['SPB_PUBLIC_BRANCH_CODE']) && preg_match('/[0-9]{5,8}/', $row['SPB_PUBLIC_BRANCH_CODE'])) {
                    $preferredTnid = $row['SPB_PUBLIC_BRANCH_CODE'];
                } else {
                    $preferredTnid = $tnid;
                }
                $forwarder->forwardRFQ($rfqId, $tnid);
            }
        }
    }
    */

    /**
     * Returns the RFQ the engine was initialised with
     *
     * @author  Yuriy Akopov
     * @date    2013-08-16
     * @story   S7924
     *
     * @return  Shipserv_Tradenet_RequestForQuote
     */
    public function getRfq() {
        return $this->rfq;
    }

    /**
     * Returns the list of brands engine managed to extract from the RFQ and possibly modified by user
     *
     * @author  Yuriy Akopov
     * @date    2013-08-16
     * @story   S7924
     *
     * @return array
     */
    public function getCurrentBrands() {
        return $this->brands;
    }

    /**
     * Returns the list of categories engine managed to extract from the RFQ and possibly modified by user
     *
     * @author  Yuriy Akopov
     * @date    2013-08-16
     * @story   S7924
     *
     * @return array
     */
    public function getCurrentCategories() {
        return $this->categories;
    }

    /**
     * Returns the list of tags engine managed to extract from the RFQ and possibly modified by user
     *
     * @author  Yuriy Akopov
     * @date    2013-08-16
     * @story   S7924
     *
     * @return array
     */
    public function getCurrentTags() {
        return $this->tags;
    }

    /**
     * Returns the list of locations used to filter suppliers with
     *
     * @author  Yuriy Akopov
     * @date    2013-11-22
     * @story   S8766
     *
     * @return array|null
     */
    public function getCurrentLocations() {
        return $this->locations;
    }

    /**
     * Sets a list of locations to filter suppliers in the next search
     *
     * @author  Yuriy Akopov
     * @date    2013-11-22
     * @story   S8766
     *
     * @param   array   $locations
     *
     * @throws  Exception
     */
    public function setLocations(array $locations) {
        $this->resultCache = array();

        if (empty($locations)) {
            $this->locations = null;
            return;
        }

        $countryMap = $this->getCountryMap();
        $countryCodes = array_keys($countryMap[self::COUNTRY_MAP_BY_COUNTRY]);

        foreach ($locations as $locationInfo) {
            if (!filter_var($locationInfo[self::TERM_SCORE], FILTER_VALIDATE_FLOAT)) {
                throw new Myshipserv_Exception_MessagedException('Location term validation error: invalid score supplied for location "' . $locationInfo[self::TERM_SCORE] . '": ' . $locationInfo[self::TERM_SCORE]);
            }

            if (!in_array($locationInfo[self::TERM_ID], $countryCodes)) {
                throw new Myshipserv_Exception_MessagedException("Invalid country code supplied for location filtering: " . $locationInfo[self::TERM_ID]);
            }
        }

        $this->locations = $locations;
    }

    /**
     * If an RFQ was selected by pre emptive matching returns supplier branches that own keywords it matched
     * Returned format is the same as with 'normal' matched suppliers
     *
     * @param   Shipserv_Helper_Stopwatch $t
     *
     * @return  array
     */
    public function getAutoMatchedSuppliers(Shipserv_Helper_Stopwatch $t = null) {
        if (is_null($t)) {
            $t = new Shipserv_Helper_Stopwatch();
        }

        $t->click();
        $rfqId = $this->rfq->getInternalRefNo();
        $rfq = Shipserv_Rfq::getInstanceById($rfqId);
        $t->click('Loaded RFQ hash string');

        $t->click();
        $sets = Shipserv_Match_Auto_Manager::getMatchedKeywordSets($rfq->rfqEventHash);
        $t->click('Loaded matching keyword sets');

        if (empty($sets)) {
            // no automatched suppliers for this RFQ event
            return array();
        }

        // if we are here then the RFQ matches one or more keyword sets, now retrieve suppliers which own those sets

        $countryMap = self::getCountryMap();
        $matchedSuppliers = array();

        // check which organisations own the matched keyword list
        $t->click();
        $orgs = Shipserv_Match_Auto_Manager::getSupplierOrgs($sets);
        foreach ($orgs as $org) {
            $branches = $org->getBranches();
            foreach ($branches as $spb) {

                $this->supplierListUpdate(
                    $matchedSuppliers,
                    $spb->tnid,
                    100,
                    $spb->countryName,
                    $countryMap[self::COUNTRY_MAP_BY_COUNTRY][$spb->countryName],
                    "Automatched as a part of an organisation",
                    null
                );

            }
        }
        $t->click('Loaded supplier branches from owner organisations');

        // check which branches own the matched keyword list
        $t->click();
        $branches = Shipserv_Match_Auto_Manager::getSupplierBranches($sets);
        foreach ($branches as $spb) {
            $this->supplierListUpdate(
                $matchedSuppliers,
                $spb->tnid,
                200,
                $spb->countryName,
                $countryMap[self::COUNTRY_MAP_BY_COUNTRY][$spb->countryName],
                "Automatched as a branch",
                null
            );
        }
        $t->click('Loaded direct supplier branches');

        $matchedSuppliers = $this->applyBuyerFilters($matchedSuppliers);

        return $matchedSuppliers;
    }

        /**
     * @param array $feedType
     * @param $results
     */
    public function setCustomResults(array $feedType, $results) {
        $this->resultCache[$feedType] = $results;
    }

    /**
     * Update version here after matching mechanism altered
     *
     * @return string
     */
    public function getVersion() {
        // version history
        // 0.1 - decided to track version (first modified (mostly by refactoring and correcting legacy errors version released to live)
        // 0.2 - location filtering added
        // 0.3 - location filtering made smarter (applied on early stage)
        // 0.4 - location search terms extracted from RFQ and are also weighted, blacklisting rules updated
        // 0.5 - support for pre emptive matching (automatch)
        return '0.5';
    }
}
