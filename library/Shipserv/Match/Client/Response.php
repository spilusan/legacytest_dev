<?php
/**
 * A parser for match terms and results returned by the match engine app
 *
 * @author  Yuriy Akopov
 * @date    2014-10-30
 * @story   S11748
 */
class Shipserv_Match_Client_Response {
    /**
     * @var array
     */
    protected $json = null;

    /**
     * @param   string  $strJson
     *
     * @throws  Shipserv_Match_Exception
     */
    public function __construct($strJson) {
        $this->json = json_decode($strJson, true);

        if (is_null($this->json)) {
            throw new Shipserv_Match_Exception("Invalid match client response supplied: " . $strJson);
        }
    }



    /**
     * @return  null|Shipserv_Match_Component_Search
     * @throws  Shipserv_Helper_Database_Exception
     */
    public function getSearch() {
        if (!array_key_exists('search', $this->json)) {
            return null;
        }

        $search = Shipserv_Match_Component_Search::getInstanceById($this->json['search']['id']);

        return $search;
    }

    /**
     * @return null|bool
     */
    public function isNewSearch() {
        if (!array_key_exists('search', $this->json)) {
            return null;
        }

        return $this->json['search']['new'];
    }

    public function getSupplierFeeds() {
        $feeds = array();

        $keys = array(
            Shipserv_Match_Component_Result::FEED_TYPE_MATCHES,
            Shipserv_Match_Component_Result::FEED_TYPE_AT_RISK,
            Shipserv_Match_Component_Result::FEED_TYPE_AUTO_DYNAMIC,
            Shipserv_Match_Component_Result::FEED_TYPE_AUTO_KEYWORDS
        );

        foreach ($keys as $feed) {
            $suppliers = $this->getSuppliers($feed);
            if (!is_null($suppliers)) {
                $feeds[$feed] = $suppliers;
            }
        }

        return $feeds;
    }

    /**
     * @param   string  $feedType
     *
     * @return  array
     * @throws  Shipserv_Match_Exception
     */
    public function getSuppliers($feedType) {
        $suppliers = array();

        // left - internal feed types, right - corresponding JSON keys
        $keys = array(
            Shipserv_Match_Component_Result::FEED_TYPE_MATCHES       => 'suppliers',
            Shipserv_Match_Component_Result::FEED_TYPE_AT_RISK       => 'suppliersAtRisk',
            Shipserv_Match_Component_Result::FEED_TYPE_AUTO_DYNAMIC  => 'suppliersAutoDynamic',
            Shipserv_Match_Component_Result::FEED_TYPE_AUTO_KEYWORDS => 'suppliersAutoKeywords'
        );

        if ((!in_array($feedType, array_keys($keys)))) {
            throw new Shipserv_Match_Exception("Requested supplier feed " . $feedType . " not supported");
        }

        if (!array_key_exists($keys[$feedType], $this->json['results'])) {
            // feed wasn't returned
            return null;
        }

        foreach ($this->json['results'][$keys[$feedType]] as $item) {
            $supplier = Shipserv_Supplier::getInstanceById($item['supplier'], '', true);
            $suppliers[] = array(
                Shipserv_Match_Match::RESULT_SUPPLIER_ID => $supplier->tnid,
                Shipserv_Match_Match::RESULT_SUPPLIER    => $supplier,
                Shipserv_Match_Match::RESULT_SCORE       => $item['score'],
                Shipserv_Match_Match::RESULT_COMMENT     => $item['comment']
            );
        }

        return $suppliers;
    }

    /**
     * Return search terms as legacy built-in match engine structures
     *
     * @return  array
     * @throws  Shipserv_Match_Exception
     */
    public function getTerms() {
        $terms = array(
            'brands'     => array(),
            'categories' => array(),
            'tags'       => array(),
            'locations'  => array(),
            'addresses'  => array()
        );

        if (empty($this->json['terms'])) {
            return $terms;
        }

        foreach ($this->json['terms'] as $type => $typeTerms) {
            if (!in_array($type, array_keys($terms))) {
                throw new Shipserv_Match_Exception("Unsupported term type returned");
            }

            if (empty($typeTerms)) {
                continue;
            }

            switch ($type) {
                case 'brands':
                    foreach ($typeTerms as $item) {
                        $brand = Shipserv_Brand::getInstanceById($item['id']);

                        $info = Shipserv_Match_TagGenerator::brandToTag($brand);
                        $info[Shipserv_Match_Match::TERM_SCORE] = $item['score'];

                        $terms['brands'][] = $info;
                    }
                    break;

                case 'categories':
                    foreach ($typeTerms as $item) {
                        $category = Shipserv_Category::getInstanceById($item['id']);

                        $info = Shipserv_Match_TagGenerator::categoryToTag($category);
                        $info[Shipserv_Match_Match::TERM_SCORE] = $item['score'];

                        $terms['categories'][] = $info;
                    }
                    break;

                case 'tags':
                    foreach ($typeTerms as $item) {
                        $info = array(
                            Shipserv_Match_Match::TERM_TAG   => $item['tag'],
                            Shipserv_Match_Match::TERM_SCORE => $item['score']
                        );

                        $terms['tags'][] = $info;
                    }
                    break;

                case 'locations':
                    $countries = new Shipserv_Oracle_Countries();
                    foreach ($typeTerms as $item) {
                        $locationRow = $countries->getCountryRow($item['id']);
                        $info = Shipserv_Match_TagGenerator::locationToTag($locationRow);
                        $info[Shipserv_Match_Match::TERM_SCORE] = $item['score'];

                        $terms['locations'][] = $info;
                    }
                    break;

                case 'addresses':
                    // no support for address terms in Pages interface which is set to retire (S12173)
                    break;

                default:
                    throw new Shipserv_Match_Exception("Unsupported term type returned");
            }
        }

        return $terms;
    }

    /**
     * Returns profiling information reported by the match app itself
     *
     * @return array|null
     */
    public function getElapsedInMatch() {
        if (array_key_exists('_debug', $this->json)) {
            if (array_key_exists('elapsed', $this->json['_debug'])) {
                return $this->json['_debug']['elapsed'];
            }
        }

        return null;
    }
}